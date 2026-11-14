<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\Policy;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Authentication manager supporting password login, PIN login, rotating selector/validator
 * remember-me tokens, role checks, and Policy authorization.
 */
final class Auth
{
    public const REMEMBER_COOKIE = 'prime_care_remember';
    public const DEVICE_USER_COOKIE = 'prime_care_device_uid';

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
            return false;
        }

        if (!self::verifySecret($password, (string) ($user['password_hash'] ?? ''))) {
            return false;
        }

        self::loginUser($user, $remember);
        return true;
    }

    /**
     * Attempt PIN authentication for a specific user ID.
     */
    public static function attemptPin(int $userId, string $pin, bool $remember = true): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM `users` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!is_array($user) || (int) ($user['is_active'] ?? 0) !== 1) {
            return false;
        }

        if (!self::verifySecret($pin, (string) ($user['pin_hash'] ?? ''))) {
            return false;
        }

        self::loginUser($user, $remember);
        return true;
    }

    /**
     * Log in a user array, regenerate session & CSRF, and optionally issue a remember token.
     *
     * @param array<string, mixed> $user
     */
    public static function loginUser(array $user, bool $remember = false): void
    {
        Session::regenerate();
        Csrf::rotate();

        $userId = (int) $user['id'];
        Session::set('auth_user_id', $userId);
        Session::set('pin_device_user_id', $userId);
        self::$cachedUser = $user;

        Database::connection()->prepare(
            'UPDATE `users` SET `last_login_at` = :now WHERE `id` = :id'
        )->execute([
            'now' => gmdate('Y-m-d H:i:s'),
            'id' => $userId,
        ]);

        if ($remember) {
            $token = self::issueRememberToken($userId);
            Session::set('remember_selector', explode(':', $token)[0]);
            self::setCookie(self::REMEMBER_COOKIE, $token, 30);
            self::setCookie(self::DEVICE_USER_COOKIE, (string) $userId, 30);
        }
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
            // Potential token theft: revoke this selector immediately
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

        // Delete used token and rotate with a fresh one
        $pdo->prepare('DELETE FROM `remember_tokens` WHERE `id` = :id')
            ->execute(['id' => (int) $tokenRow['id']]);

        $newToken = self::issueRememberToken($userId);

        Session::regenerate();
        Session::set('auth_user_id', $userId);
        Session::set('pin_device_user_id', $userId);
        Session::set('remember_selector', explode(':', $newToken)[0]);
        self::$cachedUser = $user;

        self::setCookie(self::REMEMBER_COOKIE, $newToken, 30);
        self::setCookie(self::DEVICE_USER_COOKIE, (string) $userId, 30);

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
    public static function can(string $resource, string $action): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        return Policy::allows((string) $user['role'], $resource, $action);
    }

    /**
     * Get the remembered device user (for the PIN pad screen) if any.
     *
     * @return array<string, mixed>|null
     */
    public static function deviceUser(): ?array
    {
        $deviceUid = Session::get('pin_device_user_id') ?? ($_COOKIE[self::DEVICE_USER_COOKIE] ?? null);
        if ($deviceUid === null || !is_numeric($deviceUid)) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM `users`
             WHERE `id` = :id AND `is_active` = 1 AND `deleted_at` IS NULL AND `pin_hash` IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => (int) $deviceUid]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * Clear the remembered device user ("Changer d'utilisateur").
     */
    public static function forgetDeviceUser(): void
    {
        Session::forget('pin_device_user_id');
        self::clearCookie(self::DEVICE_USER_COOKIE);
        self::clearCookie(self::REMEMBER_COOKIE);
    }

    /**
     * Log the user out and invalidate the current device's remember token.
     */
    public static function logout(bool $keepDeviceForPin = true): void
    {
        $deviceUid = Session::get('pin_device_user_id') ?? ($_COOKIE[self::DEVICE_USER_COOKIE] ?? null);
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
        self::$cachedUser = null;
        Session::destroy();
        Session::start();

        if ($keepDeviceForPin && $deviceUid !== null) {
            Session::set('pin_device_user_id', (int) $deviceUid);
        } else {
            self::clearCookie(self::DEVICE_USER_COOKIE);
        }
    }

    /**
     * Revoke all remember tokens for a user except the current session's selector.
     */
    public static function revokeOtherDevices(int $userId): int
    {
        $currentSelector = (string) Session::get('remember_selector', '');
        if ($currentSelector !== '') {
            $stmt = Database::connection()->prepare(
                'DELETE FROM `remember_tokens` WHERE `user_id` = :uid AND `selector` != :sel'
            );
            $stmt->execute(['uid' => $userId, 'sel' => $currentSelector]);
            return $stmt->rowCount();
        }

        $stmt = Database::connection()->prepare('DELETE FROM `remember_tokens` WHERE `user_id` = :uid');
        $stmt->execute(['uid' => $userId]);
        return $stmt->rowCount();
    }

    private static function setCookie(string $name, string $value, int $days): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            $_COOKIE[$name] = $value;
            return;
        }

        $env = (string) Config::get('app.env', 'development');
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $secure = $env === 'production' || (bool) Config::get('session.secure', false) || $isHttps;

        setcookie($name, $value, [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }

    private static function clearCookie(string $name): void
    {
        unset($_COOKIE[$name]);
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        setcookie($name, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
