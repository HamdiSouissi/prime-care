<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DesignController;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\PatientController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

/** @var Router $router */
$router = Router::getInstance();

$router->group([
    'middleware' => [
        SecurityHeadersMiddleware::class,
        CsrfMiddleware::class,
    ],
], static function (Router $r): void {
    // Guest authentication routes
    $r->group(['middleware' => [GuestMiddleware::class]], static function (Router $guest): void {
        $guest->get('/login', [AuthController::class, 'showLogin'], 'auth.login');
        $guest->post('/login', [AuthController::class, 'login'], 'auth.login.post');
        $guest->get('/login/pin', [AuthController::class, 'showPin'], 'auth.pin');
        $guest->post('/login/pin', [AuthController::class, 'loginPin'], 'auth.pin.post');
        $guest->get('/forgot-password', [AuthController::class, 'showForgot'], 'auth.forgot');
        $guest->post('/forgot-password', [AuthController::class, 'sendForgot'], 'auth.forgot.post');
        $guest->get('/reset-password/{token:[a-f0-9]{64}}', [AuthController::class, 'showReset'], 'auth.reset');
        $guest->post('/reset-password/{token:[a-f0-9]{64}}', [AuthController::class, 'resetPassword'], 'auth.reset.post');
    });

    $r->get('/login/switch', [AuthController::class, 'switchUser'], 'auth.switch');
    $r->post('/logout', [AuthController::class, 'logout'], 'auth.logout', [AuthMiddleware::class]);

    // Authenticated routes
    $r->group(['middleware' => [AuthMiddleware::class]], static function (Router $auth): void {
        $auth->get('/', [HomeController::class, 'index'], 'home');
        $auth->get('/more', [HomeController::class, 'more'], 'more');
        $auth->get('/about', [HomeController::class, 'about'], 'about');

        // Phase 2–4 clean Coming Soon screens (zero dead links)
        $auth->get('/coming-soon/{module:[a-z0-9-]+}', [HomeController::class, 'comingSoon'], 'coming.soon');
        $auth->get('/today', static fn($req) => (new HomeController())->comingSoon($req, 'today'), 'today');
        $auth->get('/health', static fn($req) => (new HomeController())->comingSoon($req, 'health'), 'health');
        $auth->get('/journal', static fn($req) => (new HomeController())->comingSoon($req, 'journal'), 'journal');
        $auth->get('/settings', static fn($req) => (new HomeController())->comingSoon($req, 'settings'), 'settings');

        // Profile (all roles)
        $auth->get('/profile', [ProfileController::class, 'show'], 'profile.show');
        $auth->post('/profile/info', [ProfileController::class, 'updateInfo'], 'profile.info');
        $auth->post('/profile/avatar', [ProfileController::class, 'updateAvatar'], 'profile.avatar');
        $auth->post('/profile/password', [ProfileController::class, 'updatePassword'], 'profile.password');
        $auth->post('/profile/pin', [ProfileController::class, 'updatePin'], 'profile.pin');
        $auth->post('/profile/trust-device', [ProfileController::class, 'trustDevice'], 'profile.trust_device');
        $auth->post('/profile/trust-device/revoke', [ProfileController::class, 'revokeTrustedDevice'], 'profile.revoke_trusted_device');
        $auth->post('/profile/revoke-device', [ProfileController::class, 'revokeTrustedDevice'], 'profile.revoke_device_alias');
        $auth->post('/profile/theme', [ProfileController::class, 'updateTheme'], 'profile.theme');
        $auth->post('/profile/devices/revoke', [ProfileController::class, 'revokeDevices'], 'profile.revoke');
        $auth->post('/profile/revoke-sessions', [ProfileController::class, 'revokeDevices'], 'profile.revoke_sessions_alias');

        // Patient file (view: all roles, edit/contacts: admin)
        $auth->get('/patient', [PatientController::class, 'show'], 'patient.show');
        $auth->post('/patient', [PatientController::class, 'update'], 'patient.update', [RoleMiddleware::class . ':admin']);
        $auth->post('/patient/contacts', [PatientController::class, 'storeContact'], 'patient.contacts.store', [RoleMiddleware::class . ':admin']);
        $auth->post('/patient/contacts/{id:\d+}/update', [PatientController::class, 'updateContact'], 'patient.contacts.update', [RoleMiddleware::class . ':admin']);
        $auth->post('/patient/contacts/{id:\d+}/delete', [PatientController::class, 'deleteContact'], 'patient.contacts.delete', [RoleMiddleware::class . ':admin']);

        // Team management (admin only)
        $auth->group(['middleware' => [RoleMiddleware::class . ':admin']], static function (Router $admin): void {
            $admin->get('/users', [UserController::class, 'index'], 'users.index');
            $admin->get('/users/create', [UserController::class, 'create'], 'users.create');
            $admin->post('/users', [UserController::class, 'store'], 'users.store');
            $admin->get('/users/{id:\d+}', [UserController::class, 'show'], 'users.show');
            $admin->get('/users/{id:\d+}/edit', [UserController::class, 'edit'], 'users.edit');
            $admin->post('/users/{id:\d+}/update', [UserController::class, 'update'], 'users.update');
            $admin->post('/users/{id:\d+}/toggle-status', [UserController::class, 'toggleStatus'], 'users.toggle');

            // Design System showcase (admin only)
            $admin->get('/design', [DesignController::class, 'index'], 'design.index');
        });

        // Secure uploaded media serving
        $auth->get('/media/{folder:[a-z0-9_-]+}/{filename:[a-zA-Z0-9._-]+}', [MediaController::class, 'show'], 'media.show');
    });
});
