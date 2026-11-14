<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Prime Care',
        'slogan' => 'Suivi de soins à domicile',
        'publisher' => 'Prime Vision Solutions',
        'publisher_url' => 'https://primevisionsolutions.fr',
        'version' => '1.0.0-phase1',
        'url' => 'http://localhost:8000',
        'env' => 'development', // 'development' | 'production' | 'testing'
        'debug' => true,
        'timezone' => 'Europe/Paris',
        'db_timezone' => 'UTC',
        'locale' => 'fr',
    ],

    'db' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'prime_care',
        'test_name' => 'prime_care_test',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'session' => [
        'name' => 'prime_care_session',
        'lifetime' => 7200,
        'idle_timeout' => 1800, // 30 minutes
        'secure' => false, // true in production HTTPS (__Host- prefix enforced)
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    'mail' => [
        'driver' => 'log', // 'log' in dev (storage/logs/mail.log), 'smtp' in prod
        'host' => 'smtp.example.org',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_address' => 'ne-pas-repondre@primecare.test',
        'from_name' => 'Prime Care — Suivi de soins',
    ],

    'security' => [
        'login_max_attempts' => 5,
        'login_lockout_minutes' => 15,
        'remember_days' => 30,
        'password_reset_minutes' => 60,
        'password_min_length' => 10,
        'pin_min_length' => 4,
        'pin_max_length' => 6,
        'max_upload_bytes' => 5 * 1024 * 1024, // 5 MB
    ],

    'paths' => [
        'root' => dirname(__DIR__),
        'app' => dirname(__DIR__) . '/app',
        'public' => dirname(__DIR__) . '/public',
        'storage' => dirname(__DIR__) . '/storage',
        'uploads' => dirname(__DIR__) . '/storage/uploads',
        'logs' => dirname(__DIR__) . '/storage/logs',
        'cache' => dirname(__DIR__) . '/storage/cache',
        'views' => dirname(__DIR__) . '/app/Views',
        'lang' => dirname(__DIR__) . '/lang',
        'migrations' => dirname(__DIR__) . '/database/migrations',
        'seeds' => dirname(__DIR__) . '/database/seeds',
    ],
];
