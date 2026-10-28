<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;

class AdminActivityRecorder
{
    public function record(User $admin, string $action, string $targetType, int $targetId): void
    {
        AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => request()->ip(),
        ]);
    }
}
