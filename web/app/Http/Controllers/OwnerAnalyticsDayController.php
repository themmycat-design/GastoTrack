<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StockMovement;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class OwnerAnalyticsDayController extends Controller
{
    public function __invoke(Request $request, string $date)
    {
        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)),
            404,
        );

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, config('app.timezone'));
        abort_if($day->gt(CarbonImmutable::today(config('app.timezone'))), 404);

        $businessId = (int) $request->user()->business_id;
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $transactionQuery = Transaction::query()
            ->where('business_id', $businessId)
            ->whereDate('transaction_date', $date);

        $totals = (clone $transactionQuery)
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $transactions = (clone $transactionQuery)
            ->with('user:id,name')
            ->latest('created_at')
            ->paginate(15, ['*'], 'transactions_page')
            ->withQueryString();

        $orders = Order::query()
            ->where('business_id', $businessId)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('completed_at', [$start, $end])
                    ->orWhereBetween('financials_committed_at', [$start, $end]);
            })
            ->with('items:id,order_id,product_name,quantity')
            ->latest('created_at')
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();

        $ingredientsUsed = StockMovement::query()
            ->join('stock_items', 'stock_movements.stock_item_id', '=', 'stock_items.id')
            ->join('orders', 'stock_movements.order_id', '=', 'orders.id')
            ->where('stock_items.business_id', $businessId)
            ->where('orders.business_id', $businessId)
            ->where('stock_movements.type', 'out')
            ->whereBetween('stock_movements.created_at', [$start, $end])
            ->selectRaw('stock_items.id as stock_item_id, stock_items.name, stock_items.unit, SUM(stock_movements.quantity) as quantity_used, COUNT(DISTINCT stock_movements.order_id) as order_count')
            ->groupBy('stock_items.id', 'stock_items.name', 'stock_items.unit')
            ->orderBy('stock_items.name')
            ->get();

        return view('analytics.day', [
            'day' => $day,
            'transactions' => $transactions,
            'orders' => $orders,
            'ingredientsUsed' => $ingredientsUsed,
            'income' => round((float) ($totals['income'] ?? 0), 2),
            'expenses' => round((float) ($totals['expense'] ?? 0), 2),
            'currencyCode' => strtoupper((string) ($request->user()->business?->currency ?: 'PHP')),
        ]);
    }
}
