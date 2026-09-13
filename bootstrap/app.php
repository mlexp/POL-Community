<?php

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureActiveMember;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RateLimitRegistration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [RateLimitRegistration::class, ContentSecurityPolicy::class]);
        $middleware->alias([
            'active.member' => EnsureActiveMember::class,
            'admin' => EnsureUserIsAdmin::class,
            'password.changed' => EnsurePasswordHasBeenChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
