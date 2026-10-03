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

        $business = $user->business;
        abort_if(!$business, 403, 'This account is not assigned to a business.');
        abort_if(!$business->active || !$business->isActive(), 403, 'This business is not active.');

        return $next($request);
    }
}
