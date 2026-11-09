<?php

namespace App\Services;

use App\Models\StockItem;
use Illuminate\Validation\ValidationException;

class RecipeIngredientNormalizer
{
    /** Store every recipe quantity in its stock item's unit after any compatible unit conversion. */
    public function normalize(array $ingredients, int $businessId): array
    {
        $stockItems = StockItem::query()
            ->where('business_id', $businessId)
            ->whereIn('id', array_column($ingredients, 'stock_id'))
            ->get(['id', 'unit'])
            ->keyBy('id');

        foreach ($ingredients as $index => &$ingredient) {
            $stock = $stockItems->get($ingredient['stock_id']);
            if (! $stock) {
                throw ValidationException::withMessages(["ingredients.$index.stock_id" => 'Choose a stock item from this business.']);
            }

            $stockUnit = trim($stock->unit);
            $chosenUnit = trim((string) ($ingredient['unit'] ?? $stockUnit));
            $smallerUnit = match (strtolower($stockUnit)) {
                'l', 'liter', 'liters', 'litre', 'litres' => 'ml',
                'kg', 'kilogram', 'kilograms' => 'g',
                default => null,
            };
            $largerVolumeUnit = strtolower($stockUnit) === 'ml' ? 'L' : null;
            if ($chosenUnit !== $stockUnit && $chosenUnit !== $smallerUnit && $chosenUnit !== $largerVolumeUnit) {
                throw ValidationException::withMessages(["ingredients.$index.unit" => 'Choose a unit compatible with this stock item.']);
            }

            $quantity = (float) $ingredient['quantity'];
            $canonical = match ($chosenUnit) {
                $stockUnit => $quantity,
                $largerVolumeUnit => $quantity * 1000,
                default => $quantity / 1000,
            };
            $rounded = round($canonical, 4);
            if ($canonical <= 0 || $canonical > 99999999.9999 || $rounded < 0.0001 || abs($canonical - $rounded) >= 0.0000001) {
                throw ValidationException::withMessages(["ingredients.$index.quantity" => 'Enter an amount representable in the stock unit, with up to four decimal places.']);
            }
            $ingredient['quantity'] = $rounded;
            unset($ingredient['unit']);
        }

        return $ingredients;
    }
}
