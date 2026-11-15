<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Uploader;
use PDO;
use RuntimeException;

/**
 * DocumentService — Gestion sécurisée des documents médicaux (E22, §3.9 / Phase 4 §1) :
 * - Catégories : ordonnance, compte rendu, analyse biologique, courrier, imagerie, autre
 * - Dépôt : PDF (%PDF-), JPG, PNG (sans EXIF), 10 Mo max, nom aléatoire dans storage/uploads/documents/
 * - Détection de doublon par SHA-256 (avertissement non bloquant)
 * - Synchronisation automatique des ordonnances jointes aux prescriptions (Phase 2)
 */
final class DocumentService
{
    public const CATEGORIES = [
        'ordonnance' => [
            'label' => 'Ordonnance',
            'icon' => 'clipboard-list',
            'badge' => 'pc-badge--meds',
        ],
        'compte_rendu' => [
            'label' => 'Compte rendu',
            'icon' => 'stethoscope',
            'badge' => 'pc-badge--brand',
        ],
        'analyse_biologique' => [
            'label' => 'Analyse biologique',
            'icon' => 'droplet',
            'badge' => 'pc-badge--vitals',
        ],
        'courrier' => [
            'label' => 'Courrier médical',
            'icon' => 'mail',
            'badge' => 'pc-badge--info',
        ],
        'imagerie' => [
            'label' => 'Imagerie',
            'icon' => 'activity',
            'badge' => 'pc-badge--warn',
        ],
        'autre' => [
            'label' => 'Autre document',
            'icon' => 'file-text',
            'badge' => 'pc-badge--neutral',
        ],
    ];

    /**
     * Normalise le code de catégorie de document.
     */
    public static function normalizeCategory(string $category): string
    {
        $clean = mb_strtolower(trim($category));
        $map = [
            'ordonnance' => 'ordonnance',
            'ordonnances' => 'ordonnance',
            'compte rendu' => 'compte_rendu',
            'compte-rendu' => 'compte_rendu',
            'compte_rendu' => 'compte_rendu',
            'analyse biologique' => 'analyse_biologique',
            'analyse-biologique' => 'analyse_biologique',
            'analyse_biologique' => 'analyse_biologique',
            'biologie' => 'analyse_biologique',
            'courrier' => 'courrier',
            'imagerie' => 'imagerie',
            'autre' => 'autre',
        ];

        return $map[$clean] ?? (isset(self::CATEGORIES[$clean]) ? $clean : 'autre');
    }

    public static function categoryLabel(string $category): string
    {
        $norm = self::normalizeCategory($category);
        return self::CATEGORIES[$norm]['label'] ?? 'Autre document';
    }

    /**
     * Synchronise les ordonnances jointes aux prescriptions (`prescriptions.document_path`)
     * dans la table `documents` (catégorie `ordonnance`, reliées à leur prescription).
     */
    public static function syncPrescriptionDocuments(int $patientId = 1): void
    {
        $pdo = Database::connection();
        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');

        $stmt = $pdo->prepare(
            "SELECT p.id AS prescription_id,
                    p.patient_id,
                    p.start_date,
                    p.dose,
                    p.unit,
                    p.document_path,
                    p.document_mime,
                    p.document_original_name,
                    p.created_by,
                    p.created_at,
                    m.name AS medication_name
             FROM prescriptions p
             INNER JOIN medications m ON m.id = p.medication_id
             LEFT JOIN documents d ON d.prescription_id = p.id AND d.deleted_at IS NULL
             WHERE p.patient_id = :pid
               AND p.deleted_at IS NULL
               AND p.document_path IS NOT NULL
               AND p.document_path != ''
               AND d.id IS NULL"
        );
        $stmt->execute(['pid' => $patientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO `documents`
             (`patient_id`, `prescription_id`, `category`, `title`, `doc_date`, `description`,
              `file_path`, `original_name`, `mime`, `size`, `sha256`, `uploaded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (:pid, :rx_id, \'ordonnance\', :title, :doc_date, :desc,
              :file_path, :orig, :mime, :size, :sha256, :by, :created_at, :updated_at, :created_by)'
        );

        foreach ($rows as $row) {
            $relPath = (string) $row['document_path'];
            $fullPath = $uploadsRoot . '/' . ltrim($relPath, '/');
            $size = is_file($fullPath) ? (int) filesize($fullPath) : 0;
            $sha256 = is_file($fullPath) ? (string) hash_file('sha256', $fullPath) : hash('sha256', $relPath);
            $medName = (string) $row['medication_name'];
            $doseStr = rtrim(rtrim(number_format((float) $row['dose'], 2, ',', ''), '0'), ',') . ' ' . $row['unit'];
            $actorId = (int) ($row['created_by'] ?: 1);
            $now = (string) ($row['created_at'] ?: gmdate('Y-m-d H:i:s'));

            $ins->execute([
                'pid' => (int) $row['patient_id'],
                'rx_id' => (int) $row['prescription_id'],
                'title' => 'Ordonnance — ' . $medName,
                'doc_date' => (string) $row['start_date'],
                'desc' => "Ordonnance rattachée à la prescription de {$medName} ({$doseStr}).",
                'file_path' => $relPath,
                'orig' => (string) ($row['document_original_name'] ?: basename($relPath)),
                'mime' => (string) ($row['document_mime'] ?: 'image/jpeg'),
                'size' => $size,
                'sha256' => $sha256,
                'by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $actorId,
            ]);
        }
    }

    /**
     * Liste les documents du patient, filtrables par catégorie et recherche dans le titre.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listDocuments(
        int $patientId = 1,
        ?string $category = null,
        ?string $search = null
    ): array {
        self::syncPrescriptionDocuments($patientId);

        $pdo = Database::connection();
        $sql = "SELECT d.*,
                       u.first_name AS uploader_first_name,
                       u.last_name AS uploader_last_name,
                       p.dose AS prescription_dose,
                       p.unit AS prescription_unit,
                       m.name AS medication_name
                FROM documents d
                INNER JOIN users u ON u.id = d.uploaded_by
                LEFT JOIN prescriptions p ON p.id = d.prescription_id
                LEFT JOIN medications m ON m.id = p.medication_id
                WHERE d.patient_id = :pid
                  AND d.deleted_at IS NULL";
        $params = ['pid' => $patientId];

        if ($category !== null && $category !== '' && $category !== 'all') {
            $normCat = self::normalizeCategory($category);
            $sql .= ' AND d.category = :cat';
            $params['cat'] = $normCat;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (d.title LIKE :q OR d.description LIKE :q2)';
            $qVal = '%' . trim($search) . '%';
            $params['q'] = $qVal;
            $params['q2'] = $qVal;
        }

        $sql .= ' ORDER BY d.doc_date DESC, d.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $catKey = self::normalizeCategory((string) $row['category']);
            $catMeta = self::CATEGORIES[$catKey] ?? self::CATEGORIES['autre'];
            $row['category_label'] = $catMeta['label'];
            $row['category_icon'] = $catMeta['icon'];
            $row['category_badge'] = $catMeta['badge'];
            $row['is_pdf'] = ((string) $row['mime'] === 'application/pdf');
            $row['is_image'] = str_starts_with((string) $row['mime'], 'image/');
            $row['size_formatted'] = self::formatSize((int) $row['size']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Compte les documents par catégorie.
     *
     * @return array<string, int>
     */
    public static function countByCategories(int $patientId = 1): array
    {
        self::syncPrescriptionDocuments($patientId);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT category, COUNT(*) AS cnt
             FROM documents
             WHERE patient_id = :pid AND deleted_at IS NULL
             GROUP BY category'
        );
        $stmt->execute(['pid' => $patientId]);
        $counts = ['all' => 0];
        foreach (array_keys(self::CATEGORIES) as $k) {
            $counts[$k] = 0;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cat = self::normalizeCategory((string) $r['category']);
            $c = (int) $r['cnt'];
            $counts[$cat] = ($counts[$cat] ?? 0) + $c;
            $counts['all'] += $c;
        }
        return $counts;
    }

    /**
     * Récupère un document par son identifiant.
     *
     * @return array<string, mixed>|null
     */
    public static function findById(int $id, int $patientId = 1): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT d.*,
                    u.first_name AS uploader_first_name,
                    u.last_name AS uploader_last_name,
                    p.dose AS prescription_dose,
                    p.unit AS prescription_unit,
                    m.name AS medication_name
             FROM documents d
             INNER JOIN users u ON u.id = d.uploaded_by
             LEFT JOIN prescriptions p ON p.id = d.prescription_id
             LEFT JOIN medications m ON m.id = p.medication_id
             WHERE d.id = :id AND d.patient_id = :pid AND d.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'pid' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $catKey = self::normalizeCategory((string) $row['category']);
        $catMeta = self::CATEGORIES[$catKey] ?? self::CATEGORIES['autre'];
        $row['category_label'] = $catMeta['label'];
        $row['category_icon'] = $catMeta['icon'];
        $row['category_badge'] = $catMeta['badge'];
        $row['is_pdf'] = ((string) $row['mime'] === 'application/pdf');
        $row['is_image'] = str_starts_with((string) $row['mime'], 'image/');
        $row['size_formatted'] = self::formatSize((int) $row['size']);

        return $row;
    }

    /**
     * Recherche un éventuel doublon actif par empreinte SHA-256.
     *
     * @return array<string, mixed>|null
     */
    public static function findDuplicateBySha256(string $sha256, int $patientId = 1): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, doc_date, category
             FROM documents
             WHERE patient_id = :pid AND sha256 = :sha AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['pid' => $patientId, 'sha' => $sha256]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Dépose un nouveau document médical (PDF, JPG ou PNG <= 10 Mo).
     * Détecte les doublons par SHA-256 (avertissement non bloquant).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $file
     * @return array{
     *   id: int,
     *   document: array<string, mixed>,
     *   duplicate_warning: bool,
     *   duplicate_document: ?array<string, mixed>
     * }
     */
    public static function storeDocument(
        array $input,
        array $file,
        int $actorId,
        int $patientId = 1
    ): array {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Le titre du document est obligatoire.');
        }

        $category = self::normalizeCategory((string) ($input['category'] ?? 'autre'));
        $docDate = trim((string) ($input['doc_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate)) {
            $docDate = date('Y-m-d');
        }
        $description = trim((string) ($input['description'] ?? '')) ?: null;
        $prescriptionId = !empty($input['prescription_id']) ? (int) $input['prescription_id'] : null;

        // Upload & validate (10 MB max, finfo, %PDF- check, GD re-encode stripping EXIF)
        $uploaded = Uploader::uploadDocument($file, 'documents', 10 * 1024 * 1024);

        // Check SHA-256 duplicate BEFORE inserting (warning only, never blocks)
        $existingDup = self::findDuplicateBySha256($uploaded['sha256'], $patientId);

        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO `documents`
             (`patient_id`, `prescription_id`, `category`, `title`, `doc_date`, `description`,
              `file_path`, `original_name`, `mime`, `size`, `sha256`, `uploaded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (:pid, :rx_id, :cat, :title, :doc_date, :desc,
              :file_path, :orig, :mime, :size, :sha256, :by, :created_at, :updated_at, :created_by)'
        );
        $stmt->execute([
            'pid' => $patientId,
            'rx_id' => $prescriptionId,
            'cat' => $category,
            'title' => $title,
            'doc_date' => $docDate,
            'desc' => $description,
            'file_path' => $uploaded['filename'],
            'orig' => $uploaded['original_name'],
            'mime' => $uploaded['mime'],
            'size' => $uploaded['size'],
            'sha256' => $uploaded['sha256'],
            'by' => $actorId,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
            'created_by' => $actorId,
        ]);

        $docId = (int) $pdo->lastInsertId();
        $doc = self::findById($docId, $patientId) ?? [];

        AuditService::record('create', 'documents', $docId, null, [
            'title' => $title,
            'category' => $category,
            'doc_date' => $docDate,
            'mime' => $uploaded['mime'],
            'size' => $uploaded['size'],
            'sha256' => $uploaded['sha256'],
            'duplicate_of' => $existingDup['id'] ?? null,
        ], $patientId, $actorId);

        return [
            'id' => $docId,
            'document' => $doc,
            'duplicate_warning' => $existingDup !== null,
            'duplicate_document' => $existingDup,
        ];
    }

    /**
     * Suppression logique d'un document médical (admin uniquement).
     */
    public static function deleteDocument(int $id, int $actorId, int $patientId = 1): bool
    {
        $doc = self::findById($id, $patientId);
        if ($doc === null) {
            return false;
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'UPDATE documents SET deleted_at = :del, updated_at = :upd WHERE id = :id AND patient_id = :pid'
        );
        $stmt->execute([
            'del' => $nowUtc,
            'upd' => $nowUtc,
            'id' => $id,
            'pid' => $patientId,
        ]);

        AuditService::record('delete', 'documents', $id, [
            'title' => $doc['title'],
            'category' => $doc['category'],
            'doc_date' => $doc['doc_date'],
        ], [
            'deleted_at' => $nowUtc,
        ], $patientId, $actorId);

        return true;
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' o';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
        }
        return number_format($bytes / 1048576, 2, ',', ' ') . ' Mo';
    }
}
