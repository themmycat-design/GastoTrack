<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViolationReport extends Model
{
    protected $fillable = [
        'business_id',
        'reported_by',
        'subject',
        'category',
        'priority',
        'status',
        'description',
        'resolution_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
