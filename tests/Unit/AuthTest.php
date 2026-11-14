<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;

final class AuthTest
{
    public static function run(callable $assert): void
    {
        Auth::logout();
        unset($_COOKIE[Auth::TRUSTED_DEVICE_COOKIE], $_COOKIE[Auth::LEGACY_DEVICE_USER_COOKIE]);
        $assert(Auth::check() === false, 'Auth::check() is false when logged out');

        // 1. Password login with wrong password fails
        $badLogin = Auth::attempt('fatou@primecare.test', 'WrongPass!2026', false);
        $assert($badLogin === false, 'Auth::attempt rejects wrong password');

        // 2. Password login with valid credentials succeeds
        $okLogin = Auth::attempt('fatou@primecare.test', 'Soin!2026', false);
        $assert($okLogin === true, 'Auth::attempt accepts valid caregiver password');
        $assert(Auth::check() === true, 'Auth::check() is true after login');
        $assert(Auth::authenticatedViaPassword() === true, 'Auth::authenticatedViaPassword() is true after password login');
        $assert(Auth::hasRole('caregiver') === true, 'Auth::hasRole("caregiver") is true for Fatou');
        $assert(Auth::hasRole('admin') === false, 'Auth::hasRole("admin") is false for Fatou');

        $fatouId = (int) Auth::id();
        $assert($fatouId > 0, 'Auth::id() returns positive integer user ID');

        // 3. C1 Test 1: Forged user_id in POST /login/pin (e.g. user_id=1&pin=1111 without valid selector) -> rejected
        Auth::logout();
        $forgedReq = new Request(
            [],
            ['user_id' => '1', 'pin' => '1111'],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/login/pin',
                'HTTP_ACCEPT' => 'application/json',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        );
        $forgedResp = (new AuthController())->loginPin($forgedReq);
        $assert(
            $forgedResp->statusCode() === 401 && Auth::check() === false,
            'C1: Forged user_id=1&pin=1111 in POST /login/pin is rejected (no admin takeover)'
        );

        // 4. C1 Test 2: Forged cookie or plain ID in cookie -> zero profile displayed
        $_COOKIE[Auth::LEGACY_DEVICE_USER_COOKIE] = '1';
        $_COOKIE[Auth::TRUSTED_DEVICE_COOKIE] = '1';
        $assert(
            Auth::trustedProfiles() === [],
            'C1: Plain integer user_id in cookie yields 0 trusted profiles'
        );

        $fakeCookie = bin2hex(random_bytes(12)) . ':' . bin2hex(random_bytes(32));
        $assert(
            Auth::trustedProfiles($fakeCookie) === [],
            'C1: Forged selector:validator cookie yields 0 trusted profiles'
        );

        // 5. Issue real trusted device tokens for Admin (id=1) and Fatou (id=$fatouId)
        $adminToken = Auth::issueTrustedDevice(1, 'iPhone Karim', '');
        [$adminSelector, $adminValidator] = explode(':', $adminToken, 2);

        $fatouToken = Auth::issueTrustedDevice($fatouId, 'Téléphone Domicile', $adminToken);
        [$fatouSelector, $fatouValidator] = explode(':', $fatouToken, 2);

        $sharedCookie = $adminToken . ',' . $fatouToken;
        $_COOKIE[Auth::TRUSTED_DEVICE_COOKIE] = $sharedCookie;

        $profiles = Auth::trustedProfiles($sharedCookie);
        $assert(count($profiles) === 2, 'C1: Shared device cookie displays only the 2 valid trusted profiles');

        // 6. C1 Test 3: Token belonging to another user / mismatched validator -> rejected
        $crossTokenCookie = $adminSelector . ':' . $fatouValidator;
        $crossAttempt = Auth::attemptTrustedPin($adminSelector, '1111', $crossTokenCookie, '127.0.0.1');
        $assert(
            $crossAttempt['ok'] === false && Auth::check() === false,
            'C1: Another user validator paired with admin selector is rejected'
        );

        // Using Fatou's selector with Admin's PIN -> rejected
        $wrongUserPin = Auth::attemptTrustedPin($fatouSelector, '1111', $sharedCookie, '127.0.0.1');
        $assert(
            $wrongUserPin['ok'] === false && Auth::check() === false,
            'C1: Admin PIN submitted against Fatou trusted selector is rejected'
        );
        RateLimiter::clear('pin:' . $fatouSelector);

        // 7. C1 Test 4: Valid trusted token + valid PIN -> login succeeds
        $validPinRes = Auth::attemptTrustedPin($fatouSelector, '3333', $sharedCookie, '127.0.0.1');
        $assert(
            $validPinRes['ok'] === true && Auth::check() === true && Auth::id() === $fatouId,
            'C1: Valid trusted device token + valid PIN 3333 logs Fatou in'
        );
        $assert(
            Auth::authenticatedViaPassword() === false,
            'C1: PIN-authenticated session is marked as via=pin (cannot issue new device tokens without password)'
        );
        Auth::logout();

        // 8. C1 Test 5: Lockout after 5 wrong PIN attempts for a profile
        RateLimiter::clear('pin:' . $fatouSelector);
        for ($i = 0; $i < 5; $i++) {
            $failRes = Auth::attemptTrustedPin($fatouSelector, '0000', $sharedCookie, '127.0.0.1');
        }
        $lockedRes = Auth::attemptTrustedPin($fatouSelector, '3333', $sharedCookie, '127.0.0.1');
        $assert(
            $lockedRes['ok'] === false && $lockedRes['code'] === 'RATE_LIMITED' && Auth::check() === false,
            'C1: Profile PIN is locked for 15 min after 5 failed PIN attempts (even with valid PIN on 6th try)'
        );
        RateLimiter::clear('pin:' . $fatouSelector);

        // 9. C1 Test 6: Revoked token -> rejected
        Auth::revokeTrustedDeviceSelector($fatouId, $fatouSelector);
        $revokedRes = Auth::attemptTrustedPin($fatouSelector, '3333', $sharedCookie, '127.0.0.1');
        $assert(
            $revokedRes['ok'] === false && Auth::check() === false,
            'C1: Revoked trusted device token is rejected'
        );

        // Admin revocation via revokeAllDevicesForUser also blocks PIN login
        Auth::revokeAllDevicesForUser(1, true);
        $revokedAdminRes = Auth::attemptTrustedPin($adminSelector, '1111', $sharedCookie, '127.0.0.1');
        $assert(
            $revokedAdminRes['ok'] === false && Auth::check() === false,
            'C1: revokeAllDevicesForUser revokes all trusted device tokens for user'
        );

        // 10. Remember-me token rotation
        $rawToken = Auth::issueRememberToken($fatouId, 'PHPUnit', '127.0.0.1');
        $assert(str_contains($rawToken, ':'), 'Auth::issueRememberToken returns selector:validator pair');
        $rotated = Auth::consumeRememberToken($rawToken);
        $assert($rotated !== null && $rotated !== $rawToken, 'Auth::consumeRememberToken rotates token on valid use');
        $reused = Auth::consumeRememberToken($rawToken);
        $assert($reused === null, 'Auth::consumeRememberToken rejects already-used token');

        Auth::logout();
        unset($_COOKIE[Auth::TRUSTED_DEVICE_COOKIE]);
    }
}
