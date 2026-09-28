<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseConnection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Staff users: Use SQLite for offline, MySQL for online sync
            if ($user->role === 'staff') {
                // Check if request explicitly wants online sync
                if ($request->header('X-Sync-Mode') === 'online' || $request->query('sync') === 'online') {
                    Config::set('database.default', 'mysql');
                } else {
                    // Default to offline SQLite for staff
                    Config::set('database.default', 'staff_offline');
                }
            }
            // Owner and Super Admin: Always use MySQL
            elseif (in_array($user->role, ['owner', 'super_admin'])) {
                Config::set('database.default', 'mysql');
            }
            
            // Reconnect to apply the new connection
            DB::purge(config('database.default'));
            DB::reconnect(config('database.default'));
        }

        return $next($request);
    }
}
