<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class OwnerTrustedDeviceService
{
    public const COOKIE_NAME = 'gasto_owner_trusted_device';
    public const TRUST_DAYS = 30;

    /**
     * Restore the MFA session only after Laravel has authenticated the owner,
     * either through password sign-in or its standard remembered session.
     * The cookie alone never authenticates a user or opens an owner session.
     */
    public function restoreMfaSessionForAuthenticatedOwner(Request $request, User $owner): bool
    {
        if (!$owner->isOwner()) {
            return false;
        }

        $token = $request->cookie(self::COOKIE_NAME);

        if (!is_string($token) || strlen($token) !== 64 || !ctype_alnum($token)) {
            return false;
        }

        $device = $owner->trustedDevices()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        if (!$device) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();
        $request->session()->put([
            'owner.two_factor_user_id' => $owner->getKey(),
            'owner.two_factor_verified_at' => now()->timestamp,
        ]);

        return true;
    }

    /**
     * Trust this browser for a fixed 30 days after a successful MFA challenge.
     * Only a SHA-256 token hash is persisted; the raw token stays in an HttpOnly cookie.
     */
    public function issueTrustCookie(Request $request, User $owner): Cookie
    {
        $existingToken = $request->cookie(self::COOKIE_NAME);
        if (is_string($existingToken) && strlen($existingToken) === 64 && ctype_alnum($existingToken)) {
            $owner->trustedDevices()->where('token_hash', hash('sha256', $existingToken))->delete();
        }

        $owner->trustedDevices()->where('expires_at', '<=', now())->delete();

        $token = Str::random(64);
        $expiresAt = now()->addDays(self::TRUST_DAYS);

        $owner->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'last_used_at' => now(),
        ]);

        $secure = app()->environment('production')
            || $request->isSecure()
            || (bool) config('session.secure', false);

        return app(CookieJar::class)->make(
            self::COOKIE_NAME,
            $token,
            self::TRUST_DAYS * 24 * 60,
            '/',
            config('session.domain'),
            $secure,
            true,
            false,
            'lax',
        );
    }

    public function revokeAll(User $owner): void
    {
        $owner->trustedDevices()->delete();
    }
}
