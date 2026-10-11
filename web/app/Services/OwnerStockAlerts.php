<?php

namespace App\Services;

use App\Models\StockItem;

class OwnerStockAlerts
{
    public function forBusiness(int $businessId): array
    {
        $alerts = StockItem::query()
            ->where('business_id', $businessId)
            ->where('active', true)
            ->whereColumn('current_quantity', '<=', 'minimum_quantity');

        $counts = (clone $alerts)
            ->selectRaw('SUM(CASE WHEN current_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(CASE WHEN current_quantity > 0 THEN 1 ELSE 0 END) as low_stock')
            ->first();

        $outOfStock = (int) ($counts?->out_of_stock ?? 0);
        $lowStock = (int) ($counts?->low_stock ?? 0);
        $total = $outOfStock + $lowStock;

        return [
            'outOfStock' => $outOfStock,
            'lowStock' => $lowStock,
            'total' => $total,
            'items' => $total > 0
                ? (clone $alerts)
                    ->orderByRaw('CASE WHEN current_quantity <= 0 THEN 0 ELSE 1 END')
                    ->orderBy('current_quantity')
                    ->orderBy('id')
                    ->limit(5)
                    ->get(['id', 'name', 'unit', 'current_quantity', 'minimum_quantity'])
                : collect(),
        ];
    }
}
