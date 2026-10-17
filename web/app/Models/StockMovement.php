<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'stock_item_id', 'type', 'quantity',
        'previous_quantity', 'new_quantity', 'reason',
        'order_id', 'user_id', 'client_id', 'reversal_of_id',
        'movement_category', 'unit_cost_at_movement'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost_at_movement' => 'decimal:2',
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
