<?php

namespace App\Services;

class BusinessStaffLimit
{
    public function __construct(private readonly BusinessPlanLimits $planLimits) {}

    // Keep this entry point for existing web and API staff creation flows.
    public function assertCanAddStaff(int $businessId): void
    {
        $this->planLimits->assertCanAddStaff($businessId);
    }
}
