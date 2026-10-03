<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'user_id', 'order_number', 'customer_name', 'customer_phone',
        'notes', 'subtotal', 'discount', 'total', 'payment_method',
        'status', 'cancelled_by', 'cancel_reason', 'payment_type',
        'prepared_at', 'ready_at', 'completed_at', 'client_id', 'synced'
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'total'        => 'decimal:2',
        'completed_at' => 'datetime',
        'prepared_at'  => 'datetime',
        'ready_at'     => 'datetime',
        'synced'       => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
