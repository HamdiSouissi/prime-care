<?php

declare(strict_types=1);

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
        $authApi->post('/profile/theme', [ProfileController::class, 'updateTheme'], 'api.profile.theme');
        $authApi->post('/profile/avatar', [ProfileController::class, 'updateAvatar'], 'api.profile.avatar');
    });
});
