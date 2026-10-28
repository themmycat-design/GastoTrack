<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminPasswordResetService
{
    public function forceReset(User $user): string
    {
        $status = Password::sendResetLink(['email' => $user->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            return $status;
        }

        $user->forceFill([
            'must_reset_password' => true,
            'remember_token' => Str::random(60),
        ])->save();
        $user->tokens()->delete();
        if ($user->isOwner()) {
            app(OwnerTrustedDeviceService::class)->revokeAll($user);
        }

        return $status;
    }
}
