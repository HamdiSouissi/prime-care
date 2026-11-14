<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\RateLimiter;

final class RateLimiterTest
{
    public static function run(callable $assert): void
    {
        $id = 'test-rate-' . bin2hex(random_bytes(4)) . '@primecare.test';
        $ip = '198.51.100.42';

        RateLimiter::clear($id, $ip);
        $assert(RateLimiter::tooManyAttempts($id, $ip) === false, 'RateLimiter allows fresh identifier + IP');

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::recordAttempt($id, $ip, false);
        }

        $assert(RateLimiter::tooManyAttempts($id, $ip) === true, 'RateLimiter blocks after 5 failed attempts in 15 min window');

        RateLimiter::recordAttempt($id, $ip, true);
        $assert(RateLimiter::tooManyAttempts($id, $ip) === false, 'RateLimiter clears failed attempts after successful login');
    }
}
