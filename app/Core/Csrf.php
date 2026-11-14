<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF protection manager with constant-time token verification.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Get or generate the current session's CSRF token.
     */
    public static function token(): string
    {
        $existing = Session::get(self::SESSION_KEY);
        if (is_string($existing) && strlen($existing) >= 32) {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Rotate the CSRF token (e.g. after login/logout).
     */
    public static function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Verify a candidate CSRF token in constant time.
     */
    public static function verify(?string $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /**
     * Render a hidden HTML input field containing the CSRF token.
     */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    /**
     * Render a meta tag for JavaScript fetch requests.
     */
    public static function meta(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
