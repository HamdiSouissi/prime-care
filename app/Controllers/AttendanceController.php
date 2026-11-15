<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AttendanceService;
use App\Services\HoursCalculatorService;
use App\Services\PlanningService;
use App\Services\Policy;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

/**
 * AttendanceController — Pointage, contrôle hebdomadaire (E17), récapitulatif mensuel des heures (E18)
 * et export CSV de paie (UTF-8 avec BOM, séparateur ';').
 */
final class AttendanceController extends Controller
{
    /**
     * GET /attendance (E17 — Contrôle des pointages / Mon pointage)
     */
    public function index(Request $request): Response
    {
        $this->authorize('timeclock', 'view');

        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;
        $canValidate = Policy::allows($role, 'timeclock', 'validate');

        $localTz = PlanningService::localTz();
        $todayLocal = new DateTimeImmutable('now', $localTz);

        $weekParam = trim((string) $request->query('week', ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam)) {
            $weekParam = $todayLocal->format('Y-m-d');
        }

        $weeklyGrid = AttendanceService::getWeeklyControlGrid($weekParam, 1);
        $pendingAttendances = $canValidate ? AttendanceService::getPendingAttendances(1) : [];
        $clockState = AttendanceService::getUserClockState($userId, 1);

        // Recent attendances for current caregiver (or all if admin/coordinator)
        $pdo = Database::connection();
        $histSql = 'SELECT a.*, u.first_name, u.last_name, u.color_hex
                    FROM attendances a
                    INNER JOIN users u ON u.id = a.user_id
                    WHERE a.patient_id = 1 AND a.deleted_at IS NULL';
        $histParams = [];
        if (!$canValidate) {
            $histSql .= ' AND a.user_id = :uid';
            $histParams['uid'] = $userId;
        }
        $histSql .= ' ORDER BY COALESCE(a.declared_at, a.recorded_at) DESC, a.id DESC LIMIT 25';
        $hStmt = $pdo->prepare($histSql);
        $hStmt->execute($histParams);
        $recentAttendances = $hStmt->fetchAll(PDO::FETCH_ASSOC);

        $utcTz = PlanningService::utcTz();
        foreach ($recentAttendances as &$att) {
            $effStr = (string) ($att['declared_at'] ?? $att['recorded_at']);
            $effDt = (new DateTimeImmutable($effStr, $utcTz))->setTimezone($localTz);
            $att['local_date'] = $effDt->format('d/m/Y');
            $att['local_time'] = $effDt->format('H\hi');
        }
        unset($att);

        return $this->view('attendance/index', [
            'pageTitle' => $canValidate ? 'Contrôle des pointages' : 'Mon pointage',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'wideLayout' => true,
            'canValidate' => $canValidate,
            'weeklyGrid' => $weeklyGrid,
            'pendingAttendances' => $pendingAttendances,
            'recentAttendances' => $recentAttendances,
            'clockState' => $clockState,
            'todayYmd' => $todayLocal->format('Y-m-d'),
            'nowHm' => $todayLocal->format('H:i'),
        ]);
    }

    /**
     * GET /attendance/hours (E18 — Récapitulatif mensuel des heures : normales, nuit 21h-6h, dimanche, fériés)
     */
    public function hours(Request $request): Response
    {
        $this->authorize('timeclock', 'view');

        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;
        $canValidate = Policy::allows($role, 'timeclock', 'validate');
        $onlyUserId = $canValidate ? null : $userId;

        $localTz = PlanningService::localTz();
        $todayLocal = new DateTimeImmutable('now', $localTz);

        $monthParam = trim((string) $request->query('month', ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = $todayLocal->format('Y-m');
        }
        [$year, $month] = array_map('intval', explode('-', $monthParam));
        $monthDt = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $localTz);

        $summary = HoursCalculatorService::calculateMonthlySummary($year, $month, $onlyUserId, 1);
        $holidays = HoursCalculatorService::frenchHolidaysForYear($year);

        return $this->view('attendance/hours', [
            'pageTitle' => 'Récapitulatif mensuel des heures',
            'activeTab' => 'more',
            'backUrl' => '/attendance',
            'backLabel' => 'Pointages',
            'wideLayout' => true,
            'canValidate' => $canValidate,
            'monthParam' => $monthParam,
            'prevMonth' => $monthDt->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthDt->modify('+1 month')->format('Y-m'),
            'summary' => $summary,
            'holidays' => $holidays,
        ]);
    }

    /**
     * GET /attendance/export-csv (Export CSV de paie : séparateur ';', UTF-8 avec BOM)
     */
    public function exportCsv(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        if (!Policy::allows($role, 'timeclock', 'validate')) {
            return Response::jsonError('FORBIDDEN', 'Export CSV de paie réservé au responsable et à la coordinatrice.', 403);
        }

        $localTz = PlanningService::localTz();
        $todayLocal = new DateTimeImmutable('now', $localTz);

        $monthParam = trim((string) $request->query('month', ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = $todayLocal->format('Y-m');
        }
        [$year, $month] = array_map('intval', explode('-', $monthParam));

        $csv = HoursCalculatorService::generatePayrollCsv($year, $month, null, 1);
        $filename = sprintf('prime-care-heures-paie-%04d-%02d.csv', $year, $month);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Alias API explicite pour POST /api/attendance/punch
     */
    public function apiPunch(Request $request): Response
    {
        return $this->punch($request);
    }

    /**
     * POST /attendance/punch & POST /api/attendance/punch
     */
    public function punch(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'timeclock', 'punch')) {
            return Response::jsonError('FORBIDDEN', 'Vous n’avez pas les droits pour pointer.', 403);
        }

        if (!AttendanceService::hasAcceptedGdprNotice($userId)) {
            if ($request->wantsJson() || str_starts_with($request->path(), '/api/')) {
                return Response::jsonError(
                    'GDPR_NOTICE_REQUIRED',
                    'Veuillez valider la mention d’information RGPD avant votre premier pointage.',
                    403
                );
            }
            Session::flash('error', 'Veuillez valider la mention d’information RGPD avant votre premier pointage.');
            return $this->redirect((string) $request->input('redirect_to', '/'));
        }

        try {
            $att = AttendanceService::recordPunch(
                $userId,
                $request->all(),
                $request->ip(),
                1
            );

            $type = (string) ($att['type'] ?? 'in');
            $status = (string) ($att['status'] ?? 'valid');
            $reason = (string) ($att['reason'] ?? '');
            $localTime = (new DateTimeImmutable((string) $att['recorded_at'], PlanningService::utcTz()))
                ->setTimezone(PlanningService::localTz())
                ->format('H\hi');

            $msg = $type === 'in'
                ? "Arrivée pointée à {$localTime}"
                : "Départ pointé à {$localTime}";
            if ($status === 'pending' && $reason !== '') {
                $msg .= " ({$reason} — soumis à validation)";
            }

            $payload = [
                'attendance' => $att,
                'local_time' => $localTime,
                'message' => $msg,
                'prompt_handover' => ($type === 'out'),
            ];

            if ($request->wantsJson() || str_starts_with($request->path(), '/api/')) {
                return Response::jsonSuccess($payload, 201);
            }

            Session::flash('success', $msg);
            if ($type === 'out') {
                Session::flash('prompt_handover', '1');
            }
            return $this->redirect((string) $request->input('redirect_to', '/'));
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::jsonError('GDPR_NOTICE_REQUIRED', $e->getMessage(), 403);
            }
            return Response::jsonError('PUNCH_ERROR', $e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || str_starts_with($request->path(), '/api/')) {
                return Response::jsonError('PUNCH_ERROR', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
            return $this->redirect((string) $request->input('redirect_to', '/'));
        }
    }

    /**
     * POST /attendance/declare & POST /api/attendance/declare ("J'ai oublié de pointer")
     */
    public function declareForgot(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;

        if (!Policy::allows($role, 'timeclock', 'punch')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        if (!AttendanceService::hasAcceptedGdprNotice($userId)) {
            if ($request->wantsJson() || str_starts_with($request->path(), '/api/')) {
                return Response::jsonError(
                    'GDPR_NOTICE_REQUIRED',
                    'Veuillez valider la mention d’information RGPD avant votre premier pointage.',
                    403
                );
            }
            Session::flash('error', 'Veuillez valider la mention d’information RGPD avant votre premier pointage.');
            return $this->redirect((string) $request->input('redirect_to', '/'));
        }

        try {
            $att = AttendanceService::declareForgotPunch(
                $userId,
                $request->all(),
                $request->ip(),
                1
            );

            $msg = 'Oubli de pointage déclaré et transmis au responsable pour validation.';
            if ($request->wantsJson() || str_starts_with($request->path(), '/api/')) {
                return Response::jsonSuccess([
                    'attendance' => $att,
                    'message' => $msg,
                ], 201);
            }

            Session::flash('success', $msg);
            return $this->redirect((string) $request->input('redirect_to', '/'));
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::jsonError('GDPR_NOTICE_REQUIRED', $e->getMessage(), 403);
            }
            return Response::jsonError('DECLARE_ERROR', $e->getMessage(), 422);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || str_starts_with($request->path(), '/api/')) {
                return Response::jsonError('DECLARE_ERROR', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
            return $this->redirect((string) $request->input('redirect_to', '/attendance'));
        }
    }

    /**
     * POST /attendance/gdpr (Validation de la notice d'information RGPD à la première utilisation)
     */
    public function acceptGdpr(Request $request): Response
    {
        $userId = Auth::id() ?? 0;
        if ($userId <= 0) {
            return Response::jsonError('UNAUTHENTICATED', 'Non connecté.', 401);
        }

        AttendanceService::acceptGdprNotice($userId);

        if ($request->wantsJson()) {
            return Response::jsonSuccess(['gdpr_accepted' => true]);
        }
        Session::flash('success', 'Information sur le traitement des données de pointage prise en compte.');
        return $this->redirect((string) $request->input('redirect_to', '/'));
    }

    /**
     * POST /attendance/{id}/validate
     */
    public function validate(Request $request, string $id): Response
    {
        $this->authorize('timeclock', 'validate');
        try {
            $att = AttendanceService::validateAttendance((int) $id, Auth::id() ?? 1, 1);
            if ($request->wantsJson()) {
                return Response::jsonSuccess($att);
            }
            Session::flash('success', 'Pointage validé et intégré au calcul des heures.');
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect((string) $request->input('redirect_to', '/attendance'));
    }

    /**
     * POST /attendance/{id}/reject
     */
    public function reject(Request $request, string $id): Response
    {
        $this->authorize('timeclock', 'validate');
        $reason = trim((string) ($request->input('rejection_reason', '') ?: $request->input('reason', '')));
        try {
            $att = AttendanceService::rejectAttendance((int) $id, $reason, Auth::id() ?? 1, 1);
            if ($request->wantsJson()) {
                return Response::jsonSuccess($att);
            }
            Session::flash('success', 'Pointage refusé.');
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('REJECTION_ERROR', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect((string) $request->input('redirect_to', '/attendance'));
    }
}
