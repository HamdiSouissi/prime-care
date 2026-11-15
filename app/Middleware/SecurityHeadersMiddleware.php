<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Applies strict security HTTP headers including nonce-based Content-Security-Policy
 * (no unsafe-inline) and global clickjacking protection (X-Frame-Options: DENY, frame-ancestors 'none').
 */
final class SecurityHeadersMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $nonce = View::cspNonce();
        $env = (string) Config::get('app.env', 'development');

        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        $response
            ->withHeader('Content-Security-Policy', implode('; ', $cspDirectives))
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');

        if ($env === 'production') {
            $response->withHeader('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
