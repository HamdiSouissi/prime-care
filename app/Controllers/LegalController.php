<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Legal & GDPR information pages (Phase 4 §5.3):
 * Accessible both from /about (authenticated) and /login (public):
 * - /legal/mentions
 * - /legal/privacy
 * - /legal/attendance-notice
 */
final class LegalController extends Controller
{
    public function mentions(Request $request): Response
    {
        return $this->renderLegal('legal/mentions', 'Mentions légales');
    }

    public function privacy(Request $request): Response
    {
        return $this->renderLegal('legal/privacy', 'Politique de confidentialité');
    }

    public function attendanceNotice(Request $request): Response
    {
        return $this->renderLegal('legal/attendance-notice', 'Notice d’information — Pointage & Géolocalisation');
    }

    private function renderLegal(string $viewName, string $title): Response
    {
        $isLoggedIn = Auth::check();
        $layout = $isLoggedIn ? 'layouts/app' : 'layouts/auth';

        return $this->view($viewName, [
            'pageTitle' => $title,
            'activeTab' => 'more',
            'backUrl' => $isLoggedIn ? '/about' : '/login',
            'backLabel' => $isLoggedIn ? 'À propos' : 'Connexion',
            'isLoggedIn' => $isLoggedIn,
        ], $layout);
    }
}
