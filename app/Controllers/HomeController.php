<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Patient;
use App\Models\PatientContact;
use App\Models\User;

/**
 * Handles the Home dashboard, the "Plus" menu, "À propos", and Phase 2–4 module placeholders.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('dashboard', 'view');

        $patientModel = new Patient();
        $patient = $patientModel->current(1);
        $contactModel = new PatientContact();
        $contacts = $contactModel->forPatient(1);

        $teamCount = 0;
        if (Auth::hasRole('admin')) {
            $userModel = new User();
            $teamCount = count($userModel->allTeamMembers());
        }

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

    public function comingSoon(Request $request, string $module = 'vitals'): Response
    {
        $catalog = [
            'today' => [
                'title' => 'Aujourd’hui',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'calendar-days',
                'color' => 'var(--brand)',
                'bg' => 'var(--brand-soft)',
                'description' => 'La timeline interactive de la journée (prises de médicaments, mesures programmées, repas et soins rapides) sera activée en Phase 2.',
                'tab' => 'today',
            ],
            'health' => [
                'title' => 'Santé & Constantes',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'heart-pulse',
                'color' => 'var(--c-vitals)',
                'bg' => 'rgba(255,45,85,.12)',
                'description' => 'Les courbes de glycémie, tension artérielle, SpO2, poids, température et le suivi du plan de traitement arriveront en Phase 2.',
                'tab' => 'health',
            ],
            'glycemia' => [
                'title' => 'Saisie de glycémie',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'droplet',
                'color' => 'var(--c-vitals)',
                'bg' => 'rgba(255,45,85,.12)',
                'description' => 'La saisie rapide de glycémie capillaire avec détection automatique du contexte (à jeun, pré/post-prandial) et alerte de seuil sera livrée en Phase 2.',
                'tab' => 'home',
            ],
            'blood-pressure' => [
                'title' => 'Tension artérielle',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'heart-pulse',
                'color' => 'var(--c-vitals)',
                'bg' => 'rgba(255,45,85,.12)',
                'description' => 'Le relevé systolique / diastolique et pouls avec courbe d’évolution sera disponible en Phase 2.',
                'tab' => 'home',
            ],
            'temperature' => [
                'title' => 'Température corporelle',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'thermometer',
                'color' => 'var(--c-vitals)',
                'bg' => 'rgba(255,45,85,.12)',
                'description' => 'Le suivi thermique avec seuils d’alerte infectieuse sera disponible en Phase 2.',
                'tab' => 'home',
            ],
            'medication' => [
                'title' => 'Prise de médicament',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'pill',
                'color' => 'var(--c-meds)',
                'bg' => 'rgba(0,199,190,.14)',
                'description' => 'La validation des prises médicamenteuses et l’administration d’insuline seront disponibles en Phase 2.',
                'tab' => 'home',
            ],
            'meal' => [
                'title' => 'Repas & Hydratation',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'utensils',
                'color' => 'var(--c-meals)',
                'bg' => 'rgba(255,149,0,.14)',
                'description' => 'La saisie des repas avec photos avant/après, estimation des glucides et quantité consommée sera disponible en Phase 2.',
                'tab' => 'home',
            ],
            'journal' => [
                'title' => 'Journal & Transmissions',
                'phase' => 'Phase 2 — Cœur médical',
                'icon' => 'notebook-pen',
                'color' => 'var(--c-notes)',
                'bg' => 'rgba(175,82,222,.14)',
                'description' => 'Le journal de bord horodaté, les soins rapides (toilette, change) et les transmissions de fin de poste seront activés en Phase 2.',
                'tab' => 'journal',
            ],
            'timeclock' => [
                'title' => 'Pointage & Planning',
                'phase' => 'Phase 3 — Équipe & Alertes',
                'icon' => 'clock',
                'color' => 'var(--c-team)',
                'bg' => 'var(--brand-soft)',
                'description' => 'Le pointage d’arrivée/départ par QR code au domicile + vérification GPS et le planning d’équipe seront livrés en Phase 3.',
                'tab' => 'home',
            ],
            'documents' => [
                'title' => 'Documents médicaux',
                'phase' => 'Phase 4 — Pilotage & Production',
                'icon' => 'file-text',
                'color' => 'var(--c-docs)',
                'bg' => 'rgba(0,122,255,.12)',
                'description' => 'Le coffre-fort d’ordonnances, comptes rendus hospitaliers et bilans biologiques sera activé en Phase 4.',
                'tab' => 'more',
            ],
            'settings' => [
                'title' => 'Réglages de l’application',
                'phase' => 'Phase 4 — Pilotage & Production',
                'icon' => 'settings',
                'color' => 'var(--brand)',
                'bg' => 'var(--brand-soft)',
                'description' => 'La configuration avancée des seuils d’alerte, notifications Web Push/SMTP, exports CSV et journal d’audit complet sera livrée en Phase 4.',
                'tab' => 'more',
            ],
        ];

        $meta = $catalog[$module] ?? $catalog['today'];

        return $this->view('home/coming-soon', [
            'pageTitle' => $meta['title'],
            'activeTab' => $meta['tab'],
            'backUrl' => '/',
            'backLabel' => 'Accueil',
            'moduleMeta' => $meta,
        ]);
    }
}
