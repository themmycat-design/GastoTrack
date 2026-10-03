<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'description', 'location',
        'city', 'province', 'phone', 'email', 'address', 'logo',
        'business_type', 'active', 'status', 'suspension_reason'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedOwner()
    {
        return $this->hasOne(User::class)->where('role', 'owner')->oldestOfMany();
    }

    public function getOwnerAccountAttribute(): ?User
    {
        return $this->owner ?? $this->assignedOwner;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function violationReports()
    {
        return $this->hasMany(ViolationReport::class);
    }

    // Status helper methods
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isSuspended()
    {
        return $this->status === 'suspended';
    }

    public function isInactive()
    {
        return $this->status === 'inactive';
    }
}
