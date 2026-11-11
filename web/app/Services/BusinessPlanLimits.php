<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BusinessPlanLimits
{
    private const LIMITS = [
        'free' => ['staff' => 1, 'products' => 3],
        'basic' => ['staff' => 2, 'products' => 5],
        'pro' => ['staff' => 5, 'products' => 15],
        'enterprise' => ['staff' => null, 'products' => null],
    ];

    public function forTier(?string $tier): array
    {
        // An unexpected tier must never grant Enterprise's unlimited access.
        return self::LIMITS[$tier] ?? self::LIMITS['free'];
    }

    /** Call inside the same database transaction that creates or promotes the staff account. */
    public function assertCanAddStaff(int $businessId): void
    {
        $business = Business::query()->lockForUpdate()->findOrFail($businessId);
        $limit = $this->forTier($business->plan_tier)['staff'];

        // Inactive accounts still occupy a slot; soft-deleted accounts do not.
        if ($limit !== null && User::query()->where('business_id', $businessId)->where('role', 'staff')->count() >= $limit) {
            throw ValidationException::withMessages([
                'role' => sprintf('The %s plan allows up to %d staff accounts. Remove a staff account or upgrade your plan.', $this->planName($business), $limit),
            ]);
        }
    }

    /** Call inside the same database transaction that creates the product. */
    public function assertCanAddProduct(int $businessId): void
    {
        $business = Business::query()->lockForUpdate()->findOrFail($businessId);
        $limit = $this->forTier($business->plan_tier)['products'];

        // Unavailable products still occupy a slot; soft-deleted products do not.
        if ($limit !== null && Product::query()->where('business_id', $businessId)->count() >= $limit) {
            throw ValidationException::withMessages([
                'product' => sprintf('The %s plan allows up to %d products. Remove a product or upgrade your plan.', $this->planName($business), $limit),
            ]);
        }
    }

    private function planName(Business $business): string
    {
        return ucfirst($business->plan_tier ?: 'free');
    }
}
