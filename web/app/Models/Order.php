<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'business_id', 'order_number', 'customer_name', 'customer_phone',
        'notes', 'subtotal', 'discount', 'total', 'payment_method',
        'status', 'created_by', 'cancelled_by', 'cancel_reason',
        'prepared_at', 'ready_at', 'completed_at'
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'total'        => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}