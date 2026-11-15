<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * CronService — Exécution des 5 tâches planifiées de Prime Care :
 * 1. `schedule:generate` — génère les attentes de mesures et prises de médicaments sur 48 h
 * 2. `alerts:scan` — exécute les règles périodiques et les escalades (toutes les 5 min)
 * 3. `notifications:send` — dépile `notification_queue` et envoie push + e-mails
 * 4. `alerts:daily` — résumé quotidien par e-mail au responsable (`admin`)
 * 5. `cleanup` — purge des jetons expirés et de la file de notifications envoyées de plus de 30 jours.
 */
final class CronService
{
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

        return $results;
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

        $stampFile = $cacheDir . '/cron_last_run.txt';
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
