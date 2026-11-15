<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Patient;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\ExportService;
use App\Services\MailService;
use App\Services\QrCodeService;
use PDO;

/**
 * Unified Admin Settings Controller (E24, Phase 4 §5.1 & §5.2):
 * Grouped iOS settings list covering:
 * - Patient
 * - Mesures et seuils (hypo/hyper & par moment de la journée)
 * - Traitements
 * - Soins rapides (ajout, ordre, activation) & Aliments favoris
 * - Pointage (GPS, rayon, tolérance retard, délai alerte non-pointage, majorations nuit/dimanche/férié, QR code)
 * - Alertes (délais de relance, heures calmes par défaut)
 * - E-mail (SMTP & bouton « Envoyer un e-mail de test »)
 * - Application (nom, fuseau horaire, format de date, fenêtre de prise, unité glycémie)
 * - Conservation des données & RGPD (durées configurables + « Exporter toutes les données du patient »)
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('settings', 'view');

        $pdo = Database::connection();
        $patientModel = new Patient();
        $patient = $patientModel->current(1) ?? [];

        $ciStmt = $pdo->query(
            'SELECT * FROM `care_items` WHERE `patient_id` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `id` ASC'
        );
        $careItems = $ciStmt !== false ? $ciStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $favStmt = $pdo->query(
            'SELECT * FROM `food_favorites` WHERE `patient_id` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `id` ASC'
        );
        $favorites = $favStmt !== false ? $favStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $winCfg = Setting::getValue('intake_window_min', ['minutes' => 30], 1);
        $intakeWindowMin = is_array($winCfg) ? (int) ($winCfg['minutes'] ?? 30) : 30;

        $gUnitCfg = Setting::getValue('glycemia_unit', ['unit' => 'g/L'], 1);
        $glycemiaUnit = is_array($gUnitCfg) ? (string) ($gUnitCfg['unit'] ?? 'g/L') : 'g/L';

        $attendanceSettings = AttendanceService::getSettings(1);
        $attendanceExtra = Setting::getValue('attendance_rules_extra', [
            'late_tolerance_min' => 15,
            'missing_alert_delay_min' => 15,
            'night_surcharge_pct' => 25,
            'sunday_surcharge_pct' => 50,
            'holiday_surcharge_pct' => 100,
        ], 1);
        if (is_array($attendanceExtra)) {
            $attendanceSettings = array_merge($attendanceExtra, $attendanceSettings);
        }

        $activeQrToken = AttendanceService::getActiveQrToken(1);
        $homeQrSvg = QrCodeService::renderSvg((string) $activeQrToken['qr_payload'], 200);

        $alertDefaults = Setting::getValue('alert_defaults', [
            'escalation_delay_min' => 30,
            'repeat_interval_min' => 15,
            'default_quiet_start' => '22:00',
            'default_quiet_end' => '07:00',
        ], 1);
        if (!is_array($alertDefaults)) {
            $alertDefaults = [];
        }

        $smtpSettings = Setting::getValue('smtp_config', [
            'host' => (string) Config::get('mail.host', '127.0.0.1'),
            'port' => (int) Config::get('mail.port', 1025),
            'username' => (string) Config::get('mail.username', ''),
            'encryption' => (string) Config::get('mail.encryption', 'tls'),
            'from_email' => (string) Config::get('mail.from_address', 'alertes@prime-care.fr'),
            'from_name' => (string) Config::get('mail.from_name', 'Prime Care'),
        ], 1);
        if (!is_array($smtpSettings)) {
            $smtpSettings = [];
        }

        $appSettings = Setting::getValue('app_preferences', [
            'app_name' => (string) Config::get('app.name', 'Prime Care'),
            'timezone' => (string) Config::get('app.timezone', 'Europe/Paris'),
            'date_format' => 'DD/MM/YYYY HH:mm',
        ], 1);
        if (!is_array($appSettings)) {
            $appSettings = [];
        }

        $retentionSettings = CronService::getRetentionSettings(1);

        return $this->view('settings/index', [
            'pageTitle' => 'Réglages',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'wideLayout' => true,
            'patient' => $patient,
            'careItems' => $careItems,
            'favorites' => $favorites,
            'intakeWindowMin' => $intakeWindowMin,
            'glycemiaUnit' => $glycemiaUnit,
            'attendanceSettings' => $attendanceSettings,
            'activeQrToken' => $activeQrToken,
            'homeQrSvg' => $homeQrSvg,
            'alertDefaults' => $alertDefaults,
            'smtpSettings' => $smtpSettings,
            'appSettings' => $appSettings,
            'retentionSettings' => $retentionSettings,
        ]);
    }

    public function updateGeneral(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $windowMin = max(5, min(180, (int) $request->input('intake_window_min', 30)));
        $unitRaw = trim((string) $request->input('glycemia_unit', 'g/L'));
        $unit = strtolower($unitRaw) === 'mg/dl' ? 'mg/dL' : 'g/L';

        $appName = trim((string) $request->input('app_name', 'Prime Care')) ?: 'Prime Care';
        $timezone = trim((string) $request->input('timezone', 'Europe/Paris')) ?: 'Europe/Paris';
        $dateFormat = trim((string) $request->input('date_format', 'DD/MM/YYYY HH:mm')) ?: 'DD/MM/YYYY HH:mm';

        Setting::setValue('intake_window_min', ['minutes' => $windowMin], 1);
        Setting::setValue('glycemia_unit', ['unit' => $unit, 'allow_mmol' => true], 1);
        Setting::setValue('app_preferences', [
            'app_name' => $appName,
            'timezone' => $timezone,
            'date_format' => $dateFormat,
        ], 1);

        AuditService::record('settings.update', 'settings', 1, null, [
            'app_name' => $appName,
            'timezone' => $timezone,
            'date_format' => $dateFormat,
            'intake_window_min' => $windowMin,
            'glycemia_unit' => $unit,
        ], 1, Auth::id());

        Session::flash('success', 'Réglages de l’application enregistrés.');
        return $this->redirect('/settings');
    }

    /**
     * POST /settings/thresholds (Réglages -> Mesures et seuils)
     */
    public function updateThresholds(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $pdo = Database::connection();
        $hypoCrit = max(0.30, min(0.80, (float) str_replace(',', '.', (string) $request->input('threshold_hypo_critical', '0.54'))));
        $hypo = max($hypoCrit, min(1.00, (float) str_replace(',', '.', (string) $request->input('threshold_hypo', '0.70'))));
        $fastMin = max($hypo, min(1.40, (float) str_replace(',', '.', (string) $request->input('target_glycemia_fasting_min', '0.80'))));
        $fastMax = max($fastMin, min(2.00, (float) str_replace(',', '.', (string) $request->input('target_glycemia_fasting_max', '1.30'))));
        $postMax = max($fastMax, min(2.50, (float) str_replace(',', '.', (string) $request->input('target_glycemia_postprandial_max', '1.80'))));
        $hyper = max($postMax, min(3.50, (float) str_replace(',', '.', (string) $request->input('threshold_hyper', '2.50'))));
        $hyperCrit = max($hyper, min(5.00, (float) str_replace(',', '.', (string) $request->input('threshold_hyper_critical', '3.00'))));

        $stmt = $pdo->prepare(
            'UPDATE `patients`
             SET `threshold_hypo_critical` = :hc,
                 `threshold_hypo` = :h,
                 `target_glycemia_fasting_min` = :fmin,
                 `target_glycemia_fasting_max` = :fmax,
                 `target_glycemia_postprandial_max` = :pmax,
                 `threshold_hyper` = :hy,
                 `threshold_hyper_critical` = :hyc,
                 `updated_at` = :u
             WHERE `id` = 1'
        );
        $stmt->execute([
            'hc' => $hypoCrit,
            'h' => $hypo,
            'fmin' => $fastMin,
            'fmax' => $fastMax,
            'pmax' => $postMax,
            'hy' => $hyper,
            'hyc' => $hyperCrit,
            'u' => gmdate('Y-m-d H:i:s'),
        ]);

        AuditService::record('settings.update', 'patients', 1, null, [
            'threshold_hypo_critical' => $hypoCrit,
            'threshold_hypo' => $hypo,
            'target_glycemia_fasting_min' => $fastMin,
            'target_glycemia_fasting_max' => $fastMax,
            'target_glycemia_postprandial_max' => $postMax,
            'threshold_hyper' => $hyper,
            'threshold_hyper_critical' => $hyperCrit,
        ], 1, Auth::id());

        Session::flash('success', 'Seuils glycémiques par moment de la journée enregistrés.');
        return $this->redirect('/settings');
    }

    /**
     * POST /settings/attendance (Réglages -> Pointage)
     */
    public function updateAttendance(Request $request): Response
    {
        $this->authorize('settings', 'update');

        AttendanceService::updateSettings($request->all(), Auth::id() ?? 1, 1);

        $extra = [
            'late_tolerance_min' => max(0, min(120, (int) $request->input('late_tolerance_min', 15))),
            'missing_alert_delay_min' => max(5, min(120, (int) $request->input('missing_alert_delay_min', 15))),
            'night_surcharge_pct' => max(0, min(200, (int) $request->input('night_surcharge_pct', 25))),
            'sunday_surcharge_pct' => max(0, min(200, (int) $request->input('sunday_surcharge_pct', 50))),
            'holiday_surcharge_pct' => max(0, min(200, (int) $request->input('holiday_surcharge_pct', 100))),
        ];
        Setting::setValue('attendance_rules_extra', $extra, 1);

        Session::flash('success', 'Réglages de pointage, tolérances et majorations enregistrés.');
        return $this->redirect('/settings');
    }

    /**
     * POST /settings/alerts (Réglages -> Alertes)
     */
    public function updateAlerts(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $payload = [
            'escalation_delay_min' => max(5, min(240, (int) $request->input('escalation_delay_min', 30))),
            'repeat_interval_min' => max(5, min(120, (int) $request->input('repeat_interval_min', 15))),
            'default_quiet_start' => trim((string) $request->input('default_quiet_start', '22:00')) ?: '22:00',
            'default_quiet_end' => trim((string) $request->input('default_quiet_end', '07:00')) ?: '07:00',
        ];
        Setting::setValue('alert_defaults', $payload, 1);
        AuditService::record('settings.update', 'settings', 1, null, $payload, 1, Auth::id());

        Session::flash('success', 'Délais de relance et heures calmes par défaut enregistrés.');
        return $this->redirect('/settings');
    }

    /**
     * POST /settings/smtp (Réglages -> E-mail SMTP)
     */
    public function updateSmtp(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $payload = [
            'host' => trim((string) $request->input('host', '127.0.0.1')),
            'port' => max(1, min(65535, (int) $request->input('port', 587))),
            'username' => trim((string) $request->input('username', '')),
            'encryption' => in_array((string) $request->input('encryption', 'tls'), ['tls', 'ssl', 'none'], true)
                ? (string) $request->input('encryption', 'tls')
                : 'tls',
            'from_email' => trim((string) $request->input('from_email', 'alertes@prime-care.fr')),
            'from_name' => trim((string) $request->input('from_name', 'Prime Care')),
        ];
        Setting::setValue('smtp_config', $payload, 1);
        AuditService::record('settings.update', 'settings', 1, null, $payload, 1, Auth::id());

        Session::flash('success', 'Configuration SMTP enregistrée.');
        return $this->redirect('/settings');
    }

    /**
     * POST /settings/smtp/test (Bouton « Envoyer un e-mail de test »)
     */
    public function testSmtp(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $user = Auth::user() ?? [];
        $recipient = trim((string) ($request->input('test_email') ?: ($user['email'] ?? 'karim@prime-care.local')));
        $html = '<h2>E-mail de test — Prime Care</h2>'
            . '<p>Ce message confirme le bon fonctionnement de l’envoi d’e-mails depuis votre instance Prime Care.</p>';

        $ok = MailService::send($recipient, '[Prime Care] E-mail de test SMTP', $html, strip_tags($html));
        AuditService::record('settings.smtp_test', 'settings', 1, null, ['recipient' => $recipient, 'ok' => $ok], 1, Auth::id());

        if ($ok) {
            Session::flash('success', 'E-mail de test envoyé à ' . $recipient . '.');
        } else {
            Session::flash('error', 'Échec de l’envoi de l’e-mail de test.');
        }

        return $this->redirect('/settings');
    }

    /**
     * POST /settings/retention (Réglages -> Conservation des données §5.2)
     */
    public function updateRetention(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $payload = [
            'medical_auto_purge' => false, // Invariant : jamais purgé automatiquement
            'attendances_years' => max(1, min(20, (int) $request->input('attendances_years', 5))),
            'tech_logs_months' => max(1, min(120, (int) $request->input('tech_logs_months', 12))),
            'handled_alerts_months' => max(1, min(120, (int) $request->input('handled_alerts_months', 24))),
            'audit_logs_years' => max(1, min(20, (int) $request->input('audit_logs_years', 6))),
            'physical_delete_grace_days' => 30,
        ];

        Setting::setValue('data_retention', $payload, 1);
        AuditService::record('settings.update', 'settings', 1, null, $payload, 1, Auth::id());

        Session::flash('success', 'Durées de conservation RGPD enregistrées (les données médicales ne sont jamais purgées automatiquement).');
        return $this->redirect('/settings');
    }

    /**
     * GET/POST /settings/export-patient-data (Bouton « Exporter toutes les données du patient » §5.2)
     */
    public function exportPatientData(Request $request): Response
    {
        $this->authorize('settings', 'view');

        $res = ExportService::exportPatientFullDataArchive((int) (Auth::id() ?? 1), 1);
        $content = (string) @file_get_contents($res['file_path']);
        @unlink($res['file_path']);

        return new Response($content, 200, [
            'Content-Type' => $res['mime'],
            'Content-Disposition' => 'attachment; filename="' . $res['filename'] . '"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /settings/attendance/qr-regenerate (Révoquer et régénérer le QR code du domicile)
     */
    public function regenerateAttendanceQr(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $label = trim((string) $request->input('label', 'Domicile M. Henri Benali'));
        AttendanceService::regenerateQrToken(Auth::id() ?? 1, 1, $label !== '' ? $label : 'Domicile M. Henri Benali');

        Session::flash('success', 'Nouveau QR code du domicile généré. L’ancien code a été révoqué immédiatement.');
        return $this->redirect('/settings');
    }

    /**
     * GET /settings/attendance/qr-print (Affiche A4 imprimable du QR code de pointage du domicile)
     */
    public function printAttendanceQr(Request $request): Response
    {
        $this->authorize('settings', 'view');

        $activeQrToken = AttendanceService::getActiveQrToken(1);
        $qrSvg = QrCodeService::renderSvg((string) $activeQrToken['qr_payload'], 320);

        return $this->view('settings/qr-print', [
            'pageTitle' => 'QR Code Pointage Domicile — M. Henri Benali',
            'activeQrToken' => $activeQrToken,
            'qrSvg' => $qrSvg,
        ], 'print');
    }

    public function storeCareItem(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Veuillez saisir le libellé du soin rapide.');
            return $this->redirect('/settings');
        }

        $code = 'care_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $icon = trim((string) $request->input('icon_name', 'check-circle')) ?: 'check-circle';
        $category = trim((string) $request->input('category', 'hygiene')) ?: 'hygiene';
        $sortOrder = max(1, min(999, (int) $request->input('sort_order', 20)));
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;

        $pdo = Database::connection();
        $ins = $pdo->prepare(
            'INSERT INTO `care_items`
             (`patient_id`, `code`, `label`, `icon_name`, `category`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :code, :label, :icon, :cat, :sort, 1, :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'code' => $code,
            'label' => $label,
            'icon' => $icon,
            'cat' => $category,
            'sort' => $sortOrder,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        AuditService::record('care_item.create', 'care_items', $id, null, ['label' => $label, 'sort_order' => $sortOrder], 1, $userId);
        Session::flash('success', 'Soin rapide ajouté.');
        return $this->redirect('/settings');
    }

    public function toggleCareItem(Request $request, string $id): Response
    {
        $this->authorize('settings', 'update');

        $itemId = (int) $id;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM `care_items` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            $newActive = (int) ($row['is_active'] ?? 1) === 1 ? 0 : 1;
            $sortOrder = isset($request->all()['sort_order']) ? max(1, (int) $request->input('sort_order', (int) $row['sort_order'])) : (int) $row['sort_order'];
            $pdo->prepare('UPDATE `care_items` SET `is_active` = :a, `sort_order` = :s, `updated_at` = :u WHERE `id` = :id')
                ->execute(['a' => $newActive, 's' => $sortOrder, 'u' => gmdate('Y-m-d H:i:s'), 'id' => $itemId]);
            AuditService::record('care_item.update', 'care_items', $itemId, ['is_active' => $row['is_active']], ['is_active' => $newActive, 'sort_order' => $sortOrder], 1, Auth::id());
        }

        Session::flash('success', 'Soin rapide mis à jour.');
        return $this->redirect('/settings');
    }

    public function deleteCareItem(Request $request, string $id): Response
    {
        $this->authorize('settings', 'update');

        $itemId = (int) $id;
        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE `care_items` SET `deleted_at` = :d, `is_active` = 0, `updated_at` = :u WHERE `id` = :id')
            ->execute(['d' => $nowUtc, 'u' => $nowUtc, 'id' => $itemId]);

        AuditService::record('care_item.soft_delete', 'care_items', $itemId, null, ['deleted_at' => $nowUtc], 1, Auth::id());
        Session::flash('success', 'Soin rapide retiré.');
        return $this->redirect('/settings');
    }

    public function storeFavorite(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            Session::flash('error', 'Veuillez saisir la composition de l’aliment favori.');
            return $this->redirect('/settings');
        }

        $mealType = trim((string) $request->input('meal_type', 'any'));
        if (!in_array($mealType, ['breakfast', 'lunch', 'snack', 'dinner', 'night_snack', 'any'], true)) {
            $mealType = 'any';
        }
        $carbsRaw = trim((string) $request->input('default_carbs_g', ''));
        $carbs = ($carbsRaw !== '' && is_numeric($carbsRaw)) ? max(0, (int) $carbsRaw) : null;
        $drink = trim((string) $request->input('default_drink', '')) ?: null;

        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;
        $pdo = Database::connection();

        $ins = $pdo->prepare(
            'INSERT INTO `food_favorites`
             (`patient_id`, `label`, `meal_type`, `default_carbs_g`, `default_drink`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :label, :type, :carbs, :drink, 20, 1, :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'label' => $label,
            'type' => $mealType,
            'carbs' => $carbs,
            'drink' => $drink,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        AuditService::record('food_favorite.create', 'food_favorite', $id, null, ['label' => $label], 1, $userId);
        Session::flash('success', 'Aliment favori ajouté.');
        return $this->redirect('/settings');
    }

    public function deleteFavorite(Request $request, string $id): Response
    {
        $this->authorize('settings', 'update');

        $favId = (int) $id;
        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();
        $pdo->prepare('UPDATE `food_favorites` SET `deleted_at` = :d, `is_active` = 0, `updated_at` = :u WHERE `id` = :id')
            ->execute(['d' => $nowUtc, 'u' => $nowUtc, 'id' => $favId]);

        AuditService::record('food_favorite.soft_delete', 'food_favorite', $favId, null, ['deleted_at' => $nowUtc], 1, Auth::id());
        Session::flash('success', 'Aliment favori retiré.');
        return $this->redirect('/settings');
    }
}
