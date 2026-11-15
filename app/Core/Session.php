<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Secure session manager with inactivity timeout, ID regeneration, and flash messages.
 */
final class Session
{
    private static bool $started = false;
    private static string $cliSessionId = '';

    /**
     * Start the PHP session with hardened cookie parameters and idle timeout check.
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            if (self::$cliSessionId === '') {
                self::$cliSessionId = bin2hex(random_bytes(16));
            }
            self::$started = true;
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $env = (string) Config::get('app.env', 'development');
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $secure = $env === 'production' || (bool) Config::get('session.secure', false) || $isHttps;

            $baseName = (string) Config::get('session.name', 'prime_care_session');
            $cookieName = ($env === 'production' && $secure) ? '__Host-' . $baseName : $baseName;

            session_name($cookieName);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => (string) Config::get('session.samesite', 'Lax'),
            ]);

            session_start();
        }

        self::$started = true;

        // Enforce 30-minute inactivity expiration
        $idleTimeout = (int) Config::get('session.idle_timeout', 1800);
        $now = time();
        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $idleTimeout) {
            self::destroy();
            self::start();
            self::flash('toast_info', 'Votre session a expiré après 30 minutes d’inactivité.');
        }

        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Regenerate session ID to prevent session fixation.
     */
    public static function regenerate(): void
    {
        self::start();
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        } else {
            self::$cliSessionId = bin2hex(random_bytes(16));
        }
    }

    /**
     * Return the active session cookie name (with __Host- prefix in production).
     */
    public static function cookieName(): string
    {
        $name = (string) Config::get('session.name', 'prime_care_session');
        $env = (string) Config::get('app.env', 'development');
        $secure = $env === 'production' || (bool) Config::get('session.secure', false);

        if ($secure && !str_starts_with($name, '__Host-')) {
            return '__Host-' . $name;
        }
        return $name;
    }

    /**
     * Return the active session ID.
     */
    public static function id(): string
    {
        self::start();
        if (PHP_SAPI === 'cli') {
            return self::$cliSessionId;
        }
        return (string) session_id();
    }

    /**
     * Destroy the active session completely.
     */
    public static function destroy(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    (string) session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
        } else {
            $_SESSION = [];
        }
        self::$started = false;
    }

    /**
     * Get a session value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set a session value.
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session key.
     */
    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Store or retrieve a one-time flash message.
     */
    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    /**
     * Retrieve and consume a one-time flash message (alias for flash($key)).
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $val = self::flash($key);
        return $val ?? $default;
    }

    /**
     * Check if a flash key exists without consuming it.
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }
}
