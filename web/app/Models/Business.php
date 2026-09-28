<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'logo',
        'business_type',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get all users in this business
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all owners of this business
     */
    public function owners(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'owner');
    }

    /**
     * Get all staff in this business
     */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'staff');
    }

    /**
     * Get all transactions for this business
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all goals for this business
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Get all stock items for this business
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    /**
     * Get all products for this business
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get all orders for this business
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
