<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OwnerTrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

class OwnerTwoFactorController extends Controller
{
    public function setup(Request $request, EnableTwoFactorAuthentication $enable): View|RedirectResponse|Response
    {
        $owner = $request->user();

        if ($owner->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('dashboard');
        }

        if (!$owner->two_factor_secret) {
            $enable($owner);
            $owner->refresh();
        }

        $secret = Fortify::currentEncrypter()->decrypt($owner->two_factor_secret);

        return response()->view('auth.owner-two-factor-setup', [
            'qrCodeSvg' => $owner->twoFactorQrCodeSvg(),
            'secretKey' => $secret,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function confirm(
        Request $request,
        ConfirmTwoFactorAuthentication $confirm,
        OwnerTrustedDeviceService $trustedDevices,
    ): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $owner = $request->user();
        if ($owner->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('dashboard');
        }

        $confirm($owner, $data['code']);

        $request->session()->put([
            'owner.two_factor_user_id' => $owner->getKey(),
            'owner.two_factor_verified_at' => now()->timestamp,
            'owner.two_factor.recovery_codes' => $owner->fresh()->recoveryCodes(),
        ]);

        return redirect()->route('owner.two-factor.recovery-codes')
            ->withCookie($trustedDevices->issueTrustCookie($request, $owner));
    }

    public function recoveryCodes(Request $request): View|RedirectResponse|Response
    {
        $codes = $request->session()->get('owner.two_factor.recovery_codes');
        if (!is_array($codes) || $codes === []) {
            return redirect()->route('dashboard');
        }

        return response()->view('auth.owner-two-factor-recovery-codes', [
            'recoveryCodes' => $codes,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function acknowledgeRecoveryCodes(Request $request): RedirectResponse
    {
        if (!is_array($request->session()->get('owner.two_factor.recovery_codes'))) {
            return redirect()->route('dashboard');
        }

        $request->session()->forget('owner.two_factor.recovery_codes');

        return redirect()->route('dashboard');
    }
}
