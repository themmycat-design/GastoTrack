<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEditRequest extends Model
{
    protected $fillable = [
        'business_id', 'product_id', 'requested_by', 'reviewed_by',
        'expected_revision', 'status', 'changes', 'image_path', 'reviewed_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'expected_revision' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
