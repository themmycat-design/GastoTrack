<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePlatformAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->status !== 'active' || $request->user()?->must_reset_password) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'This platform account is locked.');
        }

        return $next($request);
    }
}
