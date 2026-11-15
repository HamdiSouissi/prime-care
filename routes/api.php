<?php

declare(strict_types=1);

use App\Controllers\AlertController;
use App\Controllers\ApiMedicalController;
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\PlanningController;
use App\Controllers\ProfileController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

/** @var Router $router */
$router = Router::getInstance();

$router->group([
    'prefix' => 'api',
    'middleware' => [
        SecurityHeadersMiddleware::class,
        CsrfMiddleware::class,
    ],
], static function (Router $api): void {
    $api->get('/health', static function (Request $req): Response {
        return Response::jsonSuccess([
            'status' => 'ok',
            'app' => 'Prime Care',
            'time_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }, 'api.health');

    $api->post('/auth/pin', [AuthController::class, 'loginPin'], 'api.auth.pin');

    $api->group(['middleware' => [AuthMiddleware::class]], static function (Router $authApi): void {
        // Phase 1 Profile endpoints
        $authApi->post('/profile/theme', [ProfileController::class, 'updateTheme'], 'api.profile.theme');
        $authApi->post('/profile/avatar', [ProfileController::class, 'updateAvatar'], 'api.profile.avatar');

        // Phase 2 Medical JSON API endpoints (with Phase 3 client_uuid idempotency)
        $authApi->post('/measures', [ApiMedicalController::class, 'storeMeasure'], 'api.measures.store');
        $authApi->post('/intakes/{id:\d+}/give', [ApiMedicalController::class, 'giveIntake'], 'api.intakes.give');
        $authApi->post('/intakes/{id:\d+}/skip', [ApiMedicalController::class, 'skipIntake'], 'api.intakes.skip');
        $authApi->post('/intakes/as-needed', [ApiMedicalController::class, 'giveAsNeeded'], 'api.intakes.as_needed');
        $authApi->post('/meals', [ApiMedicalController::class, 'storeMeal'], 'api.meals.store');
        $authApi->post('/notes', [ApiMedicalController::class, 'storeNote'], 'api.notes.store');
        $authApi->post('/notes/{id:\d+}/read', [ApiMedicalController::class, 'markNoteRead'], 'api.notes.read');
        $authApi->post('/care-checks', [ApiMedicalController::class, 'storeCareCheck'], 'api.care_checks.store');

        // Phase 3 Attendance, Planning, Alerts & Web Push JSON API endpoints
        $authApi->post('/attendance/punch', [AttendanceController::class, 'punch'], 'api.attendance.punch');
        $authApi->post('/attendance/declare', [AttendanceController::class, 'declareForgot'], 'api.attendance.declare');
        $authApi->post('/attendance/gdpr-accept', [AttendanceController::class, 'acceptGdpr'], 'api.attendance.gdpr_accept');
        $authApi->post('/attendance/gdpr', [AttendanceController::class, 'acceptGdpr'], 'api.attendance.gdpr');
        $authApi->post('/shifts', [PlanningController::class, 'storeShift'], 'api.shifts.store');
        $authApi->post('/shifts/{id:\d+}/update', [PlanningController::class, 'updateShift'], 'api.shifts.update');
        $authApi->post('/shifts/duplicate', [PlanningController::class, 'duplicateWeek'], 'api.shifts.duplicate');
        $authApi->post('/alerts/{id:\d+}/seen', [AlertController::class, 'markSeen'], 'api.alerts.seen');
        $authApi->post('/alerts/{id:\d+}/handle', [AlertController::class, 'handle'], 'api.alerts.handle');
        $authApi->post('/push/subscribe', [AlertController::class, 'subscribePush'], 'api.push.subscribe');
        $authApi->post('/push/unsubscribe', [AlertController::class, 'unsubscribePush'], 'api.push.unsubscribe');
        $authApi->post('/push/test', [AlertController::class, 'sendTestNotification'], 'api.push.test');
    });
});

// Authenticated GET JSON endpoints (no CSRF required on GET)
$router->group([
    'prefix' => 'api',
    'middleware' => [
        SecurityHeadersMiddleware::class,
        AuthMiddleware::class,
    ],
], static function (Router $getApi): void {
    $getApi->get('/timeline', [ApiMedicalController::class, 'timeline'], 'api.timeline');
    $getApi->get('/alerts', [AlertController::class, 'apiList'], 'api.alerts.index');
    $getApi->get('/offline/bootstrap', [ApiMedicalController::class, 'offlineBootstrap'], 'api.offline.bootstrap');
    $getApi->get('/push/vapid-key', [AlertController::class, 'vapidPublicKey'], 'api.push.vapid_key');
});
