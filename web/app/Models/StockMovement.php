<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'stock_item_id', 'type', 'quantity',
        'previous_quantity', 'new_quantity', 'reason',
        'order_id', 'user_id', 'client_id'
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }
}
