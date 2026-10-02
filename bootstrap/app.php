<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventPageCaching;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            EnsureUserIsActive::class,
            PreventPageCaching::class,
        ]);

        // Hostinger serves the site through its own proxy/CDN: trust the forwarded
        // headers so Laravel sees the real https scheme and the visitor's real IP
        // (the login rate-limit is per IP — behind a proxy every visitor shared one).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'platform-admin' => EnsurePlatformAdmin::class,
            'tenant-user' => EnsureTenantUser::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
