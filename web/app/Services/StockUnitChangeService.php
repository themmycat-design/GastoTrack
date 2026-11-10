<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockUnitChangeService
{
    /** Update an item and convert every quantity stored in its original unit. */
    public function update(StockItem $stock, array $attributes, ?float $oldUnitsPerNewUnit = null): StockItem
    {
        return DB::transaction(function () use ($stock, $attributes, $oldUnitsPerNewUnit): StockItem {
            $item = StockItem::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $newUnit = trim((string) ($attributes['unit'] ?? $item->unit));
            $oldUnit = trim($item->unit);
            if ($newUnit === '') {
                throw ValidationException::withMessages(['unit' => 'Enter a stock unit.']);
            }

            if ($newUnit === $oldUnit) {
                unset($attributes['conversion_factor']);
                if (array_key_exists('unit', $attributes)) $attributes['unit'] = $newUnit;
                $item->update($attributes);

                return $item;
            }

            $factor = $this->oldUnitsPerNewUnit($oldUnit, $newUnit, $oldUnitsPerNewUnit);
            $item->current_quantity = $this->convert((float) $item->current_quantity / $factor, 'unit');
            $item->minimum_quantity = array_key_exists('minimum_quantity', $attributes)
                ? $this->convert((float) $attributes['minimum_quantity'], 'minimum_quantity')
                : $this->convert((float) $item->minimum_quantity / $factor, 'unit');
            $item->unit_cost = array_key_exists('unit_cost', $attributes)
                ? $this->convert((float) $attributes['unit_cost'], 'unit_cost')
                : $this->convert((float) $item->unit_cost * $factor, 'unit');

            DB::table('product_ingredients')->where('stock_item_id', $item->id)->orderBy('id')->chunkById(100, function ($ingredients) use ($factor): void {
                foreach ($ingredients as $ingredient) {
                    DB::table('product_ingredients')->where('id', $ingredient->id)->update([
                        'quantity' => $this->convert((float) $ingredient->quantity / $factor, 'unit'),
                    ]);
                }
            });

            DB::table('stock_movements')->where('stock_item_id', $item->id)->orderBy('id')->chunkById(100, function ($movements) use ($factor): void {
                foreach ($movements as $movement) {
                    DB::table('stock_movements')->where('id', $movement->id)->update([
                        'quantity' => $this->convert((float) $movement->quantity / $factor, 'unit'),
                        'previous_quantity' => $this->convert((float) $movement->previous_quantity / $factor, 'unit'),
                        'new_quantity' => $this->convert((float) $movement->new_quantity / $factor, 'unit'),
                        'unit_cost_at_movement' => $movement->unit_cost_at_movement === null
                            ? null
                            : $this->convert((float) $movement->unit_cost_at_movement * $factor, 'unit'),
                    ]);
                }
            });

            $item->fill([
                'name' => $attributes['name'] ?? $item->name,
                'unit' => $newUnit,
                'active' => $attributes['active'] ?? $item->active,
            ])->save();

            return $item;
        });
    }

    private function oldUnitsPerNewUnit(string $oldUnit, string $newUnit, ?float $explicitFactor): float
    {
        $units = [
            'g' => ['weight', 1], 'gram' => ['weight', 1], 'grams' => ['weight', 1],
            'kg' => ['weight', 1000], 'kilogram' => ['weight', 1000], 'kilograms' => ['weight', 1000],
            'ml' => ['volume', 1], 'milliliter' => ['volume', 1], 'milliliters' => ['volume', 1],
            'millilitre' => ['volume', 1], 'millilitres' => ['volume', 1],
            'l' => ['volume', 1000], 'liter' => ['volume', 1000], 'liters' => ['volume', 1000],
            'litre' => ['volume', 1000], 'litres' => ['volume', 1000],
        ];
        $old = $units[strtolower($oldUnit)] ?? null;
        $new = $units[strtolower($newUnit)] ?? null;
        if ($old && $new && $old[0] === $new[0]) {
            return $new[1] / $old[1];
        }
        if ($old && $new && $old[0] !== $new[0]) {
            throw ValidationException::withMessages(['unit' => 'Weight and volume units cannot be converted to each other.']);
        }
        if ($explicitFactor === null || ! is_finite($explicitFactor) || $explicitFactor <= 0) {
            throw ValidationException::withMessages([
                'conversion_factor' => 'Enter how many old units equal one new unit.',
            ]);
        }

        return $explicitFactor;
    }

    private function convert(float $value, string $field): float
    {
        $rounded = round($value, 4);
        if (! is_finite($value) || $value < 0 || $value > 99999999.9999
            || ($value > 0 && $rounded < 0.0001) || abs($value - $rounded) >= 0.0000001) {
            throw ValidationException::withMessages([
                $field => 'This unit change would round or exceed the limit of an existing stock, recipe, cost, or history value. Choose another unit or adjust the data first.',
            ]);
        }

        return $rounded;
    }
}
