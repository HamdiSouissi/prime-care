<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Config;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/**
 * Enforces the OWASP ASVS L1 API write rate limit (120 write requests / minute / user -> HTTP 429).
 * Read requests (GET, HEAD, OPTIONS) pass through without consuming write quota.
 */
final class ApiRateLimitMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        $method = strtoupper($request->method());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $userId = Auth::id() ?? 0;
            $limit = (int) Config::get('security.api_write_limit_per_min', 120);
            if ($userId > 0 && !RateLimiter::checkApiWriteLimit($userId, $limit)) {
                return Response::jsonError(
                    'RATE_LIMITED',
                    'Limite de débit atteinte (120 requêtes d’écriture par minute maximum). Veuillez patienter.',
                    429
                )->withHeader('Retry-After', '60');
            }
        }

        return $next($request);
    }
}
