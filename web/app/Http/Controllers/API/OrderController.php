<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class OrderController extends Controller
{
    private function staffOnly(Request $request): void
    {
        abort_unless($request->user()->isStaff(), 403, 'Only staff can place and process orders.');
    }

    public function index(Request $request)
    {
        $orders = Order::where('business_id', $request->user()->business_id)
            ->with('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->to))
            ->latest()->paginate(min($request->integer('per_page', 20), 100));

        return response()->json(['orders' => $orders->items(), 'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()]]);
    }

    public function store(Request $request)
    {
        $this->staffOnly($request);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,gcash,maya,bank',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
            'client_id' => 'nullable|uuid',
        ]);
        $businessId = $request->user()->business_id;

        $order = DB::transaction(function () use ($validated, $request, $businessId) {
            $products = Product::where('business_id', $businessId)
                ->whereIn('id', collect($validated['items'])->pluck('product_id'))
                ->with('productIngredients')->get()->keyBy('id');
            abort_if($products->count() !== collect($validated['items'])->pluck('product_id')->unique()->count(), 422, 'One or more products do not belong to this business.');

            $requiredStock = [];
            foreach ($validated['items'] as $itemData) {
                $product = $products[$itemData['product_id']];
                foreach ($product->productIngredients as $ingredient) {
                    $stockId = $ingredient->stock_item_id;
                    $requiredStock[$stockId] = ($requiredStock[$stockId] ?? 0)
                        + ((float) $ingredient->quantity * $itemData['quantity']);
                }
            }

            $stockItems = StockItem::where('business_id', $businessId)
                ->whereIn('id', array_keys($requiredStock))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            abort_if($stockItems->count() !== count($requiredStock), 422, 'One or more product ingredients are unavailable.');
            foreach ($requiredStock as $stockId => $amount) {
                $stock = $stockItems[$stockId];
                abort_if((float) $stock->current_quantity < $amount, 422, "Insufficient stock for {$stock->name}.");
            }

            $subtotal = collect($validated['items'])->sum(fn ($item) => (float) $products[$item['product_id']]->price * $item['quantity']);
            $order = Order::create([
                'business_id' => $businessId, 'user_id' => $request->user()->id,
                'order_number' => 'ORD-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'customer_name' => $validated['customer_name'] ?? 'Walk-in Customer',
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal, 'total' => $subtotal,
                'payment_type' => $validated['payment_method'] === 'cash' ? 'cash' : 'cashless',
                'payment_method' => $validated['payment_method'], 'status' => 'completed',
                'completed_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'client_id' => $validated['client_id'] ?? null, 'synced' => true,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = $products[$itemData['product_id']];
                $order->items()->create([
                    'product_id' => $product->id, 'product_name' => $product->name,
                    'price' => $product->price, 'quantity' => $itemData['quantity'],
                    'subtotal' => (float) $product->price * $itemData['quantity'],
                ]);
            }

            $orderedProducts = collect($validated['items'])
                ->map(fn ($item) => $item['quantity'].'× '.$products[$item['product_id']]->name)
                ->implode(', ');

            foreach ($requiredStock as $stockId => $amount) {
                $stock = $stockItems[$stockId];
                $before = (float) $stock->current_quantity;
                $stock->current_quantity = $before - $amount;
                $stock->save();
                app(NotificationService::class)->notifyLowStock($stock);
                StockMovement::create([
                    'stock_item_id' => $stock->id, 'user_id' => $request->user()->id,
                    'order_id' => $order->id, 'type' => 'out', 'quantity' => $amount,
                    'previous_quantity' => $before, 'new_quantity' => $stock->current_quantity,
                    'reason' => 'Order '.$order->order_number,
                ]);
            }

            Transaction::create([
                'business_id' => $order->business_id,
                'user_id' => $request->user()->id,
                'amount' => $order->total,
                'type' => 'income',
                'source' => $order->payment_method,
                'category' => 'Sales',
                'transaction_date' => today(),
                'description' => $orderedProducts,
                'entry_method' => 'order_system',
                'metadata' => ['order_id' => $order->id],
                'synced' => true,
            ]);

            return $order;
        });

        return response()->json(['message' => 'Order completed and transaction recorded', 'order' => $order->load('items')], 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json(['order' => Order::where('business_id', $request->user()->business_id)->with('items')->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $this->staffOnly($request);

        $validated = $request->validate(['status' => 'required|in:completed,cancelled', 'notes' => 'nullable|string|max:500']);
        $order = DB::transaction(function () use ($validated, $request, $id) {
            $order = Order::where('business_id', $request->user()->business_id)->lockForUpdate()->findOrFail($id);
            $previousStatus = $order->status;
            $allowedTransitions = [
                'pending' => ['completed', 'cancelled'],
                'preparing' => ['completed', 'cancelled'],
                'ready' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => [],
            ];
            abort_unless(in_array($validated['status'], $allowedTransitions[$previousStatus] ?? [], true), 422, "Order cannot move from {$previousStatus} to {$validated['status']}.");
            $extra = match ($validated['status']) { 'completed' => ['completed_at' => now()], 'cancelled' => ['cancelled_by' => $request->user()->id], default => [] };
            $order->update($validated + $extra);

            if ($validated['status'] === 'completed' && $previousStatus !== 'completed') {
                $order->loadMissing('items');
                $orderedProducts = $order->items
                    ->map(fn ($item) => $item->quantity.'× '.$item->product_name)
                    ->implode(', ');
                Transaction::create([
                    'business_id' => $order->business_id, 'user_id' => $request->user()->id,
                    'amount' => $order->total, 'type' => 'income', 'source' => $order->payment_method,
                    'category' => 'Sales', 'transaction_date' => today(), 'description' => $orderedProducts,
                    'entry_method' => 'order_system', 'metadata' => ['order_id' => $order->id], 'synced' => true,
                ]);
            }

            if ($validated['status'] === 'cancelled' && $previousStatus !== 'cancelled') {
                $order->load('items.product.productIngredients');
                $restock = [];
                foreach ($order->items as $item) {
                    foreach ($item->product?->productIngredients ?? [] as $ingredient) {
                        $stockId = $ingredient->stock_item_id;
                        $restock[$stockId] = ($restock[$stockId] ?? 0)
                            + ((float) $ingredient->quantity * $item->quantity);
                    }
                }
                $stockItems = StockItem::where('business_id', $order->business_id)
                    ->whereIn('id', array_keys($restock))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                foreach ($restock as $stockId => $amount) {
                    $stock = $stockItems->get($stockId);
                    if (!$stock) continue;
                    $before = (float) $stock->current_quantity;
                    $stock->current_quantity = $before + $amount;
                    $stock->save();
                    StockMovement::create(['stock_item_id' => $stock->id, 'user_id' => $request->user()->id, 'order_id' => $order->id, 'type' => 'in', 'quantity' => $amount, 'previous_quantity' => $before, 'new_quantity' => $before + $amount, 'reason' => 'Cancelled '.$order->order_number]);
                }
                Transaction::where('business_id', $order->business_id)->where('metadata->order_id', $order->id)->delete();
            }
            return $order;
        });
        return response()->json(['message' => 'Order updated', 'order' => $order->fresh('items')]);
    }

    public function destroy(Request $request, $id)
    {
        abort_unless($request->user()->isOwner(), 403, 'Only owners can delete orders.');
        $order = Order::where('business_id', $request->user()->business_id)->findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Order deleted']);
    }
}
