<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPaymentEvent extends Model
{
    protected $fillable = [
        'order_id', 'business_id', 'user_id', 'event_type',
        'from_status', 'to_status', 'from_payment_status', 'to_payment_status',
        'amount', 'currency', 'reference', 'reason', 'details',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'details' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
