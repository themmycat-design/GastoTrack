<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class StockPackCost
{
    /** A pack is priced in the stock item's selected unit (for example, 500 g). */
    public function attributes(array $data): array
    {
        $price = $data['pack_price'] ?? null;
        $quantity = $data['pack_quantity'] ?? null;
        if ($price === null && $quantity === null) {
            return ['unit_cost' => $data['unit_cost'] ?? null, 'pack_price' => null, 'pack_quantity' => null];
        }
        if ($price === null || $quantity === null || (float) $quantity <= 0 || (float) $price <= 0) {
            throw ValidationException::withMessages(['pack_quantity' => 'Enter both a positive whole pack price and pack quantity.']);
        }
        $unitCost = (float) $price / (float) $quantity;
        if (!is_finite($unitCost) || $unitCost < 0.0001 || $unitCost > 99999999.9999 || round($unitCost, 4) <= 0) {
            throw ValidationException::withMessages(['pack_price' => 'The price per selected unit must be between 0.0001 and 99999999.9999.']);
        }
        return [
            'unit_cost' => round($unitCost, 4),
            'pack_price' => $price,
            'pack_quantity' => $quantity,
        ];
    }
}
