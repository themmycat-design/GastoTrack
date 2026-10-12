<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderNumberAllocator
{
    /** Call only inside the transaction that creates the order. */
    public function next(int $businessId): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Order numbers must be allocated inside an order-creation transaction.');
        }

        $business = DB::table('businesses')
            ->where('id', $businessId)
            ->lockForUpdate()
            ->first(['next_order_number']);

        abort_unless($business, 404, 'Business not found.');

        $number = (int) $business->next_order_number;
        abort_if($number < 1001 || $number > 9999, 409, 'This business has used all four-digit order numbers from 1001 to 9999. A longer numbering format is required before more orders can be placed.');

        DB::table('businesses')->where('id', $businessId)
            ->update(['next_order_number' => $number + 1]);

        return (string) $number;
    }
}
