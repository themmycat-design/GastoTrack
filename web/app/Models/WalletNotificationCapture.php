<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletNotificationCapture extends Model
{
    protected $fillable = [
        'client_id', 'business_id', 'user_id', 'source', 'direction',
        'amount', 'currency', 'merchant', 'message', 'captured_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    public function capturedBy()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
