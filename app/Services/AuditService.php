<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

/**
 * Immutable audit trail logger and human-readable diff engine (E26, Phase 4 §4).
 * Strips sensitive fields (password_hash, pin_hash, tokens, CSRF) before persisting JSON diffs,
 * and computes field-by-field before/after diffs so raw JSON is never displayed.
 */
final class AuditService
{
    /** @var array<int, string> */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'password_hash',
        'current_password',
        'pin',
        'pin_confirmation',
        'pin_hash',
        'validator',
        'validator_hash',
        'token',
        'token_hash',
        '_csrf',
        'auth_token',
        'p256dh',
    ];

    /** @var array<string, string> */
    public const ENTITY_LABELS = [
        'vital_measurements' => 'Mesures & Constantes',
        'medication_intakes' => 'Prises de médicaments',
        'medications' => 'Traitements',
        'prescriptions' => 'Ordonnances',
        'meals' => 'Repas & Hydratation',
        'journal_notes' => 'Journal & Transmissions',
        'care_checks' => 'Soins rapides',
        'care_items' => 'Catalogue de soins',
        'attendances' => 'Pointages',
        'shifts' => 'Planning & Gardes',
        'absences' => 'Absences',
        'alerts' => 'Alertes',
        'documents' => 'Documents médicaux',
        'users' => 'Équipe & Connexions',
        'trusted_devices' => 'Appareils de confiance',
        'patients' => 'Fiche patient',
        'settings' => 'Réglages',
        'exports' => 'Exports CSV & RGPD',
        'audit_logs' => 'Journal d’audit',
    ];

    /** @var array<string, string> */
    public const ACTION_CATEGORIES = [
        'create' => 'Création',
        'update' => 'Modification',
        'delete' => 'Suppression',
        'login' => 'Connexion réussie',
        'login_failed' => 'Échec de connexion',
        'export' => 'Export de données',
        'validate' => 'Validation / Acquittement',
        'revoke_device' => 'Révocation d’appareil',
    ];

    /** @var array<string, string> */
    public const FIELD_LABELS = [
        'id' => 'Identifiant',
        'type' => 'Type',
        'category' => 'Catégorie',
        'title' => 'Titre',
        'description' => 'Description',
        'content' => 'Contenu',
        'comment' => 'Commentaire',
        'notes' => 'Notes',
        'value_numeric' => 'Valeur mesurée',
        'systolic' => 'Tension systolique (mmHg)',
        'diastolic' => 'Tension diastolique (mmHg)',
        'pulse' => 'Pouls (bpm)',
        'unit' => 'Unité',
        'context' => 'Contexte',
        'insulin_Rapid_units' => 'Insuline rapide (UI)',
        'insulin_slow_units' => 'Insuline lente (UI)',
        'injection_site' => 'Site d’injection',
        'is_critical' => 'Valeur critique',
        'critical_Flag' => 'Alerte critique',
        'measured_at' => 'Date de mesure',
        'status' => 'Statut',
        'scheduled_at' => 'Horaire prévu',
        'taken_at' => 'Horaire de prise',
        'dose_administered' => 'Dose administrée',
        'skip_reason' => 'Motif de non-prise',
        'meal_type' => 'Type de repas',
        'eaten_at' => 'Heure du repas',
        'portion_percent' => 'Portion consommée (%)',
        'carbs_grams' => 'Glucides (g)',
        'hydration_ml' => 'Hydratation (mL)',
        'severity' => 'Gravité',
        'is_urgent' => 'Transmission urgente',
        'is_pinned' => 'Épinglé',
        'clock_in_at' => 'Heure d’arrivée',
        'clock_out_at' => 'Heure de départ',
        'clock_in_mode' => 'Mode d’arrivée',
        'clock_out_mode' => 'Mode de départ',
        'distance_in_m' => 'Distance arrivée (m)',
        'distance_out_m' => 'Distance départ (m)',
        'validation_status' => 'Statut de validation',
        ' starts_at' => 'Début de garde',
        'starts_at' => 'Début',
        'ends_at' => 'Fin',
        'shift_type' => 'Type de garde',
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'Adresse e-mail',
        'phone' => 'Téléphone',
        'role' => 'Rôle',
        'is_active' => 'Compte actif',
        'theme' => 'Thème d’affichage',
        'method' => 'Méthode',
        'reason' => 'Motif',
        'selector' => 'Sélecteur d’appareil',
        'device_label' => 'Nom de l’appareil',
        'revoked_count' => 'Appareils révoqués',
        'scope' => 'Portée',
        'doc_date' => 'Date du document',
        'mime' => 'Format de fichier',
        'size' => 'Taille (octets)',
        'sha256' => 'Empreinte SHA-256',
        'rows' => 'Nombre de lignes',
        'from' => 'Début de période',
        'to' => 'Fin de période',
        'resolution_note' => 'Note de résolution',
        'handled_at' => 'Traité le',
        'acknowledged_at' => 'Acquitté le',
    ];

    /**
     * Record an audit log entry.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public static function record(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $patientId = 1,
        ?int $explicitUserId = null
    ): void {
        try {
            $userId = $explicitUserId ?? Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 255);

            $cleanOld = $oldValues !== null ? self::sanitize($oldValues) : null;
            $cleanNew = $newValues !== null ? self::sanitize($newValues) : null;

            $stmt = Database::connection()->prepare(
                'INSERT INTO `audit_logs`
                 (`patient_id`, `user_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `ip_address`, `user_agent`, `created_at`)
                 VALUES (:patient_id, :user_id, :action, :entity_type, :entity_id, :before_data, :after_data, :ip, :ua, :created_at)'
            );

            $stmt->execute([
                'patient_id' => $patientId,
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'before_data' => $cleanOld !== null ? json_encode($cleanOld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'after_data' => $cleanNew !== null ? json_encode($cleanNew, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip' => $ip,
                'ua' => $ua,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('[AuditService] Failed to record audit entry: ' . $e->getMessage());
        }
    }

    /**
     * Alias for record() accepting actorId as 6th parameter.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
        int $patientId = 1
    ): void {
        self::record($action, $entityType, $entityId, $before, $after, $patientId, $actorId);
    }

    /**
     * Query paginated audit logs with filters (Phase 4 §4).
     *
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function listLogs(
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        int $patientId = 1
    ): array {
        $pdo = Database::connection();
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));

        $where = ['(a.patient_id = :pid OR a.patient_id IS NULL)', 'a.deleted_at IS NULL'];
        $params = ['pid' => $patientId];

        if (!empty($filters['user_id']) && is_numeric($filters['user_id'])) {
            $where[] = 'a.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }

        if (!empty($filters['entity_type']) && is_string($filters['entity_type'])) {
            $where[] = 'a.entity_type = :entity';
            $params['entity'] = trim($filters['entity_type']);
        }

        if (!empty($filters['action']) && is_string($filters['action'])) {
            $act = trim($filters['action']);
            $actClause = self::buildActionFilterClause($act, $params);
            if ($actClause !== '') {
                $where[] = $actClause;
            }
        }

        if (!empty($filters['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['from']) === 1) {
            $where[] = 'a.created_at >= :from_dt';
            $params['from_dt'] = $filters['from'] . ' 00:00:00';
        }

        if (!empty($filters['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['to']) === 1) {
            $where[] = 'a.created_at <= :to_dt';
            $params['to_dt'] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `audit_logs` a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT a.*, u.first_name, u.last_name, u.role AS user_role
                FROM `audit_logs` a
                LEFT JOIN `users` u ON u.id = a.user_id
                WHERE {$whereSql}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $items = [];
        foreach ($rows as $row) {
            $row['action_label'] = self::formatActionLabel((string) ($row['action'] ?? ''));
            $row['action_category'] = self::categorizeAction((string) ($row['action'] ?? ''));
            $row['entity_label'] = self::ENTITY_LABELS[(string) ($row['entity_type'] ?? '')] ?? (string) ($row['entity_type'] ?? '');
            $row['diffs'] = self::computeDiff(
                isset($row['before_data']) ? (string) $row['before_data'] : null,
                isset($row['after_data']) ? (string) $row['after_data'] : null
            );
            $items[] = $row;
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * Compute a human-readable field-by-field before/after diff array.
     * Never exposes raw JSON.
     *
     * @return array<int, array{field: string, label: string, old: ?string, new: ?string, type: string}>
     */
    public static function computeDiff(?string $oldValuesJson, ?string $newValuesJson): array
    {
        $oldArr = ($oldValuesJson !== null && $oldValuesJson !== '')
            ? (json_decode($oldValuesJson, true) ?: [])
            : [];
        $newArr = ($newValuesJson !== null && $newValuesJson !== '')
            ? (json_decode($newValuesJson, true) ?: [])
            : [];

        if (!is_array($oldArr)) {
            $oldArr = [];
        }
        if (!is_array($newArr)) {
            $newArr = [];
        }

        // Ignore internal metadata timestamps if both exist and aren't meaningful
        $ignoredKeys = ['updated_at', 'created_at', 'patient_id'];
        $allKeys = array_values(array_unique(array_merge(array_keys($oldArr), array_keys($newArr))));

        $diffs = [];
        foreach ($allKeys as $key) {
            $keyStr = (string) $key;
            if (in_array($keyStr, $ignoredKeys, true) && count($allKeys) > 2) {
                continue;
            }
            if (in_array(strtolower($keyStr), array_map('strtolower', self::REDACTED_KEYS), true)) {
                continue;
            }

            $hasOld = array_key_exists($keyStr, $oldArr) && $oldArr[$keyStr] !== null && $oldArr[$keyStr] !== '';
            $hasNew = array_key_exists($keyStr, $newArr) && $newArr[$keyStr] !== null && $newArr[$keyStr] !== '';

            if (!$hasOld && !$hasNew) {
                continue;
            }

            $oldFormatted = $hasOld ? self::formatFieldValue($keyStr, $oldArr[$keyStr]) : null;
            $newFormatted = $hasNew ? self::formatFieldValue($keyStr, $newArr[$keyStr]) : null;

            if ($oldArr !== [] && $newArr !== []) {
                // Both old and new snapshots exist: highlight changed fields first
                if ($oldFormatted === $newFormatted) {
                    continue;
                }
                $type = !$hasOld ? 'added' : (!$hasNew ? 'removed' : 'changed');
            } elseif ($oldArr !== []) {
                $type = 'removed';
            } else {
                $type = 'added';
            }

            $diffs[] = [
                'field' => $keyStr,
                'label' => self::FIELD_LABELS[$keyStr] ?? ucfirst(str_replace('_', ' ', $keyStr)),
                'old' => $oldFormatted,
                'new' => $newFormatted,
                'type' => $type,
            ];
        }

        return $diffs;
    }

    public static function formatActionLabel(string $action): string
    {
        $map = [
            'auth.login' => 'Connexion par mot de passe',
            'auth.login_pin' => 'Connexion par code PIN',
            'auth.login_failed' => 'Échec de connexion (mot de passe)',
            'auth.login_pin_failed' => 'Échec de connexion (code PIN)',
            'auth.logout' => 'Déconnexion',
            'auth.revoke_device' => 'Révocation d’appareil de confiance',
            'auth.password_reset_requested' => 'Demande de réinitialisation mot de passe',
            'auth.password_reset_completed' => 'Réinitialisation mot de passe terminée',
            'vital.create' => 'Saisie d’une constante médicale',
            'vital.update' => 'Correction d’une constante médicale',
            'vital.delete' => 'Suppression d’une constante médicale',
            'intake.record' => 'Validation d’une prise de médicament',
            'treatment.create' => 'Ajout d’un traitement',
            'treatment.update' => 'Modification d’un traitement',
            'treatment.stop' => 'Arrêt d’un traitement',
            'meal.create' => 'Enregistrement d’un repas / hydratation',
            'meal.update' => 'Modification d’un repas',
            'meal.delete' => 'Suppression d’un repas',
            'journal.create' => 'Ajout d’une note / transmission',
            'journal.update' => 'Modification d’une note',
            'journal.delete' => 'Suppression d’une note',
            'journal.acknowledge' => 'Lecture confirmée d’une transmission',
            'care.check' => 'Soin rapide coché',
            'attendance.clock_in' => 'Pointage d’arrivée',
            'attendance.clock_out' => 'Pointage de départ',
            'attendance.manual' => 'Pointage manuel avec motif',
            'attendance.validate' => 'Validation / correction de pointage',
            'planning.shift_create' => 'Création d’une garde',
            'planning.shift_update' => 'Modification d’une garde',
            'planning.shift_delete' => 'Suppression d’une garde',
            'planning.duplicate_week' => 'Duplication d’une semaine de planning',
            'planning.absence_create' => 'Déclaration d’une absence',
            'alert.acknowledge' => 'Acquittement d’une alerte',
            'alert.handle' => 'Résolution d’une alerte',
            'document.upload' => 'Ajout d’un document médical',
            'document.delete' => 'Suppression d’un document médical',
            'export.csv' => 'Export CSV de données',
            'rgpd.export_user_data' => 'Export RGPD des données personnelles',
            'rgpd.export_patient_data' => 'Export RGPD intégral du dossier patient',
            'retention.purge' => 'Purge automatique RGPD (conservation)',
            'profile.update_info' => 'Mise à jour du profil',
            'profile.update_password' => 'Changement de mot de passe',
            'profile.update_pin' => 'Changement de code PIN',
            'profile.trust_device' => 'Activation d’un appareil de confiance',
            'profile.revoke_trusted_device' => 'Désactivation d’appareil de confiance',
            'profile.revoke_other_devices' => 'Révocation de tous les appareils',
            'settings.update' => 'Modification des réglages',
        ];

        return $map[$action] ?? str_replace(['.', '_'], [' · ', ' '], ucfirst($action));
    }

    public static function categorizeAction(string $action): string
    {
        $a = strtolower($action);
        if (str_contains($a, 'login_failed') || str_contains($a, 'pin_failed')) {
            return 'login_failed';
        }
        if (str_contains($a, 'login')) {
            return 'login';
        }
        if (str_contains($a, 'revoke')) {
            return 'revoke_device';
        }
        if (str_contains($a, 'export')) {
            return 'export';
        }
        if (str_contains($a, 'validate') || str_contains($a, 'acknowledge') || str_contains($a, 'handle')) {
            return 'validate';
        }
        if (str_contains($a, 'delete') || str_contains($a, 'stop') || str_contains($a, 'purge')) {
            return 'delete';
        }
        if (str_contains($a, 'update') || str_contains($a, 'edit')) {
            return 'update';
        }
        return 'create';
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function buildActionFilterClause(string $act, array &$params): string
    {
        return match ($act) {
            'login' => "(a.action IN ('auth.login', 'auth.login_pin'))",
            'login_failed' => "(a.action IN ('auth.login_failed', 'auth.login_pin_failed'))",
            'revoke_device' => "(a.action LIKE '%revoke%')",
            'export' => "(a.action LIKE 'export.%' OR a.action LIKE 'rgpd.export%')",
            'validate' => "(a.action LIKE '%validate%' OR a.action LIKE '%acknowledge%' OR a.action LIKE '%handle%')",
            'delete' => "(a.action LIKE '%delete%' OR a.action LIKE '%stop%' OR a.action LIKE '%purge%')",
            'update' => "(a.action LIKE '%update%')",
            'create' => "(a.action LIKE '%create%' OR a.action LIKE '%upload%' OR a.action LIKE '%record%' OR a.action LIKE '%clock_%' OR a.action = 'care.check')",
            default => (function () use ($act, &$params): string {
                $params['act_exact'] = $act;
                return 'a.action = :act_exact';
            })(),
        };
    }

    private static function formatFieldValue(string $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = (is_string($k) ? $k . ': ' : '') . (is_scalar($v) ? (string) $v : '');
            }
            return implode(', ', $parts);
        }
        $str = trim((string) $value);
        if (in_array($key, ['is_critical', 'critical_flag', 'is_urgent', 'is_pinned', 'is_active', 'remember'], true)) {
            return ($str === '1' || strtolower($str) === 'true') ? 'Oui' : 'Non';
        }
        if (is_numeric($str) && str_contains($str, '.')) {
            return str_replace('.', ',', $str);
        }
        return $str;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), array_map('strtolower', self::REDACTED_KEYS), true)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = self::sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }
        return $clean;
    }
}
