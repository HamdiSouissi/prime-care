<?php

declare(strict_types=1);

use App\Controllers\ApiMedicalController;
use App\Controllers\AuthController;
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

        // Phase 2 Medical JSON API endpoints
        $authApi->post('/measures', [ApiMedicalController::class, 'storeMeasure'], 'api.measures.store');
        $authApi->post('/intakes/{id:\d+}/give', [ApiMedicalController::class, 'giveIntake'], 'api.intakes.give');
        $authApi->post('/intakes/{id:\d+}/skip', [ApiMedicalController::class, 'skipIntake'], 'api.intakes.skip');
        $authApi->post('/intakes/as-needed', [ApiMedicalController::class, 'giveAsNeeded'], 'api.intakes.as_needed');
        $authApi->post('/meals', [ApiMedicalController::class, 'storeMeal'], 'api.meals.store');
        $authApi->post('/notes', [ApiMedicalController::class, 'storeNote'], 'api.notes.store');
        $authApi->post('/notes/{id:\d+}/read', [ApiMedicalController::class, 'markNoteRead'], 'api.notes.read');
        $authApi->post('/care-checks', [ApiMedicalController::class, 'storeCareCheck'], 'api.care_checks.store');
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
});
