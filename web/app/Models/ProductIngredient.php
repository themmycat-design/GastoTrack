<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductIngredient extends Model
{
    protected $fillable = [
        'product_id', 'stock_id', 'quantity', 'unit'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class, 'stock_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}