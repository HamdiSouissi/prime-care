<?php

declare(strict_types=1);

// Prime Care — Scheduled Background Runner (Cron)
// Crontab: */5 * * * * php /chemin/cron/run.php
// Usage:   php cron/run.php [schedule:generate]

require_once dirname(__DIR__) . '/app/Helpers/bootstrap.php';

use App\Core\Config;
use App\Core\Logger;
use App\Services\ScheduleService;

$cacheDir = (string) Config::get('paths.cache', dirname(__DIR__) . '/storage/cache');
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}

$lockFile = $cacheDir . '/cron.lock';
$fp = fopen($lockFile, 'c+');
if ($fp === false) {
    fwrite(STDERR, "Impossible d’ouvrir le fichier de verrou cron ({$lockFile}).\n");
    exit(1);
}

if (!flock($fp, LOCK_EX | LOCK_NB)) {
    // Another cron execution is already running
    fclose($fp);
    exit(0);
}

ftruncate($fp, 0);
fwrite($fp, (string) getmypid() . ' ' . gmdate('Y-m-d\TH:i:s\Z') . "\n");
fflush($fp);

$task = $argv[1] ?? 'all';

try {
    if ($task === 'all' || $task === 'schedule:generate') {
        $res = ScheduleService::generateUpcoming48h();
        ScheduleService::syncExpectationStatuses();
        echo sprintf(
            "[%s] schedule:generate OK (prises créées : %d, mesures attendues créées : %d)\n",
            gmdate('Y-m-d H:i:s'),
            $res['intakes_inserted'],
            $res['expectations_inserted']
        );
    }
} catch (Throwable $e) {
    Logger::error('Cron execution failed', [
        'task' => $task,
        'error' => $e->getMessage(),
    ]);
    flock($fp, LOCK_UN);
    fclose($fp);
    fwrite(STDERR, "[ERREUR CRON] " . $e->getMessage() . "\n");
    exit(1);
}

flock($fp, LOCK_UN);
fclose($fp);
exit(0);
