<?php

declare(strict_types=1);

namespace Tests\Unit;

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
    }
}
