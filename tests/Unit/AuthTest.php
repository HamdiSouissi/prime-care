<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;

final class AuthTest
{
    public static function run(callable $assert): void
    {
        Auth::logout(false);
        $assert(Auth::check() === false, 'Auth::check() is false when logged out');

        // 1. Password login with wrong password fails
        $badLogin = Auth::attempt('fatou@primecare.test', 'WrongPass!2026', false);
        $assert($badLogin === false, 'Auth::attempt rejects wrong password');

        // 2. Password login with valid credentials succeeds
        $okLogin = Auth::attempt('fatou@primecare.test', 'Soin!2026', false);
        $assert($okLogin === true, 'Auth::attempt accepts valid caregiver password');
        $assert(Auth::check() === true, 'Auth::check() is true after login');
        $assert(Auth::hasRole('caregiver') === true, 'Auth::hasRole("caregiver") is true for Fatou');
        $assert(Auth::hasRole('admin') === false, 'Auth::hasRole("admin") is false for Fatou');

        $fatouId = Auth::id();
        $assert($fatouId !== null && $fatouId > 0, 'Auth::id() returns positive integer user ID');

        Auth::logout(true);

        // 3. PIN login verification
        $badPin = Auth::attemptPin((int) $fatouId, '9999', false);
        $assert($badPin === false, 'Auth::attemptPin rejects invalid PIN');

        $okPin = Auth::attemptPin((int) $fatouId, '3333', false);
        $assert($okPin === true, 'Auth::attemptPin accepts valid PIN 3333 for Fatou');
        $assert(Auth::check() === true, 'Auth::check() is true after PIN login');

        // 4. Remember-me token rotation
        $rawToken = Auth::issueRememberToken((int) $fatouId, 'PHPUnit', '127.0.0.1');
        $assert(str_contains($rawToken, ':'), 'Auth::issueRememberToken returns selector:validator pair');
        $rotated = Auth::consumeRememberToken($rawToken);
        $assert($rotated !== null && $rotated !== $rawToken, 'Auth::consumeRememberToken rotates token on valid use');
        $reused = Auth::consumeRememberToken($rawToken);
        $assert($reused === null, 'Auth::consumeRememberToken rejects already-used token');

        Auth::logout(false);
    }
}
