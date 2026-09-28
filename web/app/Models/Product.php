<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'category',
        'price',
        'image',
        'active',
        'prep_time',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
        'prep_time' => 'integer',
    ];

    /**
     * Get the business this product belongs to
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the ingredients (stock items) for this product
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(StockItem::class, 'product_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Get order items for this product
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for products by category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if product can be made (sufficient ingredients)
     */
    public function canBeMade(): bool
    {
        foreach ($this->ingredients as $ingredient) {
            $requiredQuantity = $ingredient->pivot->quantity;
            if ($ingredient->current_quantity < $requiredQuantity) {
                return false;
            }
        }
        return true;
    }
}
