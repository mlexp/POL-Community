<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordHasBeenChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->password_reset_required && ! $request->routeIs('password.change-required.*', 'logout')) {
            return redirect()->route('password.change-required.edit');
        }

        return $next($request);
    }
}
