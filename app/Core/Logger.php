<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Daily file logger with automatic secret redaction and JSON context serialization.
 */
final class Logger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'pin',
        'pin_confirmation',
        'token',
        'validator',
        'secret',
        'password_hash',
        'pin_hash',
    ];

    /**
     * Write a log entry at the specified level.
     *
     * @param array<string, mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = (string) Config::get('paths.logs', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $date = gmdate('Y-m-d');
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $safeContext = self::scrub($context);

        $jsonContext = empty($safeContext)
            ? ''
            : ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $line = sprintf("[%s] %s: %s%s\n", $timestamp, strtoupper($level), $message, $jsonContext);
        file_put_contents($logDir . '/app-' . $date . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Recursively redact sensitive keys from context arrays.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function scrub(array $data): array
    {
        $cleaned = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $needle) {
                if (str_contains($lowerKey, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $cleaned[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $cleaned[$key] = self::scrub($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
