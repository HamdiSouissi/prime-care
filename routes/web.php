<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DesignController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\JournalController;
use App\Controllers\MealController;
use App\Controllers\MediaController;
use App\Controllers\PatientController;
use App\Controllers\ProfileController;
use App\Controllers\SettingsController;
use App\Controllers\TodayController;
use App\Controllers\TreatmentController;
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
        $auth->get('/install', [HomeController::class, 'install'], 'install');

        // Phase 3–4 Coming Soon screens (Phase 2 modules redirect to live routes)
        $auth->get('/coming-soon/{module:[a-z0-9-]+}', [HomeController::class, 'comingSoon'], 'coming.soon');

        // E04 — Aujourd'hui
        $auth->get('/today', [TodayController::class, 'index'], 'today');

        // E06 & E07 — Santé & Constantes
        $auth->get('/health', [HealthController::class, 'index'], 'health');
        $auth->get('/health/{code:[a-z0-9_]+}', [HealthController::class, 'show'], 'health.show');
        $auth->post('/health/measures', [HealthController::class, 'store'], 'health.measures.store');
        $auth->post('/health/measures/{id:\d+}/update', [HealthController::class, 'update'], 'health.measures.update');
        $auth->post('/health/measures/{id:\d+}/delete', [HealthController::class, 'delete'], 'health.measures.delete', [RoleMiddleware::class . ':admin']);

        // E08 & E09 — Traitements, Prises & Historique d'observance
        $auth->get('/treatments', [TreatmentController::class, 'index'], 'treatments.index');
        $auth->get('/treatments/history', [TreatmentController::class, 'history'], 'treatments.history');
        $auth->post('/treatments/intakes/{id:\d+}/give', [TreatmentController::class, 'give'], 'treatments.intakes.give');
        $auth->post('/treatments/intakes/{id:\d+}/skip', [TreatmentController::class, 'skip'], 'treatments.intakes.skip');
        $auth->post('/treatments/as-needed', [TreatmentController::class, 'giveAsNeeded'], 'treatments.as_needed');

        // E10 & E11 — Repas & Hydratation
        $auth->get('/meals', [MealController::class, 'index'], 'meals.index');
        $auth->get('/meals/{id:\d+}', [MealController::class, 'show'], 'meals.show');
        $auth->post('/meals', [MealController::class, 'store'], 'meals.store');
        $auth->post('/meals/{id:\d+}/update', [MealController::class, 'update'], 'meals.update');
        $auth->post('/meals/{id:\d+}/delete', [MealController::class, 'delete'], 'meals.delete', [RoleMiddleware::class . ':admin']);

        // E12 & E13 — Journal de bord unifié, Notes, Transmissions & Soins rapides
        $auth->get('/journal', [JournalController::class, 'index'], 'journal');
        $auth->post('/journal/notes', [JournalController::class, 'storeNote'], 'journal.notes.store');
        $auth->post('/journal/notes/{id:\d+}/read', [JournalController::class, 'markRead'], 'journal.notes.read');
        $auth->post('/journal/care-checks', [JournalController::class, 'storeCareCheck'], 'journal.care.store');

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

        // Administration (admin only)
        $auth->group(['middleware' => [RoleMiddleware::class . ':admin']], static function (Router $admin): void {
            // Team management
            $admin->get('/users', [UserController::class, 'index'], 'users.index');
            $admin->get('/users/create', [UserController::class, 'create'], 'users.create');
            $admin->post('/users', [UserController::class, 'store'], 'users.store');
            $admin->get('/users/{id:\d+}', [UserController::class, 'show'], 'users.show');
            $admin->get('/users/{id:\d+}/edit', [UserController::class, 'edit'], 'users.edit');
            $admin->post('/users/{id:\d+}/update', [UserController::class, 'update'], 'users.update');
            $admin->post('/users/{id:\d+}/toggle-status', [UserController::class, 'toggleStatus'], 'users.toggle');

            // E20 — Plan de traitement (admin)
            $admin->get('/admin/treatments', [TreatmentController::class, 'adminTreatments'], 'admin.treatments');
            $admin->post('/admin/medications', [TreatmentController::class, 'storeMedication'], 'admin.medications.store');
            $admin->post('/admin/prescriptions', [TreatmentController::class, 'storePrescription'], 'admin.prescriptions.store');
            $admin->post('/admin/prescriptions/{id:\d+}/update', [TreatmentController::class, 'updatePrescription'], 'admin.prescriptions.update');

            // E21 — Mesures & Seuils (admin)
            $admin->get('/admin/measures', [TreatmentController::class, 'adminMeasures'], 'admin.measures');
            $admin->post('/admin/measures/types', [TreatmentController::class, 'storeMeasureType'], 'admin.measures.types.store');
            $admin->post('/admin/measures/types/{id:\d+}/update', [TreatmentController::class, 'updateMeasureType'], 'admin.measures.types.update');
            $admin->post('/admin/measures/schedules', [TreatmentController::class, 'storeMeasureSchedule'], 'admin.measures.schedules.store');

            // Réglages médicaux (admin)
            $admin->get('/settings', [SettingsController::class, 'index'], 'settings');
            $admin->post('/settings/general', [SettingsController::class, 'updateGeneral'], 'settings.general');
            $admin->post('/settings/care-items', [SettingsController::class, 'storeCareItem'], 'settings.care.store');
            $admin->post('/settings/care-items/{id:\d+}/delete', [SettingsController::class, 'deleteCareItem'], 'settings.care.delete');
            $admin->post('/settings/favorites', [SettingsController::class, 'storeFavorite'], 'settings.favorites.store');
            $admin->post('/settings/favorites/{id:\d+}/delete', [SettingsController::class, 'deleteFavorite'], 'settings.favorites.delete');

            // Design System showcase (admin only)
            $admin->get('/design', [DesignController::class, 'index'], 'design.index');
        });

        // Secure uploaded media serving
        $auth->get('/media/{folder:[a-z0-9_-]+}/{filename:[a-zA-Z0-9._-]+}', [MediaController::class, 'show'], 'media.show');
    });
});
