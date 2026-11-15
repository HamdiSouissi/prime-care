<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuditService;
use App\Services\DocumentService;
use finfo;
use PDO;
use RuntimeException;

/**
 * DocumentController — Coffre-fort de documents médicaux (E22, §3.9 / Phase 4 §1).
 * Lecture autorisée pour tous les rôles authentifiés ; dépôt et suppression réservés à l'admin.
 */
final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('documents', 'view');

        $category = trim((string) $request->query('category', 'all'));
        $search = trim((string) $request->query('q', ''));

        $documents = DocumentService::listDocuments(1, $category, $search);
        $counts = DocumentService::countByCategories(1);

        // Load active prescriptions for optional linking in admin upload form
        $rxStmt = Database::connection()->query(
            "SELECT p.id, p.dose, p.unit, m.name AS medication_name
             FROM prescriptions p
             INNER JOIN medications m ON m.id = p.medication_id
             WHERE p.patient_id = 1 AND p.deleted_at IS NULL AND p.is_active = 1
             ORDER BY m.name ASC"
        );
        $prescriptions = $rxStmt !== false ? $rxStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $canManage = can('documents', 'create');
        $rightAction = null;
        if ($canManage) {
            $rightAction = '<button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-sheet-open="document-upload-sheet">'
                . icon('upload', '', 16) . '<span>Déposer</span></button>';
        }

        $html = View::render('documents/index', [
            'pageTitle' => 'Documents médicaux',
            'activeTab' => 'more',
            'backUrl' => '/more',
            'backLabel' => 'Plus',
            'rightActionHtml' => $rightAction,
            'wideLayout' => true,
            'documents' => $documents,
            'categories' => DocumentService::CATEGORIES,
            'counts' => $counts,
            'selectedCategory' => $category,
            'searchQuery' => $search,
            'prescriptions' => $prescriptions,
            'canManage' => $canManage,
        ]);

        return Response::html($html);
    }

    public function show(Request $request, string $id): Response
    {
        $this->authorize('documents', 'view');

        $docId = (int) $id;
        $doc = DocumentService::findById($docId, 1);
        if ($doc === null) {
            throw new RuntimeException('Document médical introuvable.', 404);
        }

        $html = View::render('documents/show', [
            'pageTitle' => (string) $doc['title'],
            'activeTab' => 'more',
            'backUrl' => '/documents',
            'backLabel' => 'Documents',
            'wideLayout' => true,
            'doc' => $doc,
            'canDelete' => can('documents', 'delete'),
        ]);

        return Response::html($html);
    }

    public function file(Request $request, string $id): Response
    {
        $this->authorize('documents', 'view');
        return $this->serveDocumentFile((int) $id, true);
    }

    public function download(Request $request, string $id): Response
    {
        $this->authorize('documents', 'view');
        return $this->serveDocumentFile((int) $id, false);
    }

    public function store(Request $request): Response
    {
        $this->authorize('documents', 'create');

        $file = $request->file('document_file') ?? ($_FILES['document_file'] ?? ($_FILES['file'] ?? null));
        if (!is_array($file)) {
            if ($request->wantsJson()) {
                return Response::jsonError('FILE_REQUIRED', 'Veuillez sélectionner un fichier PDF, JPG ou PNG.', 422);
            }
            Session::flash('toast_error', 'Veuillez sélectionner un fichier PDF, JPG ou PNG.');
            return Response::redirect('/documents');
        }

        try {
            $res = DocumentService::storeDocument(
                $request->all(),
                $file,
                (int) Auth::id(),
                1
            );
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return Response::jsonError('INVALID_DOCUMENT', $e->getMessage(), 422);
            }
            Session::flash('toast_error', $e->getMessage());
            return Response::redirect('/documents');
        }

        if ($request->wantsJson()) {
            return Response::jsonSuccess($res, 201);
        }

        if ($res['duplicate_warning'] && is_array($res['duplicate_document'])) {
            $dupTitle = (string) ($res['duplicate_document']['title'] ?? 'document existant');
            Session::flash(
                'toast_warn',
                "Document déposé. Attention : un fichier identique (même empreinte SHA-256) existait déjà (« {$dupTitle} »)."
            );
        } else {
            Session::flash('toast_success', 'Document médical enregistré dans le coffre-fort.');
        }

        return Response::redirect('/documents/' . $res['id']);
    }

    public function delete(Request $request, string $id): Response
    {
        $this->authorize('documents', 'delete');

        $docId = (int) $id;
        $deleted = DocumentService::deleteDocument($docId, (int) Auth::id(), 1);
        if (!$deleted) {
            if ($request->wantsJson()) {
                return Response::jsonError('NOT_FOUND', 'Document introuvable.', 404);
            }
            Session::flash('toast_error', 'Document introuvable.');
            return Response::redirect('/documents');
        }

        if ($request->wantsJson()) {
            return Response::jsonSuccess(['deleted' => true, 'id' => $docId]);
        }

        Session::flash('toast_success', 'Document médical supprimé.');
        return Response::redirect('/documents');
    }

    private function serveDocumentFile(int $docId, bool $inline): Response
    {
        $doc = DocumentService::findById($docId, 1);
        if ($doc === null) {
            throw new RuntimeException('Document introuvable.', 404);
        }

        $relPath = ltrim((string) $doc['file_path'], '/');
        if (
            $relPath === '' ||
            str_contains($relPath, '..') ||
            str_contains($relPath, "\0") ||
            str_contains(rawurldecode($relPath), '..')
        ) {
            throw new RuntimeException('Chemin de document invalide.', 403);
        }

        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        $realRoot = realpath($uploadsRoot);
        $candidate = $uploadsRoot . '/' . $relPath;
        $realFile = is_file($candidate) ? realpath($candidate) : false;

        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Fichier du document introuvable sur le stockage.', 404);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($realFile) ?: $doc['mime']);

        $ext = match ($mime) {
            'application/pdf' => '.pdf',
            'image/png' => '.png',
            default => '.jpg',
        };
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $doc['title']) ?: ('document_' . $docId);
        $downloadName = $safeBase . $ext;

        if (!$inline) {
            AuditService::record('export', 'documents', $docId, null, [
                'title' => $doc['title'],
                'category' => $doc['category'],
                'mode' => 'download',
            ], 1, Auth::id());
        }

        return Response::download($realFile, $mime, $downloadName, $inline);
    }
}
