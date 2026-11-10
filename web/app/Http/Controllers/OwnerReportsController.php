<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ActivityLog;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletNotificationCapture;
use App\Services\OwnerDashboardMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerReportsController extends Controller
{
    private const REPORT_SECTIONS = [
        'sales-orders' => ['key' => 'sales', 'title' => 'Sales and orders'],
        'income-expenses' => ['key' => 'ledger', 'title' => 'Income and expenses'],
        'product-costs' => ['key' => 'products', 'title' => 'Product costs and margins'],
        'inventory-waste' => ['key' => 'inventory', 'title' => 'Inventory and waste'],
        'payment-review' => ['key' => 'payments', 'title' => 'Payment review'],
        'staff-activity' => ['key' => 'staff', 'title' => 'Staff activity'],
    ];

    public function index(Request $request)
    {
        return redirect()->route('owner.reports.show', array_merge(
            ['section' => 'sales-orders'],
            $request->only(['start_date', 'end_date']),
        ));
    }

    public function show(Request $request, OwnerDashboardMetrics $metrics, string $section)
    {
        abort_unless(isset(self::REPORT_SECTIONS[$section]), 404);
        [$start, $end] = $this->dateRange($request);

        return view('reports.owner', array_merge(
            $this->reportData($request, $metrics, $start, $end),
            [
                'activeReportRoute' => $section,
                'activeReportSection' => self::REPORT_SECTIONS[$section]['key'],
                'sectionTitle' => self::REPORT_SECTIONS[$section]['title'],
            ],
        ));
    }

    public function export(Request $request, OwnerDashboardMetrics $metrics): StreamedResponse
    {
        [$start, $end] = $this->dateRange($request);
        $report = $this->reportData($request, $metrics, $start, $end);

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Section', 'Date', 'Name', 'Type / Category', 'Count / Quantity', 'Amount', 'Cost', 'Status', 'Recorded by']);

            $write = function (array $row) use ($stream): void {
                $row[2] = $this->safeCsvText($row[2] ?? '');
                $row[3] = $this->safeCsvText($row[3] ?? '');
                $row[4] = $this->safeCsvText((string) ($row[4] ?? ''));
                $row[7] = $this->safeCsvText($row[7] ?? '');
                $row[8] = $this->safeCsvText($row[8] ?? '');
                fputcsv($stream, $row);
            };

            foreach ([
                ['Sales summary', '', 'Completed paid orders', 'Confirmed sales', $report['sales']['orderCount'], $report['sales']['amount'], '', '', ''],
                ['Ledger summary', '', 'Recorded income', 'Income', '', $report['ledger']['income'], '', '', ''],
                ['Ledger summary', '', 'Recorded expenses', 'Expense', '', $report['ledger']['expenses'], '', '', ''],
                ['Product costs', '', 'Recipe ingredient cost', '', '', $report['costInsights']['ingredientCost'], '', '', ''],
                ['Product costs', '', 'Estimated gross margin', '', '', $report['costInsights']['grossMargin'], '', '', ''],
                ['Inventory / waste', '', 'Recorded waste cost', '', '', $report['costInsights']['wasteCost'], '', '', ''],
                ['Payment review', '', 'Cashless orders awaiting payment', '', $report['payments']['awaitingCount'], '', '', 'Pending / detected', ''],
                ['Payment review', '', 'Refunds awaiting review', '', $report['payments']['refundCount'], '', '', 'Refund pending', ''],
                ['Payment review', '', 'Raw wallet transactions', '', $report['payments']['walletCount'], '', '', 'Unverified', ''],
            ] as $row) {
                $write($row);
            }

            foreach ($report['sales']['paymentMethods'] as $method) {
                $write(['Sales by payment method', '', $method->payment_method ?: $method->payment_type, 'Payment method', $method->order_count, $method->sales_amount, '', '', '']);
            }
            $businessId = (int) $report['business']->id;
            $start = $report['startDate'];
            $end = $report['endDate'];
            $this->eligibleSalesOrders($businessId, $start, $end)
                ->with('creator:id,name')
                ->orderBy('orders.id')
                ->chunkById(500, function ($orders) use ($write): void {
                    foreach ($orders as $order) {
                        $write(['Sales', $order->completed_at?->toDateString(), 'Completed order', $order->payment_method ?: $order->payment_type, 1, $order->total, '', $order->payment_status, $order->creator?->name]);
                    }
                }, 'orders.id', 'id');
            foreach ($report['topSellingProducts'] as $product) {
                $write(['Product performance', '', $product->product_name, 'Top seller', $product->units_sold, $product->sales_amount, $product->cost_amount, $product->cost_basis, '']);
            }
            Transaction::query()
                ->where('business_id', $businessId)
                ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
                ->with('user:id,name')
                ->orderBy('id')
                ->chunkById(500, function ($transactions) use ($write): void {
                    foreach ($transactions as $transaction) {
                        $write(['Ledger', $transaction->transaction_date?->toDateString(), $transaction->category, $transaction->type, '', $transaction->amount, '', '', $transaction->user?->name]);
                    }
                });
            StockItem::query()
                ->where('business_id', $businessId)
                ->where('active', true)
                ->orderBy('id')
                ->chunkById(500, function ($items) use ($write): void {
                    foreach ($items as $item) {
                        $write(['Current inventory', '', $item->name, $item->unit, $item->current_quantity, '', (float) $item->current_quantity * (float) $item->unit_cost, (float) $item->current_quantity <= 0 ? 'Out of stock' : ((float) $item->current_quantity <= (float) $item->minimum_quantity ? 'Low stock' : 'In stock'), '']);
                    }
                });
            DB::table('stock_movements')
                ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
                ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
                ->where('stock_items.business_id', $businessId)
                ->whereBetween('stock_movements.created_at', [$start, $end])
                ->select([
                    'stock_movements.id as movement_id', 'stock_movements.created_at as movement_created_at',
                    'stock_movements.type as movement_type', 'stock_movements.movement_category',
                    'stock_movements.quantity', 'stock_movements.reason', 'stock_items.name as stock_name',
                    'stock_items.unit', 'users.name as recorded_by',
                ])
                ->orderBy('stock_movements.id')
                ->chunkById(500, function ($movements) use ($write): void {
                    foreach ($movements as $movement) {
                        $write(['Stock movement', $movement->movement_created_at, $movement->stock_name, $movement->movement_category ?: $movement->movement_type, $movement->quantity.' '.$movement->unit, '', '', $movement->reason ?: $movement->movement_type, $movement->recorded_by]);
                    }
                }, 'stock_movements.id', 'movement_id');
            DB::table('stock_movements')
                ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
                ->where('stock_items.business_id', $businessId)
                ->where('stock_movements.type', 'out')
                ->where('stock_movements.movement_category', 'waste')
                ->whereNull('stock_movements.reversal_of_id')
                ->whereBetween('stock_movements.created_at', [$start, $end])
                ->select('stock_items.id as stock_item_id', 'stock_items.name', 'stock_items.unit')
                ->selectRaw('SUM(stock_movements.quantity) as quantity')
                ->selectRaw('SUM(stock_movements.quantity * COALESCE(NULLIF(stock_movements.unit_cost_at_movement, 0), NULLIF(stock_items.unit_cost, 0), 0)) as cost')
                ->groupBy('stock_items.id', 'stock_items.name', 'stock_items.unit')
                ->orderByDesc('cost')
                ->get()
                ->each(fn ($item) => $write(['Recorded waste', '', $item->name, $item->unit, $item->quantity, '', $item->cost, 'Waste', '']));
            WalletNotificationCapture::transactionSignals()
                ->where('business_id', $businessId)
                ->whereBetween('captured_at', [$start, $end])
                ->with('capturedBy:id,name')
                ->orderBy('id')
                ->chunkById(500, function ($captures) use ($write): void {
                    foreach ($captures as $capture) {
                        $write(['Unverified wallet transaction', $capture->captured_at?->toDateString(), $capture->merchant ?: 'Wallet transaction', strtoupper($capture->source).' ('.strtoupper($capture->currency ?: 'PHP').')', '', $capture->amount, '', 'Unverified', $capture->capturedBy?->name]);
                    }
                });
            foreach ($report['staffActivity'] as $staff) {
                $write(['Staff activity', '', $staff['name'], 'Orders / ledger / stock changes', $staff['orders'] + $staff['ledgerEntries'] + $staff['stockChanges'], $staff['confirmedSales'], '', '', '']);
            }

            fclose($stream);
        }, 'gastotrack-owner-reports-'.$start->toDateString().'-to-'.$end->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $timezone = config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $end = isset($validated['end_date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['end_date'], $timezone)
            : $today;
        $start = isset($validated['start_date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['start_date'], $timezone)
            : $end->startOfMonth();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        if ($start->diffInDays($end) > 365) {
            throw ValidationException::withMessages([
                'start_date' => 'Choose a reporting period of one year or less.',
            ]);
        }

        return [$start->startOfDay(), $end->endOfDay()];
    }

    private function reportData(Request $request, OwnerDashboardMetrics $metrics, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $owner = $request->user();
        abort_unless($owner->business_id, 403, 'An active business is required to view owner reports.');

        $businessId = (int) $owner->business_id;
        $days = max(0, (int) $start->diffInDays($end));
        $analytics = $metrics->analytics(
            $owner,
            $start,
            $end,
            $start->subDays($days + 1)->startOfDay(),
            $start->subDay()->endOfDay(),
            'custom',
        );

        $eligibleOrders = $this->eligibleSalesOrders($businessId, $start, $end);
        $salesTotals = (clone $eligibleOrders)
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as amount')
            ->first();
        $orders = (clone $eligibleOrders)
            ->with('creator:id,name')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'user_id', 'total', 'payment_method', 'payment_type', 'payment_status', 'completed_at']);
        $paymentMethods = (clone $eligibleOrders)
            ->select('payment_type', 'payment_method')
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as sales_amount')
            ->groupBy('payment_type', 'payment_method')
            ->orderByDesc('sales_amount')
            ->get();

        $transactionsQuery = Transaction::query()
            ->where('business_id', $businessId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);
        $transactions = (clone $transactionsQuery)
            ->with('user:id,name')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
        $ledgerCategories = (clone $transactionsQuery)
            ->select('type', 'category')
            ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('type', 'category')
            ->orderByDesc('amount')
            ->limit(8)
            ->get();

        $activeStock = StockItem::query()
            ->where('business_id', $businessId)
            ->where('active', true)
            ->get(['id', 'name', 'unit', 'current_quantity', 'minimum_quantity', 'unit_cost']);
        $lowStockItems = $activeStock
            ->filter(fn (StockItem $item): bool => (float) $item->current_quantity <= (float) $item->minimum_quantity)
            ->sortBy('current_quantity')
            ->take(8)
            ->values();
        $inventory = [
            'itemCount' => $activeStock->count(),
            'outOfStock' => $activeStock->filter(fn (StockItem $item): bool => (float) $item->current_quantity <= 0)->count(),
            'lowStock' => $activeStock->filter(fn (StockItem $item): bool => (float) $item->current_quantity > 0 && (float) $item->current_quantity <= (float) $item->minimum_quantity)->count(),
            'estimatedValue' => round($activeStock->sum(fn (StockItem $item): float => (float) $item->current_quantity * (float) $item->unit_cost), 2),
        ];

        $movementBase = DB::table('stock_movements')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->where('stock_items.business_id', $businessId)
            ->whereBetween('stock_movements.created_at', [$start, $end]);
        $stockMovementSummary = (clone $movementBase)
            ->select('stock_movements.type', 'stock_movements.movement_category')
            ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(stock_movements.quantity), 0) as quantity')
            ->groupBy('stock_movements.type', 'stock_movements.movement_category')
            ->orderByDesc('entry_count')
            ->get();
        $recentStockMovements = (clone $movementBase)
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(8)
            ->get([
                'stock_movements.created_at', 'stock_movements.type', 'stock_movements.movement_category',
                'stock_movements.quantity', 'stock_movements.reason', 'stock_items.name as stock_name',
                'stock_items.unit', 'users.name as recorded_by',
            ]);

        $cashlessAwaiting = Order::query()
            ->where('business_id', $businessId)
            ->where('payment_type', 'cashless')
            ->whereIn('payment_status', ['pending', 'detected'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end]);
        $refundsAwaiting = Order::query()
            ->where('business_id', $businessId)
            ->where('payment_status', 'refund_pending')
            ->whereBetween('refund_requested_at', [$start, $end]);
        $walletQuery = WalletNotificationCapture::transactionSignals()
            ->where('business_id', $businessId)
            ->whereBetween('captured_at', [$start, $end]);
        $walletSourceCounts = (clone $walletQuery)
            ->select('source')
            ->selectRaw('COUNT(*) as alert_count')
            ->groupBy('source')
            ->orderByDesc('alert_count')
            ->get();
        $walletCaptures = (clone $walletQuery)
            ->with('capturedBy:id,name')
            ->orderByDesc('captured_at')
            ->limit(8)
            ->get();

        $staffActivity = $this->staffActivity($businessId, $start, $end, $eligibleOrders);
        $staffActions = null;
        if ($request->routeIs('owner.reports.show') && $request->route('section') === 'staff-activity') {
            $staffIds = User::withTrashed()->where('business_id', $businessId)
                ->where('role', 'staff')->pluck('id');
            $staffActions = ActivityLog::query()
                ->where('business_id', $businessId)
                ->whereIn('user_id', $staffIds)
                ->whereBetween('created_at', [$start, $end])
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(25, ['*'], 'activity_page')
                ->withQueryString();
        }

        return [
            'business' => $owner->business,
            'currencyCode' => strtoupper((string) ($owner->business?->currency ?: 'PHP')),
            'startDate' => $start,
            'endDate' => $end,
            'ledger' => $analytics['current'],
            'costInsights' => $analytics['costInsights'],
            'topSellingProducts' => $analytics['topSellingProducts'],
            'sales' => [
                'orderCount' => (int) $salesTotals->order_count,
                'amount' => round((float) $salesTotals->amount, 2),
                'averageOrder' => (int) $salesTotals->order_count > 0
                    ? round((float) $salesTotals->amount / (int) $salesTotals->order_count, 2)
                    : 0.0,
                'paymentMethods' => $paymentMethods,
            ],
            'orders' => $orders,
            'transactions' => $transactions,
            'ledgerCategories' => $ledgerCategories,
            'inventory' => $inventory,
            'lowStockItems' => $lowStockItems,
            'stockMovementSummary' => $stockMovementSummary,
            'recentStockMovements' => $recentStockMovements,
            'payments' => [
                'awaitingCount' => (clone $cashlessAwaiting)->count(),
                'refundCount' => (clone $refundsAwaiting)->count(),
                'walletCount' => (clone $walletQuery)->count(),
                'walletSources' => $walletSourceCounts,
            ],
            'walletCaptures' => $walletCaptures,
            'staffActivity' => $staffActivity,
            'staffActions' => $staffActions,
        ];
    }

    private function eligibleSalesOrders(int $businessId, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Order::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereIn('payment_status', ['confirmed', 'refund_pending'])
            ->whereNotNull('payment_confirmed_at')
            ->whereNotNull('financials_committed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.business_id', 'orders.business_id')
                    ->where('transactions.type', 'income')
                    ->whereNull('transactions.deleted_at')
                    ->whereNull('transactions.reversal_of_id')
                    ->where(function ($linked): void {
                        $linked->whereColumn('transactions.order_id', 'orders.id')
                            ->orWhereColumn('transactions.metadata->order_id', 'orders.id');
                    });
            });
    }

    private function staffActivity(int $businessId, CarbonImmutable $start, CarbonImmutable $end, Builder $eligibleOrders): array
    {
        $orderStats = (clone $eligibleOrders)
            ->select('user_id')
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total), 0) as confirmed_sales')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
        $ledgerStats = Transaction::query()
            ->where('business_id', $businessId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->select('user_id')
            ->selectRaw('COUNT(*) as entry_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
        $movementStats = DB::table('stock_movements')
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->where('stock_items.business_id', $businessId)
            ->whereBetween('stock_movements.created_at', [$start, $end])
            ->select('stock_movements.user_id')
            ->selectRaw('COUNT(*) as movement_count')
            ->groupBy('stock_movements.user_id')
            ->get()
            ->keyBy('user_id');

        return User::withTrashed()
            ->where('business_id', $businessId)
            ->where('role', 'staff')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $staff) use ($orderStats, $ledgerStats, $movementStats): array {
                $orders = $orderStats->get($staff->id);
                $ledger = $ledgerStats->get($staff->id);
                $movements = $movementStats->get($staff->id);

                return [
                    'name' => $staff->name,
                    'orders' => (int) ($orders->order_count ?? 0),
                    'confirmedSales' => round((float) ($orders->confirmed_sales ?? 0), 2),
                    'ledgerEntries' => (int) ($ledger->entry_count ?? 0),
                    'stockChanges' => (int) ($movements->movement_count ?? 0),
                ];
            })
            ->all();
    }

    private function safeCsvText(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
