<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BugReport extends Model
{
    public const AREAS = [
        'login',
        'dashboard',
        'transactions',
        'orders',
        'payments',
        'inventory',
        'analytics',
        'other',
    ];

    public const STATUSES = ['open', 'under_review', 'resolved', 'dismissed'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'business_id',
        'reported_by',
        'subject',
        'category',
        'area',
        'priority',
        'status',
        'description',
        'steps_to_reproduce',
        'screenshot_path',
        'resolution_notes',
        'public_update',
        'resolved_at',
        'is_legacy',
    ];

    protected $hidden = ['resolution_notes', 'screenshot_path'];

    protected $casts = [
        'resolved_at' => 'datetime',
        'is_legacy' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getHasScreenshotAttribute(): bool
    {
        return filled($this->screenshot_path);
    }
}
