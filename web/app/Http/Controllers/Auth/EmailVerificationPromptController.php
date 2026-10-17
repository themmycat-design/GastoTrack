<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        if (!$request->user()->hasVerifiedEmail()) {
            return view('auth.verify-email');
        }

        return $request->user()->isOwner()
            ? redirect()->route('business.status')
            : redirect()->intended(route('dashboard', absolute: false));
    }
}
