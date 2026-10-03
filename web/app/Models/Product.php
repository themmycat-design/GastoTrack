<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'business_id', 'name', 'description', 'category',
        'price', 'cost', 'emoji', 'image', 'active', 'is_available', 'display_order', 'prep_time'
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function ingredients()
    {
        return $this->belongsToMany(StockItem::class, 'product_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }
    
    public function productIngredients()
    {
        return $this->hasMany(ProductIngredient::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
