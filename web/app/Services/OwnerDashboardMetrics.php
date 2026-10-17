<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
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

        $productCosts = $this->productCostSummary($businessId, $start, $end, $productSalesTotal);
        foreach ($topSellingProducts as $product) {
            $cost = $productCosts['products'][(string) $product->product_id] ?? null;
            $product->ingredient_cost = $cost && $cost['has_recipe']
                ? round($cost['ingredient_cost'], 2)
                : null;
            $product->gross_margin = $cost && $cost['fully_costed']
                ? round($product->sales_amount - $cost['cost_amount'], 2)
                : null;
            $product->cost_amount = $cost && $cost['fully_costed']
                ? round($cost['cost_amount'], 2)
                : null;
            $product->gross_margin_percent = $cost && $cost['fully_costed'] && $product->sales_amount > 0
                ? round(($product->gross_margin / $product->sales_amount) * 100, 1)
                : null;
            $product->cost_basis = $cost['cost_basis'] ?? 'Missing cost';
        }

        $wasteCosts = $this->wasteCostSummary($businessId, $start, $end);

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
            'costInsights' => [
                'ingredientCost' => round($productCosts['ingredient_cost'], 2),
                'ingredientCostCoveragePercent' => $productSalesTotal > 0
                    ? round($productCosts['ingredient_costed_sales'] / $productSalesTotal * 100, 1)
                    : 0,
                'grossMargin' => round($productCosts['gross_margin'], 2),
                'costedSales' => round($productCosts['costed_sales'], 2),
                'costCoveragePercent' => $productSalesTotal > 0
                    ? round($productCosts['costed_sales'] / $productSalesTotal * 100, 1)
                    : 0,
                'ingredientCostEstimated' => $productCosts['ingredient_cost_estimated'],
                'wasteCost' => $wasteCosts['total'],
                'wasteCostEstimated' => $wasteCosts['estimated'],
                'wasteItems' => $wasteCosts['items'],
            ],
        ];
    }

    /**
     * Cost sold items from their sale-time recipe/unit-cost snapshots. Older
     * orders fall back to the current recipe and are explicitly marked estimated.
     */
    private function productCostSummary(
        int $businessId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        float $eligibleSales,
    ): array {
        $products = [];
        $ingredientCostTotal = 0.0;
        $grossMarginTotal = 0.0;
        $costedSalesTotal = 0.0;
        $ingredientCostedSalesTotal = 0.0;
        $ingredientCostEstimated = false;

        $orders = Order::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereIn('payment_status', ['confirmed', 'refund_pending', 'refunded'])
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
            })
            ->with('items')
            ->orderBy('id');

        $orders->chunkById(100, function ($batch) use (
            $businessId,
            &$products,
            &$ingredientCostTotal,
            &$grossMarginTotal,
            &$costedSalesTotal,
            &$ingredientCostedSalesTotal,
            &$ingredientCostEstimated,
        ): void {
            $items = $batch->flatMap->items;
            if ($items->isEmpty()) {
                return;
            }

            $productIds = $items->pluck('product_id')->filter()->unique()->values();
            $currentProductCosts = DB::table('products')
                ->where('business_id', $businessId)
                ->whereIn('id', $productIds)
                ->pluck('cost', 'id');
            $currentRecipes = DB::table('product_ingredients')
                ->join('stock_items', 'stock_items.id', '=', 'product_ingredients.stock_item_id')
                ->join('products', 'products.id', '=', 'product_ingredients.product_id')
                ->where('products.business_id', $businessId)
                ->where('stock_items.business_id', $businessId)
                ->whereIn('product_ingredients.product_id', $productIds)
                ->select([
                    'product_ingredients.product_id',
                    'product_ingredients.stock_item_id',
                    'product_ingredients.quantity',
                    'stock_items.unit_cost',
                ])
                ->get()
                ->groupBy('product_id');

            $snapshotStockIds = $items->flatMap(function ($item): array {
                return collect($item->ingredients_snapshot ?? [])
                    ->filter(fn ($ingredient): bool =>
                        !array_key_exists('unit_cost', $ingredient)
                        || $ingredient['unit_cost'] === null
                        || (float) $ingredient['unit_cost'] <= 0
                    )
                    ->pluck('stock_item_id')
                    ->filter()
                    ->all();
            })->unique()->values();
            $currentStockCosts = $snapshotStockIds->isEmpty()
                ? collect()
                : DB::table('stock_items')
                    ->where('business_id', $businessId)
                    ->whereIn('id', $snapshotStockIds)
                    ->pluck('unit_cost', 'id');

            foreach ($batch as $order) {
                foreach ($order->items as $item) {
                    // A confirmed refund removes the sale. If stock was returned,
                    // it also removes the product cost; otherwise the consumed
                    // ingredients remain a real cost with zero net sales.
                    if ($order->payment_status === 'refunded' && $order->refund_restocked) {
                        continue;
                    }

                    $productId = (string) $item->product_id;
                    $quantity = max(0, (int) $item->quantity);
                    $lineSales = $order->payment_status !== 'refunded' && (float) $order->subtotal > 0
                        ? (float) $item->subtotal * (float) $order->total / (float) $order->subtotal
                        : 0.0;
                    $snapshot = $item->ingredients_snapshot;
                    $recipe = is_array($snapshot)
                        ? collect($snapshot)
                        : collect($currentRecipes->get($item->product_id, []))->map(fn ($ingredient): array => [
                            'stock_item_id' => $ingredient->stock_item_id,
                            'quantity' => $ingredient->quantity,
                            'unit_cost' => $ingredient->unit_cost,
                        ]);

                    $hasRecipe = $recipe->isNotEmpty();
                    $recipeCostPerUnit = 0.0;
                    $recipeCostComplete = $hasRecipe;
                    $recipeCostUsesCurrentValues = !is_array($snapshot);

                    foreach ($recipe as $ingredient) {
                        $unitCost = $ingredient['unit_cost'] ?? null;
                        if ($unitCost === null || (float) $unitCost <= 0) {
                            $unitCost = $currentStockCosts->get($ingredient['stock_item_id'] ?? null);
                            $recipeCostUsesCurrentValues = true;
                        }
                        if ($unitCost === null || (float) $unitCost <= 0) {
                            $recipeCostComplete = false;
                            continue;
                        }

                        $recipeCostPerUnit += (float) ($ingredient['quantity'] ?? 0) * (float) $unitCost;
                    }

                    $ingredientLineCost = $recipeCostComplete
                        ? $recipeCostPerUnit * $quantity
                        : null;
                    $productCostPerUnit = null;
                    $costBasis = null;
                    if ($recipeCostComplete) {
                        $productCostPerUnit = $recipeCostPerUnit;
                        $costBasis = $recipeCostUsesCurrentValues ? 'Estimated recipe cost' : 'Recipe cost';
                    } else {
                        $snapshotProductCost = $item->product_cost_snapshot;
                        $currentProductCost = $currentProductCosts->get($item->product_id);
                        if ($snapshotProductCost !== null && (float) $snapshotProductCost > 0) {
                            $productCostPerUnit = (float) $snapshotProductCost;
                            $costBasis = 'Configured product cost';
                        } elseif ($currentProductCost !== null && (float) $currentProductCost > 0) {
                            $productCostPerUnit = (float) $currentProductCost;
                            $costBasis = 'Estimated product cost';
                        }
                    }

                    $costAmount = $productCostPerUnit === null ? null : $productCostPerUnit * $quantity;
                    if (!isset($products[$productId])) {
                        $products[$productId] = [
                            'ingredient_cost' => 0.0,
                            'has_recipe' => false,
                            'cost_amount' => 0.0,
                            'costed_sales' => 0.0,
                            'all_costed' => true,
                            'cost_bases' => [],
                        ];
                    }

                    if ($hasRecipe && $ingredientLineCost !== null) {
                        $products[$productId]['ingredient_cost'] += $ingredientLineCost;
                        $products[$productId]['has_recipe'] = true;
                        $ingredientCostTotal += $ingredientLineCost;
                        $ingredientCostedSalesTotal += $lineSales;
                    }
                    if ($hasRecipe && $recipeCostUsesCurrentValues) {
                        $ingredientCostEstimated = true;
                    }

                    if ($costAmount === null) {
                        $products[$productId]['all_costed'] = false;
                        continue;
                    }

                    $products[$productId]['cost_amount'] += $costAmount;
                    $products[$productId]['costed_sales'] += $lineSales;
                    $products[$productId]['cost_bases'][$costBasis] = true;
                    $costedSalesTotal += $lineSales;
                    $grossMarginTotal += $lineSales - $costAmount;
                }
            }
        });

        foreach ($products as &$product) {
            $product['fully_costed'] = $product['all_costed'];
            $product['cost_basis'] = count($product['cost_bases']) > 1
                ? 'Mixed cost basis'
                : (array_key_first($product['cost_bases']) ?? 'Missing cost');
            unset($product['all_costed'], $product['cost_bases'], $product['costed_sales']);
        }
        unset($product);

        return [
            'products' => $products,
            'ingredient_cost' => $ingredientCostTotal,
            'ingredient_costed_sales' => min($ingredientCostedSalesTotal, $eligibleSales),
            'gross_margin' => $grossMarginTotal,
            'costed_sales' => min($costedSalesTotal, $eligibleSales),
            'ingredient_cost_estimated' => $ingredientCostEstimated,
        ];
    }

    private function wasteCostSummary(int $businessId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $query = StockMovement::query()
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->where('stock_items.business_id', $businessId)
            ->where('stock_movements.type', 'out')
            ->where('stock_movements.movement_category', 'waste')
            ->whereNull('stock_movements.reversal_of_id')
            ->whereBetween('stock_movements.created_at', [$start, $end]);

        $costExpression = 'COALESCE(NULLIF(stock_movements.unit_cost_at_movement, 0), NULLIF(stock_items.unit_cost, 0), 0)';
        $total = (float) (clone $query)
            ->selectRaw("COALESCE(SUM(stock_movements.quantity * $costExpression), 0) as total")
            ->value('total');
        $estimated = (clone $query)
            ->where(function ($builder): void {
                $builder->whereNull('stock_movements.unit_cost_at_movement')
                    ->orWhere('stock_movements.unit_cost_at_movement', '<=', 0)
                    ->orWhere('stock_items.unit_cost', '<=', 0);
            })->exists();
        $items = (clone $query)
            ->select('stock_items.id as stock_item_id', 'stock_items.name', 'stock_items.unit')
            ->selectRaw('SUM(stock_movements.quantity) as quantity')
            ->selectRaw("SUM(stock_movements.quantity * $costExpression) as cost")
            ->groupBy('stock_items.id', 'stock_items.name', 'stock_items.unit')
            ->orderByDesc('cost')
            ->limit(5)
            ->get();

        return [
            'total' => round($total, 2),
            'estimated' => $estimated,
            'items' => $items,
        ];
    }

    public function wasteCostsForOwner(User $owner, CarbonImmutable $start, CarbonImmutable $end): array
    {
        abort_unless($owner->business_id, 403, 'An active business is required to view owner metrics.');

        return $this->wasteCostSummary((int) $owner->business_id, $start, $end);
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
