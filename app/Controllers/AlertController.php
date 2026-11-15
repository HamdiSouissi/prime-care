<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AlertService;
use App\Services\MailService;
use App\Services\Policy;
use App\Services\WebPushService;
use InvalidArgumentException;
use PDO;

/**
 * AlertController — Centre d'alertes (/alerts), actions `seen` / `handle`,
 * abonnements Web Push VAPID (/api/push/subscribe, /api/push/unsubscribe)
 * et bouton "Envoyer une notification de test".
 */
final class AlertController extends Controller
{
    public const QUICK_RESOLUTIONS = [
        'Vérifié',
        'Appelé l’aide-soignante',
        'Médecin contacté',
        'Contrôle normal effectué',
    ];

    /**
     * GET /alerts
     */
    public function index(Request $request): Response
    {
        $this->authorize('alerts', 'view');

        $role = Auth::role() ?? 'viewer';
        $userId = Auth::id() ?? 0;
        $canHandle = Policy::allows($role, 'alerts', 'acknowledge');

        $tab = (string) $request->query('tab', '');
        $rawFilter = (string) ($request->query('filter', '') ?: $request->query('status', ''));
        if ($rawFilter === '' && $tab !== '') {
            $rawFilter = match ($tab) {
                'history', 'handled' => 'handled',
                'rules' => 'rules',
                'all' => 'all',
                default => 'open',
            };
        }
        $filter = in_array($rawFilter, ['open', 'handled', 'all', 'rules'], true) ? $rawFilter : 'open';
        $severityFilter = (string) $request->query('severity', 'all');
        if (!in_array($severityFilter, ['all', 'critical', 'warning', 'info'], true)) {
            $severityFilter = 'all';
        }

        // Passage automatique de `new` à `seen` à l'ouverture du centre d'alertes
        if ($canHandle && $filter === 'open') {
            AlertService::markAllNewAsSeen($userId, 1);
        }

        $listFilter = $filter === 'rules' ? 'open' : $filter;
        $alerts = AlertService::listAlerts($listFilter, 1, 100);
        if ($severityFilter !== 'all') {
            $alerts = array_values(array_filter(
                $alerts,
                static fn(array $a): bool => (string) ($a['level'] ?? 'warning') === $severityFilter
            ));
        }
        $openCount = AlertService::countOpenAlerts(1);

        return $this->view('alerts/index', [
            'pageTitle' => 'Centre d’alertes',
            'activeTab' => 'more',
            'backUrl' => '/',
            'backLabel' => 'Accueil',
            'wideLayout' => true,
            'filter' => $filter,
            'statusFilter' => $filter,
            'severityFilter' => $severityFilter,
            'alerts' => $alerts,
            'openCount' => $openCount,
            'canHandle' => $canHandle,
            'quickResolutions' => self::QUICK_RESOLUTIONS,
            'alertTypes' => AlertService::TYPES,
        ]);
    }

    /**
     * GET /api/alerts
     */
    public function apiList(Request $request): Response
    {
        $role = Auth::role() ?? 'viewer';
        if (!Policy::allows($role, 'alerts', 'view')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        $filter = (string) $request->query('filter', 'open');
        if (!in_array($filter, ['open', 'handled', 'all'], true)) {
            $filter = 'open';
        }

        return Response::jsonSuccess([
            'open_count' => AlertService::countOpenAlerts(1),
            'alerts' => AlertService::listAlerts($filter, 1, 50),
        ]);
    }

    /**
     * POST /alerts/{id}/seen & POST /api/alerts/{id}/seen
     */
    public function markSeen(Request $request, string $id): Response
    {
        $role = Auth::role() ?? 'viewer';
        if (!Policy::allows($role, 'alerts', 'acknowledge')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        $alert = AlertService::markSeen((int) $id, Auth::id() ?? 1, 1);
        if ($request->wantsJson()) {
            return Response::jsonSuccess(['alert' => $alert]);
        }

        return $this->redirect('/alerts');
    }

    /**
     * POST /alerts/{id}/handle & POST /api/alerts/{id}/handle
     */
    public function handle(Request $request, string $id): Response
    {
        $role = Auth::role() ?? 'viewer';
        if (!Policy::allows($role, 'alerts', 'acknowledge')) {
            return Response::jsonError('FORBIDDEN', 'Accès refusé.', 403);
        }

        $quick = trim((string) ($request->input('quick_resolution', '') ?: $request->input('quick_choice', '')));
        $custom = trim((string) ($request->input('resolution', '') ?: $request->input('note', '')));
        $resolution = $custom !== '' ? ($quick !== '' && $custom !== $quick ? "{$quick} — {$custom}" : $custom) : ($quick !== '' ? $quick : 'Vérifié');

        try {
            $alert = AlertService::handleAlert((int) $id, Auth::id() ?? 1, $resolution, 1);
            if ($request->wantsJson()) {
                return Response::jsonSuccess(['alert' => $alert]);
            }
            Session::flash('success', 'Alerte marquée comme traitée.');
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('ALERT_ERROR', $e->getMessage(), 422);
            }
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect((string) $request->input('redirect_to', '/alerts'));
    }

    /**
     * POST /api/push/subscribe
     */
    public function subscribePush(Request $request): Response
    {
        $userId = Auth::id() ?? 0;
        if ($userId <= 0) {
            return Response::jsonError('UNAUTHENTICATED', 'Non connecté.', 401);
        }

        try {
            $subId = WebPushService::saveSubscription(
                $userId,
                $request->all(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
            return Response::jsonSuccess([
                'subscription_id' => $subId,
                'message' => 'Notifications Web Push activées sur cet appareil.',
            ], 201);
        } catch (InvalidArgumentException $e) {
            return Response::jsonError('INVALID_SUBSCRIPTION', $e->getMessage(), 422);
        }
    }

    /**
     * POST /api/push/unsubscribe
     */
    public function unsubscribePush(Request $request): Response
    {
        $userId = Auth::id() ?? 0;
        if ($userId <= 0) {
            return Response::jsonError('UNAUTHENTICATED', 'Non connecté.', 401);
        }

        $endpoint = trim((string) $request->input('endpoint', ''));
        if ($endpoint !== '') {
            WebPushService::removeSubscription($userId, $endpoint);
        }

        return Response::jsonSuccess(['unsubscribed' => true]);
    }

    /**
     * POST /profile/push-test & POST /api/push/test ("Envoyer une notification de test")
     */
    public function sendTestNotification(Request $request): Response
    {
        $user = Auth::user();
        if (!$user) {
            return Response::jsonError('UNAUTHENTICATED', 'Non connecté.', 401);
        }

        $userId = (int) $user['id'];
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM push_subscriptions WHERE user_id = :uid AND deleted_at IS NULL');
        $stmt->execute(['uid' => $userId]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pushSent = 0;
        $payload = [
            'title' => 'Test Prime Care réussi',
            'body' => 'Les notifications temps réel (Web Push VAPID) fonctionnent sur cet appareil.',
            'url' => '/alerts',
            'tag' => 'prime-care-test-' . time(),
            'level' => 'info',
            'icon' => '/assets/brand/icon-192.png',
            'badge' => '/assets/brand/badge-96.png',
        ];

        foreach ($subs as $sub) {
            $res = WebPushService::sendToSubscription($sub, $payload, 'high');
            if ($res['ok']) {
                $pushSent++;
            }
        }

        // Also send a test email notification via MailService / PHPMailer
        $emailSent = MailService::sendAlertEmail(
            (string) $user['email'],
            trim($user['first_name'] . ' ' . $user['last_name']),
            [
                'level' => 'info',
                'title' => 'Notification de test Prime Care',
                'message' => 'Cet envoi confirme le bon fonctionnement de vos alertes Prime Care (Web Push VAPID & E-mail).',
                'target_url' => '/alerts',
            ]
        );

        $msg = $pushSent > 0
            ? "Notification de test envoyée ({$pushSent} appareil(s) push + e-mail)."
            : 'Notification de test envoyée par e-mail (et prête pour Web Push dès l’abonnement du navigateur).';

        if ($request->wantsJson()) {
            return Response::jsonSuccess([
                'push_sent' => $pushSent,
                'email_sent' => $emailSent,
                'message' => $msg,
            ]);
        }

        Session::flash('success', $msg);
        return $this->redirect('/profile');
    }

    /**
     * GET /api/push/vapid-key
     */
    public function vapidPublicKey(Request $request): Response
    {
        return Response::jsonSuccess([
            'publicKey' => WebPushService::getPublicKeyBase64Url(),
        ]);
    }
}
