<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach security-related HTTP headers to every response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Strict-Transport-Security (HSTS) — enforce HTTPS for 1 year, include subdomains
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // Content-Security-Policy — restrict resources to same origin and data URIs
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'");

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'DENY');

        // Disable browser XSS filter (modern CSP replaces this)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy — only send origin on cross-origin requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy — disable sensitive browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        return $response;
    }
}
