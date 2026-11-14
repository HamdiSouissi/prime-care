<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Database-backed login rate limiter enforcing max attempts per identifier and IP address.
 */
final class RateLimiter
{
    /**
     * Check if the identifier or IP has reached the maximum failed login attempts within the lockout window.
     */
    public static function tooManyAttempts(string $identifier, string $ip, ?string $nowUtc = null): bool
    {
        $maxAttempts = (int) Config::get('security.login_max_attempts', 5);
        $lockoutMinutes = (int) Config::get('security.login_lockout_minutes', 15);

        $now = $nowUtc !== null
            ? new DateTimeImmutable($nowUtc, new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $windowStart = $now->modify("-{$lockoutMinutes} minutes")->format('Y-m-d H:i:s');

        $pdo = Database::connection();
        $normalizedId = mb_strtolower(trim($identifier));

        // Count failed attempts by identifier within window
        if ($normalizedId !== '') {
            $stmtId = $pdo->prepare(
                'SELECT COUNT(*) FROM `login_attempts`
                 WHERE `identifier` = :identifier AND `success` = 0 AND `attempted_at` >= :window_start'
            );
            $stmtId->execute([
                'identifier' => $normalizedId,
                'window_start' => $windowStart,
            ]);
            if ((int) $stmtId->fetchColumn() >= $maxAttempts) {
                return true;
            }
        }

        // Count failed attempts by IP within window
        if ($ip !== '') {
            $stmtIp = $pdo->prepare(
                'SELECT COUNT(*) FROM `login_attempts`
                 WHERE `ip_address` = :ip AND `success` = 0 AND `attempted_at` >= :window_start'
            );
            $stmtIp->execute([
                'ip' => $ip,
                'window_start' => $windowStart,
            ]);
            if ((int) $stmtIp->fetchColumn() >= $maxAttempts) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a login attempt (success or failure) in login_attempts.
     */
    public static function recordAttempt(
        string $identifier,
        string $ip,
        bool $success,
        ?string $nowUtc = null
    ): void {
        $now = $nowUtc ?? gmdate('Y-m-d H:i:s');
        $normalizedId = mb_strtolower(trim($identifier));

        $stmt = Database::connection()->prepare(
            'INSERT INTO `login_attempts` (`identifier`, `ip_address`, `attempted_at`, `success`)
             VALUES (:identifier, :ip, :attempted_at, :success)'
        );
        $stmt->execute([
            'identifier' => $normalizedId,
            'ip' => $ip,
            'attempted_at' => $now,
            'success' => $success ? 1 : 0,
        ]);

        if ($success && $normalizedId !== '') {
            self::clear($normalizedId, $ip);
        }
    }

    /**
     * Clear recent failed attempts for an identifier/IP upon successful login.
     */
    public static function clear(string $identifier, string $ip): void
    {
        $normalizedId = mb_strtolower(trim($identifier));
        $stmt = Database::connection()->prepare(
            'DELETE FROM `login_attempts` WHERE (`identifier` = :identifier OR `ip_address` = :ip) AND `success` = 0'
        );
        $stmt->execute([
            'identifier' => $normalizedId,
            'ip' => $ip,
        ]);
    }
}
