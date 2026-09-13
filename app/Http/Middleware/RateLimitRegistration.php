<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs('register.store')) {
            return $next($request);
        }

        $key = 'registration|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, '登録試行回数が上限を超えました。しばらくしてから再試行してください。');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
