<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CronService;
use Throwable;

/**
 * Unauthenticated health-check endpoint (GET /healthz, Phase 4 §6.2):
 * Returns 200 {"ok":true,"db":true,"cron_last_run":"..."} or 503 if the database does not respond.
 */
final class HealthzController extends Controller
{
    public function status(Request $request): Response
    {
        $dbOk = false;
        $cronIso = null;

        try {
            $pdo = Database::connection();
            $res = $pdo->query('SELECT 1')->fetchColumn();
            $dbOk = ((int) $res === 1);

            $cronInfo = CronService::getLastRunInfo(1);
            $cronIso = $cronInfo['iso'];
        } catch (Throwable) {
            $dbOk = false;
        }

        if (!$dbOk) {
            return Response::json([
                'ok' => false,
                'db' => false,
                'cron_last_run' => $cronIso,
            ], 503);
        }

        return Response::json([
            'ok' => true,
            'db' => true,
            'cron_last_run' => $cronIso ?? gmdate('Y-m-d\TH:i:s\Z'),
        ], 200);
    }
}
