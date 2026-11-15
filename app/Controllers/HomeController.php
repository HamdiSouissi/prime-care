<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Models\Patient;
use App\Models\PatientContact;
use App\Models\User;
use App\Services\AttentionService;
use App\Services\HandoverService;
use App\Services\InsulinService;
use App\Services\IntakeService;
use App\Services\QrCodeService;
use App\Services\ScheduleService;
use App\Services\ShiftTimelineService;

/**
 * Handles the Home dashboard (Caregiver Shift Chronology & Admin Synthesis Dashboard),
 * the "Plus" menu, "À propos", "Installer l'application" (with server-side QR code),
 * and Phase 3–4 module placeholders.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('dashboard', 'view');

        $userId = Auth::id() ?? 0;
        $role = Auth::role() ?? 'viewer';
        $cronInfo = \App\Services\CronService::getLastRunInfo(1, 900);
        $cronStale = ($role === 'admin' && (bool) ($cronInfo['is_stale'] ?? false));

        // Idempotent schedule generation & periodic alerts safety net (debounced to 5 min)
        ScheduleService::generateUpcoming48h();
        \App\Services\CronService::runFallbackIfNeeded(1);

        $patientModel = new Patient();
        $patient = $patientModel->current(1);
        $contactModel = new PatientContact();
        $contacts = $contactModel->forPatient(1);

        $teamCount = 0;
        if (Auth::hasRole('admin')) {
            $userModel = new User();
            $teamCount = count($userModel->allTeamMembers());
        }

        $todayLocal = ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'Y-m-d');
        $dayProgram = ShiftTimelineService::getDayProgram($todayLocal, 1);
        $unreadHandover = HandoverService::getLatestUnreadHandover($userId, 1);
        $attention = AttentionService::getCurrentAttentionPoints($userId, 1);
        $observance = IntakeService::getObservanceSummary(1);
        $adminMetrics = ShiftTimelineService::getAdminKeyMetrics(1);
        $rotation = InsulinService::getRotationSuggestion(1);
        $clockState = \App\Services\AttendanceService::getUserClockState($userId, 1);
        $nextShift = \App\Services\PlanningService::getNextOrCurrentShiftForUser($userId, 1);
        $openAlertsCount = \App\Services\AlertService::countOpenAlerts(1);
        $trends7d = in_array($role, ['admin', 'coordinator', 'viewer'], true)
            ? \App\Services\ReportService::getAdmin7DayTrends(1)
            : [];

        return $this->view('home/index', [
            'pageTitle' => 'Accueil',
            'activeTab' => 'home',
            'isHomePage' => true,
            'hideNavbar' => true,
            'themeColor' => '#0A1633',
            'statusBarStyle' => 'black-translucent',
            'patient' => $patient,
            'contacts' => $contacts,
            'teamCount' => $teamCount,
            'userRole' => $role,
            'todayLocal' => $todayLocal,
            'dayProgram' => $dayProgram,
            'unreadHandover' => $unreadHandover,
            'attention' => $attention,
            'observance' => $observance,
            'adminMetrics' => $adminMetrics,
            'trends7d' => $trends7d,
            'cronStale' => $cronStale,
            'rotation' => $rotation,
            'clockState' => $clockState,
            'nextShift' => $nextShift,
            'openAlertsCount' => $openAlertsCount,
        ]);
    }

    public function more(Request $request): Response
    {
        return $this->view('home/more', [
            'pageTitle' => 'Plus',
            'activeTab' => 'more',
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view('home/about', [
            'pageTitle' => 'À propos',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
        ]);
    }

    public function install(Request $request): Response
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? parse_url((string) Config::get('app.url', 'http://localhost:8080'), PHP_URL_HOST) ?: 'localhost:8080');
        $appUrl = $scheme . '://' . $host . '/?source=pwa';
        $qrSvg = QrCodeService::renderSvg($appUrl, 220);

        return $this->view('home/install', [
            'pageTitle' => 'Installer l’application',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'appUrl' => $appUrl,
            'qrSvg' => $qrSvg,
        ]);
    }

    public function comingSoon(Request $request, string $module = 'timeclock'): Response
    {
        // Phase 4 §1: No 'Coming Soon' screen remains in the application
        $redirects = [
            'today' => '/today',
            'health' => '/health',
            'vitals' => '/health',
            'glycemia' => '/health/glycemia',
            'blood-pressure' => '/health/blood_pressure',
            'temperature' => '/health/temperature',
            'medication' => '/treatments',
            'treatments' => '/treatments',
            'meal' => '/meals',
            'meals' => '/meals',
            'journal' => '/journal',
            'settings' => '/settings',
            'timeclock' => '/attendance',
            'planning' => '/planning',
            'alerts' => '/alerts',
            'documents' => '/documents',
            'reports' => '/reports',
            'audit' => '/audit',
        ];

        return Response::redirect($redirects[$module] ?? '/documents');
    }
}
