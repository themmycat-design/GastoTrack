<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class StockUnitPolicy
{
    public static function adjustmentQuantity(float $quantity, string $enteredUnit, string $stockUnit): float
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
        if ($factor === null) {
            throw ValidationException::withMessages([
                'unit' => 'Choose the saved stock unit or a compatible weight or volume unit.',
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
