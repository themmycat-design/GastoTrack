<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_name',
        'price', 'quantity', 'subtotal', 'ingredients_snapshot'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal'      => 'decimal:2',
        'ingredients_snapshot' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
