<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Localization dictionary loader with parameter replacement and simple pluralization.
 */
final class Lang
{
    /** @var array<string, mixed> */
    private static array $lines = [];

    private static string $loadedLocale = '';

    /**
     * Load a locale dictionary from lang/<locale>.php.
     */
    public static function load(string $locale = 'fr'): void
    {
        if (self::$loadedLocale === $locale && !empty(self::$lines)) {
            return;
        }

        $langDir = (string) Config::get('paths.lang', dirname(__DIR__, 2) . '/lang');
        $file = $langDir . '/' . $locale . '.php';
        if (is_file($file)) {
            /** @var array<string, mixed> $loaded */
            $loaded = require $file;
            self::$lines = $loaded;
            self::$loadedLocale = $locale;
        }
    }

    /**
     * Translate a key with optional replacements and count-based plural choice.
     *
     * @param array<string, string|int|float> $replace
     */
    public static function get(string $key, array $replace = [], ?int $count = null): string
    {
        if (empty(self::$lines)) {
            self::load((string) Config::get('app.locale', 'fr'));
        }

        $segments = explode('.', $key);
        $current = self::$lines;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $key;
            }
            $current = $current[$segment];
        }

        if (is_array($current) && isset($current['label']) && is_string($current['label'])) {
            $current = $current['label'];
        }

        if (!is_string($current)) {
            return $key;
        }

        $line = $current;

        if ($count !== null && str_contains($line, '|')) {
            $parts = explode('|', $line);
            if (count($parts) === 3) {
                $line = $count === 0 ? $parts[0] : ($count === 1 ? $parts[1] : $parts[2]);
            } elseif (count($parts) >= 2) {
                $line = $count <= 1 ? $parts[0] : $parts[1];
            }
            $replace['count'] = $count;
        }

        foreach ($replace as $placeholder => $val) {
            $line = str_replace(':' . $placeholder, (string) $val, $line);
        }

        return $line;
    }
}
