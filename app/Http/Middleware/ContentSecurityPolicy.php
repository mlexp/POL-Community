<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Livewire/Flux currently uses inline scripts and Alpine expression evaluation.
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data: blob:; font-src 'self'; connect-src 'self'; frame-src https://www.youtube.com https://www.youtube-nocookie.com https://embed.nicovideo.jp; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
