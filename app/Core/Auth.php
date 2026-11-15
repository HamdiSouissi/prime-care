<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Policy;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Authentication manager supporting password login, trusted-device PIN login
 * (selector:validator tokens stored in trusted_devices with SHA-256 hash),
 * rotating remember-me tokens, role checks, and Policy authorization.
 */
final class Auth
{
    public const REMEMBER_COOKIE = 'prime_care_remember';
    public const TRUSTED_DEVICE_COOKIE = 'prime_care_trusted_devices';
    public const LEGACY_DEVICE_USER_COOKIE = 'prime_care_device_uid';

    /** @var array<string, mixed>|null */
    private static ?array $cachedUser = null;

    /**
     * Hash a password or PIN using Argon2id if available, falling back to bcrypt.
     */
    public static function hashSecret(string $plain): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($plain, $algo);
    }

    /**
     * Verify a password or PIN against a stored hash.
     */
    public static function verifySecret(string $plain, ?string $hash): bool
    {
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        return password_verify($plain, $hash);
    }

    /**
     * Attempt email + password authentication.
     */
    public static function attempt(string $email, string $password, bool $remember = false): bool
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $stmt = Database::connection()->prepare(
            'SELECT * FROM `users` WHERE LOWER(`email`) = :email AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch();

        if (!is_array($user) || (int) ($user['is_active'] ?? 0) !== 1) {
            \App\Services\AuditService::record('auth.login_failed', 'users', null, null, [
                'email' => $normalizedEmail,
                'reason' => 'account_not_found_or_inactive',
            ]);
            return false;
        }

        if (!self::verifySecret($password, (string) ($user['password_hash'] ?? ''))) {
            \App\Services\AuditService::record('auth.login_failed', 'users', (int) $user['id'], null, [
                'email' => $normalizedEmail,
                'reason' => 'invalid_password',
            ], 1, (int) $user['id']);
            return false;
        }

        self::loginUser($user, $remember, 'password');
        \App\Services\AuditService::record('auth.login', 'users', (int) $user['id'], null, [
            'email' => $normalizedEmail,
            'method' => 'password',
            'remember' => $remember,
        ], 1, (int) $user['id']);
        return true;
    }

    /**
     * Log in a user array, regenerate session & CSRF, and optionally issue a remember token.
     *
     * @param array<string, mixed> $user
     */
    public static function loginUser(array $user, bool $remember = false, string $via = 'password'): void
    {
        Session::regenerate();
        Csrf::rotate();

        $userId = (int) $user['id'];
        Session::set('auth_user_id', $userId);
        Session::set('auth_via', $via);
        self::$cachedUser = $user;

        // Always purge any legacy plain-ID cookie
        if (isset($_COOKIE[self::LEGACY_DEVICE_USER_COOKIE])) {
            self::clearCookie(self::LEGACY_DEVICE_USER_COOKIE);
        }

        Database::connection()->prepare(
            'UPDATE `users` SET `last_login_at` = :now WHERE `id` = :id'
        )->execute([
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);

        if ($remember) {
            $token = self::issueRememberToken($userId);
            Session::set('remember_selector', explode(':', $token)[0]);
            self::setCookie(self::REMEMBER_COOKIE, $token, 30, 'Lax');
        }
    }

    /**
     * Whether the current session was authenticated directly via password.
     */
    public static function authenticatedViaPassword(): bool
    {
        return self::check() && Session::get('auth_via') === 'password';
    }

    /**
     * Issue a trusted device token (selector:validator) for PIN login on this browser.
     * The validator is hashed with SHA-256 in the database and stored in an HttpOnly,
     * SameSite=Strict cookie valid for 90 days. A single browser can hold tokens for
     * multiple profiles on a shared household device.
     */
    public static function issueTrustedDevice(
        int $userId,
        string $deviceLabel = 'Appareil mobile',
        ?string $existingCookie = null
    ): string {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $days = (int) Config::get('security.trusted_device_days', 90);
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$days} days")
            ->format('Y-m-d H:i:s');

        // Parse existing cookie tokens on this browser and revoke any prior token for this same user
        $pairs = self::parseTrustedDeviceCookie($existingCookie);
        $keptPairs = [];
        foreach ($pairs as $sel => $val) {
            $stmt = $pdo->prepare('SELECT `id`, `user_id` FROM `trusted_devices` WHERE `selector` = :s LIMIT 1');
            $stmt->execute(['s' => $sel]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                if ((int) $row['user_id'] === $userId) {
                    $pdo->prepare('UPDATE `trusted_devices` SET `revoked_at` = :now WHERE `id` = :id')
                        ->execute(['now' => $now, 'id' => (int) $row['id']]);
                } else {
                    $keptPairs[$sel] = $val;
                }
            }
        }

        $selector = bin2hex(random_bytes(12)); // 24 hex chars
        $validator = bin2hex(random_bytes(32)); // 64 hex chars
        $validatorHash = hash('sha256', $validator);

        $cleanLabel = trim($deviceLabel);
        if ($cleanLabel === '') {
            $cleanLabel = 'Appareil mobile';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO `trusted_devices`
             (`user_id`, `selector`, `validator_hash`, `device_label`, `created_at`, `last_used_at`, `expires_at`, `revoked_at`)
             VALUES (:user_id, :selector, :validator_hash, :device_label, :created_at, :last_used_at, :expires_at, NULL)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => $validatorHash,
            'device_label' => mb_substr($cleanLabel, 0, 120),
            'created_at' => $now,
            'last_used_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        $keptPairs[$selector] = $validator;
        // Keep at most 8 profile tokens on one browser
        if (count($keptPairs) > 8) {
            $keptPairs = array_slice($keptPairs, -8, 8, true);
        }

        self::writeTrustedDeviceCookie($keptPairs, $days);
        Session::set('trusted_device_selector', $selector);

        return $selector . ':' . $validator;
    }

    /**
     * Return the effective cookie name (with __Host- prefix in production).
     */
    public static function cookieName(string $baseName): string
    {
        $env = (string) Config::get('app.env', 'development');
        $secure = $env === 'production' || (bool) Config::get('session.secure', false);
        if ($secure && !str_starts_with($baseName, '__Host-')) {
            return '__Host-' . $baseName;
        }
        return $baseName;
    }

    /**
     * Parse the trusted devices cookie into a map of [selector => validator].
     * Any plain integer ID, malformed token, or non-hex string is strictly rejected.
     *
     * @return array<string, string>
     */
    public static function parseTrustedDeviceCookie(?string $rawCookie = null): array
    {
        $cookieKey = self::cookieName(self::TRUSTED_DEVICE_COOKIE);
        $raw = $rawCookie ?? (
            isset($_COOKIE[$cookieKey])
                ? (string) $_COOKIE[$cookieKey]
                : (isset($_COOKIE[self::TRUSTED_DEVICE_COOKIE]) ? (string) $_COOKIE[self::TRUSTED_DEVICE_COOKIE] : '')
        );
        if ($raw === '') {
            return [];
        }

        $entries = preg_split('/[,|]+/', $raw) ?: [];
        $valid = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '' || !str_contains($entry, ':')) {
                continue;
            }
            [$selector, $validator] = explode(':', $entry, 2);
            if (
                preg_match('/^[a-f0-9]{16,32}$/i', $selector) === 1
                && preg_match('/^[a-f0-9]{32,64}$/i', $validator) === 1
            ) {
                $valid[strtolower($selector)] = strtolower($validator);
            }
        }

        return $valid;
    }

    /**
     * Return all valid trusted profiles currently present on this browser.
     * Never falls back to a default user, never reads ?user=, and never trusts a plain user_id cookie.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function trustedProfiles(?string $rawCookie = null): array
    {
        $pairs = self::parseTrustedDeviceCookie($rawCookie);
        if ($pairs === []) {
            return [];
        }

        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $profiles = [];
        $seenUsers = [];

        foreach ($pairs as $selector => $validator) {
            $stmt = $pdo->prepare(
                'SELECT td.`id` AS `device_id`, td.`selector`, td.`validator_hash`, td.`device_label`,
                        u.`id` AS `user_id`, u.`first_name`, u.`last_name`, u.`role`, u.`avatar_path`, u.`pin_hash`
                 FROM `trusted_devices` td
                 INNER JOIN `users` u ON u.`id` = td.`user_id`
                 WHERE td.`selector` = :selector
                   AND td.`revoked_at` IS NULL
                   AND td.`expires_at` > :now
                   AND u.`is_active` = 1
                   AND u.`deleted_at` IS NULL
                   AND u.`pin_hash` IS NOT NULL
                   AND u.`pin_hash` != \'\'
                 LIMIT 1'
            );
            $stmt->execute([
                'selector' => $selector,
                'now' => $now,
            ]);
            $row = $stmt->fetch();

            if (!is_array($row)) {
                continue;
            }

            $expectedHash = (string) $row['validator_hash'];
            $candidateHash = hash('sha256', $validator);
            if (!hash_equals($expectedHash, $candidateHash)) {
                continue;
            }

            $uid = (int) $row['user_id'];
            if (isset($seenUsers[$uid])) {
                continue;
            }
            $seenUsers[$uid] = true;

            $pinLocked = RateLimiter::isAccountLocked('pin:' . $selector);

            $profiles[] = [
                'selector' => (string) $row['selector'],
                'device_label' => (string) $row['device_label'],
                'user_id' => $uid,
                'first_name' => (string) $row['first_name'],
                'last_name' => (string) $row['last_name'],
                'role' => (string) $row['role'],
                'avatar_path' => $row['avatar_path'],
                'pin_locked' => $pinLocked,
            ];
        }

        return $profiles;
    }

    /**
     * Check if the currently authenticated user has an active trusted device token on this browser.
     */
    public static function currentBrowserTrustedForUser(int $userId, ?string $rawCookie = null): ?array
    {
        foreach (self::trustedProfiles($rawCookie) as $profile) {
            if ((int) $profile['user_id'] === $userId) {
                return $profile;
            }
        }
        return null;
    }

    /**
     * Attempt PIN authentication using ONLY a trusted-device selector and the browser's cookie validator.
     * Never accepts a raw user_id.
     *
     * @return array{ok: bool, code: string, message: string}
     */
    public static function attemptTrustedPin(
        string $selector,
        string $pin,
        ?string $rawCookie = null,
        string $ip = '127.0.0.1'
    ): array {
        $selector = strtolower(trim($selector));
        $pin = trim($pin);

        if (preg_match('/^[a-f0-9]{16,32}$/', $selector) !== 1) {
            return [
                'ok' => false,
                'code' => 'INVALID_SELECTOR',
                'message' => t('auth.pin.invalid'),
            ];
        }

        $pairs = self::parseTrustedDeviceCookie($rawCookie);
        if (!isset($pairs[$selector])) {
            return [
                'ok' => false,
                'code' => 'MISSING_COOKIE_TOKEN',
                'message' => t('auth.pin.invalid'),
            ];
        }

        $cookieValidator = $pairs[$selector];
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'SELECT * FROM `trusted_devices`
             WHERE `selector` = :selector AND `revoked_at` IS NULL AND `expires_at` > :now
             LIMIT 1'
        );
        $stmt->execute([
            'selector' => $selector,
            'now' => $now,
        ]);
        $device = $stmt->fetch();

        if (!is_array($device)) {
            return [
                'ok' => false,
                'code' => 'DEVICE_NOT_FOUND_OR_REVOKED',
                'message' => t('auth.pin.invalid'),
            ];
        }

        $expectedHash = (string) $device['validator_hash'];
        $candidateHash = hash('sha256', $cookieValidator);
        if (!hash_equals($expectedHash, $candidateHash)) {
            return [
                'ok' => false,
                'code' => 'VALIDATOR_MISMATCH',
                'message' => t('auth.pin.invalid'),
            ];
        }

        $userId = (int) $device['user_id'];
        $userStmt = $pdo->prepare(
            'SELECT * FROM `users`
             WHERE `id` = :id AND `is_active` = 1 AND `deleted_at` IS NULL
               AND `pin_hash` IS NOT NULL AND `pin_hash` != \'\'
             LIMIT 1'
        );
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch();

        if (!is_array($user)) {
            return [
                'ok' => false,
                'code' => 'USER_UNAVAILABLE',
                'message' => t('auth.pin.invalid'),
            ];
        }

        $rateKey = 'pin:' . $selector;
        if (RateLimiter::tooManyAttempts($rateKey, $ip)) {
            return [
                'ok' => false,
                'code' => 'RATE_LIMITED',
                'message' => t('auth.pin.locked'),
            ];
        }

        if (preg_match('/^\d{4,6}$/', $pin) !== 1 || !self::verifySecret($pin, (string) $user['pin_hash'])) {
            RateLimiter::recordAttempt($rateKey, $ip, false);
            \App\Services\AuditService::record('auth.login_pin_failed', 'users', $userId, null, [
                'selector' => $selector,
                'method' => 'pin',
            ], 1, $userId);
            if (RateLimiter::tooManyAttempts($rateKey, $ip)) {
                return [
                    'ok' => false,
                    'code' => 'RATE_LIMITED',
                    'message' => t('auth.pin.locked'),
                ];
            }
            return [
                'ok' => false,
                'code' => 'INVALID_PIN',
                'message' => t('auth.pin.invalid'),
            ];
        }

        RateLimiter::recordAttempt($rateKey, $ip, true);
        $pdo->prepare('UPDATE `trusted_devices` SET `last_used_at` = :now WHERE `id` = :id')
            ->execute(['now' => $now, 'id' => (int) $device['id']]);

        self::loginUser($user, false, 'pin');
        Session::set('trusted_device_selector', $selector);
        \App\Services\AuditService::record('auth.login_pin', 'users', $userId, null, [
            'selector' => $selector,
            'method' => 'pin',
        ], 1, $userId);

        return [
            'ok' => true,
            'code' => 'OK',
            'message' => '',
        ];
    }

    /**
     * Revoke a single trusted device selector for a user and remove it from the browser cookie.
     */
    public static function revokeTrustedDeviceSelector(int $userId, string $selector): bool
    {
        $selector = strtolower(trim($selector));
        $stmt = Database::connection()->prepare(
            'UPDATE `trusted_devices`
             SET `revoked_at` = :now
             WHERE `user_id` = :uid AND `selector` = :selector AND `revoked_at` IS NULL'
        );
        $stmt->execute([
            'now' => gmdate('Y-m-d H:i:s'),
            'uid' => $userId,
            'selector' => $selector,
        ]);

        $pairs = self::parseTrustedDeviceCookie();
        if (isset($pairs[$selector])) {
            unset($pairs[$selector]);
            self::writeTrustedDeviceCookie($pairs, (int) Config::get('security.trusted_device_days', 90));
        }

        if ($stmt->rowCount() > 0) {
            \App\Services\AuditService::record('auth.revoke_device', 'trusted_devices', $userId, null, [
                'selector' => $selector,
                'scope' => 'single_device',
            ], 1, self::id() ?? $userId);
            return true;
        }

        return false;
    }

    /**
     * Revoke all trusted device tokens and remember tokens for a user (used on "Déconnecter les autres appareils",
     * password change/reset, or admin account deactivation).
     */
    public static function revokeAllDevicesForUser(int $userId, bool $clearCurrentBrowserToken = false): int
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        $stmtTrusted = $pdo->prepare(
            'UPDATE `trusted_devices` SET `revoked_at` = :now WHERE `user_id` = :uid AND `revoked_at` IS NULL'
        );
        $stmtTrusted->execute(['now' => $now, 'uid' => $userId]);
        $count = $stmtTrusted->rowCount();

        $currentRememberSelector = (string) Session::get('remember_selector', '');
        if ($currentRememberSelector !== '' && !$clearCurrentBrowserToken) {
            $stmtRem = $pdo->prepare(
                'DELETE FROM `remember_tokens` WHERE `user_id` = :uid AND `selector` != :sel'
            );
            $stmtRem->execute(['uid' => $userId, 'sel' => $currentRememberSelector]);
            $count += $stmtRem->rowCount();
        } else {
            $stmtRem = $pdo->prepare('DELETE FROM `remember_tokens` WHERE `user_id` = :uid');
            $stmtRem->execute(['uid' => $userId]);
            $count += $stmtRem->rowCount();
        }

        if ($clearCurrentBrowserToken) {
            $pairs = self::parseTrustedDeviceCookie();
            $changed = false;
            foreach (array_keys($pairs) as $sel) {
                $check = $pdo->prepare('SELECT `user_id` FROM `trusted_devices` WHERE `selector` = :s LIMIT 1');
                $check->execute(['s' => $sel]);
                $row = $check->fetch();
                if (is_array($row) && (int) $row['user_id'] === $userId) {
                    unset($pairs[$sel]);
                    $changed = true;
                }
            }
            if ($changed) {
                self::writeTrustedDeviceCookie($pairs, (int) Config::get('security.trusted_device_days', 90));
            }
        }

        if ($count > 0) {
            \App\Services\AuditService::record('auth.revoke_device', 'trusted_devices', $userId, null, [
                'revoked_count' => $count,
                'scope' => 'all_devices',
            ], 1, self::id() ?? $userId);
        }

        return $count;
    }

    /**
     * Revoke all remember tokens and trusted device tokens for a user.
     */
    public static function revokeOtherDevices(int $userId): int
    {
        return self::revokeAllDevicesForUser($userId, false);
    }

    /**
     * Issue a new selector:validator remember-me token valid for 30 days.
     */
    public static function issueRememberToken(int $userId, ?string $userAgent = null, ?string $ip = null): string
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $validatorHash = hash('sha256', $validator);

        $days = (int) Config::get('security.remember_days', 30);
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$days} days")
            ->format('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO `remember_tokens`
             (`user_id`, `selector`, `validator_hash`, `user_agent`, `ip_address`, `expires_at`, `created_at`)
             VALUES (:user_id, :selector, :validator_hash, :user_agent, :ip, :expires_at, :created_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => $validatorHash,
            'user_agent' => substr($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 255),
            'ip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $selector . ':' . $validator;
    }

    /**
     * Validate and rotate a selector:validator remember token.
     * Returns the new rotated "selector:validator" string on success, or null on failure.
     */
    public static function consumeRememberToken(string $rawToken): ?string
    {
        if (!str_contains($rawToken, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $rawToken, 2);
        if ($selector === '' || $validator === '') {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM `remember_tokens` WHERE `selector` = :selector AND `expires_at` > :now LIMIT 1'
        );
        $stmt->execute([
            'selector' => $selector,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
        $tokenRow = $stmt->fetch();

        if (!is_array($tokenRow)) {
            return null;
        }

        $expectedHash = (string) $tokenRow['validator_hash'];
        $candidateHash = hash('sha256', $validator);

        if (!hash_equals($expectedHash, $candidateHash)) {
            $pdo->prepare('DELETE FROM `remember_tokens` WHERE `id` = :id')
                ->execute(['id' => (int) $tokenRow['id']]);
            return null;
        }

        $userId = (int) $tokenRow['user_id'];
        $userStmt = $pdo->prepare(
            'SELECT * FROM `users` WHERE `id` = :id AND `is_active` = 1 AND `deleted_at` IS NULL LIMIT 1'
        );
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch();

        if (!is_array($user)) {
            return null;
        }

        $pdo->prepare('DELETE FROM `remember_tokens` WHERE `id` = :id')
            ->execute(['id' => (int) $tokenRow['id']]);

        $newToken = self::issueRememberToken($userId);

        Session::regenerate();
        Session::set('auth_user_id', $userId);
        Session::set('auth_via', 'remember');
        Session::set('remember_selector', explode(':', $newToken)[0]);
        self::$cachedUser = $user;

        self::setCookie(self::REMEMBER_COOKIE, $newToken, 30, 'Lax');

        return $newToken;
    }

    /**
     * Check if a user is currently authenticated (restoring from remember cookie if present).
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Get the currently authenticated user's ID, or null.
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * Get the currently authenticated user record, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        $userId = Session::get('auth_user_id');

        if ($userId === null && isset($_COOKIE[self::REMEMBER_COOKIE])) {
            self::consumeRememberToken((string) $_COOKIE[self::REMEMBER_COOKIE]);
            $userId = Session::get('auth_user_id');
        }

        if ($userId === null) {
            self::$cachedUser = null;
            return null;
        }

        if (self::$cachedUser !== null && (int) self::$cachedUser['id'] === (int) $userId) {
            return self::$cachedUser;
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM `users` WHERE `id` = :id AND `is_active` = 1 AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => (int) $userId]);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            self::logout();
            return null;
        }

        self::$cachedUser = $user;
        return self::$cachedUser;
    }

    /**
     * Refresh the cached authenticated user from the database.
     */
    public static function refreshUser(): void
    {
        self::$cachedUser = null;
        self::user();
    }

    /**
     * Check if the authenticated user has one of the given roles.
     */
    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        return in_array((string) $user['role'], $roles, true);
    }

    /**
     * Check if the authenticated user is authorized for a resource action via Policy.
     */
    public static function role(): ?string
    {
        $user = self::user();
        return is_array($user) && isset($user['role']) ? (string) $user['role'] : null;
    }

    public static function can(string $resource, string $action): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        return Policy::allows((string) $user['role'], $resource, $action);
    }

    /**
     * Log the user out and invalidate the current device's remember token.
     * Note: trusted device PIN tokens on the shared browser remain active unless explicitly revoked.
     */
    public static function logout(): void
    {
        $selector = Session::get('remember_selector');

        if (is_string($selector) && $selector !== '') {
            Database::connection()->prepare('DELETE FROM `remember_tokens` WHERE `selector` = :s')
                ->execute(['s' => $selector]);
        }

        if (isset($_COOKIE[self::REMEMBER_COOKIE]) && str_contains((string) $_COOKIE[self::REMEMBER_COOKIE], ':')) {
            [$cookieSel] = explode(':', (string) $_COOKIE[self::REMEMBER_COOKIE], 2);
            Database::connection()->prepare('DELETE FROM `remember_tokens` WHERE `selector` = :s')
                ->execute(['s' => $cookieSel]);
        }

        self::clearCookie(self::REMEMBER_COOKIE);
        if (isset($_COOKIE[self::LEGACY_DEVICE_USER_COOKIE])) {
            self::clearCookie(self::LEGACY_DEVICE_USER_COOKIE);
        }

        self::$cachedUser = null;
        Session::destroy();
        Session::start();
    }

    /**
     * @param array<string, string> $pairs
     */
    private static function writeTrustedDeviceCookie(array $pairs, int $days): void
    {
        if ($pairs === []) {
            self::clearCookie(self::TRUSTED_DEVICE_COOKIE, 'Strict');
            return;
        }

        $chunks = [];
        foreach ($pairs as $sel => $val) {
            $chunks[] = $sel . ':' . $val;
        }
        $value = implode(',', $chunks);
        self::setCookie(self::TRUSTED_DEVICE_COOKIE, $value, $days, 'Strict');
    }

    private static function setCookie(string $name, string $value, int $days, string $sameSite = 'Lax'): void
    {
        $effectiveName = self::cookieName($name);
        if (PHP_SAPI === 'cli' || headers_sent()) {
            $_COOKIE[$effectiveName] = $value;
            $_COOKIE[$name] = $value;
            return;
        }

        $env = (string) Config::get('app.env', 'development');
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $secure = $env === 'production' || (bool) Config::get('session.secure', false) || $isHttps;

        setcookie($effectiveName, $value, [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
        $_COOKIE[$effectiveName] = $value;
        $_COOKIE[$name] = $value;
    }

    private static function clearCookie(string $name, string $sameSite = 'Lax'): void
    {
        $effectiveName = self::cookieName($name);
        unset($_COOKIE[$effectiveName], $_COOKIE[$name]);
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        setcookie($effectiveName, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }
}
