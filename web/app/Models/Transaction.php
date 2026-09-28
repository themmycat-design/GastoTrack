<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'recorded_by', 'amount', 'type',
        'source', 'category', 'date', 'notes', 'entry_method', 'metadata'
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'date'     => 'date',
        'metadata' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}