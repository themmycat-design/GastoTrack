<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPaymentEvent;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderNumberAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    private function staffOnly(Request $request): void
    {
        abort_unless($request->user()->isStaff(), 403, 'Only staff can process orders and confirm payments.');
    }

    private function businessMemberOnly(Request $request): void
    {
        abort_unless(
            $request->user()->isStaff() || $request->user()->isOwner(),
            403,
            'Only this business\'s owner or staff can manage orders.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::where('business_id', $request->user()->business_id)
            ->with('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->payment_status))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->to))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'orders' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->staffOnly($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'payment_method' => ['required', Rule::in(['cash', 'gcash', 'maya', 'bank'])],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_id' => ['nullable', 'uuid'],
        ]);

        $businessId = (int) $request->user()->business_id;
        $clientId = $validated['client_id'] ?? (string) Str::uuid();
        $items = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($rows, $productId): array => [
                'product_id' => (int) $productId,
                'quantity' => (int) $rows->sum('quantity'),
            ])
            ->sortBy('product_id')
            ->values();
        $customerName = $validated['customer_name'] ?? 'Walk-in Customer';
        $paymentMethod = strtolower($validated['payment_method']);
        $notes = $validated['notes'] ?? null;
        $idempotencyHash = hash('sha256', json_encode([
            'items' => $items->all(),
            'payment_method' => $paymentMethod,
            'customer_name' => $customerName,
            'customer_phone' => $validated['customer_phone'] ?? null,
            'notes' => $notes,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $created = false;

        try {
            $order = DB::transaction(function () use (
                $request,
                $businessId,
                $clientId,
                $items,
                $customerName,
                $paymentMethod,
                $notes,
                $validated,
                $idempotencyHash,
                &$created
            ): Order {
                $existing = Order::where('client_id', $clientId)->lockForUpdate()->first();
                if ($existing) {
                    return $this->resolveIdempotentReplay($existing, $businessId, $idempotencyHash);
                }

                $products = Product::where('business_id', $businessId)
                    ->where('active', true)
                    ->where('is_available', true)
                    ->whereIn('id', $items->pluck('product_id'))
                    ->with('productIngredients.stockItem')
                    ->get()
                    ->keyBy('id');
                abort_if(
                    $products->count() !== $items->count(),
                    422,
                    'One or more products are unavailable or do not belong to this business.'
                );

                $subtotalCents = 0;
                foreach ($items as $item) {
                    $product = $products[$item['product_id']];
                    $subtotalCents += (int) round((float) $product->price * 100) * $item['quantity'];
                }
                $subtotal = number_format($subtotalCents / 100, 2, '.', '');
                $paymentType = $paymentMethod === 'cash' ? 'cash' : 'cashless';
                $business = $request->user()->business;
                $currency = strtoupper((string) ($business?->currency ?: 'PHP'));
                abort_unless(preg_match('/^[A-Z]{3}$/', $currency) === 1, 422, 'The business currency is not configured correctly.');

                $order = Order::create([
                    'business_id' => $businessId,
                    'user_id' => $request->user()->id,
                    'order_number' => app(OrderNumberAllocator::class)->next($businessId),
                    'customer_name' => $customerName,
                    'customer_phone' => $validated['customer_phone'] ?? null,
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'payment_type' => $paymentType,
                    'payment_method' => $paymentMethod,
                    'payment_currency' => $currency,
                    'payment_status' => $paymentMethod === 'cash' ? 'confirmed' : 'pending',
                    'payment_confirmed_by' => $paymentMethod === 'cash' ? $request->user()->id : null,
                    'payment_confirmed_at' => $paymentMethod === 'cash' ? now() : null,
                    'status' => $paymentMethod === 'cash' ? 'completed' : 'pending',
                    'completed_at' => $paymentMethod === 'cash' ? now() : null,
                    'notes' => $notes,
                    'client_id' => $clientId,
                    'idempotency_hash' => $idempotencyHash,
                    'synced' => true,
                ]);

                foreach ($items as $item) {
                    $product = $products[$item['product_id']];
                    $ingredientSnapshot = $product->productIngredients
                        ->map(fn ($ingredient): array => [
                            'stock_item_id' => (int) $ingredient->stock_item_id,
                            'quantity' => (float) $ingredient->quantity,
                            'stock_name' => $ingredient->stockItem?->name,
                            'unit' => $ingredient->stockItem?->unit,
                        ])
                        ->values()
                        ->all();

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'price' => $product->price,
                        'quantity' => $item['quantity'],
                        'subtotal' => number_format(((int) round((float) $product->price * 100) * $item['quantity']) / 100, 2, '.', ''),
                        'ingredients_snapshot' => $ingredientSnapshot,
                    ]);
                }

                $this->recordEvent(
                    $order,
                    $request->user(),
                    'order_created',
                    null,
                    $order->status,
                    null,
                    $order->payment_status,
                    details: ['idempotency_key' => $clientId]
                );

                // Cash order placement is the staff member's explicit attestation
                // that cash was received. Cashless orders remain pending here.
                if ($paymentMethod === 'cash') {
                    $this->recordEvent(
                        $order,
                        $request->user(),
                        'cash_payment_manually_confirmed',
                        $order->status,
                        $order->status,
                        'pending',
                        'confirmed',
                        details: ['confirmation_method' => 'staff_cash_attestation']
                    );
                    $this->commitOrderFinancials($order, $request->user());
                } else {
                    $this->lockAndValidateRequiredStock($order, $businessId);
                }

                $created = true;
                return $order->load('items');
            });
        } catch (QueryException $exception) {
            // The unique client_id constraint is the final concurrency guard when
            // two retries race before either transaction can see the new order.
            $existing = Order::where('client_id', $clientId)->first();
            if (!$existing) {
                throw $exception;
            }
            $order = $this->resolveIdempotentReplay($existing, $businessId, $idempotencyHash);
        }

        return response()->json([
            'message' => $created ? 'Order created.' : 'This order request was already processed.',
            'order' => $order->load('items'),
        ], $created ? 201 : 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('business_id', $request->user()->business_id)
            ->with(['items', 'paymentEvents.actor'])
            ->findOrFail($id);

        return response()->json(['order' => $order]);
    }

    public function detectPayment(Request $request, int $id): JsonResponse
    {
        $this->staffOnly($request);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'source' => ['required', Rule::in(['gcash', 'maya'])],
        ]);

        $order = DB::transaction(function () use ($request, $id, $validated): Order {
            $order = $this->lockBusinessOrder($request, $id);
            abort_unless($order->payment_type === 'cashless', 409, 'Payment notifications are only supported for cashless orders.');
            abort_unless($order->status === 'pending', 409, 'This order is no longer awaiting payment.');
            abort_unless(in_array($order->payment_status, ['pending', 'detected'], true), 409, 'This payment attempt is not awaiting a notification.');
            abort_unless($validated['source'] === $order->payment_method, 422, 'The detected wallet does not match this order.');
            abort_unless($validated['currency'] === $order->payment_currency, 422, 'The notification currency does not match the business currency.');

            $previousPaymentStatus = $order->payment_status;
            $order->update([
                'payment_status' => 'detected',
                'detected_payment_amount' => $validated['amount'],
                'detected_payment_currency' => $validated['currency'],
                'payment_detected_at' => now(),
            ]);
            $matchesExpectedAmount = $this->amountsMatch($validated['amount'], $order->total);
            $this->recordEvent(
                $order,
                $request->user(),
                'device_payment_signal_detected',
                $order->status,
                $order->status,
                $previousPaymentStatus,
                'detected',
                amount: $validated['amount'],
                currency: $validated['currency'],
                details: [
                    'source' => $validated['source'],
                    'amount_matches_order' => $matchesExpectedAmount,
                    'authoritative' => false,
                ]
            );

            return $order->load('items');
        });

        return response()->json([
            'message' => 'A device notification was detected. Verify the payment in the wallet app; this signal did not confirm payment.',
            'order' => $order,
            'payment_verified' => false,
        ]);
    }

    public function confirmPayment(Request $request, int $id): JsonResponse
    {
        $this->staffOnly($request);
        $orderForValidation = Order::where('business_id', $request->user()->business_id)->findOrFail($id);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'reference' => [
                'required', 'string', 'max:120',
                Rule::unique('orders', 'payment_reference')
                    ->where('business_id', $request->user()->business_id)
                    ->where('payment_method', $orderForValidation->payment_method)
                    ->ignore($orderForValidation->id),
            ],
        ]);

        try {
            $order = DB::transaction(function () use ($request, $id, $validated): Order {
                $order = $this->lockBusinessOrder($request, $id);
                abort_unless($order->payment_type === 'cashless', 409, 'Manual wallet confirmation is only for cashless orders.');
                abort_unless($this->amountsMatch($validated['amount'], $order->total), 422, 'The confirmed amount must exactly match the server-calculated order total.');
                abort_unless($validated['currency'] === $order->payment_currency, 422, 'The confirmed currency does not match the order currency.');

                if ($order->payment_status === 'confirmed') {
                    abort_unless($order->payment_reference === $validated['reference'], 409, 'This order was already confirmed with a different payment reference.');
                    return $order->load('items');
                }

                abort_unless(
                    $order->status === 'pending' || ($order->status === 'completed' && $order->financials_committed_at),
                    409,
                    'Only an active or legacy-review order can be manually confirmed.'
                );
                abort_unless(in_array($order->payment_status, ['pending', 'detected'], true), 409, 'Retry the failed payment attempt before confirming it.');

                $previousStatus = $order->status;
                $previousPaymentStatus = $order->payment_status;
                $now = now();
                $order->update([
                    'payment_status' => 'confirmed',
                    'payment_reference' => $validated['reference'],
                    'payment_confirmed_by' => $request->user()->id,
                    'payment_confirmed_at' => $now,
                    'status' => 'completed',
                    'completed_at' => $order->completed_at ?: $now,
                ]);

                $this->recordEvent(
                    $order,
                    $request->user(),
                    'payment_manually_confirmed',
                    $previousStatus,
                    'completed',
                    $previousPaymentStatus,
                    'confirmed',
                    amount: $validated['amount'],
                    currency: $validated['currency'],
                    reference: $validated['reference'],
                    details: [
                        'confirmation_method' => 'staff_manual_provider_app_check',
                        'provider_api_verified' => false,
                        'legacy_financials_preserved' => (bool) $order->financials_committed_at,
                    ]
                );

                // Completed legacy orders already had stock and a sales entry in
                // the previous release. Do not apply those effects twice.
                $this->commitOrderFinancials($order, $request->user());

                return $order->load('items');
            });
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'This payment reference is already linked to another order, or the payment update conflicted. Verify the order before retrying.',
            ], 409);
        }

        return response()->json([
            'message' => 'Payment manually confirmed by staff after checking the wallet app. GastoTrack did not verify it with the provider.',
            'order' => $order,
            'payment_verified' => false,
            'payment_manually_confirmed' => true,
        ]);
    }

    public function failPayment(Request $request, int $id): JsonResponse
    {
        $this->staffOnly($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'payment_not_received' => ['required', 'accepted'],
        ]);

        $order = DB::transaction(function () use ($request, $id, $validated): Order {
            $order = $this->lockBusinessOrder($request, $id);
            abort_unless($order->status === 'pending', 409, 'Only a pending order can have a failed payment attempt.');
            abort_unless(in_array($order->payment_status, ['pending', 'detected'], true), 409, 'This payment attempt cannot be marked failed.');

            $previousPaymentStatus = $order->payment_status;
            $order->update(['payment_status' => 'failed']);
            $this->recordEvent(
                $order,
                $request->user(),
                'payment_marked_failed',
                $order->status,
                $order->status,
                $previousPaymentStatus,
                'failed',
                reason: $validated['reason'],
                details: [
                    'payment_not_received' => true,
                    'confirmation_method' => 'staff_wallet_account_check',
                ]
            );

            return $order->load('items');
        });

        return response()->json(['message' => 'Payment attempt marked failed; no income or stock was recorded.', 'order' => $order]);
    }

    public function retryPayment(Request $request, int $id): JsonResponse
    {
        $this->staffOnly($request);

        $order = DB::transaction(function () use ($request, $id): Order {
            $order = $this->lockBusinessOrder($request, $id);
            abort_unless($order->status === 'pending' && $order->payment_status === 'failed', 409, 'Only a failed, uncancelled payment can be retried.');

            $order->update([
                'payment_status' => 'pending',
                'detected_payment_amount' => null,
                'detected_payment_currency' => null,
                'payment_detected_at' => null,
            ]);
            $this->recordEvent(
                $order,
                $request->user(),
                'payment_retry_started',
                $order->status,
                $order->status,
                'failed',
                'pending'
            );

            return $order->load('items');
        });

        return response()->json(['message' => 'A new manual verification attempt is pending.', 'order' => $order]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $this->businessMemberOnly($request);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'payment_not_received' => ['required', 'accepted'],
            'restock' => ['nullable', 'boolean'],
        ]);

        $order = DB::transaction(function () use ($request, $id, $validated): Order {
            $order = $this->lockBusinessOrder($request, $id);
            if ($order->status === 'cancelled' && $order->payment_status === 'cancelled') {
                return $order->load('items');
            }

            abort_unless(
                in_array($order->payment_status, ['pending', 'detected', 'failed'], true),
                409,
                'A confirmed sale cannot be cancelled. Request and record a manual refund instead.'
            );

            $fromStatus = $order->status;
            $fromPaymentStatus = $order->payment_status;
            $legacyCommittedSale = (bool) $order->financials_committed_at;
            if ($legacyCommittedSale) {
                abort_unless($fromPaymentStatus === 'detected', 409, 'This order has financial effects that require payment review before cancellation.');
                abort_unless(array_key_exists('restock', $validated), 422, 'Confirm whether the ordered items were physically returned before voiding this legacy sale.');
                $this->createSaleReversal($order, $request->user(), $validated['reason'], 'Sale void');
                if ((bool) $validated['restock']) {
                    $this->restoreOrderStock($order, $request->user(), $validated['reason']);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancel_reason' => $validated['reason'],
                'cancelled_at' => now(),
            ]);
            $this->recordEvent(
                $order,
                $request->user(),
                $legacyCommittedSale ? 'legacy_sale_voided_as_unpaid' : 'order_cancelled_before_payment',
                $fromStatus,
                'cancelled',
                $fromPaymentStatus,
                'cancelled',
                reason: $validated['reason'],
                details: [
                    'financials_were_previously_committed' => $legacyCommittedSale,
                    'payment_not_received' => true,
                    'confirmation_method' => 'staff_wallet_account_check',
                    'restocked' => (bool) ($validated['restock'] ?? false),
                ]
            );

            return $order->load('items');
        });

        return response()->json([
            'message' => $order->financials_committed_at
                ? 'The legacy sale was voided as unpaid. Its accounting history was retained and reversed.'
                : 'Order cancelled. No payment was recorded and no stock was deducted.',
            'order' => $order,
        ]);
    }

    public function requestRefund(Request $request, int $id): JsonResponse
    {
        $this->businessMemberOnly($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $order = DB::transaction(function () use ($request, $id, $validated): Order {
            $order = $this->lockBusinessOrder($request, $id);
            if ($order->payment_status === 'refund_pending') {
                return $order->load('items');
            }

            abort_unless($order->status === 'completed' && $order->payment_status === 'confirmed', 409, 'Only a completed, confirmed sale can enter the refund workflow.');
            abort_unless($order->financials_committed_at, 409, 'No completed sale entry exists to reverse.');

            $order->update([
                'payment_status' => 'refund_pending',
                'refund_requested_at' => now(),
                'refund_requested_by' => $request->user()->id,
                'refund_reason' => $validated['reason'],
                'refund_amount' => $order->total,
            ]);
            $this->recordEvent(
                $order,
                $request->user(),
                'manual_refund_requested',
                $order->status,
                $order->status,
                'confirmed',
                'refund_pending',
                amount: $order->total,
                currency: $order->payment_currency,
                reason: $validated['reason'],
                details: ['provider_refund_available' => false]
            );

            return $order->load('items');
        });

        return response()->json([
            'message' => 'Refund is pending manual processing outside GastoTrack. No refund or accounting reversal has been recorded yet.',
            'order' => $order,
            'external_refund_performed' => false,
        ]);
    }

    public function confirmRefund(Request $request, int $id): JsonResponse
    {
        $this->businessMemberOnly($request);
        $orderForValidation = Order::where('business_id', $request->user()->business_id)->findOrFail($id);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'reference' => [
                'required', 'string', 'max:120',
                Rule::unique('orders', 'refund_reference')
                    ->where('business_id', $request->user()->business_id)
                    ->ignore($orderForValidation->id),
            ],
            'restock' => ['required', 'boolean'],
        ]);

        try {
            $order = DB::transaction(function () use ($request, $id, $validated): Order {
                $order = $this->lockBusinessOrder($request, $id);
                if ($order->payment_status === 'refunded') {
                    abort_unless(
                        $order->refund_reference === $validated['reference']
                            && $this->amountsMatch($validated['amount'], $order->total)
                            && $validated['currency'] === $order->payment_currency,
                        409,
                        'This order was already refunded with different details.'
                    );
                    return $order->load('items');
                }
                abort_unless($order->payment_status === 'refund_pending', 409, 'Only a pending manual refund can be confirmed.');
                abort_unless($this->amountsMatch($validated['amount'], $order->total), 422, 'The refund amount must exactly match the order total. Partial refunds are not supported.');
                abort_unless($validated['currency'] === $order->payment_currency, 422, 'The refund currency does not match the order currency.');

                $originalSale = $this->originalSaleTransaction($order);
                abort_unless($originalSale, 409, 'The original sales transaction is missing; no refund reversal was recorded.');

                Transaction::create([
                    'business_id' => $order->business_id,
                    'user_id' => $request->user()->id,
                    'reversal_of_id' => $originalSale->id,
                    'amount' => $order->total,
                    'type' => 'expense',
                    'source' => $order->payment_method,
                    'category' => 'Sales refund',
                    'transaction_date' => now(config('business.timezone'))->toDateString(),
                    'description' => 'Manual refund for '.$order->order_number.': '.$order->refund_reason,
                    'entry_method' => 'order_reversal',
                    'metadata' => [
                        'order_id' => $order->id,
                        'refund_reference' => $validated['reference'],
                        'refund_method' => 'staff_confirmed_external_manual',
                    ],
                    'synced' => true,
                ]);

                if ((bool) $validated['restock']) {
                    $this->restoreOrderStock($order, $request->user(), $order->refund_reason ?: 'Manual refund');
                }

                $order->update([
                    'payment_status' => 'refunded',
                    'refund_amount' => $validated['amount'],
                    'refund_reference' => $validated['reference'],
                    'refund_confirmed_by' => $request->user()->id,
                    'refund_confirmed_at' => now(),
                    'refund_restocked' => (bool) $validated['restock'],
                ]);
                $this->recordEvent(
                    $order,
                    $request->user(),
                    'manual_refund_confirmed',
                    $order->status,
                    $order->status,
                    'refund_pending',
                    'refunded',
                    amount: $validated['amount'],
                    currency: $validated['currency'],
                    reference: $validated['reference'],
                    reason: $order->refund_reason,
                    details: [
                        'refund_method' => 'staff_attested_external_manual_refund',
                        'provider_refund_performed_by_gastotrack' => false,
                        'restocked' => (bool) $validated['restock'],
                    ]
                );

                return $order->load('items');
            });
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'This refund reference or reversal was already recorded. Refresh order history before trying again.',
            ], 409);
        }

        return response()->json([
            'message' => 'Manual refund recorded after staff confirmation outside GastoTrack. No provider refund was initiated by the system.',
            'order' => $order,
            'external_refund_performed' => false,
            'manual_refund_recorded' => true,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->staffOnly($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'ready', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'cancel_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:500'],
            'payment_not_received' => ['required_if:status,cancelled', 'accepted'],
            'restock' => ['nullable', 'boolean'],
        ]);

        if ($validated['status'] === 'cancelled') {
            $request->merge(['reason' => $validated['cancel_reason']]);
            return $this->cancel($request, $id);
        }

        $order = DB::transaction(function () use ($request, $id, $validated): Order {
            $order = $this->lockBusinessOrder($request, $id);
            $allowedTransitions = [
                'pending' => ['preparing'],
                'preparing' => ['ready'],
                'ready' => ['completed'],
                'completed' => [],
                'cancelled' => [],
            ];
            abort_unless(
                in_array($validated['status'], $allowedTransitions[$order->status] ?? [], true),
                422,
                "Order cannot move from {$order->status} to {$validated['status']}."
            );
            abort_unless($order->payment_status === 'confirmed', 409, 'Payment must be manually confirmed before an order can be prepared or completed.');

            $previousStatus = $order->status;
            $timestamps = match ($validated['status']) {
                'preparing' => ['prepared_at' => now()],
                'ready' => ['ready_at' => now()],
                'completed' => ['completed_at' => now()],
                default => [],
            };
            $order->update(['status' => $validated['status']] + $timestamps + (array_key_exists('notes', $validated) ? ['notes' => $validated['notes']] : []));
            if ($validated['status'] === 'completed') {
                $this->commitOrderFinancials($order, $request->user());
            }
            $this->recordEvent(
                $order,
                $request->user(),
                'order_status_changed',
                $previousStatus,
                $order->status,
                $order->payment_status,
                $order->payment_status
            );

            return $order->fresh('items');
        });

        return response()->json(['message' => 'Order updated.', 'order' => $order]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->isOwner(), 403, 'Only owners can manage order records.');
        Order::where('business_id', $request->user()->business_id)->findOrFail($id);
        abort(409, 'Orders and accounting records are retained. Use the cancellation or manual refund workflow with a reason.');
    }

    private function resolveIdempotentReplay(Order $order, int $businessId, string $requestHash): Order
    {
        abort_unless((int) $order->business_id === $businessId, 409, 'This idempotency key is already assigned to another business order.');
        abort_unless(
            !$order->idempotency_hash || hash_equals($order->idempotency_hash, $requestHash),
            409,
            'This idempotency key was already used for different order details.'
        );

        return $order->load('items');
    }

    private function lockBusinessOrder(Request $request, int $id): Order
    {
        return Order::where('business_id', $request->user()->business_id)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    private function lockAndValidateRequiredStock(Order $order, int $businessId): array
    {
        $order->loadMissing('items.product.productIngredients.stockItem');
        $requiredStock = [];

        foreach ($order->items as $item) {
            $snapshot = $item->ingredients_snapshot;
            $ingredients = is_array($snapshot)
                ? collect($snapshot)
                : ($item->product?->productIngredients ?? collect())->map(fn ($ingredient): array => [
                    'stock_item_id' => $ingredient->stock_item_id,
                    'quantity' => $ingredient->quantity,
                    'stock_name' => $ingredient->stockItem?->name,
                ]);

            foreach ($ingredients as $ingredient) {
                $stockId = (int) ($ingredient['stock_item_id'] ?? 0);
                $quantity = (float) ($ingredient['quantity'] ?? 0) * (int) $item->quantity;
                if ($stockId < 1 || $quantity <= 0) {
                    continue;
                }
                $requiredStock[$stockId] = ($requiredStock[$stockId] ?? 0) + $quantity;
            }
        }

        if (!$requiredStock) {
            return [];
        }

        ksort($requiredStock);
        $stockItems = StockItem::where('business_id', $businessId)
            ->whereIn('id', array_keys($requiredStock))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        abort_if($stockItems->count() !== count($requiredStock), 422, 'One or more order ingredients are unavailable in this business.');

        foreach ($requiredStock as $stockId => $requiredQuantity) {
            $stock = $stockItems[$stockId];
            abort_unless($stock->active, 422, "{$stock->name} is inactive and cannot be used for this order.");
            abort_if(
                (float) $stock->current_quantity + 0.000001 < $requiredQuantity,
                422,
                "Insufficient stock for {$stock->name}. No payment or inventory changes were made."
            );
        }

        return ['requirements' => $requiredStock, 'items' => $stockItems];
    }

    private function commitOrderFinancials(Order $order, User $actor): void
    {
        if ($order->financials_committed_at) {
            return;
        }
        abort_unless($order->status === 'completed' && $order->payment_status === 'confirmed', 409, 'Only a completed, confirmed order can affect income or inventory.');

        $stock = $this->lockAndValidateRequiredStock($order, (int) $order->business_id);
        foreach ($stock['requirements'] ?? [] as $stockId => $quantity) {
            $item = $stock['items'][$stockId];
            $before = (float) $item->current_quantity;
            $after = round($before - $quantity, 4);
            $item->current_quantity = $after;
            $item->save();

            StockMovement::create([
                'stock_item_id' => $item->id,
                'user_id' => $actor->id,
                'order_id' => $order->id,
                'type' => 'out',
                'quantity' => $quantity,
                'previous_quantity' => $before,
                'new_quantity' => $after,
                'reason' => 'Order '.$order->order_number,
            ]);
            app(NotificationService::class)->notifyLowStock($item);
        }

        $existingSale = $this->originalSaleTransaction($order);
        if (!$existingSale) {
            $productDescription = $order->items
                ->map(fn ($item) => $item->quantity.'× '.$item->product_name)
                ->implode(', ');

            Transaction::create([
                'business_id' => $order->business_id,
                'user_id' => $actor->id,
                'order_id' => $order->id,
                'amount' => $order->total,
                'type' => 'income',
                'source' => $order->payment_method,
                'category' => 'Sales',
                'transaction_date' => now(config('business.timezone'))->toDateString(),
                'description' => $productDescription,
                'entry_method' => 'order_system',
                'metadata' => [
                    'order_id' => $order->id,
                    'payment_confirmation_method' => $order->payment_method === 'cash' ? 'staff_cash_attestation' : 'staff_manual_provider_app_check',
                ],
                'synced' => true,
            ]);
        }

        $order->update(['financials_committed_at' => now()]);
    }

    private function originalSaleTransaction(Order $order): ?Transaction
    {
        return Transaction::where('business_id', $order->business_id)
            ->where('type', 'income')
            ->whereNull('reversal_of_id')
            ->where(function ($query) use ($order): void {
                $query->where('order_id', $order->id)
                    ->orWhere('metadata->order_id', $order->id);
            })
            ->lockForUpdate()
            ->first();
    }

    private function createSaleReversal(Order $order, User $actor, string $reason, string $category): void
    {
        if (Transaction::where('business_id', $order->business_id)
            ->where('entry_method', 'order_reversal')
            ->where('metadata->order_id', $order->id)
            ->exists()) {
            return;
        }

        $sale = $this->originalSaleTransaction($order);
        abort_unless($sale, 409, 'The original sales transaction could not be found; no reversal was recorded.');

        Transaction::create([
            'business_id' => $order->business_id,
            'user_id' => $actor->id,
            'reversal_of_id' => $sale->id,
            'amount' => $order->total,
            'type' => 'expense',
            'source' => $order->payment_method,
            'category' => $category,
            'transaction_date' => now(config('business.timezone'))->toDateString(),
            'description' => $category.' for '.$order->order_number.': '.$reason,
            'entry_method' => 'order_reversal',
            'metadata' => ['order_id' => $order->id, 'reason' => $reason],
            'synced' => true,
        ]);
    }

    private function restoreOrderStock(Order $order, User $actor, string $reason): void
    {
        $outMovements = StockMovement::where('order_id', $order->id)
            ->where('type', 'out')
            ->whereNull('reversal_of_id')
            ->orderBy('stock_item_id')
            ->lockForUpdate()
            ->get();

        foreach ($outMovements as $movement) {
            if (StockMovement::where('reversal_of_id', $movement->id)->exists()) {
                continue;
            }

            $item = StockItem::withTrashed()
                ->where('business_id', $order->business_id)
                ->lockForUpdate()
                ->find($movement->stock_item_id);
            if (!$item) {
                continue;
            }

            $before = (float) $item->current_quantity;
            $after = round($before + (float) $movement->quantity, 4);
            $item->current_quantity = $after;
            $item->save();

            StockMovement::create([
                'stock_item_id' => $item->id,
                'user_id' => $actor->id,
                'order_id' => $order->id,
                'type' => 'in',
                'quantity' => $movement->quantity,
                'previous_quantity' => $before,
                'new_quantity' => $after,
                'reason' => 'Reversal for '.$order->order_number.': '.$reason,
                'reversal_of_id' => $movement->id,
            ]);
        }
    }

    private function amountsMatch(string|int|float $left, string|int|float $right): bool
    {
        return (int) round((float) $left * 100) === (int) round((float) $right * 100);
    }

    private function recordEvent(
        Order $order,
        ?User $actor,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $fromPaymentStatus,
        ?string $toPaymentStatus,
        string|int|float|null $amount = null,
        ?string $currency = null,
        ?string $reference = null,
        ?string $reason = null,
        ?array $details = null
    ): void {
        OrderPaymentEvent::create([
            'order_id' => $order->id,
            'business_id' => $order->business_id,
            'user_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_payment_status' => $fromPaymentStatus,
            'to_payment_status' => $toPaymentStatus,
            'amount' => $amount ?? $order->total,
            'currency' => $currency ?? $order->payment_currency,
            'reference' => $reference,
            'reason' => $reason,
            'details' => $details,
        ]);
    }
}
