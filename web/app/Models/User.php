<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'business_id', 'phone', 'status'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }
}