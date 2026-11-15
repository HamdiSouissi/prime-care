<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\InsulinService;
use App\Services\ScheduleService;
use App\Services\ShiftTimelineService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * E04 — "Aujourd'hui" screen: displays all medication intakes, expected measurements,
 * and meals of the day grouped by moment (Matin, Midi, Soir, Coucher), with day navigation
 * and idempotent schedule generation.
 */
final class TodayController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('dashboard', 'view');

        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $todayStr = (new DateTimeImmutable('now', $parisTz))->format('Y-m-d');

        $selectedDate = trim((string) $request->query('date', $todayStr));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = $todayStr;
        }

        // Trigger idempotent generation for the selected date & upcoming 48h
        ScheduleService::generateUpcoming48h();
        $program = ShiftTimelineService::getDayProgram($selectedDate, 1);

        $selDt = new DateTimeImmutable($selectedDate . ' 12:00:00', $parisTz);
        $prevDate = $selDt->sub(new DateInterval('P1D'))->format('Y-m-d');
        $nextDate = $selDt->add(new DateInterval('P1D'))->format('Y-m-d');

        $rotation = InsulinService::getRotationSuggestion(1);

        return $this->view('today/index', [
            'pageTitle' => 'Aujourd’hui',
            'activeTab' => 'today',
            'selectedDate' => $selectedDate,
            'todayDate' => $todayStr,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'isToday' => $selectedDate === $todayStr,
            'program' => $program,
            'rotation' => $rotation,
        ]);
    }
}
