<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordStrength;
use App\Services\OwnerTrustedDeviceService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function changed(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('password_reset_complete') !== true) {
            return redirect()->route('login');
        }

        return view('auth.password-changed');
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, OwnerTrustedDeviceService $trustedDevices): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', new PasswordStrength()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $trustedDevices) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                    'must_reset_password' => false,
                ])->save();

                if ($user->isOwner()) {
                    $trustedDevices->revokeAll($user);
                }

                event(new PasswordReset($user));
            }
        );

        // Show confirmation only after a completed reset. Keep the user signed out
        // so the next sign-in uses the new password and any required MFA challenge.
        if ($status == Password::PASSWORD_RESET) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('password.changed')->with('password_reset_complete', true);
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
