<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Uploader;
use App\Models\Patient;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\EditWindowService;
use App\Services\ScheduleService;
use App\Services\ThresholdService;
use App\Services\TimelineService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

/**
 * E06 & E07 — Health & Vitals overview (/health), Measure Type detail (/health/{code})
 * with Bleu nuit hero block, 3-zone glycemia gauge, 7/30/90-day ApexCharts curve with threshold bands,
 * accessible textual summary, paginated/filterable history, and 30-minute rule enforcement.
 */
final class HealthController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('vitals', 'view');

        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT * FROM `measure_types` WHERE `patient_id` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC, `id` ASC'
        );
        $types = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $patientModel = new Patient();
        $patient = $patientModel->current(1);
        $gUnitCfg = Setting::getValue('glycemia_unit', ['unit' => 'g/L'], 1);
        $glycemiaUnit = is_array($gUnitCfg) ? (string) ($gUnitCfg['unit'] ?? 'g/L') : 'g/L';

        $primaryTypes = [];
        $secondaryTypes = [];

        foreach ($types as $mt) {
            $lastStmt = $pdo->prepare(
                'SELECT m.*, u.first_name AS author_first_name
                 FROM `measures` m
                 LEFT JOIN `users` u ON u.id = m.recorded_by
                 WHERE m.patient_id = 1
                   AND m.measure_type_id = :tid
                   AND m.deleted_at IS NULL
                 ORDER BY m.measured_at DESC
                 LIMIT 1'
            );
            $lastStmt->execute(['tid' => (int) $mt['id']]);
            $last = $lastStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($last) {
                $eval = ThresholdService::evaluate(
                    $mt,
                    (float) $last['value'],
                    (string) $last['context'],
                    $last['value2'] !== null ? (float) $last['value2'] : null,
                    $patient
                );
                $decimals = (int) ($mt['decimals'] ?? 1);
                if ((string) $mt['code'] === 'blood_pressure' && $last['value2'] !== null) {
                    $last['formatted_value'] = sprintf('%d/%d', (int) round((float) $last['value']), (int) round((float) $last['value2']));
                } elseif ((string) $mt['code'] === 'glycemia') {
                    $last['formatted_value'] = ThresholdService::formatGlycemia((float) $last['value'], $glycemiaUnit);
                    $mt['display_unit'] = $glycemiaUnit;
                } else {
                    $last['formatted_value'] = number_format((float) $last['value'], $decimals, ',', ' ');
                }
                $last['time_local'] = ScheduleService::utcToLocalParis((string) $last['measured_at'], 'd/m à H:i');
                $last['context_label'] = TimelineService::contextLabel((string) $last['context']);
                $last['eval'] = $eval;
            }

            $mt['last_measure'] = $last;
            $mt['display_unit'] = $mt['display_unit'] ?? $mt['unit'];

            if ((int) ($mt['is_secondary'] ?? 0) === 1) {
                $secondaryTypes[] = $mt;
            } else {
                $primaryTypes[] = $mt;
            }
        }

        return $this->view('health/index', [
            'pageTitle' => 'Santé & Constantes',
            'activeTab' => 'health',
            'primaryTypes' => $primaryTypes,
            'secondaryTypes' => $secondaryTypes,
            'glycemiaUnit' => $glycemiaUnit,
            'patient' => $patient,
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $this->authorize('vitals', 'view');

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM `measure_types` WHERE `patient_id` = 1 AND `code` = :code AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $measureType = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$measureType) {
            throw new RuntimeException('Type de mesure introuvable.', 404);
        }

        $patientModel = new Patient();
        $patient = $patientModel->current(1);
        $gUnitCfg = Setting::getValue('glycemia_unit', ['unit' => 'g/L'], 1);
        $glycemiaUnit = is_array($gUnitCfg) ? (string) ($gUnitCfg['unit'] ?? 'g/L') : 'g/L';
        $isGlycemia = (string) $measureType['code'] === 'glycemia';
        $isBp = (string) $measureType['code'] === 'blood_pressure';

        // Period: 7, 30, or 90 days
        $days = (int) $request->query('days', 7);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 7;
        }

        $filterAlert = trim((string) $request->query('alert', 'all'));
        if (!in_array($filterAlert, ['all', 'ok', 'warn', 'critical'], true)) {
            $filterAlert = 'all';
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        // Latest measure for the Bleu nuit hero block
        $lastStmt = $pdo->prepare(
            'SELECT m.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM `measures` m
             LEFT JOIN `users` u ON u.id = m.recorded_by
             WHERE m.patient_id = 1
               AND m.measure_type_id = :tid
               AND m.deleted_at IS NULL
             ORDER BY m.measured_at DESC
             LIMIT 1'
        );
        $lastStmt->execute(['tid' => (int) $measureType['id']]);
        $latest = $lastStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $decimals = (int) ($measureType['decimals'] ?? 1);
        $heroEval = null;
        if ($latest) {
            $heroEval = ThresholdService::evaluate(
                $measureType,
                (float) $latest['value'],
                (string) $latest['context'],
                $latest['value2'] !== null ? (float) $latest['value2'] : null,
                $patient
            );
            if ($isBp && $latest['value2'] !== null) {
                $latest['formatted_value'] = sprintf('%d/%d', (int) round((float) $latest['value']), (int) round((float) $latest['value2']));
            } elseif ($isGlycemia) {
                $latest['formatted_value'] = ThresholdService::formatGlycemia((float) $latest['value'], $glycemiaUnit);
            } else {
                $latest['formatted_value'] = number_format((float) $latest['value'], $decimals, ',', ' ');
            }
            $latest['time_local'] = ScheduleService::utcToLocalParis((string) $latest['measured_at'], 'd/m/Y à H:i');
            $latest['context_label'] = TimelineService::contextLabel((string) $latest['context']);
        }

        // Chart data over $days
        $fromUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');

        $chartStmt = $pdo->prepare(
            'SELECT m.*
             FROM `measures` m
             WHERE m.patient_id = 1
               AND m.measure_type_id = :tid
               AND m.measured_at >= :from_utc
               AND m.deleted_at IS NULL
             ORDER BY m.measured_at ASC'
        );
        $chartStmt->execute(['tid' => (int) $measureType['id'], 'from_utc' => $fromUtc]);
        $chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

        $series1 = [];
        $series2 = [];
        $sum = 0.0;
        $minVal = 9999.0;
        $maxVal = 0.0;
        $lowCount = 0;
        $highCount = 0;
        $critCount = 0;

        foreach ($chartRows as $cr) {
            $v1 = (float) $cr['value'];
            $v2 = $cr['value2'] !== null ? (float) $cr['value2'] : null;
            $sum += $v1;
            if ($v1 < $minVal) {
                $minVal = $v1;
            }
            if ($v1 > $maxVal) {
                $maxVal = $v1;
            }

            $tLevel = (string) $cr['threshold_level'];
            if (in_array($tLevel, ['crit_low', 'low'], true)) {
                $lowCount++;
            }
            if (in_array($tLevel, ['high', 'crit_high'], true)) {
                $highCount++;
            }
            if ((string) $cr['alert_level'] === 'critical') {
                $critCount++;
            }

            $ev = ThresholdService::evaluate($measureType, $v1, (string) $cr['context'], $v2, $patient);
            $xLabel = ScheduleService::utcToLocalParis((string) $cr['measured_at'], $days <= 7 ? 'd/m H:i' : 'd/m');

            $series1[] = [
                'x' => $xLabel,
                'y' => $v1,
                'alertLevel' => (string) $cr['alert_level'],
                'statusLabel' => $ev['label'],
            ];
            if ($isBp && $v2 !== null) {
                $series2[] = [
                    'x' => $xLabel,
                    'y' => $v2,
                    'alertLevel' => (string) $cr['alert_level'],
                    'statusLabel' => 'Diastolique',
                ];
            }
        }

        $totalChart = count($series1);
        $avgVal = $totalChart > 0 ? round($sum / $totalChart, $decimals) : 0.0;
        if ($totalChart === 0) {
            $minVal = 0.0;
            $maxVal = 0.0;
        }

        $chartSummaryText = sprintf(
            'Moyenne %d j : %s %s (min %s · max %s %s) · %d valeur(s) basse(s) · %d valeur(s) haute(s) sur %d relevé(s).',
            $days,
            number_format($avgVal, $decimals, ',', ''),
            (string) $measureType['unit'],
            number_format($minVal, $decimals, ',', ''),
            number_format($maxVal, $decimals, ',', ''),
            (string) $measureType['unit'],
            $lowCount,
            $highCount,
            $totalChart
        );

        // Build ApexCharts configuration JSON
        $annotationsY = [];
        if ($measureType['low'] !== null && $measureType['high'] !== null) {
            $annotationsY[] = [
                'y' => (float) $measureType['low'],
                'y2' => (float) $measureType['high'],
                'fillColor' => '#34C759',
                'borderColor' => '#34C759',
                'opacity' => 0.13,
                'label' => ['text' => 'Zone cible'],
            ];
        }
        if ($measureType['crit_low'] !== null) {
            $annotationsY[] = [
                'y' => (float) $measureType['crit_low'],
                'borderColor' => '#FF3B30',
                'strokeDashArray' => '4 4',
                'label' => ['text' => 'Seuil critique bas (' . str_replace('.', ',', (string) $measureType['crit_low']) . ')'],
            ];
        }
        if ($measureType['crit_high'] !== null && (float) $measureType['crit_high'] <= 300) {
            $annotationsY[] = [
                'y' => (float) $measureType['crit_high'],
                'borderColor' => '#FF3B30',
                'strokeDashArray' => '4 4',
                'label' => ['text' => 'Seuil critique haut (' . str_replace('.', ',', (string) $measureType['crit_high']) . ')'],
            ];
        }

        $apexSeries = [
            [
                'name' => $isBp ? 'Systolique' : (string) $measureType['label'],
                'data' => $series1,
                'color' => '#FF2D55',
            ],
        ];
        if ($isBp && $series2 !== []) {
            $apexSeries[] = [
                'name' => 'Diastolique',
                'data' => $series2,
                'color' => '#5E5CE6',
            ];
        }

        $apexConfig = [
            'chart' => [
                'type' => 'area',
                'height' => 235,
            ],
            'colors' => ['#FF2D55', '#5E5CE6'],
            'series' => $apexSeries,
            'yaxis' => [
                'unit' => (string) $measureType['unit'],
                'decimalsInFloat' => $decimals,
            ],
            'annotations' => [
                'yaxis' => $annotationsY,
            ],
        ];

        // Paginated & filtered history
        $whereSql = 'WHERE m.patient_id = 1 AND m.measure_type_id = :tid AND m.deleted_at IS NULL';
        $params = ['tid' => (int) $measureType['id']];
        if ($filterAlert !== 'all') {
            $whereSql .= ' AND m.alert_level = :alert';
            $params['alert'] = $filterAlert;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `measures` m {$whereSql}");
        $countStmt->execute($params);
        $totalHistory = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $histStmt = $pdo->prepare(
            "SELECT m.*, u.first_name AS author_first_name, u.last_name AS author_last_name
             FROM `measures` m
             LEFT JOIN `users` u ON u.id = m.recorded_by
             {$whereSql}
             ORDER BY m.measured_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $histStmt->execute($params);
        $historyRows = [];
        $currentUserId = Auth::id() ?? 0;
        $currentUserRole = Auth::role() ?? 'viewer';

        foreach ($histStmt->fetchAll(PDO::FETCH_ASSOC) as $hr) {
            $ev = ThresholdService::evaluate(
                $measureType,
                (float) $hr['value'],
                (string) $hr['context'],
                $hr['value2'] !== null ? (float) $hr['value2'] : null,
                $patient
            );
            if ($isBp && $hr['value2'] !== null) {
                $hr['formatted_value'] = sprintf('%d/%d', (int) round((float) $hr['value']), (int) round((float) $hr['value2']));
                if ($hr['value3'] !== null) {
                    $hr['formatted_secondary'] = sprintf('Pouls : %d bpm', (int) round((float) $hr['value3']));
                }
            } elseif ($isGlycemia) {
                $hr['formatted_value'] = ThresholdService::formatGlycemia((float) $hr['value'], $glycemiaUnit);
            } else {
                $hr['formatted_value'] = number_format((float) $hr['value'], $decimals, ',', ' ');
            }
            $hr['time_local'] = ScheduleService::utcToLocalParis((string) $hr['measured_at'], 'd/m/Y à H:i');
            $hr['context_label'] = TimelineService::contextLabel((string) $hr['context']);
            $hr['eval'] = $ev;
            $hr['edit_perm'] = EditWindowService::checkEditPermission($hr, $currentUserId, $currentUserRole, 'check');
            $historyRows[] = $hr;
        }

        return $this->view('health/show', [
            'pageTitle' => (string) $measureType['label'],
            'activeTab' => 'health',
            'backUrl' => '/health',
            'backLabel' => 'Santé',
            'measureType' => $measureType,
            'latest' => $latest,
            'heroEval' => $heroEval,
            'days' => $days,
            'filterAlert' => $filterAlert,
            'page' => $page,
            'perPage' => $perPage,
            'totalHistory' => $totalHistory,
            'historyRows' => $historyRows,
            'chartSummaryText' => $chartSummaryText,
            'apexConfigJson' => json_encode($apexConfig, JSON_UNESCAPED_UNICODE),
            'glycemiaUnit' => $glycemiaUnit,
            'patient' => $patient,
        ]);
    }

    /**
     * POST /health/measures (standard form fallback for E06)
     */
    public function store(Request $request): Response
    {
        $api = new ApiMedicalController();
        $res = $api->storeMeasure($request);
        if ($res->status() === 403) {
            return $res;
        }
        $decoded = json_decode($res->body(), true) ?: [];

        if (!($decoded['ok'] ?? false)) {
            $errMsg = (string) ($decoded['error']['message'] ?? 'Erreur lors de l’enregistrement.');
            Session::flash('error', $errMsg);
            return $this->redirect((string) $request->input('redirect_to', '/health'));
        }

        $data = $decoded['data'] ?? [];
        Session::flash('success', sprintf('Mesure de %s enregistrée.', (string) ($data['type_label'] ?? 'constante')));
        if (!empty($data['show_hypo_protocol'])) {
            Session::flash('error', 'ALERTE HYPOGLYCÉMIE CRITIQUE (< 0,70 g/L) : appliquez immédiatement le protocole de resucrage !');
        }

        $redirectTo = trim((string) $request->input('redirect_to', ''));
        if ($redirectTo !== '' && str_starts_with($redirectTo, '/')) {
            return $this->redirect($redirectTo);
        }

        return $this->redirect('/health/' . urlencode((string) ($data['type_code'] ?? 'glycemia')));
    }

    /**
     * POST /health/measures/{id}/update (enforces the 30-minute rule via EditWindowService)
     */
    public function update(Request $request, string $id): Response
    {
        $this->authorize('vitals', 'update');

        $measureId = (int) $id;
        $userId = Auth::id() ?? 0;
        $role = Auth::role() ?? 'viewer';
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT m.*, mt.code AS type_code, mt.decimals, mt.crit_low, mt.low, mt.high, mt.crit_high,
                    mt.secondary_crit_low, mt.secondary_low, mt.secondary_high, mt.secondary_crit_high
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.id = :id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $measureId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Mesure introuvable.', 404);
        }

        $modifiedReason = trim((string) $request->input('modified_reason', ''));
        $perm = EditWindowService::checkEditPermission($row, $userId, $role, $modifiedReason);
        if (!$perm['allowed']) {
            if ($request->wantsJson()) {
                return Response::jsonError((string) $perm['code'], (string) $perm['message'], $perm['http_status']);
            }
            Session::flash('error', (string) $perm['message']);
            return $this->redirect('/health/' . urlencode((string) $row['type_code']));
        }

        $rawValue = str_replace(',', '.', trim((string) $request->input('value', (string) $row['value'])));
        if (!is_numeric($rawValue)) {
            Session::flash('error', 'Valeur numérique invalide.');
            return $this->redirect('/health/' . urlencode((string) $row['type_code']));
        }
        $newVal = (float) $rawValue;
        $rawVal2 = str_replace(',', '.', trim((string) $request->input('value2', (string) ($row['value2'] ?? ''))));
        $newVal2 = ($rawVal2 !== '' && is_numeric($rawVal2)) ? (float) $rawVal2 : null;
        $newContext = trim((string) $request->input('context', (string) $row['context'])) ?: 'routine';
        $newComment = trim((string) $request->input('comment', (string) ($row['comment'] ?? ''))) ?: null;

        $eval = ThresholdService::evaluate($row, $newVal, $newContext, $newVal2);
        $nowUtc = gmdate('Y-m-d H:i:s');

        $upd = $pdo->prepare(
            'UPDATE `measures`
             SET `value` = :val1,
                 `value2` = :val2,
                 `context` = :ctx,
                 `comment` = :comment,
                 `alert_level` = :alert,
                 `threshold_level` = :tlevel,
                 `modified_reason` = :reason,
                 `updated_at` = :now_utc
             WHERE `id` = :id'
        );
        $upd->execute([
            'val1' => $newVal,
            'val2' => $newVal2,
            'ctx' => $newContext,
            'comment' => $newComment,
            'alert' => $eval['alert_level'],
            'tlevel' => $eval['threshold_level'],
            'reason' => $modifiedReason !== '' ? $modifiedReason : $row['modified_reason'],
            'now_utc' => $nowUtc,
            'id' => $measureId,
        ]);

        $afterData = [
            'value' => $newVal,
            'value2' => $newVal2,
            'context' => $newContext,
            'comment' => $newComment,
            'alert_level' => $eval['alert_level'],
            'modified_reason' => $modifiedReason,
        ];

        AuditService::record(
            'measure.update',
            'measure',
            $measureId,
            [
                'value' => $row['value'],
                'value2' => $row['value2'],
                'context' => $row['context'],
                'comment' => $row['comment'],
            ],
            $afterData,
            1,
            $userId
        );

        if ($request->wantsJson()) {
            return Response::jsonSuccess($afterData);
        }

        Session::flash('success', 'La mesure a été mise à jour et tracée dans le journal d’audit.');
        return $this->redirect('/health/' . urlencode((string) $row['type_code']));
    }

    /**
     * POST /health/measures/{id}/delete (logical deletion only, admin)
     */
    public function delete(Request $request, string $id): Response
    {
        $this->authorize('vitals', 'delete');

        $measureId = (int) $id;
        $userId = Auth::id() ?? 0;
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT m.*, mt.code AS type_code
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.id = :id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $measureId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Mesure introuvable.', 404);
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $del = $pdo->prepare('UPDATE `measures` SET `deleted_at` = :now_utc, `updated_at` = :upd_utc WHERE `id` = :id');
        $del->execute(['now_utc' => $nowUtc, 'upd_utc' => $nowUtc, 'id' => $measureId]);

        AuditService::record('measure.soft_delete', 'measure', $measureId, $row, ['deleted_at' => $nowUtc], 1, $userId);

        Session::flash('success', 'Mesure archivée (suppression logique).');
        return $this->redirect('/health/' . urlencode((string) $row['type_code']));
    }
}
