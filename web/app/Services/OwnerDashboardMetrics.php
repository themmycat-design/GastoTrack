<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Shared, business-scoped calculations for the owner overview and analytics.
 * Ledger income is intentionally kept separate from eligible order sales:
 * orders are not added to the transaction ledger a second time.
 */
class OwnerDashboardMetrics
{
    public function overview(User $owner): array
    {
        $business = $owner->business;
        abort_unless($business, 403, 'An active business is required to view owner metrics.');

        $businessId = (int) $business->id;
        $now = CarbonImmutable::now(config('app.timezone'));
        $monthStart = $now->startOfMonth()->startOfDay();
        $todayStart = $now->startOfDay();
        $todayEnd = $now->endOfDay();
        $monthLedger = $this->ledgerSummary($businessId, $monthStart, $todayEnd);

        $eligibleOrders = Order::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed')
            ->where('payment_status', 'confirmed');

        $todaySales = (clone $eligibleOrders)
            ->whereBetween('completed_at', [$todayStart, $todayEnd])
            ->sum('total');

        $completedOrders = Order::query()
            ->where('business_id', $businessId)
            ->where('status', 'completed');

        $activeStock = StockItem::query()
            ->where('business_id', $businessId)
            ->where('active', true);

        $activeStockCount = (clone $activeStock)->count();

        $recentTransactions = Transaction::query()
            ->where('business_id', $businessId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return [
            'business' => $business,
            'periodStart' => $monthStart,
            'periodEnd' => $now,
            'currencyCode' => strtoupper((string) ($business->currency ?: 'PHP')),
            'ledger' => $monthLedger,
            'todaySales' => round((float) $todaySales, 2),
            'recentCompletedOrders' => $completedOrders
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
            'activeProductCount' => Product::query()
                ->where('business_id', $businessId)
                ->where('active', true)
                ->count(),
            'activeStockCount' => $activeStockCount,
            'recentTransactions' => $recentTransactions,
        ];
    }

    public function analytics(
        User $owner,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $comparisonStart,
        CarbonImmutable $comparisonEnd,
        string $period = 'month',
    ): array {
        $businessId = (int) $owner->business_id;
        $current = $this->ledgerSummary($businessId, $start, $end);
        $comparison = $this->ledgerSummary($businessId, $comparisonStart, $comparisonEnd);

        $dailyRows = Transaction::query()
            ->where('business_id', $businessId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_date as report_date, type, SUM(amount) as total')
            ->groupBy('transaction_date', 'type')
            ->orderBy('transaction_date')
            ->get();

        $chartInterval = $period === 'year' || $start->diffInDays($end) > 60 ? 'month' : 'day';
        $bucketTotals = [];
        foreach ($dailyRows as $row) {
            $key = $chartInterval === 'month'
                ? substr((string) $row->report_date, 0, 7)
                : (string) $row->report_date;
            $type = (string) $row->type;
            $bucketTotals[$key][$type] = round(($bucketTotals[$key][$type] ?? 0) + (float) $row->total, 2);
        }

        $labels = [];
        $dates = [];
        $incomeValues = [];
        $expenseValues = [];
        for (
            $bucket = $chartInterval === 'month' ? $start->startOfMonth() : $start;
            $bucket->lte($end);
            $bucket = $chartInterval === 'month' ? $bucket->addMonth() : $bucket->addDay()
        ) {
            $key = $chartInterval === 'month' ? $bucket->format('Y-m') : $bucket->toDateString();
            $dates[] = $key;
            $labels[] = $chartInterval === 'month' ? $bucket->format('M') : $bucket->format('M j');
            $incomeValues[] = $bucketTotals[$key]['income'] ?? 0;
            $expenseValues[] = $bucketTotals[$key]['expense'] ?? 0;
        }

        $productSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.business_id', $businessId)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', 'completed')
            ->whereIn('orders.payment_status', ['confirmed', 'refund_pending'])
            ->whereNotNull('orders.payment_confirmed_at')
            ->whereNotNull('orders.financials_committed_at')
            ->whereBetween('orders.completed_at', [$start, $end])
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
        // Allocate any order-level discount across its items. This is product
        // sales, not a second addition to the transaction-ledger income.
        $itemSalesExpression = 'CASE WHEN orders.subtotal > 0 THEN order_items.subtotal * orders.total / orders.subtotal ELSE 0 END';
        $productSalesTotal = (float) (clone $productSales)
            ->selectRaw("COALESCE(SUM($itemSalesExpression), 0) as amount")
            ->first()->amount;
        $topSellingProducts = (clone $productSales)
            ->select('order_items.product_id')
            ->selectRaw('COALESCE(MAX(products.name), MAX(order_items.product_name)) as product_name')
            ->selectRaw('MAX(CASE WHEN products.deleted_at IS NULL THEN products.image END) as image')
            ->selectRaw('SUM(order_items.quantity) as units_sold')
            ->selectRaw("COALESCE(SUM($itemSalesExpression), 0) as sales_amount")
            ->groupBy('order_items.product_id')
            ->orderByDesc('units_sold')
            ->orderByDesc('sales_amount')
            ->limit(5)
            ->get()
            ->each(function ($item) use ($productSalesTotal): void {
                $item->sales_amount = round((float) $item->sales_amount, 2);
                $item->sales_share = $productSalesTotal > 0
                    ? round($item->sales_amount / $productSalesTotal * 100, 1)
                    : 0;
            });

        return [
            'current' => $current,
            'comparison' => $comparison,
            'growth' => [
                'income' => $this->percentageChange($current['income'], $comparison['income']),
                'expenses' => $this->percentageChange($current['expenses'], $comparison['expenses']),
                'transactions' => $this->percentageChange($current['transactionCount'], $comparison['transactionCount']),
            ],
            'startDate' => $start,
            'endDate' => $end,
            'comparisonStartDate' => $comparisonStart,
            'comparisonEndDate' => $comparisonEnd,
            'chartInterval' => $chartInterval,
            'chart' => [
                'labels' => $labels,
                'dates' => $dates,
                'income' => $incomeValues,
                'expenses' => $expenseValues,
            ],
            'topSellingProducts' => $topSellingProducts,
        ];
    }

    private function ledgerSummary(int $businessId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $base = Transaction::query()
            ->where('business_id', $businessId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()]);

        $totals = (clone $base)
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $income = round((float) ($totals['income'] ?? 0), 2);
        $expenses = round((float) ($totals['expense'] ?? 0), 2);

        return [
            'income' => $income,
            'expenses' => $expenses,
            'netRecordedMovement' => round($income - $expenses, 2),
            'transactionCount' => (clone $base)->count(),
        ];
    }

    private function percentageChange(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous <= 0) {
            return null;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }

}
