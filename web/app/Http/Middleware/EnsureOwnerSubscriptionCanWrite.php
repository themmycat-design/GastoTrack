<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOwnerSubscriptionCanWrite
{
    public function handle(Request $request, Closure $next)
    {
        $accountOnlyAction = $request->routeIs('owner.notifications.read-all', 'owner.bug-reports.store');
        if (! $request->isMethodSafe() && ! $accountOnlyAction && $request->user()?->business?->subscriptionNeedsRenewal()) {
            return back()->with('error', 'Your subscription has expired or is suspended. Records remain available to read. Contact the platform administrator to renew.');
        }

        return $next($request);
    }
}
