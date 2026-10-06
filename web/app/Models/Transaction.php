<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'user_id', 'amount', 'type',
        'source', 'category', 'transaction_date', 'description', 'receipt_image',
        'entry_method', 'metadata', 'client_id', 'synced'
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
        'metadata' => 'array',
        'synced' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function recordedBy()
    {
        return $this->user();
    }
}
