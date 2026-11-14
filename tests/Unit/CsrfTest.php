<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Session;

final class CsrfTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        $token = Csrf::token();
        $assert(strlen($token) === 64, 'CSRF token is 64 hex characters (32 random bytes)');
        $assert(Csrf::verify($token) === true, 'CSRF verify accepts matching token via hash_equals');
        $assert(Csrf::verify('invalid_token') === false, 'CSRF verify rejects mismatched token');
        $assert(Csrf::verify(null) === false, 'CSRF verify rejects null token');

        $rotated = Csrf::rotate();
        $assert($rotated !== $token, 'CSRF rotate generates a fresh token');
        $assert(Csrf::verify($rotated) === true, 'CSRF verify accepts newly rotated token');
    }
}
