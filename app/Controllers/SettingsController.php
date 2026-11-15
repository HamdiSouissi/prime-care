<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Services\QrCodeService;
use PDO;

/**
 * Admin Settings screen (/settings):
 * - Quick care items (care_items)
 * - Food favorites (food_favorites)
 * - Intake window (intake_window_min) & glycemia unit (g/L vs mg/dL)
 * - Phase 3: Attendance verification settings (gps, qr, both, none, radius_m, require_clocked_in)
 *   & Home QR code HMAC generation / A4 printable sheet.
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('settings', 'view');

        $pdo = Database::connection();
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
        $activeQrToken = AttendanceService::getActiveQrToken(1);
        $homeQrSvg = QrCodeService::renderSvg((string) $activeQrToken['qr_payload'], 200);

        return $this->view('settings/index', [
            'pageTitle' => 'Réglages médicaux & Pointage',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'wideLayout' => true,
            'careItems' => $careItems,
            'favorites' => $favorites,
            'intakeWindowMin' => $intakeWindowMin,
            'glycemiaUnit' => $glycemiaUnit,
            'attendanceSettings' => $attendanceSettings,
            'activeQrToken' => $activeQrToken,
            'homeQrSvg' => $homeQrSvg,
        ]);
    }

    public function updateGeneral(Request $request): Response
    {
        $this->authorize('settings', 'update');

        $windowMin = max(5, min(180, (int) $request->input('intake_window_min', 30)));
        $unitRaw = trim((string) $request->input('glycemia_unit', 'g/L'));
        $unit = strtolower($unitRaw) === 'mg/dl' ? 'mg/dL' : 'g/L';

        Setting::setValue('intake_window_min', ['minutes' => $windowMin], 1);
        Setting::setValue('glycemia_unit', ['unit' => $unit, 'allow_mmol' => true], 1);

        AuditService::record('settings.update', 'settings', 1, null, [
            'intake_window_min' => $windowMin,
            'glycemia_unit' => $unit,
        ], 1, Auth::id());

        Session::flash('success', 'Réglages généraux enregistrés.');
        return $this->redirect('/settings');
    }

    /**
     * POST /settings/attendance (Réglages -> Pointage)
     */
    public function updateAttendance(Request $request): Response
    {
        $this->authorize('settings', 'update');

        AttendanceService::updateSettings($request->all(), Auth::id() ?? 1, 1);
        Session::flash('success', 'Réglages de pointage et de vérification de présence enregistrés.');
        return $this->redirect('/settings');
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
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;

        $pdo = Database::connection();
        $ins = $pdo->prepare(
            'INSERT INTO `care_items`
             (`patient_id`, `code`, `label`, `icon_name`, `category`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :code, :label, :icon, :cat, 20, 1, :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'code' => $code,
            'label' => $label,
            'icon' => $icon,
            'cat' => $category,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $id = (int) $pdo->lastInsertId();

        AuditService::record('care_item.create', 'care_item', $id, null, ['label' => $label], 1, $userId);
        Session::flash('success', 'Soin rapide ajouté.');
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

        AuditService::record('care_item.soft_delete', 'care_item', $itemId, null, ['deleted_at' => $nowUtc], 1, Auth::id());
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
