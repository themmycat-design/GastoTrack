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
        'status', 'payment_status', 'payment_currency', 'detected_payment_amount',
        'detected_payment_currency', 'payment_detected_at', 'payment_reference',
        'payment_confirmed_by', 'payment_confirmed_at', 'financials_committed_at',
        'cancelled_by', 'cancel_reason', 'cancelled_at', 'payment_type',
        'prepared_at', 'ready_at', 'completed_at', 'client_id', 'idempotency_hash',
        'refund_requested_at', 'refund_requested_by', 'refund_reason', 'refund_amount',
        'refund_reference', 'refund_confirmed_by', 'refund_confirmed_at', 'refund_restocked',
        'synced'
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'total'        => 'decimal:2',
        'completed_at' => 'datetime',
        'prepared_at'  => 'datetime',
        'ready_at'     => 'datetime',
        'payment_detected_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'financials_committed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refund_confirmed_at' => 'datetime',
        'detected_payment_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_restocked' => 'boolean',
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
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function paymentEvents()
    {
        return $this->hasMany(OrderPaymentEvent::class)->orderBy('id');
    }

    public function paymentConfirmer()
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by')->withTrashed();
    }
}
