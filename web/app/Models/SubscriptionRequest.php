<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionRequest extends Model
{
    public const PAID_PLANS = ['basic', 'pro', 'enterprise'];

    public const PAYMENT_METHODS = ['gcash', 'maya', 'bank_transfer', 'cash', 'other'];

    protected $fillable = [
        'business_id', 'requested_by', 'plan_tier', 'payment_method',
        'payment_reference', 'owner_note', 'proof_path', 'status',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
