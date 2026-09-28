<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_id', 'business_id', 'type', 'quantity',
        'quantity_before', 'quantity_after', 'reason',
        'order_id', 'user_id'
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }
}