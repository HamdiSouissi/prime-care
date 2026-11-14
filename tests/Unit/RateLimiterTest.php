<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\RateLimiter;

final class RateLimiterTest
{
    public static function run(callable $assert): void
    {
        $idA = 'caregiver-a-' . bin2hex(random_bytes(4)) . '@primecare.test';
        $idB = 'caregiver-b-' . bin2hex(random_bytes(4)) . '@primecare.test';
        $sharedIp = '198.51.100.' . random_int(10, 240);

        RateLimiter::clear($idA, $sharedIp);
        RateLimiter::clear($idB, $sharedIp);

        $assert(RateLimiter::tooManyAttempts($idA, $sharedIp) === false, 'RateLimiter allows fresh identifier + IP');

        // 1. 5 failed attempts on account A locks account A
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::recordAttempt($idA, $sharedIp, false);
        }
        $assert(RateLimiter::isAccountLocked($idA) === true, 'C2: Account A is locked after 5 failed attempts in 15 min');
        $assert(RateLimiter::tooManyAttempts($idA, $sharedIp) === true, 'C2: RateLimiter blocks locked Account A');

        // 2. Account B on the SAME shared household Wi-Fi IP is NOT blocked after Account A's 5 failures
        $assert(
            RateLimiter::isIpLocked($sharedIp) === false && RateLimiter::tooManyAttempts($idB, $sharedIp) === false,
            'C2: Account B on the same household IP is NOT blocked after 5 failures on Account A (IP limit is 30)'
        );

        // 3. Clearing Account A upon valid login unlocks Account A
        RateLimiter::recordAttempt($idA, $sharedIp, true);
        $assert(RateLimiter::tooManyAttempts($idA, $sharedIp) === false, 'RateLimiter clears failed attempts for Account A after successful login');

        // 4. Reaching configurable security.ip_max_attempts (30) across multiple accounts blocks the IP
        $ipLimit = (int) Config::get('security.ip_max_attempts', 30);
        $assert($ipLimit === 30, 'C2: Default security.ip_max_attempts is configured to 30');

        $floodIp = '203.0.113.' . random_int(10, 240);
        for ($i = 0; $i < $ipLimit; $i++) {
            RateLimiter::recordAttempt('flood-user-' . $i . '@primecare.test', $floodIp, false);
        }

        $assert(
            RateLimiter::isIpLocked($floodIp) === true && RateLimiter::tooManyAttempts('fresh@primecare.test', $floodIp) === true,
            'C2: IP address is blocked once 30 failed attempts are reached within 15 min'
        );
    }
}
