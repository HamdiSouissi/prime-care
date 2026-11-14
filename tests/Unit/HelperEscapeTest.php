<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\SecurityHeadersMiddleware;

final class HelperEscapeTest
{
    public static function run(callable $assert): void
    {
        $raw = '<script>alert("xss" & \'single\')</script>';
        $escaped = e($raw);

        $assert(
            $escaped === '&lt;script&gt;alert(&quot;xss&quot; &amp; &#039;single&#039;)&lt;/script&gt;',
            'Helper e() escapes HTML entities including single and double quotes'
        );

        $assert(e(null) === '', 'Helper e(null) returns empty string');
        $assert(e(42) === '42', 'Helper e(int) returns string representation');
        $assert(initials('Fatou', 'Diallo') === 'FD', 'Helper initials() returns uppercase initials');

        // C5: Avatar color class palette (.pc-avatar--c1 to .pc-avatar--c8)
        $assert(avatar_color_class(1) === 'pc-avatar--c1', 'C5: avatar_color_class(1) returns pc-avatar--c1');
        $assert(avatar_color_class(8) === 'pc-avatar--c8', 'C5: avatar_color_class(8) returns pc-avatar--c8');
        $assert(avatar_color_class(9) === 'pc-avatar--c1', 'C5: avatar_color_class(9) wraps around to pc-avatar--c1');

        // C5: SecurityHeadersMiddleware sends CSP without unsafe-inline, plus frame-ancestors 'none' and X-Frame-Options: DENY
        $mw = new SecurityHeadersMiddleware();
        $resp = $mw->handle(new Request(), static fn() => Response::html('ok', 200));
        $headers = $resp->headers();
        $csp = (string) ($headers['Content-Security-Policy'] ?? '');
        $xfo = (string) ($headers['X-Frame-Options'] ?? '');

        $assert(
            !str_contains($csp, 'unsafe-inline'),
            'C5: Content-Security-Policy contains zero unsafe-inline directives'
        );
        $assert(
            str_contains($csp, "frame-ancestors 'none'") && $xfo === 'DENY',
            'C5: frame-ancestors \'none\' and X-Frame-Options: DENY are sent in all environments'
        );
    }
}
