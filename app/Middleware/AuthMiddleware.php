<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Ensures the visitor is authenticated before accessing protected routes.
 */
final class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return Response::jsonError('UNAUTHENTICATED', 'Authentification requise.', 401);
            }

            if ($request->method() === 'GET') {
                Session::set('intended_url', $request->path());
            }

            if (Auth::trustedProfiles() !== []) {
                return Response::redirect('/login/pin');
            }

            return Response::redirect('/login');
        }

        return $next($request);
    }
}
