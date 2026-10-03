<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['business_id', 'user_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip_address'];
    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];
    public function subject() { return $this->morphTo(); }
}
