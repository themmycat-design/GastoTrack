<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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

        $attentionPayments = Order::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['pending', 'detected', 'refund_pending'])
            ->selectRaw('payment_status, COUNT(*) as status_count')
            ->groupBy('payment_status')
            ->pluck('status_count', 'payment_status');

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
            'attentionPayments' => $this->paymentCountMap($attentionPayments),
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

        $stockItems = StockItem::query()
            ->where('business_id', $businessId)
            ->where('active', true)
            ->select('stock_items.*')
            ->selectRaw('(current_quantity * unit_cost) as stock_value')
            ->orderByRaw('(current_quantity * unit_cost) DESC')
            ->limit(5)
            ->get();

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
            'topStockItems' => $stockItems,
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

    /** @param Collection<string, int|string> $counts */
    private function paymentCountMap(Collection $counts): array
    {
        $statuses = ['pending', 'detected', 'refund_pending'];
        $result = array_fill_keys($statuses, 0);

        foreach ($counts as $status => $count) {
            if (array_key_exists((string) $status, $result)) {
                $result[(string) $status] = (int) $count;
            }
        }

        return $result;
    }
}
