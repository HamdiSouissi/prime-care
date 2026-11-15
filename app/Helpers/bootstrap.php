<?php

declare(strict_types=1);

/**
 * Prime Care — Application Bootstrap & PSR-4 Autoloader
 */

$rootDir = dirname(__DIR__, 2);

spl_autoload_register(static function (string $class) use ($rootDir): void {
    $prefixes = [
        'App\\' => $rootDir . '/app/',
        'Database\\Seeds\\' => $rootDir . '/database/seeds/',
        'PHPMailer\\PHPMailer\\' => $rootDir . '/public/assets/vendor/phpmailer/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

$configPath = is_file($rootDir . '/config/config.php')
    ? $rootDir . '/config/config.php'
    : $rootDir . '/config/config.example.php';

\App\Core\Config::load($configPath);
date_default_timezone_set('UTC');

require_once __DIR__ . '/functions.php';

\App\Core\Lang::load((string) \App\Core\Config::get('app.locale', 'fr'));
\App\Core\ErrorHandler::register();
