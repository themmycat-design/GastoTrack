<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $fillable = [
        'business_id', 'name', 'quantity', 'unit',
        'threshold', 'cost_per_unit', 'supplier'
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'threshold' => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ingredients()
    {
        return $this->hasMany(ProductIngredient::class, 'stock_id');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'stock_id');
    }
}