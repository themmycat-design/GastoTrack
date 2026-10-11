<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Order;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class MonthlySalesGoals
{
    public function monthStart(?string $month = null): CarbonImmutable
    {
        $timezone = config('business.timezone', 'Asia/Manila');

        return $month
            ? CarbonImmutable::createFromFormat('!Y-m-d', $month.'-01', $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfMonth();
    }

    public function recordedSales(Goal $goal): float
    {
        if ($goal->goal_type !== 'monthly_confirmed_sales' || !$goal->period_start) {
            return (float) $goal->current_amount;
        }

        $start = CarbonImmutable::parse($goal->period_start->format('Y-m-d'), config('business.timezone'))->startOfDay();
        $end = $start->addMonth();

        // A confirmed refund removes the original sale from its original month.
        // A pending refund remains counted until its accounting reversal is saved.
        $orders = Order::query()
            ->where('business_id', $goal->business_id)
            ->where('status', 'completed')
            ->whereIn('payment_status', ['confirmed', 'refund_pending'])
            ->whereNotNull('payment_confirmed_at')
            ->whereNotNull('financials_committed_at')
            ->where('completed_at', '>=', $start->utc())
            ->where('completed_at', '<', $end->utc())
            ->get(['id']);

        if ($orders->isEmpty()) {
            return 0.0;
        }

        $totalCents = 0;
        $seen = [];
        // Chunk IDs to stay below SQLite's parameter limit in tests and avoid
        // oversized queries for busy shops. Count each linked ledger sale once.
        foreach ($orders->pluck('id')->chunk(400) as $chunk) {
            $orderIds = $chunk->all();
            $allowed = array_fill_keys($orderIds, true);
            $sales = Transaction::query()
                ->where('business_id', $goal->business_id)
                ->where('type', 'income')
                ->whereNull('reversal_of_id')
                ->where(function ($query) use ($orderIds): void {
                    $query->whereIn('order_id', $orderIds)
                        ->orWhereIn('metadata->order_id', $orderIds);
                })
                ->orderBy('id')
                ->get(['order_id', 'metadata', 'amount']);

            foreach ($sales as $sale) {
                $orderId = (int) ($sale->order_id ?: ($sale->metadata['order_id'] ?? 0));
                if (!isset($allowed[$orderId]) || isset($seen[$orderId])) {
                    continue;
                }
                $seen[$orderId] = true;
                $totalCents += (int) round((float) $sale->amount * 100);
            }
        }

        return $totalCents / 100;
    }

    public function present(Goal $goal): array
    {
        $current = $this->recordedSales($goal);
        $target = (float) $goal->target_amount;
        $data = $goal->toArray();
        $data['current_amount'] = number_format($current, 2, '.', '');
        $data['progress_percentage'] = $target > 0 ? round(min(100, $current / $target * 100), 1) : 0;
        $data['target_reached'] = $target > 0 && $current >= $target;

        return $data;
    }
}
