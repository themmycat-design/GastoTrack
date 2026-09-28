<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $fillable = [
        'owner_id', 'name', 'description', 'location',
        'city', 'province', 'phone', 'email', 'status'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }
}