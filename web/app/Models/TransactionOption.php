<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionOption extends Model
{
    use SoftDeletes;

    protected $fillable = ['business_id', 'kind', 'transaction_type', 'name', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
