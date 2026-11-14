<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * PHP template engine supporting layouts, named sections, partials, and CSP nonces.
 */
final class View
{
    private static ?string $cspNonce = null;

    /** @var array<string, string> */
    private static array $sections = [];

    /** @var array<int, string> */
    private static array $sectionStack = [];

    /**
     * Get or generate the per-request Content-Security-Policy nonce.
     */
    public static function cspNonce(): string
    {
        if (self::$cspNonce === null) {
            self::$cspNonce = base64_encode(random_bytes(16));
        }
        return self::$cspNonce;
    }

    /**
     * Start capturing a named template section.
     */
    public static function section(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    /**
     * Alias for section().
     */
    public static function startSection(string $name): void
    {
        self::section($name);
    }

    /**
     * Stop capturing the active template section.
     */
    public static function endSection(): void
    {
        $name = array_pop(self::$sectionStack);
        if ($name !== null) {
            self::$sections[$name] = (string) ob_get_clean();
        }
    }

    /**
     * Output a captured section by name.
     */
    public static function yieldSection(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    /**
     * Render a view inside an optional layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        self::$sections = [];
        self::$sectionStack = [];

        $viewsDir = (string) Config::get('paths.views', dirname(__DIR__) . '/Views');
        $viewFile = $viewsDir . '/' . ltrim($view, '/') . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException("Vue introuvable : {$view}", 500);
        }

        $data['cspNonce'] = self::cspNonce();

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = $viewsDir . '/' . ltrim($layout, '/') . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException("Layout introuvable : {$layout}", 500);
        }

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }

    /**
     * Render a partial template and return its HTML string.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $partial, array $data = []): string
    {
        $viewsDir = (string) Config::get('paths.views', dirname(__DIR__) . '/Views');
        $partialFile = $viewsDir . '/partials/' . ltrim($partial, '/') . '.php';

        if (!is_file($partialFile)) {
            throw new RuntimeException("Partial introuvable : {$partial}", 500);
        }

        $data['cspNonce'] = self::cspNonce();
        extract($data, EXTR_SKIP);

        ob_start();
        require $partialFile;
        return (string) ob_get_clean();
    }
}
