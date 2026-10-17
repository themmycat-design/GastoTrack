<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Laravel 13 uses PHP's global SortDirection enum. Ensure Symfony's guarded
// polyfill stub is loaded even if Composer generated its class map on PHP 8.6+
// and this application is subsequently run on PHP 8.4 or 8.5.
require_once __DIR__.'/../vendor/symfony/polyfill-php86/Resources/stubs/SortDirection.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'owner' => \App\Http\Middleware\EnsureUserIsOwner::class,
            'set.database' => \App\Http\Middleware\SetDatabaseConnection::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'business.active' => \App\Http\Middleware\EnsureBusinessIsActive::class,
            'owner.security' => \App\Http\Middleware\EnsureOwnerAccountSecurity::class,
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
