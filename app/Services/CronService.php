<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * CronService — Exécution des 6 tâches planifiées de Prime Care :
 * 1. `schedule:generate` — génère les attentes de mesures et prises de médicaments sur 48 h
 * 2. `alerts:scan` — exécute les règles périodiques et les escalades (toutes les 5 min)
 * 3. `notifications:send` — dépile `notification_queue` et envoie push + e-mails
 * 4. `alerts:daily` — résumé quotidien par e-mail au responsable (`admin`)
 * 5. `cleanup` — purge des jetons expirés et de la file de notifications envoyées de plus de 30 jours
 * 6. `retention:purge` — purge RGPD selon les durées de conservation configurables (suppression logique puis physique après 30 j, sans jamais toucher aux données médicales).
 */
final class CronService
{
    /** @var array<string, mixed> */
    public const DEFAULT_RETENTION = [
        'medical_auto_purge' => false,
        'attendances_years' => 5,
        'tech_logs_months' => 12,
        'handled_alerts_months' => 24,
        'audit_logs_years' => 6,
        'physical_delete_grace_days' => 30,
    ];

    /**
     * Exécute une tâche spécifique ou toutes les tâches périodiques (`all`).
     *
     * @return array<string, mixed>
     */
    public static function runTask(string $task = 'all', int $patientId = 1): array
    {
        $results = [];

        if ($task === 'all' || $task === 'schedule:generate') {
            $gen = ScheduleService::generateUpcoming48h(null, $patientId);
            $results['schedule:generate'] = $gen;
        }

        if ($task === 'all' || $task === 'alerts:scan') {
            $scan = AlertService::scanPeriodicAlerts($patientId);
            $results['alerts:scan'] = $scan;
        }

        if ($task === 'all' || $task === 'notifications:send') {
            $send = AlertService::processNotificationQueue(50);
            $results['notifications:send'] = $send;
        }

        if ($task === 'alerts:daily') {
            $daily = self::sendDailySummaryEmail($patientId);
            $results['alerts:daily'] = $daily;
        }

        if ($task === 'all' || $task === 'cleanup') {
            $clean = self::cleanupExpiredRecords();
            $results['cleanup'] = $clean;
        }

        if ($task === 'all' || $task === 'retention:purge') {
            $purge = self::runRetentionPurge($patientId);
            $results['retention:purge'] = $purge;
        }

        self::recordHeartbeat(null, $patientId);

        return $results;
    }

    /**
     * Enregistre le dernier passage du cron (dans `storage/cache/cron_last_run.txt` et dans `settings.cron_last_run`).
     */
    public static function recordHeartbeat(?int $timestamp = null, int $patientId = 1): void
    {
        $ts = $timestamp ?? time();
        $iso = gmdate('Y-m-d\TH:i:s\Z', $ts);

        $cacheDir = (string) Config::get('paths.cache', dirname(__DIR__, 2) . '/storage/cache');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        @file_put_contents($cacheDir . '/cron_last_run.txt', (string) $ts);

        try {
            Setting::setValue('cron_last_run', ['ts' => $ts, 'iso' => $iso], $patientId);
        } catch (\Throwable) {
            // Ignore if DB not ready
        }
    }

    /**
     * Retourne l'état du dernier passage du cron (pour `/healthz` et le bandeau d'alerte admin > 15 min).
     *
     * @return array{ts: ?int, iso: ?string, age_seconds: ?int, is_stale: bool}
     */
    public static function getLastRunInfo(int $patientId = 1, int $maxAgeSeconds = 900): array
    {
        $ts = null;

        try {
            $val = Setting::getValue('cron_last_run', null, $patientId);
            if (is_array($val) && isset($val['ts']) && is_numeric($val['ts'])) {
                $ts = (int) $val['ts'];
            }
        } catch (\Throwable) {
            // Fallback to file
        }

        if ($ts === null) {
            $cacheDir = (string) Config::get('paths.cache', dirname(__DIR__, 2) . '/storage/cache');
            $stampFile = $cacheDir . '/cron_last_run.txt';
            if (is_file($stampFile)) {
                $raw = trim((string) @file_get_contents($stampFile));
                if ($raw !== '' && is_numeric($raw)) {
                    $ts = (int) $raw;
                }
            }
        }

        if ($ts === null || $ts <= 0) {
            return [
                'ts' => null,
                'iso' => null,
                'age_seconds' => null,
                'is_stale' => true,
            ];
        }

        $age = max(0, time() - $ts);
        return [
            'ts' => $ts,
            'iso' => gmdate('Y-m-d\TH:i:s\Z', $ts),
            'age_seconds' => $age,
            'is_stale' => $age > $maxAgeSeconds,
        ];
    }

    /**
     * Retourne la configuration de conservation des données (Phase 4 §5.2).
     *
     * @return array<string, mixed>
     */
    public static function getRetentionSettings(int $patientId = 1): array
    {
        $stored = Setting::getValue('data_retention', [], $patientId);
        if (!is_array($stored)) {
            $stored = [];
        }

        return [
            'medical_auto_purge' => false, // Invariant RGPD / médical : jamais purgé automatiquement
            'attendances_years' => max(1, min(20, (int) ($stored['attendances_years'] ?? self::DEFAULT_RETENTION['attendances_years']))),
            'tech_logs_months' => max(1, min(120, (int) ($stored['tech_logs_months'] ?? self::DEFAULT_RETENTION['tech_logs_months']))),
            'handled_alerts_months' => max(1, min(120, (int) ($stored['handled_alerts_months'] ?? self::DEFAULT_RETENTION['handled_alerts_months']))),
            'audit_logs_years' => max(1, min(20, (int) ($stored['audit_logs_years'] ?? self::DEFAULT_RETENTION['audit_logs_years']))),
            'physical_delete_grace_days' => 30,
        ];
    }

    /**
     * Tâche `retention:purge` (Phase 4 §5.2) :
     * Applique les durées de conservation configurées :
     * 1. Suppression logique (`deleted_at = NOW()`) au-delà de la durée de conservation
     * 2. Suppression physique (`DELETE`) 30 jours après la suppression logique
     * 3. Trace chaque exécution dans `audit_logs`
     * 4. NE TOUCHE JAMAIS aux données médicales (`vital_measurements`, `medication_intakes`, `medications`,
     *    `prescriptions`, `meals`, `journal_notes`, `care_checks`, `documents`, `patients`).
     *
     * @return array<string, mixed>
     */
    public static function runRetentionPurge(int $patientId = 1, ?string $referenceNowUtc = null): array
    {
        $pdo = Database::connection();
        $cfg = self::getRetentionSettings($patientId);

        $nowDt = $referenceNowUtc !== null
            ? new DateTimeImmutable($referenceNowUtc, new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowUtc = $nowDt->format('Y-m-d H:i:s');

        $graceCutoff = $nowDt->modify('-' . (int) $cfg['physical_delete_grace_days'] . ' days')->format('Y-m-d H:i:s');
        $attCutoff = $nowDt->modify('-' . (int) $cfg['attendances_years'] . ' years')->format('Y-m-d H:i:s');
        $techCutoff = $nowDt->modify('-' . (int) $cfg['tech_logs_months'] . ' months')->format('Y-m-d H:i:s');
        $alertCutoff = $nowDt->modify('-' . (int) $cfg['handled_alerts_months'] . ' months')->format('Y-m-d H:i:s');
        $auditCutoff = $nowDt->modify('-' . (int) $cfg['audit_logs_years'] . ' years')->format('Y-m-d H:i:s');

        // 1. Pointages (attendances) — 5 ans par défaut
        $sAttSoft = $pdo->prepare(
            'UPDATE `attendances` SET `deleted_at` = :now, `updated_at` = :u
             WHERE `patient_id` = :pid AND `deleted_at` IS NULL AND `recorded_at` < :cutoff'
        );
        $sAttSoft->execute(['now' => $nowUtc, 'u' => $nowUtc, 'pid' => $patientId, 'cutoff' => $attCutoff]);
        $attSoftCount = $sAttSoft->rowCount();

        $sAttHard = $pdo->prepare(
            'DELETE FROM `attendances`
             WHERE `patient_id` = :pid AND `deleted_at` IS NOT NULL AND `deleted_at` < :grace'
        );
        $sAttHard->execute(['pid' => $patientId, 'grace' => $graceCutoff]);
        $attHardCount = $sAttHard->rowCount();

        // 2. Journaux techniques (login_attempts, notification_queue, api_rate_limits) — 12 mois par défaut
        $sLogSoft = $pdo->prepare(
            'UPDATE `login_attempts` SET `deleted_at` = :now
             WHERE `deleted_at` IS NULL AND `attempted_at` < :cutoff'
        );
        $sLogSoft->execute(['now' => $nowUtc, 'cutoff' => $techCutoff]);
        $techSoftCount = $sLogSoft->rowCount();

        $sLogHard = $pdo->prepare(
            'DELETE FROM `login_attempts`
             WHERE `deleted_at` IS NOT NULL AND `deleted_at` < :grace'
        );
        $sLogHard->execute(['grace' => $graceCutoff]);
        $techHardCount = $sLogHard->rowCount();

        $sNotifHard = $pdo->prepare(
            'DELETE FROM `notification_queue` WHERE `created_at` < :cutoff'
        );
        $sNotifHard->execute(['cutoff' => $techCutoff]);
        $techHardCount += $sNotifHard->rowCount();

        try {
            $sRateHard = $pdo->prepare('DELETE FROM `api_rate_limits` WHERE `window_start` < :cutoff');
            $sRateHard->execute(['cutoff' => $techCutoff]);
            $techHardCount += $sRateHard->rowCount();
        } catch (\Throwable) {
            // Ignore if table not yet migrated
        }

        // 3. Alertes traitées (alerts status = 'handled') — 24 mois par défaut
        $sAlertSoft = $pdo->prepare(
            "UPDATE `alerts` SET `deleted_at` = :now, `updated_at` = :u
             WHERE `patient_id` = :pid AND `deleted_at` IS NULL
               AND `status` = 'handled' AND COALESCE(`handled_at`, `created_at`) < :cutoff"
        );
        $sAlertSoft->execute(['now' => $nowUtc, 'u' => $nowUtc, 'pid' => $patientId, 'cutoff' => $alertCutoff]);
        $alertSoftCount = $sAlertSoft->rowCount();

        $sAlertHard = $pdo->prepare(
            "DELETE FROM `alerts`
             WHERE `patient_id` = :pid AND `status` = 'handled'
               AND `deleted_at` IS NOT NULL AND `deleted_at` < :grace"
        );
        $sAlertHard->execute(['pid' => $patientId, 'grace' => $graceCutoff]);
        $alertHardCount = $sAlertHard->rowCount();

        // 4. Journal d'audit (audit_logs) — 6 ans par défaut
        $sAuditSoft = $pdo->prepare(
            'UPDATE `audit_logs` SET `deleted_at` = :now
             WHERE (`patient_id` = :pid OR `patient_id` IS NULL)
               AND `deleted_at` IS NULL AND `created_at` < :cutoff'
        );
        $sAuditSoft->execute(['now' => $nowUtc, 'pid' => $patientId, 'cutoff' => $auditCutoff]);
        $auditSoftCount = $sAuditSoft->rowCount();

        $sAuditHard = $pdo->prepare(
            'DELETE FROM `audit_logs`
             WHERE (`patient_id` = :pid OR `patient_id` IS NULL)
               AND `deleted_at` IS NOT NULL AND `deleted_at` < :grace'
        );
        $sAuditHard->execute(['pid' => $patientId, 'grace' => $graceCutoff]);
        $auditHardCount = $sAuditHard->rowCount();

        $summary = [
            'medical_touched' => 0,
            'attendances_soft' => $attSoftCount,
            'attendances_hard' => $attHardCount,
            'tech_logs_soft' => $techSoftCount,
            'tech_logs_hard' => $techHardCount,
            'handled_alerts_soft' => $alertSoftCount,
            'handled_alerts_hard' => $alertHardCount,
            'audit_logs_soft' => $auditSoftCount,
            'audit_logs_hard' => $auditHardCount,
        ];

        AuditService::record('retention.purge', 'settings', 1, null, $summary, $patientId);

        return $summary;
    }

    /**
     * Déclenchement de secours à l'ouverture de `/` et `/today` au maximum une fois toutes les 5 minutes.
     */
    public static function runFallbackIfNeeded(int $patientId = 1): bool
    {
        $cacheDir = (string) Config::get('paths.cache', dirname(__DIR__, 2) . '/storage/cache');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $stampFile = $cacheDir . '/cron_fallback_last_run.txt';
        if (is_file($stampFile)) {
            $lastTs = (int) trim((string) @file_get_contents($stampFile));
            if (time() - $lastTs < 300) {
                return false;
            }
        }

        $lockFile = $cacheDir . '/cron.lock';
        $fp = @fopen($lockFile, 'c+');
        if ($fp === false) {
            return false;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        try {
            @file_put_contents($stampFile, (string) time());
            ScheduleService::generateUpcoming48h($patientId);
            ScheduleService::syncExpectationStatuses($patientId);
            AlertService::scanPeriodicAlerts($patientId);
            AlertService::processNotificationQueue(25);
        } catch (\Throwable) {
            // Do not break user page load on fallback cron error
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return true;
    }

    /**
     * Tâche `alerts:daily` — Envoie le résumé quotidien par e-mail au responsable (`admin`).
     *
     * @return array{sent: int}
     */
    public static function sendDailySummaryEmail(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $localTz = PlanningService::localTz();
        $todayLocal = (new DateTimeImmutable('now', $localTz))->format('d/m/Y');

        $openCount = AlertService::countOpenAlerts($patientId);
        $obs = IntakeService::getObservanceSummary($patientId);

        $stmt = $pdo->prepare(
            "SELECT id, first_name, last_name, email
             FROM users
             WHERE patient_id = :pid AND deleted_at IS NULL AND is_active = 1 AND role = 'admin'"
        );
        $stmt->execute(['pid' => $patientId]);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sentCount = 0;
        foreach ($admins as $adm) {
            if (empty($adm['email'])) {
                continue;
            }
            $name = trim($adm['first_name'] . ' ' . $adm['last_name']);
            $subject = "[Prime Care] Synthèse quotidienne du {$todayLocal}";
            $html = '<h2 style="margin:0 0 12px;font-size:18px;color:#0A1633;">Synthèse quotidienne — M. Henri Benali</h2>'
                . '<p style="margin:0 0 16px;color:#334155;font-size:14px;">Bonjour ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ', voici le point de situation du ' . $todayLocal . ' :</p>'
                . '<ul style="margin:0 0 20px;padding-left:20px;color:#1E293B;font-size:14px;line-height:1.6;">'
                . '<li><strong>Alertes ouvertes :</strong> ' . $openCount . '</li>'
                . '<li><strong>Observance du jour :</strong> ' . $obs['today_given'] . ' / ' . $obs['today_total'] . ' prises (' . $obs['today_pct'] . ' %)</li>'
                . '<li><strong>Observance 7 jours :</strong> ' . $obs['days_7_pct'] . ' %</li>'
                . '</ul>';

            if (MailService::send((string) $adm['email'], $subject, $html, strip_tags($html), $name)) {
                $sentCount++;
            }
        }

        return ['sent' => $sentCount];
    }

    /**
     * Tâche `cleanup` — Purge des jetons expirés et de la file de notifications envoyées de plus de 30 jours.
     *
     * @return array{tokens_deleted: int, notifications_deleted: int}
     */
    public static function cleanupExpiredRecords(): array
    {
        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $cutoff30d = gmdate('Y-m-d H:i:s', time() - 30 * 86400);

        $d1 = $pdo->prepare('DELETE FROM remember_tokens WHERE expires_at < :now');
        $d1->execute(['now' => $nowUtc]);

        $d2 = $pdo->prepare('DELETE FROM password_resets WHERE expires_at < :now');
        $d2->execute(['now' => $nowUtc]);

        $d3 = $pdo->prepare('DELETE FROM trusted_devices WHERE expires_at < :now');
        $d3->execute(['now' => $nowUtc]);

        $d4 = $pdo->prepare("DELETE FROM notification_queue WHERE status = 'sent' AND sent_at < :cutoff");
        $d4->execute(['cutoff' => $cutoff30d]);

        return [
            'tokens_deleted' => $d1->rowCount() + $d2->rowCount() + $d3->rowCount(),
            'notifications_deleted' => $d4->rowCount(),
        ];
    }
}
