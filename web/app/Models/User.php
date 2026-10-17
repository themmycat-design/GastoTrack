<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'business_id', 'phone', 'status'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ownedBusiness()
    {
        return $this->hasOne(Business::class, 'owner_id');
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(OwnerTrustedDevice::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    public function isStaff()
    {
        return $this->role === 'staff';
    }

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function canAccessBusiness(int $businessId): bool
    {
        return !$this->isSuperAdmin() && (int) $this->business_id === $businessId;
    }
}
