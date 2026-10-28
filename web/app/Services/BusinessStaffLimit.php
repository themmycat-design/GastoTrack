<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BusinessStaffLimit
{
    // Call inside a database transaction before creating/promoting a staff account.
    public function assertCanAddStaff(int $businessId): void
    {
        $business = Business::query()->lockForUpdate()->findOrFail($businessId);
        if ($business->plan_tier === 'free' && User::where('business_id', $businessId)->where('role', 'staff')->count() >= 2) {
            throw ValidationException::withMessages([
                'role' => 'The Free plan supports up to 2 staff accounts. Contact the platform administrator to upgrade.',
            ]);
        }
    }
}
