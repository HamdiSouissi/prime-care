<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use RuntimeException;

/**
 * Verifies CSRF tokens on state-changing HTTP methods (POST, PUT, PATCH, DELETE).
 */
final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                if ($request->wantsJson()) {
                    return Response::jsonError(
                        'CSRF_EXPIRED',
                        'Le jeton de sécurité a expiré. Veuillez actualiser la page.',
                        419
                    );
                }
                throw new RuntimeException(
                    'La session de sécurité du formulaire a expiré. Veuillez actualiser la page.',
                    419
                );
            }
        }

        return $next($request);
    }
}
