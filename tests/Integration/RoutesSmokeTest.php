<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use Throwable;

/**
 * HTTP Smoke Test (D1): Dispatches every GET route through the real Router and Middlewares
 * across 4 authentication states: unauthenticated, caregiver, viewer, and admin.
 */
final class RoutesSmokeTest
{
    private static function buildProductionRouter(): Router
    {
        $router = new Router();
        Router::setInstance($router);
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';
        return $router;
    }

    private static function dispatchGet(Router $router, string $path): Response
    {
        $req = new Request(
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $path,
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PrimeCareSmokeTest/1.0',
            ]
        );

        try {
            return $router->dispatch($req);
        } catch (Throwable $e) {
            return ErrorHandler::handleException($e, $req);
        }
    }

    public static function run(callable $assert): void
    {
        $router = self::buildProductionRouter();

        $protectedGetRoutes = [
            '/',
            '/more',
            '/about',
            '/today',
            '/health',
            '/journal',
            '/settings',
            '/coming-soon/glycemia',
            '/profile',
            '/patient',
            '/users',
            '/users/create',
            '/users/3',
            '/users/3/edit',
            '/design',
        ];

        $publicCareRoutes = [
            '/',
            '/more',
            '/about',
            '/today',
            '/health',
            '/journal',
            '/settings',
            '/coming-soon/glycemia',
            '/profile',
            '/patient',
            '/api/health',
        ];

        $adminOnlyRoutes = [
            '/users',
            '/users/create',
            '/users/3',
            '/users/3/edit',
            '/design',
        ];

        // ---------------------------------------------------------------------
        // 1. Unauthenticated visitor (no session, no trusted device cookie)
        // ---------------------------------------------------------------------
        Auth::logout();
        unset($_COOKIE[Auth::TRUSTED_DEVICE_COOKIE], $_COOKIE[Auth::REMEMBER_COOKIE], $_COOKIE[Auth::LEGACY_DEVICE_USER_COOKIE]);

        $allUnauthRedirectToLogin = true;
        $zero500Unauth = true;

        foreach ($protectedGetRoutes as $uri) {
            $resp = self::dispatchGet($router, $uri);
            $status = $resp->status();
            $loc = $resp->headers()['Location'] ?? '';
            if ($status >= 500) {
                $zero500Unauth = false;
            }
            if ($status !== 302 || $loc !== '/login') {
                $allUnauthRedirectToLogin = false;
            }
        }

        foreach (['/login', '/login/pin', '/forgot-password', '/api/health'] as $guestUri) {
            $resp = self::dispatchGet($router, $guestUri);
            if ($resp->status() >= 500) {
                $zero500Unauth = false;
            }
        }

        $assert($zero500Unauth, 'D1 Smoke (sans session): 0 réponse 500 sur toutes les routes GET');
        $assert($allUnauthRedirectToLogin, 'D1 Smoke (sans session): toutes les routes protégées redirigent en 302 vers /login');

        // Verify unauthenticated redirect to /login/pin when a valid trusted device token exists
        $fatouCookie = Auth::issueTrustedDevice(3, 'iPhone Domicile');
        $_COOKIE[Auth::TRUSTED_DEVICE_COOKIE] = $fatouCookie;
        $respWithTrusted = self::dispatchGet($router, '/');
        $assert(
            $respWithTrusted->status() === 302 && ($respWithTrusted->headers()['Location'] ?? '') === '/login/pin',
            'D1 Smoke (sans session + appareil de confiance): GET / redirige en 302 vers /login/pin'
        );
        Auth::revokeAllDevicesForUser(3, false);
        unset($_COOKIE[Auth::TRUSTED_DEVICE_COOKIE]);

        // ---------------------------------------------------------------------
        // 2. Authenticated as Caregiver (Fatou Diallo)
        // ---------------------------------------------------------------------
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $caregiver200Ok = true;
        $caregiver403Ok = true;
        $zero500Caregiver = true;

        foreach ($publicCareRoutes as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Caregiver = false;
            }
            if ($resp->status() !== 200) {
                $caregiver200Ok = false;
            }
        }

        foreach ($adminOnlyRoutes as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Caregiver = false;
            }
            if ($resp->status() !== 403) {
                $caregiver403Ok = false;
            }
        }

        $assert($zero500Caregiver, 'D1 Smoke (caregiver): 0 réponse 500 sur toutes les routes GET');
        $assert($caregiver200Ok, 'D1 Smoke (caregiver): 200 sur toutes les routes autorisées (/, /patient, /profile, /more, /about...)');
        $assert($caregiver403Ok, 'D1 Smoke (caregiver): 403 sur /users, /users/create, /users/3, /users/3/edit et /design');

        $homeHtml = self::dispatchGet($router, '/')->body();
        $assert(
            str_contains($homeHtml, 'name="theme-color" content="#0A1633"')
            && str_contains($homeHtml, 'name="apple-mobile-web-app-status-bar-style" content="black-translucent"')
            && str_contains($homeHtml, '<header class="pc-home-hero"')
            && str_contains($homeHtml, 'Bonjour Fatou')
            && str_contains($homeHtml, 'aria-current="page"'),
            'D2 Design B (accueil & tabbar): en-tête pleine largeur #0A1633, black-translucent, Bonjour Fatou et onglet actif aria-current="page"'
        );

        // ---------------------------------------------------------------------
        // 3. Authenticated as Viewer (Dr Laurent Petit — Lecture!2026)
        // ---------------------------------------------------------------------
        Auth::logout();
        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $viewer200Ok = true;
        $viewer403Ok = true;
        $zero500Viewer = true;

        foreach ($publicCareRoutes as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Viewer = false;
            }
            if ($resp->status() !== 200) {
                $viewer200Ok = false;
            }
        }

        foreach ($adminOnlyRoutes as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Viewer = false;
            }
            if ($resp->status() !== 403) {
                $viewer403Ok = false;
            }
        }

        $assert($zero500Viewer, 'D1 Smoke (viewer): 0 réponse 500 sur toutes les routes GET');
        $assert($viewer200Ok, 'D1 Smoke (viewer): 200 sur toutes les routes de consultation');
        $assert($viewer403Ok, 'D1 Smoke (viewer): 403 sur /users, /users/create, /users/3, /users/3/edit et /design');

        // ---------------------------------------------------------------------
        // 4. Authenticated as Admin (Karim Benali — Admin!2026)
        // ---------------------------------------------------------------------
        Auth::logout();
        Auth::attempt('admin@primecare.test', 'Admin!2026');
        $admin200All = true;
        $zero500Admin = true;

        foreach (array_merge($publicCareRoutes, $adminOnlyRoutes) as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Admin = false;
            }
            if ($resp->status() !== 200) {
                $admin200All = false;
            }
        }

        $assert($zero500Admin, 'D1 Smoke (admin): 0 réponse 500 sur toutes les routes GET');
        $assert($admin200All, 'D1 Smoke (admin): 200 partout sur les 16 routes authentifiées (dont /users et /design)');

        Auth::logout();
        Session::destroy();
    }
}
