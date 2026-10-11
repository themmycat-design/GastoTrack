<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\MonthlySalesGoals;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'target_amount',
        'current_amount',
        'deadline',
        'status',
        'goal_type',
        'period_start',
        'completed_at',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'deadline' => 'date',
        'period_start' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the business this goal belongs to
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount == 0) {
            return 0;
        }
        
        $current = $this->goal_type === 'monthly_confirmed_sales'
            ? app(MonthlySalesGoals::class)->recordedSales($this)
            : (float) $this->current_amount;

        return min(($current / (float) $this->target_amount) * 100, 100);
    }

    /**
     * Check if goal is completed
     */
    public function isCompleted(): bool
    {
        if ($this->goal_type === 'monthly_confirmed_sales') {
            return app(MonthlySalesGoals::class)->recordedSales($this) >= (float) $this->target_amount;
        }

        return $this->status === 'completed' || $this->current_amount >= $this->target_amount;
    }

    /**
     * Scope for active goals
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
