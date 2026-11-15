<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Models\Setting;
use Throwable;

/**
 * HTTP Smoke Test (D1 + Phase 2 + Phase 3): Dispatches every GET route and JSON API endpoint
 * through the real Router and Middlewares across 4 authentication states:
 * unauthenticated, caregiver, viewer, and admin.
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
        [$uriOnly, $queryStr] = array_pad(explode('?', $path, 2), 2, '');
        $getParams = [];
        if ($queryStr !== '') {
            parse_str($queryStr, $getParams);
        }

        $req = new Request(
            $getParams,
            [],
            [],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $path,
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PrimeCareSmokeTest/1.3',
            ]
        );

        try {
            return $router->dispatch($req);
        } catch (Throwable $e) {
            return ErrorHandler::handleException($e, $req);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function dispatchJsonPost(Router $router, string $path, array $payload): Response
    {
        $csrf = Csrf::token();
        $req = new Request(
            [],
            array_merge($payload, ['_csrf' => $csrf]),
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => $path,
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $csrf,
                'HTTP_USER_AGENT' => 'PrimeCareSmokeTest/1.3',
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
            '/install',
            '/today',
            '/health',
            '/health/glycemia',
            '/health/blood_pressure',
            '/treatments',
            '/treatments/history',
            '/meals',
            '/meals/1',
            '/journal',
            '/planning',
            '/planning?view=month',
            '/attendance',
            '/attendance/hours',
            '/alerts',
            '/documents',
            '/documents/1',
            '/reports',
            '/reports/exports',
            '/audit',
            '/profile',
            '/patient',
            '/users',
            '/users/create',
            '/users/3',
            '/users/3/edit',
            '/admin/treatments',
            '/admin/measures',
            '/settings',
            '/settings/attendance/qr-print',
            '/design',
        ];

        $publicCareRoutes = [
            '/',
            '/more',
            '/about',
            '/install',
            '/today',
            '/health',
            '/health/glycemia',
            '/health/blood_pressure',
            '/treatments',
            '/treatments/history',
            '/meals',
            '/meals/1',
            '/journal',
            '/planning',
            '/planning?view=month',
            '/alerts',
            '/documents',
            '/documents/1',
            '/profile',
            '/patient',
            '/api/health',
            '/api/timeline',
            '/api/alerts',
        ];

        $adminOnlyRoutes = [
            '/users',
            '/users/create',
            '/users/3',
            '/users/3/edit',
            '/admin/treatments',
            '/admin/measures',
            '/audit',
            '/settings',
            '/settings/attendance/qr-print',
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

        foreach (['/login', '/login/pin', '/forgot-password', '/api/health', '/healthz', '/legal/mentions', '/legal/privacy', '/legal/attendance-notice'] as $guestUri) {
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

        foreach (array_merge($publicCareRoutes, ['/attendance', '/attendance/hours']) as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Caregiver = false;
            }
            if ($resp->status() !== 200) {
                $caregiver200Ok = false;
            }
        }

        foreach (array_merge($adminOnlyRoutes, ['/attendance/export-csv']) as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Caregiver = false;
            }
            if ($resp->status() !== 403) {
                $caregiver403Ok = false;
            }
        }

        $assert($zero500Caregiver, 'D1 Smoke (caregiver): 0 réponse 500 sur toutes les routes GET (Phases 1, 2 & 3)');
        $assert($caregiver200Ok, 'D1 Smoke (caregiver): 200 sur toutes les routes autorisées (/, /today, /planning, /attendance, /alerts...)');
        $assert($caregiver403Ok, 'D1 Smoke (caregiver): 403 sur /users, /admin/treatments, /admin/measures, /settings, /attendance/export-csv et /design');

        // Verify Caregiver JSON API endpoints + CSRF + Offline client_uuid idempotency
        $noCsrfReq = new Request(
            [],
            ['type_code' => 'glycemia', 'value' => '1.14', 'context' => 'fasting'],
            [],
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/measures',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_USER_AGENT' => 'PrimeCareSmokeTest/1.3',
            ]
        );
        try {
            $noCsrfResp = $router->dispatch($noCsrfReq);
        } catch (Throwable $e) {
            $noCsrfResp = ErrorHandler::handleException($e, $noCsrfReq);
        }
        $noCsrfJson = json_decode($noCsrfResp->body(), true) ?: [];
        $assert(
            $noCsrfResp->status() === 419
            && ($noCsrfJson['ok'] ?? null) === false
            && isset($noCsrfJson['error']['code'], $noCsrfJson['error']['message']),
            'E2 API JSON: POST /api/measures sans en-tête CSRF retourne 419 au format JSON {ok: false, error: {code, message}}'
        );

        // Offline client_uuid idempotency test on /api/measures, /api/notes, /api/meals, /api/care-checks
        $measureUuid = '11111111-2222-4333-8444-555555555501';
        $apiMeasureResp = self::dispatchJsonPost($router, '/api/measures', [
            'type_code' => 'glycemia',
            'value' => '1.14',
            'context' => 'fasting',
            'comment' => 'Test API glycémie',
            'client_uuid' => $measureUuid,
        ]);
        $assert($apiMeasureResp->status() === 201, 'API JSON (caregiver): POST /api/measures retourne 201 Created');

        // Second call with the exact same client_uuid must return 200 OK and NOT insert a second row
        $apiMeasureReplay = self::dispatchJsonPost($router, '/api/measures', [
            'type_code' => 'glycemia',
            'value' => '1.14',
            'context' => 'fasting',
            'comment' => 'Test API glycémie',
            'client_uuid' => $measureUuid,
        ]);
        $pdo = Database::connection();
        $uuidCountStmt = $pdo->prepare('SELECT COUNT(*) FROM measures WHERE client_uuid = :u');
        $uuidCountStmt->execute(['u' => $measureUuid]);
        $measureUuidCount = (int) $uuidCountStmt->fetchColumn();
        $assert(
            $apiMeasureReplay->status() === 200 && $measureUuidCount === 1,
            'Phase 3 Mode hors ligne: rejouer le même client_uuid sur POST /api/measures retourne 200 et ne crée qu’un seul enregistrement'
        );

        $noteUuid = '11111111-2222-4333-8444-555555555502';
        $apiNoteResp = self::dispatchJsonPost($router, '/api/notes', [
            'category' => 'general',
            'content' => 'Note de test smoke API',
            'is_important' => 0,
            'is_handover' => 1,
            'client_uuid' => $noteUuid,
        ]);
        $apiNoteReplay = self::dispatchJsonPost($router, '/api/notes', [
            'category' => 'general',
            'content' => 'Note de test smoke API',
            'is_important' => 0,
            'is_handover' => 1,
            'client_uuid' => $noteUuid,
        ]);
        $assert(
            $apiNoteResp->status() === 201 && $apiNoteReplay->status() === 200,
            'API JSON (caregiver): POST /api/notes retourne 201 Created puis 200 sur rejeu idempotent client_uuid'
        );

        $careUuid = '11111111-2222-4333-8444-555555555503';
        $apiCareResp = self::dispatchJsonPost($router, '/api/care-checks', [
            'care_item_id' => 1,
            'comment' => 'Soin rapide validé',
            'client_uuid' => $careUuid,
        ]);
        $apiCareReplay = self::dispatchJsonPost($router, '/api/care-checks', [
            'care_item_id' => 1,
            'comment' => 'Soin rapide validé',
            'client_uuid' => $careUuid,
        ]);
        $assert(
            $apiCareResp->status() === 201 && $apiCareReplay->status() === 200,
            'API JSON (caregiver): POST /api/care-checks retourne 201 Created puis 200 sur rejeu idempotent client_uuid'
        );

        // Test HTTP 403 on POST /api/measures when require_clocked_in=1 and caregiver is clocked out
        self::dispatchJsonPost($router, '/api/attendance/punch', [
            'type' => 'out',
            'method' => 'slide',
            'latitude' => 48.79036,
            'longitude' => 2.45557,
        ]);
        \App\Services\AttendanceService::updateSettings(['require_clocked_in' => true], 1);
        $blockedWhenClockedOut = self::dispatchJsonPost($router, '/api/measures', [
            'type_code' => 'glycemia',
            'value' => '1.10',
            'context' => 'fasting',
        ]);
        $blockedJson = json_decode($blockedWhenClockedOut->body(), true) ?: [];
        \App\Services\AttendanceService::updateSettings(['require_clocked_in' => false], 1);
        $assert(
            $blockedWhenClockedOut->status() === 403
            && ($blockedJson['error']['message'] ?? '') === 'Pointez votre arrivée pour saisir',
            'Phase 3 Pointage: POST /api/measures retourne HTTP 403 « Pointez votre arrivée pour saisir » quand require_clocked_in est actif et l’aide-soignante est hors poste'
        );

        $homeHtml = self::dispatchGet($router, '/')->body();
        $assert(
            str_contains($homeHtml, 'name="theme-color" content="#0A1633"')
            && str_contains($homeHtml, 'name="apple-mobile-web-app-status-bar-style" content="black-translucent"')
            && str_contains($homeHtml, '<header class="pc-home-hero"')
            && str_contains($homeHtml, 'Bonjour Fatou')
            && str_contains($homeHtml, 'data-slide-punch')
            && str_contains($homeHtml, 'aria-current="page"'),
            'D2 Design B + E05 (accueil & tabbar): en-tête pleine largeur #0A1633, bouton glissant de pointage, Bonjour Fatou et onglet actif aria-current="page"'
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

        foreach (array_merge($adminOnlyRoutes, ['/attendance', '/attendance/hours', '/attendance/export-csv']) as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Viewer = false;
            }
            if ($resp->status() !== 403) {
                $viewer403Ok = false;
            }
        }

        // Viewer must be blocked (403) from writing via JSON API
        $viewerWriteMeasure = self::dispatchJsonPost($router, '/api/measures', [
            'type_code' => 'glycemia',
            'value' => '1.10',
        ]);
        $assert($viewerWriteMeasure->status() === 403, 'API JSON (viewer): POST /api/measures est bloqué en 403');

        $assert($zero500Viewer, 'D1 Smoke (viewer): 0 réponse 500 sur toutes les routes GET');
        $assert($viewer200Ok, 'D1 Smoke (viewer): 200 sur toutes les routes de consultation (dont /planning et /alerts)');
        $assert($viewer403Ok, 'D1 Smoke (viewer): 403 sur les routes d’administration et de pointage');

        // ---------------------------------------------------------------------
        // 4. Authenticated as Admin (Karim Benali — Admin!2026)
        // ---------------------------------------------------------------------
        Auth::logout();
        Auth::attempt('admin@primecare.test', 'Admin!2026');
        $admin200All = true;
        $zero500Admin = true;
        $zeroInlineStyle = true;

        foreach (array_merge($publicCareRoutes, $adminOnlyRoutes, ['/attendance', '/attendance/hours', '/attendance/export-csv']) as $uri) {
            $resp = self::dispatchGet($router, $uri);
            if ($resp->status() >= 500) {
                $zero500Admin = false;
            }
            if ($resp->status() !== 200) {
                $admin200All = false;
            }
            if (!str_starts_with($uri, '/api/') && !str_starts_with($uri, '/attendance/export-csv')) {
                if (preg_match('/\sstyle\s*=/i', $resp->body())) {
                    $zeroInlineStyle = false;
                }
            }
        }

        $assert($zero500Admin, 'D1 Smoke (admin): 0 réponse 500 sur toutes les routes GET');
        $assert($admin200All, 'D1 Smoke (admin): 200 partout sur les 34 routes authentifiées (dont /planning, /attendance, /attendance/hours, /alerts, /settings/attendance/qr-print)');
        $assert($zeroInlineStyle, 'C5 CSP stricte: 0 attribut style= en ligne dans le HTML généré sur l’ensemble des vues Phases 1, 2 & 3');

        Auth::logout();
        Session::destroy();
    }
}
