<?php

declare(strict_types=1);

use App\Controllers\AlertController;
use App\Controllers\AttendanceController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DesignController;
use App\Controllers\DocumentController;
use App\Controllers\HealthController;
use App\Controllers\HealthzController;
use App\Controllers\HomeController;
use App\Controllers\JournalController;
use App\Controllers\LegalController;
use App\Controllers\MealController;
use App\Controllers\MediaController;
use App\Controllers\PatientController;
use App\Controllers\PlanningController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
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

    // Public healthcheck & legal pages (Phase 4 §5.3 & §6.2)
    $r->get('/healthz', [HealthzController::class, 'status'], 'healthz');
    $r->get('/legal/mentions', [LegalController::class, 'mentions'], 'legal.mentions');
    $r->get('/legal/privacy', [LegalController::class, 'privacy'], 'legal.privacy');
    $r->get('/legal/attendance-notice', [LegalController::class, 'attendanceNotice'], 'legal.attendance_notice');

    // Authenticated routes
    $r->group(['middleware' => [AuthMiddleware::class]], static function (Router $auth): void {
        $auth->get('/', [HomeController::class, 'index'], 'home');
        $auth->get('/more', [HomeController::class, 'more'], 'more');
        $auth->get('/about', [HomeController::class, 'about'], 'about');
        $auth->get('/install', [HomeController::class, 'install'], 'install');

        // Phase 3–4 Coming Soon screens
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

        // E16 — Planning d'équipe (Phase 3)
        $auth->get('/planning', [PlanningController::class, 'index'], 'planning.index');
        $auth->post('/planning/shifts', [PlanningController::class, 'storeShift'], 'planning.shifts.store');
        $auth->post('/planning/shifts/{id:\d+}/update', [PlanningController::class, 'updateShift'], 'planning.shifts.update');
        $auth->post('/planning/shifts/{id:\d+}/delete', [PlanningController::class, 'deleteShift'], 'planning.shifts.delete');
        $auth->post('/planning/duplicate', [PlanningController::class, 'duplicateWeek'], 'planning.duplicate');
        $auth->post('/planning/absences', [PlanningController::class, 'storeAbsence'], 'planning.absences.store');
        $auth->post('/planning/absences/{id:\d+}/delete', [PlanningController::class, 'deleteAbsence'], 'planning.absences.delete');
        $auth->post('/planning/templates', [PlanningController::class, 'saveTemplate'], 'planning.templates.save');

        // E05, E17 & E18 — Pointage, contrôle hebdomadaire & récapitulatif mensuel des heures (Phase 3)
        $auth->get('/attendance', [AttendanceController::class, 'index'], 'attendance.index');
        $auth->get('/attendance/hours', [AttendanceController::class, 'hours'], 'attendance.hours');
        $auth->get('/attendance/export-csv', [AttendanceController::class, 'exportCsv'], 'attendance.export_csv');
        $auth->post('/attendance/punch', [AttendanceController::class, 'punch'], 'attendance.punch');
        $auth->post('/attendance/declare', [AttendanceController::class, 'declareForgot'], 'attendance.declare');
        $auth->post('/attendance/gdpr', [AttendanceController::class, 'acceptGdpr'], 'attendance.gdpr');
        $auth->post('/attendance/{id:\d+}/validate', [AttendanceController::class, 'validate'], 'attendance.validate');
        $auth->post('/attendance/{id:\d+}/reject', [AttendanceController::class, 'reject'], 'attendance.reject');

        // §3.8 — Centre d'alertes (Phase 3)
        $auth->get('/alerts', [AlertController::class, 'index'], 'alerts.index');
        $auth->post('/alerts/{id:\d+}/seen', [AlertController::class, 'markSeen'], 'alerts.seen');
        $auth->post('/alerts/{id:\d+}/handle', [AlertController::class, 'handle'], 'alerts.handle');

        // E22 — Documents médicaux (Phase 4 §1)
        $auth->get('/documents', [DocumentController::class, 'index'], 'documents.index');
        $auth->post('/documents', [DocumentController::class, 'store'], 'documents.store');
        $auth->get('/documents/{id:\d+}', [DocumentController::class, 'show'], 'documents.show');
        $auth->get('/documents/{id:\d+}/file', [DocumentController::class, 'file'], 'documents.file');
        $auth->get('/documents/{id:\d+}/download', [DocumentController::class, 'download'], 'documents.download');
        $auth->post('/documents/{id:\d+}/delete', [DocumentController::class, 'delete'], 'documents.delete');

        // E23 — Rapports & Exports CSV (Phase 4 §2 & §3)
        $auth->get('/reports', [ReportController::class, 'index'], 'reports.index');
        $auth->get('/reports/exports', [ReportController::class, 'exports'], 'reports.exports');
        $auth->get('/reports/export/{type:[a-z_]+}', [ReportController::class, 'downloadExport'], 'reports.export_csv');

        // Profile (all roles)
        $auth->get('/profile', [ProfileController::class, 'show'], 'profile.show');
        $auth->get('/profile/export-data', [ProfileController::class, 'exportData'], 'profile.export_data');
        $auth->post('/profile/export-data', [ProfileController::class, 'exportData'], 'profile.export_data.post');
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
        $auth->post('/profile/notifications', [ProfileController::class, 'updateNotifications'], 'profile.notifications');
        $auth->post('/profile/push-test', [AlertController::class, 'sendTestNotification'], 'profile.push_test');

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

            // E24 — Réglages unifiés (admin, Phase 4 §5)
            $admin->get('/settings', [SettingsController::class, 'index'], 'settings');
            $admin->post('/settings/general', [SettingsController::class, 'updateGeneral'], 'settings.general');
            $admin->post('/settings/thresholds', [SettingsController::class, 'updateThresholds'], 'settings.thresholds');
            $admin->post('/settings/attendance', [SettingsController::class, 'updateAttendance'], 'settings.attendance');
            $admin->post('/settings/attendance/qr-regenerate', [SettingsController::class, 'regenerateAttendanceQr'], 'settings.attendance.qr_regenerate');
            $admin->get('/settings/attendance/qr-print', [SettingsController::class, 'printAttendanceQr'], 'settings.attendance.qr_print');
            $admin->post('/settings/alerts', [SettingsController::class, 'updateAlerts'], 'settings.alerts');
            $admin->post('/settings/smtp', [SettingsController::class, 'updateSmtp'], 'settings.smtp');
            $admin->post('/settings/smtp/test', [SettingsController::class, 'testSmtp'], 'settings.smtp.test');
            $admin->post('/settings/retention', [SettingsController::class, 'updateRetention'], 'settings.retention');
            $admin->get('/settings/export-patient-data', [SettingsController::class, 'exportPatientData'], 'settings.export_patient_data');
            $admin->post('/settings/export-patient-data', [SettingsController::class, 'exportPatientData'], 'settings.export_patient_data.post');
            $admin->post('/settings/care-items', [SettingsController::class, 'storeCareItem'], 'settings.care.store');
            $admin->post('/settings/care-items/{id:\d+}/toggle', [SettingsController::class, 'toggleCareItem'], 'settings.care.toggle');
            $admin->post('/settings/care-items/{id:\d+}/delete', [SettingsController::class, 'deleteCareItem'], 'settings.care.delete');
            $admin->post('/settings/favorites', [SettingsController::class, 'storeFavorite'], 'settings.favorites.store');
            $admin->post('/settings/favorites/{id:\d+}/delete', [SettingsController::class, 'deleteFavorite'], 'settings.favorites.delete');

            // E26 — Journal d'audit (admin, Phase 4 §4)
            $admin->get('/audit', [AuditController::class, 'index'], 'audit.index');

            // Design System showcase (admin only)
            $admin->get('/design', [DesignController::class, 'index'], 'design.index');
        });

        // Secure uploaded media serving
        $auth->get('/media/{folder:[a-z0-9_-]+}/{filename:[a-zA-Z0-9._-]+}', [MediaController::class, 'show'], 'media.show');
    });
});
