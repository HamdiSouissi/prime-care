<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\HoursCalculatorService;
use App\Services\PlanningService;
use App\Services\Policy;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * PlanningController — Planning d'équipe (E16) :
 * - Vue semaine (par défaut) et vue mois, colorée par aide-soignante
 * - Création / modification en bottom sheet, remplacement, duplication sur N semaines, absences
 * - Contrôle de couverture ("Non couvert" rouge hachuré) et refus serveur (422) des chevauchements
 * - Côté aide-soignante (`caregiver`) : lecture seule filtrée sur ses propres créneaux.
 */
final class PlanningController extends Controller
{
    /**
     * GET /planning
     */
    public function index(Request $request): Response
    {
        $this->authorize('schedule', 'view');

        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;
        $canManage = Policy::allows($role, 'schedule', 'manage');
        $onlyUserId = ($role === 'caregiver') ? $userId : null;

        $viewMode = (string) $request->query('view', 'week');
        if (!in_array($viewMode, ['week', 'month'], true)) {
            $viewMode = 'week';
        }

        $localTz = PlanningService::localTz();
        $todayLocal = new DateTimeImmutable('now', $localTz);

        // Week navigation
        $weekParam = trim((string) $request->query('week', ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam)) {
            $weekParam = $todayLocal->format('Y-m-d');
        }
        $weekRef = new DateTimeImmutable($weekParam . ' 00:00:00', $localTz);
        $isoDow = (int) $weekRef->format('N');
        $mondayDt = ($isoDow === 1) ? $weekRef : $weekRef->modify('-' . ($isoDow - 1) . ' days');
        $sundayDt = $mondayDt->modify('+6 days');

        // Month navigation
        $monthParam = trim((string) $request->query('month', ''));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = $mondayDt->format('Y-m');
        }
        [$mYear, $mMonth] = array_map('intval', explode('-', $monthParam));
        $monthStartDt = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $mYear, $mMonth), $localTz);
        $monthEndDt = $monthStartDt->modify('last day of this month');

        if ($viewMode === 'month') {
            $rangeStart = $monthStartDt->format('Y-m-d');
            $rangeEnd = $monthEndDt->format('Y-m-d');
        } else {
            $rangeStart = $mondayDt->format('Y-m-d');
            $rangeEnd = $sundayDt->format('Y-m-d');
        }

        $shifts = PlanningService::getShiftsForLocalRange($rangeStart, $rangeEnd, $onlyUserId, 1);
        $absences = PlanningService::getAbsencesForRange($rangeStart, $rangeEnd, $onlyUserId, 1);
        $templates = PlanningService::getTemplates(1);
        $team = PlanningService::getTeamMembers(1);

        // Group shifts by local_date
        $shiftsByDate = [];
        $uncoveredCount = 0;
        $replacementNeededCount = 0;
        foreach ($shifts as $sh) {
            $d = (string) $sh['local_date'];
            $shiftsByDate[$d][] = $sh;
            if (!empty($sh['is_uncovered'])) {
                $uncoveredCount++;
            }
            if (!empty($sh['needs_replacement'])) {
                $replacementNeededCount++;
            }
        }

        // Build 7 days array for Week view
        $weekDays = [];
        $frDayNames = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
        $frShortNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        for ($i = 0; $i < 7; $i++) {
            $dDt = $mondayDt->modify("+{$i} days");
            $ymd = $dDt->format('Y-m-d');
            $dow = (int) $dDt->format('N');
            $weekDays[$ymd] = [
                'date' => $ymd,
                'day_name' => $frDayNames[$dow],
                'short_name' => $frShortNames[$dow],
                'day_num' => $dDt->format('d/m'),
                'is_today' => ($ymd === $todayLocal->format('Y-m-d')),
                'holiday_label' => HoursCalculatorService::getFrenchHolidayLabel($ymd),
                'shifts' => $shiftsByDate[$ymd] ?? [],
            ];
        }

        // Build calendar cells for Month view (Monday-aligned grid)
        $monthGridStart = $monthStartDt;
        $startDow = (int) $monthGridStart->format('N');
        if ($startDow > 1) {
            $monthGridStart = $monthGridStart->modify('-' . ($startDow - 1) . ' days');
        }
        $monthGridEnd = $monthEndDt;
        $endDow = (int) $monthGridEnd->format('N');
        if ($endDow < 7) {
            $monthGridEnd = $monthGridEnd->modify('+' . (7 - $endDow) . ' days');
        }

        $monthCells = [];
        $cursor = $monthGridStart;
        while ($cursor <= $monthGridEnd) {
            $ymd = $cursor->format('Y-m-d');
            $monthCells[] = [
                'date' => $ymd,
                'day_number' => $cursor->format('j'),
                'in_month' => ($cursor->format('Y-m') === $monthParam),
                'is_today' => ($ymd === $todayLocal->format('Y-m-d')),
                'shifts' => $shiftsByDate[$ymd] ?? [],
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $this->view('planning/index', [
            'pageTitle' => $role === 'caregiver' ? 'Mon planning' : 'Planning d’équipe',
            'activeTab' => 'planning',
            'wideLayout' => true,
            'viewMode' => $viewMode,
            'canManage' => $canManage,
            'isCaregiverReadOnly' => ($role === 'caregiver'),
            'mondayDate' => $mondayDt->format('Y-m-d'),
            'sundayDate' => $sundayDt->format('Y-m-d'),
            'prevWeek' => $mondayDt->modify('-7 days')->format('Y-m-d'),
            'nextWeek' => $mondayDt->modify('+7 days')->format('Y-m-d'),
            'monthParam' => $monthParam,
            'monthLabel' => HoursCalculatorService::frenchMonthLabel($mYear, $mMonth),
            'prevMonth' => $monthStartDt->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStartDt->modify('+1 month')->format('Y-m'),
            'weekDays' => $weekDays,
            'monthCells' => $monthCells,
            'templates' => $templates,
            'team' => $team,
            'absences' => $absences,
            'uncoveredCount' => $uncoveredCount,
            'replacementNeededCount' => $replacementNeededCount,
        ]);
    }

    /**
     * POST /planning/shifts & POST /api/shifts
     */
    public function storeShift(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        if (!Policy::allows($role, 'schedule', 'manage')) {
            return $request->wantsJson()
                ? Response::jsonError('FORBIDDEN', 'Accès réservé au responsable et à la coordinatrice.', 403)
                : Response::html($this->renderError(403), 403);
        }

        try {
            $shift = PlanningService::createShift($request->all(), Auth::id() ?? 1, 1);
            if ($request->wantsJson()) {
                return Response::jsonSuccess($shift, 201);
            }
            Session::flash('success', 'Créneau ajouté au planning.');
            return $this->redirect('/planning?week=' . urlencode((string) ($shift['local_date'] ?? '')));
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('OVERLAP_OR_INVALID', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::jsonError('OVERLAP_OR_INVALID', $e->getMessage(), 422);
        }
    }

    /**
     * POST /planning/shifts/{id}/update & POST /api/shifts/{id}/update
     */
    public function updateShift(Request $request, string $id): Response
    {
        $role = Auth::role() ?? 'viewer';
        if (!Policy::allows($role, 'schedule', 'manage')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        try {
            $shift = PlanningService::updateShift((int) $id, $request->all(), Auth::id() ?? 1, 1);
            if ($request->wantsJson()) {
                return Response::jsonSuccess($shift, 200);
            }
            Session::flash('success', 'Créneau mis à jour.');
            return $this->redirect('/planning?week=' . urlencode((string) ($shift['local_date'] ?? '')));
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('OVERLAP_OR_INVALID', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
            return Response::jsonError('OVERLAP_OR_INVALID', $e->getMessage(), 422);
        }
    }

    /**
     * POST /planning/shifts/{id}/delete
     */
    public function deleteShift(Request $request, string $id): Response
    {
        $this->authorize('schedule', 'manage');
        PlanningService::deleteShift((int) $id, Auth::id() ?? 1, 1);

        if ($request->wantsJson()) {
            return Response::jsonSuccess(['deleted' => (int) $id]);
        }
        Session::flash('success', 'Créneau annulé.');
        return $this->redirect((string) $request->input('redirect_to', '/planning'));
    }

    /**
     * POST /planning/duplicate & POST /api/shifts/duplicate
     */
    public function duplicateWeek(Request $request): Response
    {
        $this->authorize('schedule', 'manage');
        $monday = trim((string) ($request->input('week_start', '') ?: $request->input('source_monday', '')));
        $weeks = max(1, min(12, (int) $request->input('weeks_count', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday)) {
            if ($request->wantsJson()) {
                return Response::jsonError('INVALID_WEEK', 'Date de semaine source invalide.', 422);
            }
            Session::flash('error', 'Date de semaine source invalide.');
            return $this->redirect('/planning');
        }

        $res = PlanningService::duplicateWeek($monday, $weeks, Auth::id() ?? 1, 1);
        if ($request->wantsJson()) {
            return Response::jsonSuccess($res, 200);
        }

        Session::flash(
            'success',
            sprintf(
                'Semaine dupliquée sur %d semaine(s) : %d créneau(x) créé(s)%s.',
                $weeks,
                $res['created'],
                $res['skipped'] > 0 ? " ({$res['skipped']} ignoré(s) pour éviter un chevauchement)" : ''
            )
        );
        return $this->redirect('/planning?week=' . urlencode($monday));
    }

    /**
     * POST /planning/absences
     */
    public function storeAbsence(Request $request): Response
    {
        $this->authorize('schedule', 'manage');
        try {
            $id = PlanningService::createAbsence($request->all(), Auth::id() ?? 1, 1);
            if ($request->wantsJson()) {
                return Response::jsonSuccess(['id' => $id], 201);
            }
            Session::flash('success', 'Absence enregistrée. Les créneaux concernés sont marqués « À remplacer ».');
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('VALIDATION_ERROR', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect((string) $request->input('redirect_to', '/planning'));
    }

    /**
     * POST /planning/absences/{id}/delete
     */
    public function deleteAbsence(Request $request, string $id): Response
    {
        $this->authorize('schedule', 'manage');
        PlanningService::deleteAbsence((int) $id, Auth::id() ?? 1, 1);
        Session::flash('success', 'Absence supprimée.');
        return $this->redirect((string) $request->input('redirect_to', '/planning'));
    }

    /**
     * POST /planning/templates
     */
    public function saveTemplate(Request $request): Response
    {
        $this->authorize('schedule', 'manage');
        try {
            PlanningService::saveTemplate($request->all(), Auth::id() ?? 1, 1);
            Session::flash('success', 'Modèle de créneau enregistré.');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/planning');
    }

    private function renderError(int $code): string
    {
        return 'Accès refusé (' . $code . ').';
    }
}
