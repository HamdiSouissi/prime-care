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

        // Idempotent schedule generation & periodic alerts safety net (debounced to 5 min)
        ScheduleService::generateUpcoming48h();
        \App\Services\CronService::runFallbackIfNeeded(1);

        $userId = Auth::id() ?? 0;
        $role = Auth::role() ?? 'viewer';

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
        // Redirect any Phase 2 module to its live screen so zero Phase 2 'Coming Soon' remains
        $phase2Redirects = [
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
        ];
        if (isset($phase2Redirects[$module])) {
            return Response::redirect($phase2Redirects[$module]);
        }

        $catalog = [
            'timeclock' => [
                'title' => 'Pointage & Planning',
                'phase' => 'Phase 3 — Équipe, alertes & hors ligne',
                'icon' => 'clock',
                'color' => 'var(--c-team)',
                'bg' => 'var(--brand-soft)',
                'description' => 'Le pointage d’arrivée/départ par QR code au domicile + vérification GPS, le planning d’équipe et le récapitulatif des heures seront livrés en Phase 3.',
                'tab' => 'home',
            ],
            'planning' => [
                'title' => 'Planning & Absences',
                'phase' => 'Phase 3 — Équipe, alertes & hors ligne',
                'icon' => 'calendar-days',
                'color' => 'var(--c-team)',
                'bg' => 'var(--brand-soft)',
                'description' => 'La planification des gardes, le suivi des absences et la validation des pointages arriveront en Phase 3.',
                'tab' => 'more',
            ],
            'alerts' => [
                'title' => 'Alertes Web Push & E-mail',
                'phase' => 'Phase 3 — Équipe, alertes & hors ligne',
                'icon' => 'bell',
                'color' => 'var(--danger)',
                'bg' => 'rgba(255,59,48,.12)',
                'description' => 'Les notifications Web Push (VAPID) et e-mail automatiques ainsi que le centre d’alertes seront activés en Phase 3.',
                'tab' => 'more',
            ],
            'documents' => [
                'title' => 'Documents médicaux',
                'phase' => 'Phase 4 — Pilotage & Production',
                'icon' => 'file-text',
                'color' => 'var(--c-docs)',
                'bg' => 'rgba(0,122,255,.12)',
                'description' => 'Le coffre-fort d’ordonnances, comptes rendus hospitaliers, rapport médical imprimable et exports CSV sera activé en Phase 4.',
                'tab' => 'more',
            ],
        ];

        $meta = $catalog[$module] ?? $catalog['timeclock'];

        return $this->view('home/coming-soon', [
            'pageTitle' => $meta['title'],
            'activeTab' => $meta['tab'],
            'backUrl' => '/',
            'backLabel' => 'Accueil',
            'moduleMeta' => $meta,
        ]);
    }
}
