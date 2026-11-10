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
                'unit' => 'The unit cannot be changed after a stock item is created. Add stock in the saved unit.',
            ]);
        }
    }
}
