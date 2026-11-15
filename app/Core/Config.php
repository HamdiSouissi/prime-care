<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Centralized configuration loader supporting dot-notation access.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    /**
     * Load configuration from file or array.
     *
     * @param string|array<string, mixed> $source
     */
    public static function load(string|array $source): void
    {
        if (is_array($source)) {
            self::$items = $source;
            self::enforceProductionSafety();
            return;
        }

        if (is_file($source)) {
            /** @var array<string, mixed> $loaded */
            $loaded = require $source;
            self::$items = $loaded;
            self::enforceProductionSafety();
        }
    }

    /**
     * Force app.debug = false whenever app.env === 'production', regardless of configuration file values.
     */
    private static function enforceProductionSafety(): void
    {
        if (isset(self::$items['app']) && is_array(self::$items['app'])) {
            if ((string) (self::$items['app']['env'] ?? '') === 'production') {
                self::$items['app']['debug'] = false;
            }
        }
    }

    /**
     * Retrieve a configuration value using dot notation.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if ($key === 'app.debug' && (string) (self::$items['app']['env'] ?? '') === 'production') {
            return false;
        }

        $segments = explode('.', $key);
        $current = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Override a configuration value at runtime (useful for tests).
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
        self::enforceProductionSafety();
    }
}
