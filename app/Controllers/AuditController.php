<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;

/**
 * Audit Log Controller (E26, Phase 4 §4):
 * Read-only paginated audit trail for administrators with field-by-field before/after diff.
 */
final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('audit', 'view');

        $filters = [
            'user_id' => trim((string) $request->query('user_id', '')),
            'entity_type' => trim((string) $request->query('entity_type', '')),
            'action' => trim((string) $request->query('action', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $result = AuditService::listLogs($filters, $page, 25, 1);

        $pdo = Database::connection();
        $users = $pdo->query(
            'SELECT id, first_name, last_name, role FROM `users` WHERE deleted_at IS NULL ORDER BY first_name ASC'
        )->fetchAll() ?: [];

        return $this->view('audit/index', [
            'pageTitle' => 'Journal d’audit',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filters' => $filters,
            'users' => $users,
            'entityLabels' => AuditService::ENTITY_LABELS,
            'actionCategories' => AuditService::ACTION_CATEGORIES,
        ]);
    }
}
