<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivityLog extends Model
{
    protected $fillable = ['admin_id', 'action', 'target_type', 'target_id', 'ip_address'];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
