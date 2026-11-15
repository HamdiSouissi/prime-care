<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\InsulinService;
use App\Services\IntakeService;
use App\Services\ScheduleService;
use App\Services\ShiftTimelineService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Handles E08 (Intake validation & variable insulin), E09 (Intake history & 7d/30d observance),
 * E20 (Admin Treatment Plan CRUD + Correction Table Editor + Prescription attachment),
 * and E21 (Admin Measure Types & Thresholds configuration).
 */
final class TreatmentController extends Controller
{
    /**
     * GET /treatments (E08 — Today's intakes, variable insulin validation & as-needed medications)
     */
    public function index(Request $request): Response
    {
        $this->authorize('medications', 'view');

        $todayLocal = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'Y-m-d');
        $program = ShiftTimelineService::getDayProgram($todayLocal, 1);
        $observance = IntakeService::getObservanceSummary(1);
        $rotation = InsulinService::getRotationSuggestion(1);

        // Recent glycemia readings for variable insulin association
        $pdo = Database::connection();
        $gStmt = $pdo->query(
            'SELECT m.`id`, m.`value`, m.`context`, m.`measured_at`
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = 1 AND mt.code = \'glycemia\' AND m.deleted_at IS NULL
             ORDER BY m.measured_at DESC
             LIMIT 6'
        );
        $recentGlycemias = [];
        if ($gStmt !== false) {
            foreach ($gStmt->fetchAll(PDO::FETCH_ASSOC) as $gr) {
                $gr['time_local'] = ScheduleService::utcToLocalParis((string) $gr['measured_at'], 'd/m à H:i');
                $recentGlycemias[] = $gr;
            }
        }

        return $this->view('treatments/index', [
            'pageTitle' => 'Traitements & Prises',
            'activeTab' => 'health',
            'backUrl' => '/health',
            'backLabel' => 'Santé',
            'todayLocal' => $todayLocal,
            'program' => $program,
            'observance' => $observance,
            'rotation' => $rotation,
            'recentGlycemias' => $recentGlycemias,
        ]);
    }

    /**
     * GET /treatments/history (E09 — Intake history by day and medication + 7d/30d observance)
     */
    public function history(Request $request): Response
    {
        $this->authorize('medications', 'view');

        $pdo = Database::connection();
        $observance = IntakeService::getObservanceSummary(1);

        $medFilter = (int) $request->query('medication_id', 0);
        $daysFilter = (int) $request->query('days', 14);
        if (!in_array($daysFilter, [7, 14, 30], true)) {
            $daysFilter = 14;
        }

        $fromUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("-{$daysFilter} days")
            ->format('Y-m-d H:i:s');
        $nowUtc = gmdate('Y-m-d H:i:s');

        $sql = 'SELECT mi.*,
                       p.dose AS rx_dose,
                       p.unit AS rx_unit,
                       p.insulin_kind,
                       p.dose_mode,
                       m.id AS medication_id,
                       m.name AS medication_name,
                       m.form AS medication_form,
                       u.first_name AS giver_first_name,
                       u.last_name AS giver_last_name,
                       gm.value AS linked_glycemia_value
                FROM `medication_intakes` mi
                INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
                INNER JOIN `medications` m ON m.id = p.medication_id
                LEFT JOIN `users` u ON u.id = mi.given_by
                LEFT JOIN `measures` gm ON gm.id = mi.related_measure_id
                WHERE mi.patient_id = 1
                  AND mi.scheduled_at BETWEEN :from_utc AND :to_utc
                  AND mi.deleted_at IS NULL';
        $params = ['from_utc' => $fromUtc, 'to_utc' => $nowUtc];
        if ($medFilter > 0) {
            $sql .= ' AND m.id = :med_id';
            $params['med_id'] = $medFilter;
        }
        $sql .= ' ORDER BY mi.scheduled_at DESC, mi.id DESC LIMIT 120';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $byDay = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $dayLocal = ScheduleService::utcToLocalParis((string) $row['scheduled_at'], 'Y-m-d');
            $row['time_local'] = ScheduleService::utcToLocalParis((string) $row['scheduled_at'], 'H:i');
            $row['given_at_local'] = !empty($row['given_at'])
                ? ScheduleService::utcToLocalParis((string) $row['given_at'], 'H:i')
                : null;
            $row['disp'] = IntakeService::computeDisplayStatus($row);
            $byDay[$dayLocal][] = $row;
        }

        $medsStmt = $pdo->query('SELECT `id`, `name` FROM `medications` WHERE `patient_id` = 1 AND `deleted_at` IS NULL ORDER BY `name` ASC');
        $medications = $medsStmt !== false ? $medsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $this->view('treatments/history', [
            'pageTitle' => 'Historique & Observance',
            'activeTab' => 'health',
            'backUrl' => '/treatments',
            'backLabel' => 'Traitements',
            'observance' => $observance,
            'byDay' => $byDay,
            'medications' => $medications,
            'medFilter' => $medFilter,
            'daysFilter' => $daysFilter,
        ]);
    }

    /**
     * POST /treatments/intakes/{id}/give (HTML form fallback)
     */
    public function give(Request $request, string $id): Response
    {
        $this->authorize('medications', 'validate_intake');
        if (!\App\Services\AttendanceService::canUserWriteMedical(Auth::user())) {
            return Response::jsonError('CLOCK_IN_REQUIRED', 'Pointez votre arrivée pour saisir', 403);
        }
        $res = IntakeService::giveIntake((int) $id, Auth::id() ?? 0, $request->all());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);
        return $this->redirect((string) $request->input('redirect_to', '/today'));
    }

    /**
     * POST /treatments/intakes/{id}/skip (HTML form fallback)
     */
    public function skip(Request $request, string $id): Response
    {
        $this->authorize('medications', 'validate_intake');
        if (!\App\Services\AttendanceService::canUserWriteMedical(Auth::user())) {
            return Response::jsonError('CLOCK_IN_REQUIRED', 'Pointez votre arrivée pour saisir', 403);
        }
        $res = IntakeService::skipIntake(
            (int) $id,
            Auth::id() ?? 0,
            (string) $request->input('reason', ''),
            (string) $request->input('comment', '')
        );
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);
        return $this->redirect((string) $request->input('redirect_to', '/today'));
    }

    /**
     * POST /treatments/as-needed (HTML form fallback)
     */
    public function giveAsNeeded(Request $request): Response
    {
        $this->authorize('medications', 'validate_intake');
        if (!\App\Services\AttendanceService::canUserWriteMedical(Auth::user())) {
            return Response::jsonError('CLOCK_IN_REQUIRED', 'Pointez votre arrivée pour saisir', 403);
        }
        $rxId = (int) $request->input('prescription_id', 0);
        $res = IntakeService::giveAsNeeded($rxId, Auth::id() ?? 0, $request->all());
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);
        return $this->redirect((string) $request->input('redirect_to', '/today'));
    }

    /**
     * GET /admin/treatments (E20 — Admin Treatment Plan management & Correction Table editor)
     */
    public function adminTreatments(Request $request): Response
    {
        $this->authorize('medications', 'create');

        $pdo = Database::connection();
        $medStmt = $pdo->query(
            'SELECT * FROM `medications` WHERE `patient_id` = 1 AND `deleted_at` IS NULL ORDER BY `is_active` DESC, `name` ASC'
        );
        $medications = $medStmt !== false ? $medStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $rxStmt = $pdo->query(
            'SELECT p.*, m.name AS medication_name, m.dci, m.form, m.box_photo_thumb_path
             FROM `prescriptions` p
             INNER JOIN `medications` m ON m.id = p.medication_id
             WHERE p.patient_id = 1 AND p.deleted_at IS NULL
             ORDER BY p.is_active DESC, p.as_needed ASC, m.name ASC'
        );
        $prescriptions = [];
        if ($rxStmt !== false) {
            foreach ($rxStmt->fetchAll(PDO::FETCH_ASSOC) as $rx) {
                $tStmt = $pdo->prepare(
                    'SELECT * FROM `prescription_times` WHERE `prescription_id` = :rx_id AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `scheduled_time` ASC'
                );
                $tStmt->execute(['rx_id' => (int) $rx['id']]);
                $rx['times'] = $tStmt->fetchAll(PDO::FETCH_ASSOC);
                $rx['correction_rows'] = is_string($rx['correction_table'] ?? null)
                    ? (json_decode((string) $rx['correction_table'], true) ?: [])
                    : [];
                $prescriptions[] = $rx;
            }
        }

        return $this->view('treatments/admin', [
            'pageTitle' => 'Plan de traitement',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'wideLayout' => true,
            'medications' => $medications,
            'prescriptions' => $prescriptions,
        ]);
    }

    /**
     * POST /admin/medications (Create a medication with optional box photo)
     */
    public function storeMedication(Request $request): Response
    {
        $this->authorize('medications', 'create');

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Le nom du médicament est obligatoire.');
            return $this->redirect('/admin/treatments');
        }

        $dci = trim((string) $request->input('dci', '')) ?: null;
        $form = trim((string) $request->input('form', 'comprimé')) ?: 'comprimé';
        $dosageRaw = str_replace(',', '.', trim((string) $request->input('default_dosage', '')));
        $dosage = ($dosageRaw !== '' && is_numeric($dosageRaw)) ? (float) $dosageRaw : null;
        $unit = trim((string) $request->input('default_unit', 'mg')) ?: 'mg';
        $instructions = trim((string) $request->input('instructions', '')) ?: null;

        $photoPath = null;
        $thumbPath = null;
        $boxFile = $request->file('box_photo');
        if (is_array($boxFile) && ($boxFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $uploaded = Uploader::uploadImage($boxFile, 'medications', 1200, 300);
                $photoPath = $uploaded['filename'];
                $thumbPath = $uploaded['thumb'];
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
                return $this->redirect('/admin/treatments');
            }
        }

        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;

        $ins = $pdo->prepare(
            'INSERT INTO `medications`
             (`patient_id`, `name`, `dci`, `form`, `default_dosage`, `default_unit`, `box_photo_path`, `box_photo_thumb_path`,
              `instructions`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :name, :dci, :form, :dosage, :unit, :photo, :thumb, :instructions, 1, :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'name' => $name,
            'dci' => $dci,
            'form' => $form,
            'dosage' => $dosage,
            'unit' => $unit,
            'photo' => $photoPath,
            'thumb' => $thumbPath,
            'instructions' => $instructions,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $medId = (int) $pdo->lastInsertId();

        AuditService::record('medication.create', 'medication', $medId, null, [
            'name' => $name,
            'dci' => $dci,
            'form' => $form,
            'default_dosage' => $dosage,
            'default_unit' => $unit,
        ], 1, $userId);

        Session::flash('success', sprintf('Médicament « %s » ajouté au catalogue.', $name));
        return $this->redirect('/admin/treatments');
    }

    /**
     * POST /admin/prescriptions (Create or update a prescription + times + correction table + rx document)
     */
    public function storePrescription(Request $request): Response
    {
        $this->authorize('medications', 'create');

        $pdo = Database::connection();
        $userId = Auth::id() ?? 1;
        $nowUtc = gmdate('Y-m-d H:i:s');

        $medicationId = (int) $request->input('medication_id', 0);
        if ($medicationId <= 0) {
            Session::flash('error', 'Veuillez sélectionner un médicament.');
            return $this->redirect('/admin/treatments');
        }

        $dose = (float) str_replace(',', '.', (string) $request->input('dose', '1'));
        $unit = trim((string) $request->input('unit', 'mg')) ?: 'mg';
        $startDate = trim((string) $request->input('start_date', date('Y-m-d')));
        $endDate = trim((string) $request->input('end_date', '')) ?: null;
        $endsOnRx = $request->input('ends_on_rx') ? 1 : 0;
        $asNeeded = $request->input('as_needed') ? 1 : 0;
        $minIntervalH = $asNeeded ? max(1, (int) $request->input('min_interval_h', 6)) : null;

        $insulinKindRaw = trim((string) $request->input('insulin_kind', ''));
        $insulinKind = in_array($insulinKindRaw, ['rapide', 'lente'], true) ? $insulinKindRaw : null;
        $doseMode = trim((string) $request->input('dose_mode', 'fixe')) === 'tableau' ? 'tableau' : 'fixe';

        $correctionJson = null;
        if ($doseMode === 'tableau') {
            $correctionJson = json_encode($this->parseCorrectionTableFromRequest($request), JSON_UNESCAPED_UNICODE);
        }

        $docPath = null;
        $docMime = null;
        $docOriginal = null;
        $docFile = $request->file('document');
        if (is_array($docFile) && ($docFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $upDoc = Uploader::uploadDocument($docFile, 'prescriptions');
                $docPath = $upDoc['filename'];
                $docMime = $upDoc['mime'];
                $docOriginal = $upDoc['original_name'];
            } catch (Throwable $e) {
                Session::flash('error', $e->getMessage());
                return $this->redirect('/admin/treatments');
            }
        }

        $instructions = trim((string) $request->input('instructions', '')) ?: null;

        $ins = $pdo->prepare(
            'INSERT INTO `prescriptions`
             (`patient_id`, `medication_id`, `dose`, `unit`, `start_date`, `end_date`, `ends_on_rx`, `days_mask`,
              `as_needed`, `min_interval_h`, `insulin_kind`, `dose_mode`, `correction_table`, `instructions`,
              `document_path`, `document_mime`, `document_original_name`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :med_id, :dose, :unit, :start_date, :end_date, :ends_on_rx, \'1,2,3,4,5,6,7\',
              :as_needed, :min_int, :insulin_kind, :dose_mode, :corr, :instructions,
              :doc_path, :doc_mime, :doc_orig, 1, :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'med_id' => $medicationId,
            'dose' => $dose,
            'unit' => $unit,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'ends_on_rx' => $endsOnRx,
            'as_needed' => $asNeeded,
            'min_int' => $minIntervalH,
            'insulin_kind' => $insulinKind,
            'dose_mode' => $doseMode,
            'corr' => $correctionJson,
            'instructions' => $instructions,
            'doc_path' => $docPath,
            'doc_mime' => $docMime,
            'doc_orig' => $docOriginal,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $rxId = (int) $pdo->lastInsertId();

        if (!$asNeeded) {
            $timesInput = $request->input('scheduled_times', '08:00');
            $timesList = is_array($timesInput)
                ? $timesInput
                : array_filter(array_map('trim', explode(',', (string) $timesInput)));
            $ptStmt = $pdo->prepare(
                'INSERT INTO `prescription_times`
                 (`patient_id`, `prescription_id`, `scheduled_time`, `moment_label`, `sort_order`, `created_at`, `updated_at`, `created_by`)
                 VALUES (1, :rx_id, :time, :moment, :ord, :created_at, :updated_at, :by)'
            );
            $ord = 1;
            foreach ($timesList as $tStr) {
                $cleanT = substr(trim((string) $tStr), 0, 5);
                if (!preg_match('/^\d{2}:\d{2}$/', $cleanT)) {
                    continue;
                }
                $h = (int) substr($cleanT, 0, 2);
                $moment = $h < 11 ? 'matin' : ($h < 15 ? 'midi' : ($h < 20 ? 'soir' : 'coucher'));
                $ptStmt->execute([
                    'rx_id' => $rxId,
                    'time' => $cleanT,
                    'moment' => $moment,
                    'ord' => $ord++,
                    'created_at' => $nowUtc,
                    'updated_at' => $nowUtc,
                    'by' => $userId,
                ]);
            }
            ScheduleService::regenerateForPrescription($rxId);
        }

        AuditService::record('prescription.create', 'prescription', $rxId, null, [
            'medication_id' => $medicationId,
            'dose' => $dose,
            'unit' => $unit,
            'dose_mode' => $doseMode,
        ], 1, $userId);

        Session::flash('success', 'Prescription enregistrée et prises futures mises à jour.');
        return $this->redirect('/admin/treatments');
    }

    /**
     * POST /admin/prescriptions/{id}/update (Updates prescription, correction table & regenerates future pending intakes)
     */
    public function updatePrescription(Request $request, string $id): Response
    {
        $this->authorize('medications', 'update');

        $rxId = (int) $id;
        $userId = Auth::id() ?? 1;
        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM `prescriptions` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $rxId]);
        $rx = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rx) {
            throw new RuntimeException('Prescription introuvable.', 404);
        }

        $dose = (float) str_replace(',', '.', (string) $request->input('dose', (string) $rx['dose']));
        $unit = trim((string) $request->input('unit', (string) $rx['unit'])) ?: 'mg';
        $isActive = $request->input('is_active', '1') ? 1 : 0;
        $doseMode = trim((string) $request->input('dose_mode', (string) $rx['dose_mode'])) === 'tableau' ? 'tableau' : 'fixe';

        $correctionJson = $rx['correction_table'];
        if ($doseMode === 'tableau') {
            $parsedTable = $this->parseCorrectionTableFromRequest($request);
            if ($parsedTable !== []) {
                $correctionJson = json_encode($parsedTable, JSON_UNESCAPED_UNICODE);
            }
        }

        $upd = $pdo->prepare(
            'UPDATE `prescriptions`
             SET `dose` = :dose,
                 `unit` = :unit,
                 `dose_mode` = :dose_mode,
                 `correction_table` = :corr,
                 `is_active` = :active,
                 `updated_at` = :now_utc
             WHERE `id` = :id'
        );
        $upd->execute([
            'dose' => $dose,
            'unit' => $unit,
            'dose_mode' => $doseMode,
            'corr' => $correctionJson,
            'active' => $isActive,
            'now_utc' => $nowUtc,
            'id' => $rxId,
        ]);

        // Update scheduled_times if provided
        $timesRaw = trim((string) $request->input('scheduled_times', ''));
        if ($timesRaw !== '' && (int) $rx['as_needed'] === 0) {
            $pdo->prepare('UPDATE `prescription_times` SET `deleted_at` = :now_utc WHERE `prescription_id` = :rx_id AND `deleted_at` IS NULL')
                ->execute(['now_utc' => $nowUtc, 'rx_id' => $rxId]);

            $ptStmt = $pdo->prepare(
                'INSERT INTO `prescription_times`
                 (`patient_id`, `prescription_id`, `scheduled_time`, `moment_label`, `sort_order`, `created_at`, `updated_at`, `created_by`)
                 VALUES (1, :rx_id, :time, :moment, :ord, :created_at, :updated_at, :by)'
            );
            $ord = 1;
            foreach (array_filter(array_map('trim', explode(',', $timesRaw))) as $tStr) {
                $cleanT = substr($tStr, 0, 5);
                if (!preg_match('/^\d{2}:\d{2}$/', $cleanT)) {
                    continue;
                }
                $h = (int) substr($cleanT, 0, 2);
                $moment = $h < 11 ? 'matin' : ($h < 15 ? 'midi' : ($h < 20 ? 'soir' : 'coucher'));
                $ptStmt->execute([
                    'rx_id' => $rxId,
                    'time' => $cleanT,
                    'moment' => $moment,
                    'ord' => $ord++,
                    'created_at' => $nowUtc,
                    'updated_at' => $nowUtc,
                    'by' => $userId,
                ]);
            }
        }

        // Regenerate future pending occurrences without touching past or already given/skipped intakes
        $regen = ScheduleService::regenerateForPrescription($rxId);

        AuditService::record('prescription.update', 'prescription', $rxId, $rx, [
            'dose' => $dose,
            'unit' => $unit,
            'dose_mode' => $doseMode,
            'correction_table' => $correctionJson,
            'is_active' => $isActive,
            'regenerated_intakes' => $regen,
        ], 1, $userId);

        Session::flash('success', 'Prescription mise à jour et occurrences futures régénérées.');
        return $this->redirect('/admin/treatments');
    }

    /**
     * GET /admin/measures (E21 — Admin Measure Types, Thresholds, Schedules & Glycemia Unit)
     */
    public function adminMeasures(Request $request): Response
    {
        $this->authorize('vitals', 'configure');

        $pdo = Database::connection();
        $mtStmt = $pdo->query(
            'SELECT * FROM `measure_types` WHERE `patient_id` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `id` ASC'
        );
        $measureTypes = $mtStmt !== false ? $mtStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $msStmt = $pdo->query(
            'SELECT ms.*, mt.label AS type_label, mt.unit
             FROM `measure_schedules` ms
             INNER JOIN `measure_types` mt ON mt.id = ms.measure_type_id
             WHERE ms.patient_id = 1 AND ms.deleted_at IS NULL
             ORDER BY ms.scheduled_time ASC'
        );
        $schedules = $msStmt !== false ? $msStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $gUnitCfg = Setting::getValue('glycemia_unit', ['unit' => 'g/L'], 1);
        $glycemiaUnit = is_array($gUnitCfg) ? (string) ($gUnitCfg['unit'] ?? 'g/L') : 'g/L';

        return $this->view('health/admin-measures', [
            'pageTitle' => 'Mesures & Seuils',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'wideLayout' => true,
            'measureTypes' => $measureTypes,
            'schedules' => $schedules,
            'glycemiaUnit' => $glycemiaUnit,
        ]);
    }

    /**
     * POST /admin/measures/types (Create a custom measure type)
     */
    public function storeMeasureType(Request $request): Response
    {
        $this->authorize('vitals', 'configure');

        $label = trim((string) $request->input('label', ''));
        $unit = trim((string) $request->input('unit', ''));
        if ($label === '' || $unit === '') {
            Session::flash('error', 'Le libellé et l’unité du type de mesure sont obligatoires.');
            return $this->redirect('/admin/measures');
        }

        $code = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string) $request->input('code', '')))) ?: '';
        if ($code === '') {
            $code = 'custom_' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        $decimals = max(0, min(2, (int) $request->input('decimals', 1)));
        $minVal = (float) str_replace(',', '.', (string) $request->input('min_value', '0'));
        $maxVal = (float) str_replace(',', '.', (string) $request->input('max_value', '500'));
        $parseOpt = static function (mixed $v): ?float {
            $s = str_replace(',', '.', trim((string) $v));
            return ($s !== '' && is_numeric($s)) ? (float) $s : null;
        };

        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;

        $ins = $pdo->prepare(
            'INSERT INTO `measure_types`
             (`patient_id`, `code`, `label`, `unit`, `decimals`, `min_value`, `max_value`,
              `crit_low`, `low`, `high`, `crit_high`, `icon_name`, `is_secondary`, `is_custom`, `is_active`, `sort_order`,
              `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :code, :label, :unit, :decimals, :min_val, :max_val,
              :crit_low, :low, :high, :crit_high, \'activity\', :is_sec, 1, 1, 20,
              :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'code' => $code,
            'label' => $label,
            'unit' => $unit,
            'decimals' => $decimals,
            'min_val' => $minVal,
            'max_val' => $maxVal,
            'crit_low' => $parseOpt($request->input('crit_low')),
            'low' => $parseOpt($request->input('low')),
            'high' => $parseOpt($request->input('high')),
            'crit_high' => $parseOpt($request->input('crit_high')),
            'is_sec' => $request->input('is_secondary') ? 1 : 0,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $typeId = (int) $pdo->lastInsertId();

        AuditService::record('measure_type.create', 'measure_type', $typeId, null, [
            'code' => $code,
            'label' => $label,
            'unit' => $unit,
        ], 1, $userId);

        Session::flash('success', sprintf('Type de mesure personnalisé « %s » créé.', $label));
        return $this->redirect('/admin/measures');
    }

    /**
     * POST /admin/measures/types/{id}/update (Update thresholds and plausibility bounds)
     */
    public function updateMeasureType(Request $request, string $id): Response
    {
        $this->authorize('vitals', 'configure');

        $typeId = (int) $id;
        $userId = Auth::id() ?? 1;
        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT * FROM `measure_types` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $typeId]);
        $before = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            throw new RuntimeException('Type de mesure introuvable.', 404);
        }

        $parseOpt = static function (mixed $v): ?float {
            $s = str_replace(',', '.', trim((string) $v));
            return ($s !== '' && is_numeric($s)) ? (float) $s : null;
        };

        $minVal = $parseOpt($request->input('min_value')) ?? (float) $before['min_value'];
        $maxVal = $parseOpt($request->input('max_value')) ?? (float) $before['max_value'];
        $critLow = $parseOpt($request->input('crit_low'));
        $low = $parseOpt($request->input('low'));
        $high = $parseOpt($request->input('high'));
        $critHigh = $parseOpt($request->input('crit_high'));

        $upd = $pdo->prepare(
            'UPDATE `measure_types`
             SET `min_value` = :min_val,
                 `max_value` = :max_val,
                 `crit_low` = :crit_low,
                 `low` = :low,
                 `high` = :high,
                 `crit_high` = :crit_high,
                 `updated_at` = :now_utc
             WHERE `id` = :id'
        );
        $upd->execute([
            'min_val' => $minVal,
            'max_val' => $maxVal,
            'crit_low' => $critLow,
            'low' => $low,
            'high' => $high,
            'crit_high' => $critHigh,
            'now_utc' => $nowUtc,
            'id' => $typeId,
        ]);

        AuditService::record('measure_type.update', 'measure_type', $typeId, $before, [
            'min_value' => $minVal,
            'max_value' => $maxVal,
            'crit_low' => $critLow,
            'low' => $low,
            'high' => $high,
            'crit_high' => $critHigh,
        ], 1, $userId);

        Session::flash('success', sprintf('Seuils de « %s » mis à jour.', (string) $before['label']));
        return $this->redirect('/admin/measures');
    }

    /**
     * POST /admin/measures/schedules (Add a scheduled expected measurement)
     */
    public function storeMeasureSchedule(Request $request): Response
    {
        $this->authorize('vitals', 'configure');

        $typeId = (int) $request->input('measure_type_id', 0);
        $time = substr(trim((string) $request->input('scheduled_time', '08:00')), 0, 5);
        $context = trim((string) $request->input('context', 'routine')) ?: 'routine';
        $label = trim((string) $request->input('label', '')) ?: null;

        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;

        $ins = $pdo->prepare(
            'INSERT INTO `measure_schedules`
             (`patient_id`, `measure_type_id`, `scheduled_time`, `days_mask`, `context`, `label`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :tid, :time, \'1,2,3,4,5,6,7\', :ctx, :label, 1, :created_at, :updated_at, :by)'
        );
        $ins->execute([
            'tid' => $typeId,
            'time' => $time,
            'ctx' => $context,
            'label' => $label,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'by' => $userId,
        ]);
        $schedId = (int) $pdo->lastInsertId();

        ScheduleService::generateUpcoming48h();
        AuditService::record('measure_schedule.create', 'measure_schedule', $schedId, null, [
            'measure_type_id' => $typeId,
            'scheduled_time' => $time,
            'context' => $context,
        ], 1, $userId);

        Session::flash('success', 'Planification de mesure attendue ajoutée.');
        return $this->redirect('/admin/measures');
    }

    /**
     * @return array<int, array{min: float, max: float, dose: float, note: string}>
     */
    private function parseCorrectionTableFromRequest(Request $request): array
    {
        $mins = $request->input('corr_min', []);
        $maxs = $request->input('corr_max', []);
        $doses = $request->input('corr_dose', []);
        $notes = $request->input('corr_note', []);

        if (!is_array($mins) || !is_array($maxs) || !is_array($doses)) {
            return [];
        }

        $table = [];
        foreach ($mins as $idx => $minRaw) {
            $minStr = str_replace(',', '.', trim((string) $minRaw));
            $maxStr = str_replace(',', '.', trim((string) ($maxs[$idx] ?? '')));
            $doseStr = str_replace(',', '.', trim((string) ($doses[$idx] ?? '')));
            if ($minStr === '' || $maxStr === '' || $doseStr === '') {
                continue;
            }
            $table[] = [
                'min' => (float) $minStr,
                'max' => (float) $maxStr,
                'dose' => (float) $doseStr,
                'note' => trim((string) ($notes[$idx] ?? '')),
            ];
        }
        return $table;
    }
}
