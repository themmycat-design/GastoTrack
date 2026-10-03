<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isOwner()) {
            abort(403, 'Access denied. Owner privileges required.');
        }

        $business = $request->user()->business;

        if ($request->user()->status !== 'active' || !$business || !$business->active || !$business->isActive()) {
            return redirect()->route('business.status');
        }

        return $next($request);
    }
}
