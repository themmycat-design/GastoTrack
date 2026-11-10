<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class StockUnitPolicy
{
    public static function ensureUnchanged(string $unit, string $currentUnit): void
    {
        // A saved unit defines every stock movement and recipe quantity for this item.
        if (trim($unit) !== trim($currentUnit)) {
            throw ValidationException::withMessages([
                'unit' => 'The saved stock unit cannot be changed. Choose a compatible unit when adding or adjusting stock.',
            ]);
        }
    }

    public static function adjustmentQuantity(float $quantity, string $enteredUnit, string $stockUnit, ?float $conversionFactor = null): float
    {
        $enteredUnit = trim($enteredUnit);
        $stockUnit = trim($stockUnit);
        $factor = match (strtolower($stockUnit)) {
            'kg', 'kilogram', 'kilograms' => strtolower($enteredUnit) === 'g' ? 0.001 : null,
            'g', 'gram', 'grams' => strtolower($enteredUnit) === 'kg' ? 1000 : null,
            'l', 'liter', 'liters', 'litre', 'litres' => strtolower($enteredUnit) === 'ml' ? 0.001 : null,
            'ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres' => strtolower($enteredUnit) === 'l' ? 1000 : null,
            default => null,
        };
        if (strcasecmp($enteredUnit, $stockUnit) === 0) {
            $factor = 1;
        }
        if ($conversionFactor !== null && $factor !== null) {
            throw ValidationException::withMessages([
                'conversion_factor' => 'A conversion is only needed for another unit.',
            ]);
        }
        if ($factor === null && $conversionFactor !== null) {
            // Familiar weight and volume units must use their defined conversions.
            $standardUnits = ['g', 'gram', 'grams', 'kg', 'kilogram', 'kilograms',
                'ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres',
                'l', 'liter', 'liters', 'litre', 'litres'];
            if (in_array(strtolower($enteredUnit), $standardUnits, true)) {
                throw ValidationException::withMessages([
                    'unit' => 'Use a compatible weight or volume unit for this stock item.',
                ]);
            }
            $factor = $conversionFactor;
        }
        if ($factor === null) {
            throw ValidationException::withMessages([
                'unit' => 'Choose the saved unit, a compatible unit, or enter another unit with its conversion.',
            ]);
        }

        $converted = $quantity * $factor;
        $rounded = round($converted, 4);
        if ($converted > 99999999.9999 || ($quantity > 0 && $rounded < 0.0001)
            || abs($converted - $rounded) >= 0.0000001) {
            throw ValidationException::withMessages([
                'quantity' => 'Enter an amount that fits the saved stock unit with up to four decimal places.',
            ]);
        }

        return $rounded;
    }
}
