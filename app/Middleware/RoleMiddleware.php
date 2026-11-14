<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use RuntimeException;

/**
 * Restricts route access to users possessing one of the permitted roles.
 */
final class RoleMiddleware
{
    /** @var array<int, string> */
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check() || !Auth::hasRole(...$this->roles)) {
            if ($request->wantsJson()) {
                return Response::jsonError(
                    'FORBIDDEN',
                    'Vous n’avez pas les droits nécessaires pour effectuer cette action.',
                    403
                );
            }
            throw new RuntimeException('Accès refusé à cette section.', 403);
        }

        return $next($request);
    }
}
