<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OwnerCostLogController extends Controller
{
    public function __invoke(Request $request)
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

        $businessId = (int) $request->user()->business_id;
        $orders = Order::query()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->where('status', 'completed')
            ->whereIn('payment_status', ['confirmed', 'refund_pending', 'refunded'])
            ->whereNotNull('payment_confirmed_at')
            ->whereNotNull('financials_committed_at')
            ->whereBetween('completed_at', [$start->startOfDay(), $end->endOfDay()])
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
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'orders_page')
            ->withQueryString();

        $orderItems = $orders->getCollection()->flatMap->items;
        $productIds = $orderItems->pluck('product_id')->filter()->unique()->values();
        $currentProductCosts = $productIds->isEmpty()
            ? collect()
            : DB::table('products')
                ->where('business_id', $businessId)
                ->whereIn('id', $productIds)
                ->pluck('cost', 'id');

        $currentRecipes = $productIds->isEmpty()
            ? collect()
            : DB::table('product_ingredients')
                ->join('stock_items', 'stock_items.id', '=', 'product_ingredients.stock_item_id')
                ->join('products', 'products.id', '=', 'product_ingredients.product_id')
                ->where('products.business_id', $businessId)
                ->where('stock_items.business_id', $businessId)
                ->whereIn('product_ingredients.product_id', $productIds)
                ->select([
                    'product_ingredients.product_id',
                    'product_ingredients.stock_item_id',
                    'product_ingredients.quantity',
                    'stock_items.name as stock_name',
                    'stock_items.unit',
                    'stock_items.unit_cost',
                ])
                ->get()
                ->groupBy('product_id');

        $snapshotStockIds = $orderItems->flatMap(function ($item): array {
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
        $currentSnapshotStocks = $snapshotStockIds->isEmpty()
            ? collect()
            : DB::table('stock_items')
                ->where('business_id', $businessId)
                ->whereIn('id', $snapshotStockIds)
                ->get(['id', 'name', 'unit', 'unit_cost'])
                ->keyBy('id');

        $costEntries = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($order->payment_status === 'refunded' && $order->refund_restocked) {
                    continue;
                }

                $saleAmount = $order->payment_status !== 'refunded' && (float) $order->subtotal > 0
                    ? (float) $item->subtotal * (float) $order->total / (float) $order->subtotal
                    : 0.0;
                $snapshot = $item->ingredients_snapshot;
                $usesCurrentRecipe = !is_array($snapshot);
                $recipe = is_array($snapshot)
                    ? collect($snapshot)
                    : collect($currentRecipes->get($item->product_id, []))->map(fn ($ingredient): array => [
                        'stock_item_id' => $ingredient->stock_item_id,
                        'stock_name' => $ingredient->stock_name,
                        'unit' => $ingredient->unit,
                        'quantity' => $ingredient->quantity,
                        'unit_cost' => $ingredient->unit_cost,
                    ]);

                $quantitySold = max(0, (int) $item->quantity);
                $hasRecipe = $recipe->isNotEmpty();
                $recipeIsComplete = $hasRecipe;
                $usesCurrentCost = $usesCurrentRecipe;
                $recipeCostPerUnit = 0.0;
                $ingredientLines = [];

                foreach ($recipe as $ingredient) {
                    $stockId = $ingredient['stock_item_id'] ?? null;
                    $unitCost = $ingredient['unit_cost'] ?? null;
                    $stock = $stockId === null ? null : $currentSnapshotStocks->get($stockId);
                    if ($unitCost === null || (float) $unitCost <= 0) {
                        $unitCost = $stock?->unit_cost;
                        $usesCurrentCost = true;
                    }

                    $perProductQuantity = (float) ($ingredient['quantity'] ?? 0);
                    $lineQuantity = $perProductQuantity * $quantitySold;
                    $lineCost = $unitCost !== null && (float) $unitCost > 0
                        ? $lineQuantity * (float) $unitCost
                        : null;
                    if ($unitCost === null || (float) $unitCost <= 0) {
                        $recipeIsComplete = false;
                    } else {
                        $recipeCostPerUnit += $perProductQuantity * (float) $unitCost;
                    }

                    $ingredientLines[] = [
                        'name' => $ingredient['stock_name'] ?? $stock?->name ?? 'Ingredient',
                        'unit' => $ingredient['unit'] ?? $stock?->unit ?? '',
                        'quantity' => $lineQuantity,
                        'unit_cost' => $unitCost !== null && (float) $unitCost > 0 ? (float) $unitCost : null,
                        'cost' => $lineCost,
                    ];
                }

                $ingredientCost = $recipeIsComplete ? $recipeCostPerUnit * $quantitySold : null;
                $productCost = null;
                $costBasis = 'Missing cost';
                $costEstimated = $usesCurrentCost;

                if ($recipeIsComplete) {
                    $productCost = $ingredientCost;
                    $costBasis = $usesCurrentCost ? 'Estimated recipe cost' : 'Recipe cost';
                } else {
                    $snapshotProductCost = $item->product_cost_snapshot;
                    $currentProductCost = $currentProductCosts->get($item->product_id);
                    if ($snapshotProductCost !== null && (float) $snapshotProductCost > 0) {
                        $productCost = (float) $snapshotProductCost * $quantitySold;
                        $costBasis = 'Configured product cost';
                    } elseif ($currentProductCost !== null && (float) $currentProductCost > 0) {
                        $productCost = (float) $currentProductCost * $quantitySold;
                        $costBasis = 'Estimated product cost';
                        $costEstimated = true;
                    }
                }

                $costEntries->push([
                    'order_id' => $order->id,
                    'date' => $order->completed_at,
                    'product_name' => $item->product_name ?: 'Unnamed product',
                    'quantity' => $quantitySold,
                    'sale_amount' => $saleAmount,
                    'ingredients' => $ingredientLines,
                    'ingredient_cost' => $ingredientCost,
                    'product_cost' => $productCost,
                    'gross_margin' => $productCost === null ? null : $saleAmount - $productCost,
                    'cost_basis' => $costBasis,
                    'estimated' => $costEstimated,
                ]);
            }
        }

        $wasteBase = StockMovement::query()
            ->join('stock_items', 'stock_items.id', '=', 'stock_movements.stock_item_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->where('stock_items.business_id', $businessId)
            ->where('stock_movements.type', 'out')
            ->where('stock_movements.movement_category', 'waste')
            ->whereNull('stock_movements.reversal_of_id')
            ->whereBetween('stock_movements.created_at', [$start->startOfDay(), $end->endOfDay()]);
        $wasteCostExpression = 'COALESCE(NULLIF(stock_movements.unit_cost_at_movement, 0), NULLIF(stock_items.unit_cost, 0), 0)';
        $wasteTotal = (float) (clone $wasteBase)
            ->selectRaw("COALESCE(SUM(stock_movements.quantity * {$wasteCostExpression}), 0) as total")
            ->value('total');
        $wasteMovements = (clone $wasteBase)
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.quantity',
                'stock_movements.reason',
                'stock_movements.unit_cost_at_movement',
                'stock_items.name as stock_name',
                'stock_items.unit as stock_unit',
                'stock_items.unit_cost as current_unit_cost',
                'users.name as recorded_by',
            ])
            ->selectRaw("{$wasteCostExpression} as effective_unit_cost")
            ->selectRaw("stock_movements.quantity * {$wasteCostExpression} as total_cost")
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->paginate(20, ['*'], 'waste_page')
            ->withQueryString();

        return view('analytics.cost-logs', [
            'startDate' => $start,
            'endDate' => $end,
            'currencyCode' => strtoupper((string) ($request->user()->business?->currency ?: 'PHP')),
            'costEntries' => $costEntries,
            'orders' => $orders,
            'wasteMovements' => $wasteMovements,
            'wasteTotal' => round($wasteTotal, 2),
        ]);
    }
}
