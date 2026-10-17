<?php

namespace App\Http\Controllers\Auth;

use App\Services\OwnerTrustedDeviceService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController as FortifyTwoFactorSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class OwnerTwoFactorChallengeController extends FortifyTwoFactorSessionController
{
    public function store(TwoFactorLoginRequest $request)
    {
        $response = parent::store($request);
        $user = Auth::guard('web')->user();

        if ($user?->isOwner()) {
            $request->session()->put([
                'owner.two_factor_user_id' => $user->getKey(),
                'owner.two_factor_verified_at' => now()->timestamp,
            ]);

            // Fortify returns a Responsable object on success, not an HTTP response.
            // Convert it before attaching the trusted-device cookie.
            if ($response instanceof Responsable) {
                $response = $response->toResponse($request);
            }

            $response->headers->setCookie(
                app(OwnerTrustedDeviceService::class)->issueTrustCookie($request, $user)
            );
        }

        return $response;
    }
}
