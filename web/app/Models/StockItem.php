<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'business_id', 'name', 'unit', 'current_quantity',
        'minimum_quantity', 'unit_cost', 'active', 'client_id'
    ];

    protected $casts = [
        'current_quantity'  => 'decimal:2',
        'minimum_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ingredients()
    {
        return $this->hasMany(ProductIngredient::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'stock_item_id');
    }

    public function isLowStock(): bool
    {
        return $this->current_quantity <= $this->minimum_quantity;
    }
}
