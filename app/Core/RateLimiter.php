<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Database-backed login rate limiter enforcing separate max attempts per account/identifier
 * (default 5 / 15 min) and per IP address (default 30 / 15 min for shared household Wi-Fi).
 */
final class RateLimiter
{
    /**
     * Check if the identifier or IP has reached its maximum failed login attempts within the lockout window.
     */
    public static function tooManyAttempts(string $identifier, string $ip, ?string $nowUtc = null): bool
    {
        if (self::isAccountLocked($identifier, $nowUtc)) {
            return true;
        }

        if (self::isIpLocked($ip, $nowUtc)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a specific account / identifier (or PIN selector) has reached its lockout limit (default 5 / 15 min).
     */
    public static function isAccountLocked(string $identifier, ?string $nowUtc = null): bool
    {
        $normalizedId = mb_strtolower(trim($identifier));
        if ($normalizedId === '') {
            return false;
        }

        $maxAttempts = (int) Config::get('security.login_max_attempts', 5);
        $windowStart = self::windowStart($nowUtc);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM `login_attempts`
             WHERE `identifier` = :identifier AND `success` = 0 AND `attempted_at` >= :window_start'
        );
        $stmt->execute([
            'identifier' => $normalizedId,
            'window_start' => $windowStart,
        ]);

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    /**
     * Check if an IP address has reached its shared-network lockout limit (default 30 / 15 min).
     */
    public static function isIpLocked(string $ip, ?string $nowUtc = null): bool
    {
        $cleanIp = trim($ip);
        if ($cleanIp === '') {
            return false;
        }

        $ipMaxAttempts = (int) Config::get('security.ip_max_attempts', 30);
        $windowStart = self::windowStart($nowUtc);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM `login_attempts`
             WHERE `ip_address` = :ip AND `success` = 0 AND `attempted_at` >= :window_start'
        );
        $stmt->execute([
            'ip' => $cleanIp,
            'window_start' => $windowStart,
        ]);

        return (int) $stmt->fetchColumn() >= $ipMaxAttempts;
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
     * Clear failed attempts for the authenticated identifier upon successful login.
     */
    public static function clear(string $identifier, string $ip = ''): void
    {
        $normalizedId = mb_strtolower(trim($identifier));
        if ($normalizedId === '') {
            return;
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM `login_attempts` WHERE `identifier` = :identifier AND `success` = 0'
        );
        $stmt->execute([
            'identifier' => $normalizedId,
        ]);
    }

    private static function windowStart(?string $nowUtc = null): string
    {
        $lockoutMinutes = (int) Config::get('security.login_lockout_minutes', 15);
        $now = $nowUtc !== null
            ? new DateTimeImmutable($nowUtc, new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $now->modify("-{$lockoutMinutes} minutes")->format('Y-m-d H:i:s');
    }
}
