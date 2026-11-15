<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Uploader;
use App\Services\AlertService;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Services\HandoverService;
use App\Services\IntakeService;
use App\Services\MealService;
use App\Services\Policy;
use App\Services\ScheduleService;
use App\Services\ThresholdService;
use App\Services\TimelineService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/**
 * JSON API controller for Phase 2 & Phase 3 fast medical actions and offline synchronization:
 * - POST /api/measures
 * - POST /api/intakes/{id}/give
 * - POST /api/intakes/{id}/skip
 * - POST /api/intakes/as-needed
 * - POST /api/meals
 * - POST /api/notes
 * - POST /api/notes/{id}/read
 * - POST /api/care-checks
 * - GET  /api/timeline
 *
 * Supports idempotent `client_uuid` (returns 200 with existing record if already synchronized)
 * and enforces the `require_clocked_in` setting ("Pointez votre arrivée pour saisir" -> 403).
 */
final class ApiMedicalController extends Controller
{
    private function checkClockInRequirement(): ?Response
    {
        if (!AttendanceService::canUserWriteMedical(Auth::user())) {
            return Response::jsonError('CLOCK_IN_REQUIRED', 'Pointez votre arrivée pour saisir', 403);
        }
        return null;
    }

    private function extractClientUuid(Request $request): ?string
    {
        $raw = trim((string) $request->input('client_uuid', ''));
        if ($raw === '') {
            return null;
        }
        return substr($raw, 0, 36);
    }

    /**
     * Valide et convertit `client_recorded_at` (ISO 8601 UTC complet) en horodatage UTC `Y-m-d H:i:s` :
     * - Si absent : retourne l'heure serveur ($fallbackUtc) ;
     * - Si dans le futur (> 2 min après l'heure serveur) : ramené à l'heure serveur ;
     * - Si plus ancien que 72 h : refusé en HTTP 422 `OFFLINE_TOO_OLD`.
     *
     * @return array{ok: bool, utc: string, is_client_time: bool, response?: Response}
     */
    private function resolveClientTimestamp(Request $request, ?string $fallbackUtc = null): array
    {
        $serverNowUtc = $fallbackUtc ?? gmdate('Y-m-d H:i:s');
        $raw = trim((string) ($request->input('client_recorded_at', '') ?: $request->input('recorded_at', '')));
        if ($raw === '') {
            return [
                'ok' => true,
                'utc' => $serverNowUtc,
                'is_client_time' => false,
            ];
        }

        try {
            $utcTz = new DateTimeZone('UTC');
            $dt = (new DateTimeImmutable($raw, $utcTz))->setTimezone($utcTz);
            $serverDt = new DateTimeImmutable($serverNowUtc, $utcTz);
            $diffSec = $dt->getTimestamp() - $serverDt->getTimestamp();

            // Valeur de plus de 72 h dans le passé -> refusée (422 OFFLINE_TOO_OLD)
            if ($diffSec < -(72 * 3600)) {
                return [
                    'ok' => false,
                    'utc' => $serverNowUtc,
                    'is_client_time' => true,
                    'response' => Response::jsonError(
                        'OFFLINE_TOO_OLD',
                        'Saisie hors ligne trop ancienne (> 72 h).',
                        422
                    ),
                ];
            }

            // Valeur dans le futur (> 2 min) -> ramenée à l'heure serveur
            if ($diffSec > 120) {
                return [
                    'ok' => true,
                    'utc' => $serverNowUtc,
                    'is_client_time' => true,
                ];
            }

            return [
                'ok' => true,
                'utc' => $dt->format('Y-m-d H:i:s'),
                'is_client_time' => true,
            ];
        } catch (Throwable) {
            return [
                'ok' => true,
                'utc' => $serverNowUtc,
                'is_client_time' => false,
            ];
        }
    }

    /**
     * POST /api/measures
     */
    public function storeMeasure(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'vitals', 'create')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour enregistrer une mesure.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $pdo = Database::connection();
        $clientUuid = $this->extractClientUuid($request);

        // Idempotence hors ligne : si client_uuid existe déjà, retourner 200 avec l'enregistrement existant
        if ($clientUuid !== null) {
            $dupStmt = $pdo->prepare(
                'SELECT m.*, mt.code AS type_code, mt.label AS type_label
                 FROM `measures` m
                 INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
                 WHERE m.client_uuid = :cuuid AND m.deleted_at IS NULL
                 LIMIT 1'
            );
            $dupStmt->execute(['cuuid' => $clientUuid]);
            $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return Response::jsonSuccess([
                    'id' => (int) $existing['id'],
                    'client_uuid' => $clientUuid,
                    'measure_type_id' => (int) $existing['measure_type_id'],
                    'type_code' => (string) $existing['type_code'],
                    'type_label' => (string) $existing['type_label'],
                    'value' => (float) $existing['value'],
                    'value2' => $existing['value2'] !== null ? (float) $existing['value2'] : null,
                    'unit' => (string) $existing['unit_recorded'],
                    'context' => (string) $existing['context'],
                    'measured_at' => (string) $existing['measured_at'],
                    'alert_level' => (string) $existing['alert_level'],
                    'threshold_level' => (string) $existing['threshold_level'],
                    'idempotent' => true,
                ], 200);
            }
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }

        $typeId = (int) $request->input('measure_type_id', 0);
        $typeCode = trim((string) $request->input('type_code', ''));

        if ($typeId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM `measure_types` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
            $stmt->execute(['id' => $typeId]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM `measure_types` WHERE `patient_id` = 1 AND `code` = :code AND `deleted_at` IS NULL LIMIT 1');
            $stmt->execute(['code' => $typeCode !== '' ? $typeCode : 'glycemia']);
        }

        $measureType = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$measureType) {
            return Response::jsonError('INVALID_TYPE', 'Type de mesure introuvable.', 422);
        }

        $rawValue = str_replace(',', '.', trim((string) (
            $request->input('value', '') !== ''
                ? $request->input('value', '')
                : ($request->input('primary_value', '') !== '' ? $request->input('primary_value', '') : $request->input('value_primary', ''))
        )));
        if ($rawValue === '' || !is_numeric($rawValue)) {
            return Response::jsonError('VALIDATION_ERROR', 'Veuillez saisir une valeur numérique valide.', 422);
        }

        $value = (float) $rawValue;
        $unitInput = trim((string) $request->input('unit', (string) $measureType['unit']));
        if ((string) $measureType['code'] === 'glycemia' && strtolower($unitInput) === 'mg/dl') {
            $value = ThresholdService::mgDlToG($value);
        }

        $rawVal2 = str_replace(',', '.', trim((string) (
            $request->input('value2', '') !== ''
                ? $request->input('value2', '')
                : ($request->input('secondary_value', '') !== '' ? $request->input('secondary_value', '') : $request->input('value_secondary', ''))
        )));
        $value2 = ($rawVal2 !== '' && is_numeric($rawVal2)) ? (float) $rawVal2 : null;

        $rawVal3 = str_replace(',', '.', trim((string) $request->input('value3', '')));
        $value3 = ($rawVal3 !== '' && is_numeric($rawVal3)) ? (float) $rawVal3 : null;

        if ((string) $measureType['code'] === 'blood_pressure' && $value2 === null) {
            return Response::jsonError('VALIDATION_ERROR', 'Veuillez saisir la tension systolique et diastolique.', 422);
        }

        $plausibility = ThresholdService::checkPlausibility($measureType, $value, $value2);
        $confirmed = in_array((string) $request->input('out_of_bounds_confirmed', '0'), ['1', 'true', 'yes'], true);
        if (!$plausibility['plausible'] && !$confirmed) {
            return Response::jsonError(
                'OUT_OF_BOUNDS_CONFIRM_REQUIRED',
                (string) $plausibility['message'],
                422,
                ['requires_confirmation' => true]
            );
        }

        $context = trim((string) $request->input('context', 'routine')) ?: 'routine';

        $localDate = trim((string) $request->input('measured_date', ''));
        $localTime = trim((string) $request->input('measured_time', ''));
        if ($tsRes['is_client_time']) {
            $measuredAtUtc = $tsRes['utc'];
        } elseif ($localDate !== '' && $localTime !== '') {
            $measuredAtUtc = ScheduleService::localParisToUtc($localDate, $localTime);
        } else {
            $measuredAtUtc = $tsRes['utc'];
        }

        $patStmt = $pdo->query('SELECT * FROM `patients` WHERE `id` = 1 LIMIT 1');
        $patient = $patStmt !== false ? ($patStmt->fetch(PDO::FETCH_ASSOC) ?: null) : null;

        $eval = ThresholdService::evaluate($measureType, $value, $context, $value2, $patient);

        $photoPath = null;
        $photoThumbPath = null;
        $photoFile = $request->file('photo');
        if (is_array($photoFile) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $uploaded = Uploader::uploadImage($photoFile, 'measures', 1600, 400);
                $photoPath = $uploaded['filename'];
                $photoThumbPath = $uploaded['thumb'];
            } catch (Throwable $e) {
                return Response::jsonError('UPLOAD_ERROR', $e->getMessage(), 422);
            }
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $comment = trim((string) $request->input('comment', ''));

        $ins = $pdo->prepare(
            'INSERT INTO `measures`
             (`patient_id`, `client_uuid`, `measure_type_id`, `value`, `value2`, `value3`, `unit_recorded`, `context`, `measured_at`,
              `comment`, `photo_path`, `photo_thumb_path`, `alert_level`, `threshold_level`, `out_of_bounds_confirmed`,
              `recorded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :cuuid, :type_id, :val1, :val2, :val3, :unit, :context, :measured_at,
              :comment, :photo, :thumb, :alert, :tlevel, :oob,
              :by, :created_at, :updated_at, :created_by)'
        );
        $ins->execute([
            'cuuid' => $clientUuid,
            'type_id' => (int) $measureType['id'],
            'val1' => $value,
            'val2' => $value2,
            'val3' => $value3,
            'unit' => (string) $measureType['unit'],
            'context' => $context,
            'measured_at' => $measuredAtUtc,
            'comment' => $comment !== '' ? $comment : null,
            'photo' => $photoPath,
            'thumb' => $photoThumbPath,
            'alert' => $eval['alert_level'],
            'tlevel' => $eval['threshold_level'],
            'oob' => (!$plausibility['plausible'] && $confirmed) ? 1 : 0,
            'by' => $userId,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'created_by' => $userId,
        ]);
        $measureId = (int) $pdo->lastInsertId();

        ScheduleService::fulfillExpectationWithMeasure($measureId, (int) $measureType['id'], $measuredAtUtc, 1);

        $afterData = [
            'id' => $measureId,
            'patient_id' => 1,
            'client_uuid' => $clientUuid,
            'measure_type_id' => (int) $measureType['id'],
            'type_code' => (string) $measureType['code'],
            'type_label' => (string) $measureType['label'],
            'value' => $value,
            'value2' => $value2,
            'value3' => $value3,
            'unit' => (string) $measureType['unit'],
            'unit_recorded' => (string) $measureType['unit'],
            'context' => $context,
            'measured_at' => $measuredAtUtc,
            'time_local' => ScheduleService::utcToLocalParis($measuredAtUtc, 'H:i'),
            'alert_level' => $eval['alert_level'],
            'threshold_level' => $eval['threshold_level'],
            'status_label' => $eval['label'],
            'status_icon' => $eval['icon'],
            'show_hypo_protocol' => $eval['show_hypo_protocol'],
            'recorded_by' => $userId,
        ];

        $expectationId = (int) $request->input('expectation_id', 0);
        AuditService::record('measure.create', 'measure', $measureId, null, $afterData, 1, $userId);
        AlertService::onMeasureRecorded($afterData, $measureType, $expectationId > 0 ? $expectationId : null);

        return Response::jsonSuccess(array_merge($afterData, ['measure' => $afterData]), 201);
    }

    /**
     * POST /api/intakes/{id}/give
     */
    public function giveIntake(Request $request, string $id): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'medications', 'validate_intake')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour valider une prise.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }

        $res = IntakeService::giveIntake((int) $id, $userId, $request->all(), $tsRes['utc']);
        if (!$res['ok']) {
            return Response::jsonError(
                (string) ($res['code'] ?? 'INTAKE_ERROR'),
                $res['message'],
                $res['http_status']
            );
        }

        return Response::jsonSuccess($res['data'] ?? ['message' => $res['message']], $res['http_status']);
    }

    /**
     * POST /api/intakes/{id}/skip
     */
    public function skipIntake(Request $request, string $id): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'medications', 'validate_intake')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour modifier une prise.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }

        $reason = (string) $request->input('reason', '');
        $comment = (string) $request->input('comment', '');
        $clientUuid = $this->extractClientUuid($request);

        $res = IntakeService::skipIntake((int) $id, $userId, $reason, $comment, $tsRes['utc'], $clientUuid);
        if (!$res['ok']) {
            return Response::jsonError(
                (string) ($res['code'] ?? 'SKIP_ERROR'),
                $res['message'],
                $res['http_status']
            );
        }

        return Response::jsonSuccess($res['data'] ?? ['message' => $res['message']], $res['http_status']);
    }

    /**
     * POST /api/intakes/as-needed
     */
    public function giveAsNeeded(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'medications', 'validate_intake')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour enregistrer une prise.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }

        $prescriptionId = (int) $request->input('prescription_id', 0);
        if ($prescriptionId <= 0) {
            return Response::jsonError('VALIDATION_ERROR', 'Veuillez sélectionner un médicament « si besoin ».', 422);
        }

        $res = IntakeService::giveAsNeeded($prescriptionId, $userId, $request->all(), $tsRes['utc']);
        if (!$res['ok']) {
            return Response::jsonError(
                (string) ($res['code'] ?? 'AS_NEEDED_ERROR'),
                $res['message'],
                $res['http_status'],
                isset($res['next_allowed_local']) ? ['next_allowed_local' => $res['next_allowed_local']] : []
            );
        }

        return Response::jsonSuccess($res['data'] ?? ['message' => $res['message']], $res['http_status']);
    }

    /**
     * POST /api/meals
     */
    public function storeMeal(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'meals', 'create')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour enregistrer un repas.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $pdo = Database::connection();
        $clientUuid = $this->extractClientUuid($request);

        if ($clientUuid !== null) {
            $dupStmt = $pdo->prepare('SELECT * FROM `meals` WHERE `client_uuid` = :cuuid AND `deleted_at` IS NULL LIMIT 1');
            $dupStmt->execute(['cuuid' => $clientUuid]);
            $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return Response::jsonSuccess([
                    'id' => (int) $existing['id'],
                    'client_uuid' => $clientUuid,
                    'meal_type' => (string) $existing['meal_type'],
                    'eaten_at' => (string) $existing['eaten_at'],
                    'description' => (string) $existing['description'],
                    'portion_eaten' => (int) $existing['portion_eaten'],
                    'idempotent' => true,
                ], 200);
            }
        }

        $filesByKind = [];
        foreach (['avant' => 'photo_avant', 'apres' => 'photo_apres', 'autre' => 'photo_autre'] as $k => $field) {
            $f = $request->file($field);
            if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $filesByKind[$k] = $f;
            }
        }

        $description = trim((string) $request->input('description', ''));
        if ($description === '' && $filesByKind === []) {
            return Response::jsonError('VALIDATION_ERROR', 'Veuillez indiquer la composition du repas ou ajouter une photo.', 422);
        }
        if ($description === '') {
            $description = 'Repas photographié';
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }

        $localDate = trim((string) $request->input('meal_date', ''));
        $localTime = trim((string) $request->input('meal_time', ''));
        if ($tsRes['is_client_time']) {
            $eatenAtUtc = $tsRes['utc'];
            $localTime = ScheduleService::utcToLocalParis($eatenAtUtc, 'H:i');
        } elseif ($localDate !== '' && $localTime !== '') {
            $eatenAtUtc = ScheduleService::localParisToUtc($localDate, $localTime);
        } else {
            $eatenAtUtc = $tsRes['utc'];
            $localTime = ScheduleService::utcToLocalParis($eatenAtUtc, 'H:i');
        }

        $mealType = trim((string) $request->input('meal_type', ''));
        if (!isset(MealService::MEAL_TYPES[$mealType])) {
            $mealType = MealService::deduceMealType($localTime);
        }

        $portion = (int) $request->input('portion_eaten', 100);
        if (!in_array($portion, [0, 25, 50, 75, 100], true)) {
            $portion = 100;
        }

        $carbsRaw = trim((string) $request->input('carbs_g', ''));
        $carbsG = ($carbsRaw !== '' && is_numeric($carbsRaw)) ? max(0, (int) $carbsRaw) : null;
        $drinkType = trim((string) $request->input('drink_type', '')) ?: null;
        $drinkMlRaw = trim((string) $request->input('drink_ml', ''));
        $drinkMl = ($drinkMlRaw !== '' && is_numeric($drinkMlRaw)) ? max(0, (int) $drinkMlRaw) : null;
        $comment = trim((string) $request->input('comment', '')) ?: null;

        $nowUtc = gmdate('Y-m-d H:i:s');

        $ins = $pdo->prepare(
            'INSERT INTO `meals`
             (`patient_id`, `client_uuid`, `meal_type`, `eaten_at`, `description`, `portion_eaten`, `carbs_g`, `drink_type`, `drink_ml`,
              `comment`, `recorded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :cuuid, :type, :eaten_at, :desc, :portion, :carbs, :drink, :ml, :comment, :by, :created_at, :updated_at, :created_by)'
        );
        $ins->execute([
            'cuuid' => $clientUuid,
            'type' => $mealType,
            'eaten_at' => $eatenAtUtc,
            'desc' => $description,
            'portion' => $portion,
            'carbs' => $carbsG,
            'drink' => $drinkType,
            'ml' => $drinkMl,
            'comment' => $comment,
            'by' => $userId,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'created_by' => $userId,
        ]);
        $mealId = (int) $pdo->lastInsertId();

        $savedPhotos = [];
        if ($filesByKind !== []) {
            try {
                $savedPhotos = MealService::saveMealPhotos($mealId, $filesByKind, $userId, 1);
            } catch (Throwable $e) {
                $pdo->prepare('DELETE FROM `meals` WHERE `id` = :id')->execute(['id' => $mealId]);
                return Response::jsonError('UPLOAD_ERROR', $e->getMessage(), 422);
            }
        }

        $linked = MealService::findLinkedGlycemias($eatenAtUtc, 1);
        $afterData = [
            'id' => $mealId,
            'client_uuid' => $clientUuid,
            'meal_type' => $mealType,
            'meal_type_label' => MealService::MEAL_TYPES[$mealType] ?? 'Repas',
            'eaten_at' => $eatenAtUtc,
            'description' => $description,
            'portion_eaten' => $portion,
            'carbs_g' => $carbsG,
            'drink_type' => $drinkType,
            'drink_ml' => $drinkMl,
            'photos' => $savedPhotos,
            'linked_glycemia' => $linked,
        ];

        AuditService::record('meal.create', 'meal', $mealId, null, $afterData, 1, $userId);

        return Response::jsonSuccess($afterData, 201);
    }

    /**
     * POST /api/notes
     */
    public function storeNote(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'notes', 'create')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour rédiger une note.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $pdo = Database::connection();
        $clientUuid = $this->extractClientUuid($request);

        if ($clientUuid !== null) {
            $dupStmt = $pdo->prepare('SELECT * FROM `notes` WHERE `client_uuid` = :cuuid AND `deleted_at` IS NULL LIMIT 1');
            $dupStmt->execute(['cuuid' => $clientUuid]);
            $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return Response::jsonSuccess([
                    'id' => (int) $existing['id'],
                    'client_uuid' => $clientUuid,
                    'category' => (string) $existing['category'],
                    'priority' => (string) $existing['priority'],
                    'title' => $existing['title'],
                    'content' => (string) $existing['content'],
                    'is_handover' => (bool) $existing['is_handover'],
                    'created_at' => (string) $existing['created_at'],
                    'idempotent' => true,
                ], 200);
            }
        }

        $content = trim((string) $request->input('content', ''));
        if ($content === '') {
            return Response::jsonError('VALIDATION_ERROR', 'Le contenu de la note ou transmission est obligatoire.', 422);
        }

        $isHandover = in_array((string) $request->input('is_handover', '0'), ['1', 'true', 'yes'], true) ? 1 : 0;
        $category = trim((string) $request->input('category', $isHandover ? 'handover' : 'general'));
        if (!in_array($category, ['general', 'medical', 'behavior', 'hygiene', 'family', 'supply', 'handover'], true)) {
            $category = $isHandover ? 'handover' : 'general';
        }

        $priority = trim((string) $request->input('priority', 'normal'));
        if (!in_array($priority, ['normal', 'important', 'urgent'], true)) {
            $priority = 'normal';
        }

        $title = trim((string) $request->input('title', '')) ?: ($isHandover ? 'Transmission de fin de poste' : null);

        $photoPath = null;
        $photoThumbPath = null;
        $photoFile = $request->file('photo');
        if (is_array($photoFile) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $uploaded = Uploader::uploadImage($photoFile, 'notes', 1600, 400);
                $photoPath = $uploaded['filename'];
                $photoThumbPath = $uploaded['thumb'];
            } catch (Throwable $e) {
                return Response::jsonError('UPLOAD_ERROR', $e->getMessage(), 422);
            }
        }

        $summaryJson = null;
        $summaryArr = null;
        if ($isHandover === 1) {
            $summaryArr = HandoverService::buildShiftSummary();
            $summaryJson = json_encode($summaryArr, JSON_UNESCAPED_UNICODE);
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }
        $nowUtc = $tsRes['utc'];

        $ins = $pdo->prepare(
            'INSERT INTO `notes`
             (`patient_id`, `client_uuid`, `category`, `priority`, `title`, `content`, `photo_path`, `photo_thumb_path`,
              `is_handover`, `handover_summary`, `recorded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :cuuid, :cat, :prio, :title, :content, :photo, :thumb, :handover, :summary, :by, :created_at, :updated_at, :created_by)'
        );
        $ins->execute([
            'cuuid' => $clientUuid,
            'cat' => $category,
            'prio' => $priority,
            'title' => $title,
            'content' => $content,
            'photo' => $photoPath,
            'thumb' => $photoThumbPath,
            'handover' => $isHandover,
            'summary' => $summaryJson,
            'by' => $userId,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'created_by' => $userId,
        ]);
        $noteId = (int) $pdo->lastInsertId();

        $afterData = [
            'id' => $noteId,
            'client_uuid' => $clientUuid,
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
            'content' => $content,
            'is_handover' => (bool) $isHandover,
            'handover_summary' => $summaryArr,
            'created_at' => $nowUtc,
        ];

        AuditService::record($isHandover ? 'handover.create' : 'note.create', 'note', $noteId, null, $afterData, 1, $userId);

        return Response::jsonSuccess($afterData, 201);
    }

    /**
     * POST /api/notes/{id}/read
     */
    public function markNoteRead(Request $request, string $id): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'notes', 'view')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        $noteId = (int) $id;
        HandoverService::markAsRead($noteId, $userId, 1);

        return Response::jsonSuccess([
            'note_id' => $noteId,
            'read_by' => $userId,
            'read_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * POST /api/care-checks
     */
    public function storeCareCheck(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'notes', 'create')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour cocher un soin.', 403);
        }
        if ($clockResp = $this->checkClockInRequirement()) {
            return $clockResp;
        }

        $pdo = Database::connection();
        $clientUuid = $this->extractClientUuid($request);

        if ($clientUuid !== null) {
            $dupStmt = $pdo->prepare(
                'SELECT cc.*, ci.label
                 FROM `care_checks` cc
                 INNER JOIN `care_items` ci ON ci.id = cc.care_item_id
                 WHERE cc.client_uuid = :cuuid AND cc.deleted_at IS NULL
                 LIMIT 1'
            );
            $dupStmt->execute(['cuuid' => $clientUuid]);
            $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return Response::jsonSuccess([
                    'id' => (int) $existing['id'],
                    'client_uuid' => $clientUuid,
                    'care_item_id' => (int) $existing['care_item_id'],
                    'label' => (string) $existing['label'],
                    'checked_at' => (string) $existing['checked_at'],
                    'time_local' => ScheduleService::utcToLocalParis((string) $existing['checked_at'], 'H:i'),
                    'idempotent' => true,
                ], 200);
            }
        }

        $careItemId = (int) $request->input('care_item_id', 0);

        $stmt = $pdo->prepare('SELECT * FROM `care_items` WHERE `id` = :id AND `deleted_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $careItemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return Response::jsonError('INVALID_CARE_ITEM', 'Soin rapide introuvable.', 422);
        }

        $tsRes = $this->resolveClientTimestamp($request);
        if (!$tsRes['ok']) {
            return $tsRes['response'];
        }
        $nowUtc = $tsRes['utc'];
        $comment = trim((string) $request->input('comment', '')) ?: null;

        $ins = $pdo->prepare(
            'INSERT INTO `care_checks`
             (`patient_id`, `client_uuid`, `care_item_id`, `checked_at`, `comment`, `performed_by`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :cuuid, :item_id, :checked_at, :comment, :by, :created_at, :updated_at, :created_by)'
        );
        $ins->execute([
            'cuuid' => $clientUuid,
            'item_id' => $careItemId,
            'checked_at' => $nowUtc,
            'comment' => $comment,
            'by' => $userId,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'created_by' => $userId,
        ]);
        $checkId = (int) $pdo->lastInsertId();

        $afterData = [
            'id' => $checkId,
            'client_uuid' => $clientUuid,
            'care_item_id' => $careItemId,
            'label' => (string) $item['label'],
            'checked_at' => $nowUtc,
            'done_at' => $nowUtc,
            'time_local' => ScheduleService::utcToLocalParis($nowUtc, 'H:i'),
        ];

        AuditService::record('care_check.create', 'care_check', $checkId, null, $afterData, 1, $userId);

        return Response::jsonSuccess($afterData, 201);
    }

    /**
     * GET /api/timeline?date=YYYY-MM-DD&type=all
     */
    public function timeline(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'dashboard', 'view')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        $defaultDate = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'Y-m-d');
        $date = trim((string) $request->query('date', $defaultDate));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $defaultDate;
        }

        $type = trim((string) $request->query('type', 'all'));
        $page = max(1, (int) $request->query('page', 1));

        $feed = TimelineService::getDailyFeed($date, $type, $page, 25, 1, $userId);
        return Response::jsonSuccess($feed);
    }

    /**
     * GET /api/offline/bootstrap
     * Retourne les données essentielles du jour pour le cache IndexedDB hors ligne.
     */
    public function offlineBootstrap(Request $request): Response
    {
        $userId = Auth::id() ?? 0;
        $todayParis = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'Y-m-d');
        $clockState = AttendanceService::getUserClockState($userId, 1);
        $openAlertsCount = AlertService::countOpen(1);

        return Response::jsonSuccess([
            'date_paris' => $todayParis,
            'synced_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'clock_state' => $clockState,
            'open_alerts_count' => $openAlertsCount,
        ]);
    }
}
