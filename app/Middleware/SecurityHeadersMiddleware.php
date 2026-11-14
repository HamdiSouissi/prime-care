<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Applies strict security HTTP headers including nonce-based Content-Security-Policy.
 */
final class SecurityHeadersMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $nonce = View::cspNonce();
        $env = (string) Config::get('app.env', 'development');
        $denyFraming = (bool) Config::get('security.deny_framing', $env === 'production');

        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        if ($denyFraming) {
            $cspDirectives[] = "frame-ancestors 'none'";
            $response->withHeader('X-Frame-Options', 'DENY');
        }

        $response
            ->withHeader('Content-Security-Policy', implode('; ', $cspDirectives))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');

        if ($env === 'production') {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
