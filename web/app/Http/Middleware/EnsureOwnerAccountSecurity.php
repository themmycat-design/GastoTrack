<?php

namespace App\Http\Middleware;

use App\Services\OwnerTrustedDeviceService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerAccountSecurity
{
    private OwnerTrustedDeviceService $trustedDevices;

    public function __construct(OwnerTrustedDeviceService $trustedDevices)
    {
        $this->trustedDevices = $trustedDevices;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user?->isOwner()) {
            return $next($request);
        }

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $business = $user->business;
        if ($user->status !== 'active' || !$business || !$business->active || !$business->isActive()) {
            return redirect()->route('business.status');
        }

        // Enrollment and recovery-code acknowledgement are the only owner
        // portal routes that must remain reachable before a session has passed MFA.
        if ($request->routeIs('owner.two-factor.*')) {
            return $next($request);
        }

        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('owner.two-factor.setup');
        }

        $verifiedAt = $request->session()->get('owner.two_factor_verified_at');
        $verifiedAtIsFresh = is_numeric($verifiedAt)
            && (int) $verifiedAt <= now()->timestamp
            && (int) $verifiedAt > now()->subDays(OwnerTrustedDeviceService::TRUST_DAYS)->timestamp;
        $hasRecentMfa = (int) $request->session()->get('owner.two_factor_user_id') === (int) $user->getKey()
            && $verifiedAtIsFresh;

        if (!$hasRecentMfa && !$this->trustedDevices->restoreMfaSessionForAuthenticatedOwner($request, $user)) {
            $intendedUrl = $request->getRequestUri();

            Auth::guard('web')->logout();
            $request->session()->forget([
                'owner.two_factor_user_id',
                'owner.two_factor_verified_at',
            ]);
            $request->session()->regenerate();
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
                'url.intended' => $intendedUrl,
            ]);

            return redirect()->route('two-factor.login');
        }

        return $next($request);
    }
}
