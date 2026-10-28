<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(!$user || $user->isSuperAdmin(), 403, 'Platform administrators cannot access private business operations.');
        abort_if($user->status !== 'active', 403, 'This account is inactive.');
        abort_if($user->must_reset_password, 403, 'A password reset is required before using this account.');

        $business = $user->business;
        abort_if(!$business, 403, 'This account is not assigned to a business.');
        abort_if(!$business->active || !$business->isActive(), 403, 'This business is not active.');
        abort_if($business->subscriptionNeedsRenewal(), 403, 'Your subscription has expired or is suspended. Contact the platform administrator to renew.');

        return $next($request);
    }
}
