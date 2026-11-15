<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\ExportService;
use App\Services\ReportService;
use App\Services\ScheduleService;
use RuntimeException;

/**
 * ReportController — Rapports médicaux imprimables A4 (E23, §2.1) et Exports CSV (§3).
 */
final class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('reports', 'view');

        $periodParam = trim((string) $request->query('period', '30d'));
        $fromParam = trim((string) $request->query('from', '')) ?: null;
        $toParam = trim((string) $request->query('to', '')) ?: null;

        $period = ReportService::resolvePeriod($periodParam, $fromParam, $toParam);
        $report = ReportService::buildMedicalReport($period['from_utc'], $period['to_utc'], 1);

        $user = Auth::user() ?? [];
        $editorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Responsable';
        $editedAtLocal = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'd/m/Y à H:i');

        $rightAction = '<button type="button" class="pc-btn pc-btn--primary pc-btn--sm pc-no-print" data-print-report>'
            . icon('printer', '', 15) . '<span>Imprimer</span></button>';

        $html = View::render('reports/index', [
            'pageTitle' => 'Rapports & Synthèse',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'rightActionHtml' => $rightAction,
            'wideLayout' => true,
            'period' => $period,
            'report' => $report,
            'editorName' => $editorName,
            'editedAtLocal' => $editedAtLocal,
            'userRole' => (string) (Auth::role() ?? 'viewer'),
        ]);

        return Response::html($html);
    }

    public function exports(Request $request): Response
    {
        $this->authorize('reports', 'export');

        $role = (string) (Auth::role() ?? 'viewer');
        $periodParam = trim((string) $request->query('period', '30d'));
        $fromParam = trim((string) $request->query('from', '')) ?: null;
        $toParam = trim((string) $request->query('to', '')) ?: null;
        $period = ReportService::resolvePeriod($periodParam, $fromParam, $toParam);

        $availableExports = [];
        foreach (ExportService::EXPORT_CATALOG as $type => $meta) {
            if (ExportService::canExport($role, $type)) {
                $availableExports[$type] = $meta;
            }
        }

        $html = View::render('reports/exports', [
            'pageTitle' => 'Exports CSV',
            'activeTab' => 'more',
            'backUrl' => '/reports',
            'backLabel' => 'Rapports',
            'wideLayout' => true,
            'period' => $period,
            'availableExports' => $availableExports,
            'userRole' => $role,
        ]);

        return Response::html($html);
    }

    public function downloadExport(Request $request, string $type): Response
    {
        $role = (string) (Auth::role() ?? '');
        if (!ExportService::canExport($role, $type)) {
            throw new RuntimeException('Accès non autorisé à cet export.', 403);
        }

        $periodParam = trim((string) $request->query('period', '30d'));
        $fromParam = trim((string) $request->query('from', '')) ?: null;
        $toParam = trim((string) $request->query('to', '')) ?: null;
        $period = ReportService::resolvePeriod($periodParam, $fromParam, $toParam);

        $res = ExportService::generateExportCsv(
            $type,
            $period['from_utc'],
            $period['to_utc'],
            $period['from_ymd'],
            $period['to_ymd'],
            (int) (Auth::id() ?? 1),
            1
        );

        return new Response($res['csv'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $res['filename'] . '"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
