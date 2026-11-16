<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class StockUnitOptions
{
    public const DEFAULTS = ['pcs', 'g', 'kg', 'ml', 'L'];

    public function forBusiness(int $businessId): array
    {
        $saved = DB::table('stock_unit_options')->where('business_id', $businessId)->orderBy('unit')->pluck('unit');
        $inUse = StockItem::where('business_id', $businessId)->distinct()->pluck('unit');
        $seen = [];
        $options = [];

        foreach (array_merge(self::DEFAULTS, $saved->all(), $inUse->all()) as $unit) {
            $unit = trim((string) $unit);
            $key = mb_strtolower($unit);
            if ($unit !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $options[] = $unit;
            }
        }

        return $options;
    }
}
