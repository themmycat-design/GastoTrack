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
            $alternate = match (strtolower($stockUnit)) {
                'kg', 'kilogram', 'kilograms' => ['unit' => 'g', 'factor' => 0.001],
                'l', 'liter', 'liters', 'litre', 'litres' => ['unit' => 'ml', 'factor' => 0.001],
                'ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres' => ['unit' => 'L', 'factor' => 1000],
                default => null,
            };
            if ($chosenUnit !== $stockUnit && $chosenUnit !== ($alternate['unit'] ?? null)) {
                throw ValidationException::withMessages(["ingredients.$index.unit" => 'Choose a unit compatible with this stock item.']);
            }

            $quantity = (float) $ingredient['quantity'];
            $canonical = $chosenUnit === $stockUnit ? $quantity : $quantity * $alternate['factor'];
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
