<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'stock_item_id', 'type', 'quantity',
        'previous_quantity', 'new_quantity', 'reason',
        'order_id', 'user_id', 'client_id', 'reversal_of_id',
        'movement_category', 'unit_cost_at_movement', 'offline_recorded_at', 'created_at'
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'previous_quantity' => 'decimal:4',
        'new_quantity' => 'decimal:4',
        'unit_cost_at_movement' => 'decimal:4',
        'offline_recorded_at' => 'datetime',
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
