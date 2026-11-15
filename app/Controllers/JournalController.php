<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\HandoverService;
use App\Services\ScheduleService;
use App\Services\TimelineService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * E12 & E13 — Unified daily Journal (/journal), filterable by type (all, measures, intakes, meals, notes, care),
 * End-of-Shift Handover with automatic summary and read tracking, and 1-tap Quick Care checks.
 */
final class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('notes', 'view');

        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $todayStr = (new DateTimeImmutable('now', $parisTz))->format('Y-m-d');

        $selectedDate = trim((string) $request->query('date', $todayStr));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = $todayStr;
        }

        $filter = trim((string) $request->query('filter', (string) $request->query('type', 'all')));
        if (!in_array($filter, ['all', 'measures', 'intakes', 'meals', 'notes', 'care'], true)) {
            $filter = 'all';
        }

        $page = max(1, (int) $request->query('page', 1));
        $userId = Auth::id() ?? 0;

        $feed = TimelineService::getDailyFeed($selectedDate, $filter, $page, 25, 1, $userId);
        $unreadHandover = HandoverService::getLatestUnreadHandover($userId, 1);
        $shiftSummary = HandoverService::buildShiftSummary();

        $selDt = new DateTimeImmutable($selectedDate . ' 12:00:00', $parisTz);
        $prevDate = $selDt->sub(new DateInterval('P1D'))->format('Y-m-d');
        $nextDate = $selDt->add(new DateInterval('P1D'))->format('Y-m-d');

        $pdo = Database::connection();
        $ciStmt = $pdo->query(
            'SELECT * FROM `care_items` WHERE `patient_id` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL ORDER BY `sort_order` ASC'
        );
        $careItems = $ciStmt !== false ? $ciStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $this->view('journal/index', [
            'pageTitle' => 'Journal & Transmissions',
            'activeTab' => 'journal',
            'selectedDate' => $selectedDate,
            'todayDate' => $todayStr,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'filter' => $filter,
            'feed' => $feed,
            'unreadHandover' => $unreadHandover,
            'shiftSummary' => $shiftSummary,
            'careItems' => $careItems,
        ]);
    }

    public function storeNote(Request $request): Response
    {
        $api = new ApiMedicalController();
        $res = $api->storeNote($request);
        if ($res->status() === 403) {
            return $res;
        }
        $decoded = json_decode($res->body(), true) ?: [];

        if (!($decoded['ok'] ?? false)) {
            Session::flash('error', (string) ($decoded['error']['message'] ?? 'Erreur lors de l’enregistrement.'));
            return $this->redirect('/journal');
        }

        $isHandover = !empty($decoded['data']['is_handover']);
        Session::flash(
            'success',
            $isHandover
                ? 'Transmission de fin de poste enregistrée avec le résumé automatique.'
                : 'Note ajoutée au journal de bord.'
        );
        return $this->redirect('/journal');
    }

    public function markRead(Request $request, string $id): Response
    {
        $this->authorize('notes', 'view');
        HandoverService::markAsRead((int) $id, Auth::id() ?? 0, 1);
        Session::flash('success', 'Transmission marquée comme lue.');
        return $this->redirect((string) $request->input('redirect_to', '/'));
    }

    public function storeCareCheck(Request $request): Response
    {
        $api = new ApiMedicalController();
        $res = $api->storeCareCheck($request);
        if ($res->status() === 403) {
            return $res;
        }
        $decoded = json_decode($res->body(), true) ?: [];

        if (!($decoded['ok'] ?? false)) {
            Session::flash('error', (string) ($decoded['error']['message'] ?? 'Erreur lors de la validation du soin.'));
            return $this->redirect((string) $request->input('redirect_to', '/journal'));
        }

        Session::flash('success', sprintf('Soin « %s » horodaté.', (string) ($decoded['data']['label'] ?? 'Soin')));
        return $this->redirect((string) $request->input('redirect_to', '/journal'));
    }
}
