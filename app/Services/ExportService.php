<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * ExportService — Génération des exports CSV (Phase 4 §3) et des archives RGPD (§5.2) :
 * - Séparateur point-virgule (;), encodage UTF-8 avec BOM (\xEF\xBB\xBF)
 * - Dates en heure de Paris au format JJ/MM/AAAA HH:MM
 * - Nombres avec virgule décimale
 * - En-têtes en français
 * - Traçabilité systématique dans audit_logs (qui, quoi, quelle période).
 */
final class ExportService
{
    public const EXPORT_CATALOG = [
        'measures' => [
            'label' => 'Mesures & Constantes médicales',
            'description' => 'Glycémie, tension, température, SpO2, poids, contexte et statut de seuil.',
            'icon' => 'heart-pulse',
            'category' => 'medical',
            'roles' => ['admin', 'coordinator', 'viewer'],
        ],
        'intakes' => [
            'label' => 'Prises de médicaments & Insuline',
            'description' => 'Prises programmées et « si besoin », doses d’insuline, sites d’injection et motifs de non-prise.',
            'icon' => 'pill',
            'category' => 'medical',
            'roles' => ['admin', 'coordinator', 'viewer'],
        ],
        'meals' => [
            'label' => 'Repas & Hydratation',
            'description' => 'Composition des repas, portion mangée (%), glucides estimés (g) et boissons (mL).',
            'icon' => 'utensils',
            'category' => 'medical',
            'roles' => ['admin', 'coordinator', 'viewer'],
        ],
        'notes' => [
            'label' => 'Notes & Transmissions de relève',
            'description' => 'Journal de bord, événements cliniques et transmissions de fin de poste.',
            'icon' => 'notebook-pen',
            'category' => 'medical',
            'roles' => ['admin', 'coordinator', 'viewer'],
        ],
        'care_checks' => [
            'label' => 'Soins rapides quotidiens',
            'description' => 'Toilette, inspection des pieds diabétiques, hydratation, marche accompagnée.',
            'icon' => 'sparkles',
            'category' => 'medical',
            'roles' => ['admin', 'coordinator', 'viewer'],
        ],
        'attendances' => [
            'label' => 'Pointages d’arrivée et de départ',
            'description' => 'Horodatages serveur, méthode (glisser, QR, déclaré), distance GPS et statut de validation.',
            'icon' => 'clock',
            'category' => 'hr',
            'roles' => ['admin', 'coordinator'],
        ],
        'hours' => [
            'label' => 'Récapitulatif des heures (Paie)',
            'description' => 'Heures planifiées terminées, heures pointées, nuit (21h–6h), dimanche, jours fériés et écart.',
            'icon' => 'file-spreadsheet',
            'category' => 'hr',
            'roles' => ['admin', 'coordinator'],
        ],
        'alerts' => [
            'label' => 'Alertes & Escalades',
            'description' => 'Historique des alertes déclenchées, gravité, statut, escalade et résolution.',
            'icon' => 'bell',
            'category' => 'team',
            'roles' => ['admin', 'coordinator'],
        ],
        'audit' => [
            'label' => 'Journal d’audit de sécurité',
            'description' => 'Traçabilité complète des créations, modifications, suppressions, connexions et exports.',
            'icon' => 'shield-check',
            'category' => 'security',
            'roles' => ['admin'],
        ],
    ];

    /**
     * Vérifie si un rôle est autorisé à exporter un module donné.
     */
    public static function canExport(string $role, string $type): bool
    {
        $meta = self::EXPORT_CATALOG[$type] ?? null;
        if ($meta === null) {
            return false;
        }
        return in_array($role, $meta['roles'], true);
    }

    /**
     * Génère le contenu CSV (UTF-8 avec BOM, séparateur ';') pour un type donné et trace l'export dans audit_logs.
     *
     * @return array{csv: string, filename: string, rows_count: int}
     */
    public static function generateExportCsv(
        string $type,
        string $fromUtc,
        string $toUtc,
        string $fromYmd,
        string $toYmd,
        int $actorId,
        int $patientId = 1
    ): array {
        if (!isset(self::EXPORT_CATALOG[$type])) {
            throw new RuntimeException('Type d’export inconnu.', 404);
        }

        $pdo = Database::connection();
        $headers = [];
        $rows = [];

        switch ($type) {
            case 'measures':
                $headers = [
                    'Date et heure (Paris)',
                    'Type de mesure',
                    'Valeur principale',
                    'Valeur secondaire',
                    'Valeur tertiaire',
                    'Unité',
                    'Contexte',
                    'Niveau d’alerte',
                    'Statut de seuil',
                    'Commentaire',
                    'Saisi par',
                ];
                $stmt = $pdo->prepare(
                    "SELECT m.measured_at, mt.label AS type_label, mt.decimals, m.value, m.value2, m.value3,
                            COALESCE(m.unit_recorded, mt.unit) AS unit, m.context, m.alert_level, m.threshold_level, m.comment,
                            u.first_name, u.last_name
                     FROM measures m
                     INNER JOIN measure_types mt ON mt.id = m.measure_type_id
                     LEFT JOIN users u ON u.id = m.recorded_by
                     WHERE m.patient_id = :pid AND m.deleted_at IS NULL
                       AND m.measured_at BETWEEN :from_utc AND :to_utc
                     ORDER BY m.measured_at DESC, m.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $dec = (int) ($r['decimals'] ?? 2);
                    $rows[] = [
                        self::formatParisDateTime((string) $r['measured_at']),
                        (string) $r['type_label'],
                        self::formatNumber($r['value'], $dec),
                        $r['value2'] !== null ? self::formatNumber($r['value2'], 0) : '',
                        $r['value3'] !== null ? self::formatNumber($r['value3'], 0) : '',
                        (string) $r['unit'],
                        self::contextLabelFr((string) $r['context']),
                        self::alertLevelFr((string) $r['alert_level']),
                        (string) $r['threshold_level'],
                        (string) ($r['comment'] ?? ''),
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    ];
                }
                break;

            case 'intakes':
                $headers = [
                    'Horaire prévu (Paris)',
                    'Horaire réel (Paris)',
                    'Médicament',
                    'Statut',
                    'Dose administrée',
                    'Unité',
                    'Site d’injection',
                    'Motif si non donné / si besoin',
                    'Commentaire',
                    'Validé par',
                ];
                $stmt = $pdo->prepare(
                    "SELECT mi.scheduled_at, mi.given_at, mi.status, mi.given_dose, mi.injection_site, mi.reason, mi.comment,
                            p.dose AS default_dose, p.unit,
                            m.name AS medication_name,
                            u.first_name, u.last_name
                     FROM medication_intakes mi
                     INNER JOIN prescriptions p ON p.id = mi.prescription_id
                     INNER JOIN medications m ON m.id = p.medication_id
                     LEFT JOIN users u ON u.id = mi.given_by
                     WHERE mi.patient_id = :pid AND mi.deleted_at IS NULL
                       AND COALESCE(mi.given_at, mi.scheduled_at) BETWEEN :from_utc AND :to_utc
                     ORDER BY COALESCE(mi.given_at, mi.scheduled_at) DESC, mi.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $statusFr = match ((string) $r['status']) {
                        'given' => 'Donné',
                        'skipped' => 'Non donné',
                        default => 'En attente',
                    };
                    $siteCode = (string) ($r['injection_site'] ?? '');
                    $siteLabel = InsulinService::INJECTION_SITES[$siteCode] ?? $siteCode;
                    $doseVal = $r['given_dose'] ?? $r['default_dose'];
                    $rows[] = [
                        self::formatParisDateTime((string) $r['scheduled_at']),
                        !empty($r['given_at']) ? self::formatParisDateTime((string) $r['given_at']) : '',
                        (string) $r['medication_name'],
                        $statusFr,
                        $doseVal !== null ? self::formatNumber($doseVal, 2) : '',
                        (string) $r['unit'],
                        $siteLabel,
                        (string) ($r['reason'] ?? ''),
                        (string) ($r['comment'] ?? ''),
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    ];
                }
                break;

            case 'meals':
                $headers = [
                    'Date et heure (Paris)',
                    'Type de repas',
                    'Composition',
                    'Portion mangée (%)',
                    'Glucides estimés (g)',
                    'Boisson',
                    'Volume bu (mL)',
                    'Commentaire',
                    'Saisi par',
                ];
                $stmt = $pdo->prepare(
                    "SELECT ml.eaten_at, ml.meal_type, ml.description, ml.portion_eaten, ml.carbs_g,
                            ml.drink_type, ml.drink_ml, ml.comment,
                            u.first_name, u.last_name
                     FROM meals ml
                     LEFT JOIN users u ON u.id = ml.recorded_by
                     WHERE ml.patient_id = :pid AND ml.deleted_at IS NULL
                       AND ml.eaten_at BETWEEN :from_utc AND :to_utc
                     ORDER BY ml.eaten_at DESC, ml.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $mealTypeFr = match ((string) $r['meal_type']) {
                        'breakfast' => 'Petit-déjeuner',
                        'lunch' => 'Déjeuner',
                        'snack' => 'Collation',
                        'dinner' => 'Dîner',
                        default => 'Collation nocturne',
                    };
                    $rows[] = [
                        self::formatParisDateTime((string) $r['eaten_at']),
                        $mealTypeFr,
                        (string) $r['description'],
                        self::formatNumber($r['portion_eaten'], 0),
                        $r['carbs_g'] !== null ? self::formatNumber($r['carbs_g'], 0) : '',
                        (string) ($r['drink_type'] ?? ''),
                        $r['drink_ml'] !== null ? self::formatNumber($r['drink_ml'], 0) : '',
                        (string) ($r['comment'] ?? ''),
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    ];
                }
                break;

            case 'notes':
                $headers = [
                    'Date et heure (Paris)',
                    'Catégorie',
                    'Priorité',
                    'Transmission de relève',
                    'Titre',
                    'Contenu',
                    'Rédigé par',
                ];
                $stmt = $pdo->prepare(
                    "SELECT n.created_at, n.category, n.priority, n.is_handover, n.title, n.content,
                            u.first_name, u.last_name
                     FROM notes n
                     LEFT JOIN users u ON u.id = n.recorded_by
                     WHERE n.patient_id = :pid AND n.deleted_at IS NULL
                       AND n.created_at BETWEEN :from_utc AND :to_utc
                     ORDER BY n.created_at DESC, n.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = [
                        self::formatParisDateTime((string) $r['created_at']),
                        (string) $r['category'],
                        (string) $r['priority'],
                        !empty($r['is_handover']) ? 'Oui' : 'Non',
                        (string) ($r['title'] ?? ''),
                        (string) $r['content'],
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    ];
                }
                break;

            case 'care_checks':
                $headers = [
                    'Date et heure (Paris)',
                    'Soin rapide',
                    'Catégorie',
                    'Commentaire',
                    'Réalisé par',
                ];
                $stmt = $pdo->prepare(
                    "SELECT cc.checked_at, ci.label, ci.category, cc.comment,
                            u.first_name, u.last_name
                     FROM care_checks cc
                     INNER JOIN care_items ci ON ci.id = cc.care_item_id
                     LEFT JOIN users u ON u.id = cc.performed_by
                     WHERE cc.patient_id = :pid AND cc.deleted_at IS NULL
                       AND cc.checked_at BETWEEN :from_utc AND :to_utc
                     ORDER BY cc.checked_at DESC, cc.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = [
                        self::formatParisDateTime((string) $r['checked_at']),
                        (string) $r['label'],
                        (string) $r['category'],
                        (string) ($r['comment'] ?? ''),
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    ];
                }
                break;

            case 'attendances':
                $headers = [
                    'Date et heure serveur (Paris)',
                    'Date et heure déclarée (Paris)',
                    'Aide-soignante',
                    'Type',
                    'Méthode',
                    'Distance domicile (m)',
                    'Statut',
                    'Motif / Anomalie',
                ];
                $stmt = $pdo->prepare(
                    "SELECT a.recorded_at, a.declared_at, a.type, a.method, a.distance_m, a.status, a.reason,
                            u.first_name, u.last_name
                     FROM attendances a
                     INNER JOIN users u ON u.id = a.user_id
                     WHERE a.patient_id = :pid AND a.deleted_at IS NULL
                       AND COALESCE(a.declared_at, a.recorded_at) BETWEEN :from_utc AND :to_utc
                     ORDER BY COALESCE(a.declared_at, a.recorded_at) DESC, a.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = [
                        self::formatParisDateTime((string) $r['recorded_at']),
                        !empty($r['declared_at']) ? self::formatParisDateTime((string) $r['declared_at']) : '',
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                        $r['type'] === 'in' ? 'Arrivée' : 'Départ',
                        (string) $r['method'],
                        $r['distance_m'] !== null ? self::formatNumber($r['distance_m'], 0) : '',
                        (string) $r['status'],
                        (string) ($r['reason'] ?? ''),
                    ];
                }
                break;

            case 'hours':
                $year = (int) substr($toYmd, 0, 4);
                $month = (int) substr($toYmd, 5, 2);
                $summary = HoursCalculatorService::calculateMonthlySummary($year, $month, null, $patientId);
                $headers = [
                    'Salariée',
                    'Contrat',
                    'Mois',
                    'Heures planifiées terminées (h)',
                    'Planifié restant ce mois-ci (h)',
                    'Heures pointées validées (h)',
                    'Heures de nuit 21h-6h (h)',
                    'Heures dimanche (h)',
                    'Heures jours fériés (h)',
                    'Écart sur créneaux terminés (h)',
                ];
                $monthPeriodStr = sprintf('01/%02d/%04d 00:00', $month, $year);
                foreach ($summary['caregivers'] as $cg) {
                    $rows[] = [
                        (string) $cg['full_name'],
                        (string) $cg['contract_type'],
                        $monthPeriodStr,
                        self::formatNumber($cg['completed_planned_hours'] ?? 0, 2),
                        self::formatNumber($cg['remaining_planned_hours'] ?? 0, 2),
                        self::formatNumber($cg['punched_hours'] ?? 0, 2),
                        self::formatNumber($cg['night_hours'] ?? 0, 2),
                        self::formatNumber($cg['sunday_hours'] ?? 0, 2),
                        self::formatNumber($cg['holiday_hours'] ?? 0, 2),
                        self::formatNumber($cg['diff_hours'] ?? 0, 2),
                    ];
                }
                break;

            case 'alerts':
                $headers = [
                    'Date de création (Paris)',
                    'Niveau',
                    'Type',
                    'Titre',
                    'Message',
                    'Statut',
                    'Date de traitement (Paris)',
                    'Résolution',
                    'Traité par',
                ];
                $stmt = $pdo->prepare(
                    "SELECT a.created_at, a.level, a.type, a.title, a.message, a.status, a.handled_at, a.resolution,
                            u.first_name, u.last_name
                     FROM alerts a
                     LEFT JOIN users u ON u.id = a.handled_by
                     WHERE a.patient_id = :pid AND a.deleted_at IS NULL
                       AND a.created_at BETWEEN :from_utc AND :to_utc
                     ORDER BY a.created_at DESC, a.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = [
                        self::formatParisDateTime((string) $r['created_at']),
                        (string) $r['level'],
                        (string) $r['type'],
                        (string) $r['title'],
                        (string) $r['message'],
                        (string) $r['status'],
                        !empty($r['handled_at']) ? self::formatParisDateTime((string) $r['handled_at']) : '',
                        (string) ($r['resolution'] ?? ''),
                        trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')),
                    ];
                }
                break;

            case 'audit':
                $headers = [
                    'Date et heure (Paris)',
                    'Utilisateur',
                    'Action',
                    'Module / Table',
                    'ID Ressource',
                    'Adresse IP',
                    'Résumé des modifications',
                ];
                $stmt = $pdo->prepare(
                    "SELECT al.created_at, al.action, al.entity_type, al.entity_id, al.ip_address,
                            al.before_data, al.after_data,
                            u.first_name, u.last_name
                     FROM audit_logs al
                     LEFT JOIN users u ON u.id = al.user_id
                     WHERE (al.patient_id = :pid OR al.patient_id IS NULL)
                       AND al.deleted_at IS NULL
                       AND al.created_at BETWEEN :from_utc AND :to_utc
                     ORDER BY al.created_at DESC, al.id DESC"
                );
                $stmt->execute(['pid' => $patientId, 'from_utc' => $fromUtc, 'to_utc' => $toUtc]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $userName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Système';
                    $summaryText = self::summarizeAuditDiff($r['before_data'] ?? null, $r['after_data'] ?? null);
                    $rows[] = [
                        self::formatParisDateTime((string) $r['created_at']),
                        $userName,
                        (string) $r['action'],
                        (string) $r['entity_type'],
                        $r['entity_id'] !== null ? (string) $r['entity_id'] : '',
                        (string) ($r['ip_address'] ?? ''),
                        $summaryText,
                    ];
                }
                break;
        }

        $csv = self::buildCsvString($headers, $rows);
        $filename = sprintf('prime_care_%s_%s_%s.csv', $type, $fromYmd, $toYmd);

        // Log every export in audit_logs (who, what, which period)
        AuditService::record('export', $type, null, null, [
            'export_type' => $type,
            'period_from' => $fromYmd,
            'period_to' => $toYmd,
            'rows_count' => count($rows),
        ], $patientId, $actorId);

        return [
            'csv' => $csv,
            'filename' => $filename,
            'rows_count' => count($rows),
        ];
    }

    /**
     * Génère l'archive RGPD personnelle d'un utilisateur (§5.2 : « Exporter mes données »).
     * Contient strictement le compte de l'utilisateur connecté, ses saisies et ses pointages.
     *
     * @return array{file_path: string, filename: string, mime: string}
     */
    public static function exportUserPersonalDataArchive(int $userId, int $patientId = 1): array
    {
        $pdo = Database::connection();

        $uStmt = $pdo->prepare(
            'SELECT id, first_name, last_name, email, phone, role, contract_type, weekly_hours, hired_at, theme, locale, attendance_gdpr_accepted_at, created_at
             FROM users WHERE id = :uid LIMIT 1'
        );
        $uStmt->execute(['uid' => $userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException('Utilisateur introuvable.', 404);
        }

        // 1. Account CSV
        $accountCsv = self::buildCsvString(
            ['ID', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Rôle', 'Contrat', 'Heures hebdo', 'Date embauche', 'Notice RGPD pointage acceptée le', 'Compte créé le'],
            [[
                (string) $user['id'],
                (string) $user['first_name'],
                (string) $user['last_name'],
                (string) $user['email'],
                (string) ($user['phone'] ?? ''),
                (string) $user['role'],
                (string) ($user['contract_type'] ?? ''),
                self::formatNumber($user['weekly_hours'] ?? 0, 2),
                (string) ($user['hired_at'] ?? ''),
                !empty($user['attendance_gdpr_accepted_at']) ? self::formatParisDateTime((string) $user['attendance_gdpr_accepted_at']) : '',
                self::formatParisDateTime((string) $user['created_at']),
            ]]
        );

        // 2. Medical entries recorded by this user only
        $entryRows = [];
        $mStmt = $pdo->prepare(
            "SELECT m.measured_at, mt.label, m.value, mt.unit, m.comment
             FROM measures m
             INNER JOIN measure_types mt ON mt.id = m.measure_type_id
             WHERE m.recorded_by = :uid AND m.deleted_at IS NULL
             ORDER BY m.measured_at DESC"
        );
        $mStmt->execute(['uid' => $userId]);
        foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entryRows[] = [
                self::formatParisDateTime((string) $r['measured_at']),
                'Mesure médicale',
                $r['label'] . ' : ' . self::formatNumber($r['value'], 2) . ' ' . $r['unit'],
                (string) ($r['comment'] ?? ''),
            ];
        }

        $iStmt = $pdo->prepare(
            "SELECT COALESCE(mi.given_at, mi.scheduled_at) AS at_utc, m.name, mi.status, mi.given_dose, p.unit, mi.comment
             FROM medication_intakes mi
             INNER JOIN prescriptions p ON p.id = mi.prescription_id
             INNER JOIN medications m ON m.id = p.medication_id
             WHERE mi.given_by = :uid AND mi.deleted_at IS NULL
             ORDER BY at_utc DESC"
        );
        $iStmt->execute(['uid' => $userId]);
        foreach ($iStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entryRows[] = [
                self::formatParisDateTime((string) $r['at_utc']),
                'Prise médicament (' . $r['status'] . ')',
                $r['name'] . ' (' . self::formatNumber($r['given_dose'] ?? 0, 2) . ' ' . $r['unit'] . ')',
                (string) ($r['comment'] ?? ''),
            ];
        }

        $mealStmt = $pdo->prepare(
            "SELECT eaten_at, meal_type, description, portion_eaten, comment
             FROM meals WHERE recorded_by = :uid AND deleted_at IS NULL ORDER BY eaten_at DESC"
        );
        $mealStmt->execute(['uid' => $userId]);
        foreach ($mealStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entryRows[] = [
                self::formatParisDateTime((string) $r['eaten_at']),
                'Repas (' . $r['meal_type'] . ')',
                $r['description'] . ' (' . $r['portion_eaten'] . ' %)',
                (string) ($r['comment'] ?? ''),
            ];
        }

        $noteStmt = $pdo->prepare(
            "SELECT created_at, category, title, content
             FROM notes WHERE recorded_by = :uid AND deleted_at IS NULL ORDER BY created_at DESC"
        );
        $noteStmt->execute(['uid' => $userId]);
        foreach ($noteStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $entryRows[] = [
                self::formatParisDateTime((string) $r['created_at']),
                'Note (' . $r['category'] . ')',
                (string) ($r['title'] ?? ''),
                (string) $r['content'],
            ];
        }

        $entriesCsv = self::buildCsvString(
            ['Date et heure (Paris)', 'Type de saisie', 'Détail', 'Commentaire'],
            $entryRows
        );

        // 3. Attendances of this user only
        $attStmt = $pdo->prepare(
            "SELECT recorded_at, declared_at, type, method, distance_m, status, reason
             FROM attendances WHERE user_id = :uid AND deleted_at IS NULL ORDER BY recorded_at DESC"
        );
        $attStmt->execute(['uid' => $userId]);
        $attRows = [];
        foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $attRows[] = [
                self::formatParisDateTime((string) $r['recorded_at']),
                !empty($r['declared_at']) ? self::formatParisDateTime((string) $r['declared_at']) : '',
                $r['type'] === 'in' ? 'Arrivée' : 'Départ',
                (string) $r['method'],
                $r['distance_m'] !== null ? (string) $r['distance_m'] : '',
                (string) $r['status'],
                (string) ($r['reason'] ?? ''),
            ];
        }
        $attendancesCsv = self::buildCsvString(
            ['Heure serveur (Paris)', 'Heure déclarée (Paris)', 'Type', 'Méthode', 'Distance (m)', 'Statut', 'Motif'],
            $attRows
        );

        AuditService::record('export', 'rgpd_user_data', $userId, null, [
            'user_id' => $userId,
            'entries_count' => count($entryRows),
            'attendances_count' => count($attRows),
        ], $patientId, $userId);

        $cacheDir = (string) Config::get('paths.cache', dirname(__DIR__, 2) . '/storage/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        if (class_exists(ZipArchive::class)) {
            $zipPath = $cacheDir . '/rgpd_user_' . $userId . '_' . bin2hex(random_bytes(6)) . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('01_mon_compte.csv', $accountCsv);
                $zip->addFromString('02_mes_saisies_medicales.csv', $entriesCsv);
                $zip->addFromString('03_mes_pointages.csv', $attendancesCsv);
                $zip->addFromString(
                    'LISEZMOI_RGPD.txt',
                    "Prime Care — Export personnel RGPD (Article 15 - Droit d'accès et de portabilité)\r\n"
                    . 'Utilisateur : ' . $user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ")\r\n"
                    . 'Généré le : ' . self::formatParisDateTime(gmdate('Y-m-d H:i:s')) . "\r\n"
                );
                $zip->close();

                return [
                    'file_path' => $zipPath,
                    'filename' => 'prime_care_mes_donnees_rgpd_' . $userId . '.zip',
                    'mime' => 'application/zip',
                ];
            }
        }

        // Fallback combined CSV if ZipArchive is unavailable
        $combinedPath = $cacheDir . '/rgpd_user_' . $userId . '_' . bin2hex(random_bytes(6)) . '.csv';
        $combined = $accountCsv . "\r\n" . $entriesCsv . "\r\n" . $attendancesCsv;
        file_put_contents($combinedPath, $combined);

        return [
            'file_path' => $combinedPath,
            'filename' => 'prime_care_mes_donnees_rgpd_' . $userId . '.csv',
            'mime' => 'text/csv; charset=UTF-8',
        ];
    }

    /**
     * Génère l'archive RGPD complète du patient (réservé à l'admin, §5.2 : « Exporter toutes les données du patient »).
     *
     * @return array{file_path: string, filename: string, mime: string}
     */
    public static function exportPatientFullDataArchive(int $actorId, int $patientId = 1): array
    {
        $fromUtc = '2000-01-01 00:00:00';
        $toUtc = gmdate('Y-m-d H:i:s', time() + 86400);
        $fromYmd = '2000-01-01';
        $toYmd = gmdate('Y-m-d');

        $measures = self::generateExportCsv('measures', $fromUtc, $toUtc, $fromYmd, $toYmd, $actorId, $patientId)['csv'];
        $intakes = self::generateExportCsv('intakes', $fromUtc, $toUtc, $fromYmd, $toYmd, $actorId, $patientId)['csv'];
        $meals = self::generateExportCsv('meals', $fromUtc, $toUtc, $fromYmd, $toYmd, $actorId, $patientId)['csv'];
        $notes = self::generateExportCsv('notes', $fromUtc, $toUtc, $fromYmd, $toYmd, $actorId, $patientId)['csv'];
        $cares = self::generateExportCsv('care_checks', $fromUtc, $toUtc, $fromYmd, $toYmd, $actorId, $patientId)['csv'];

        AuditService::record('export', 'rgpd_patient_full', $patientId, null, [
            'patient_id' => $patientId,
            'exported_by' => $actorId,
        ], $patientId, $actorId);

        $cacheDir = (string) Config::get('paths.cache', dirname(__DIR__, 2) . '/storage/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        if (class_exists(ZipArchive::class)) {
            $zipPath = $cacheDir . '/rgpd_patient_' . $patientId . '_' . bin2hex(random_bytes(6)) . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFromString('01_mesures_medicales.csv', $measures);
                $zip->addFromString('02_prises_medicaments.csv', $intakes);
                $zip->addFromString('03_repas_hydratation.csv', $meals);
                $zip->addFromString('04_notes_transmissions.csv', $notes);
                $zip->addFromString('05_soins_rapides.csv', $cares);
                $zip->close();

                return [
                    'file_path' => $zipPath,
                    'filename' => 'prime_care_dossier_complet_patient_' . $patientId . '.zip',
                    'mime' => 'application/zip',
                ];
            }
        }

        $combinedPath = $cacheDir . '/rgpd_patient_' . $patientId . '_' . bin2hex(random_bytes(6)) . '.csv';
        file_put_contents($combinedPath, $measures . "\r\n" . $intakes . "\r\n" . $meals . "\r\n" . $notes . "\r\n" . $cares);

        return [
            'file_path' => $combinedPath,
            'filename' => 'prime_care_dossier_complet_patient_' . $patientId . '.csv',
            'mime' => 'text/csv; charset=UTF-8',
        ];
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
     */
    public static function buildCsvString(array $headers, array $rows): string
    {
        $bom = "\xEF\xBB\xBF";
        $lines = [];
        $lines[] = implode(';', array_map([self::class, 'escapeCsv'], $headers));
        foreach ($rows as $row) {
            $lines[] = implode(';', array_map([self::class, 'escapeCsv'], $row));
        }
        return $bom . implode("\r\n", $lines) . "\r\n";
    }

    public static function formatParisDateTime(string $utcDateTime): string
    {
        if (trim($utcDateTime) === '') {
            return '';
        }
        return ScheduleService::utcToLocalParis($utcDateTime, 'd/m/Y H:i');
    }

    public static function formatNumber(float|int|string|null $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return number_format((float) $value, $decimals, ',', '');
    }

    private static function escapeCsv(string $val): string
    {
        $clean = str_replace(["\r\n", "\r", "\n"], ' ', $val);
        if (str_contains($clean, ';') || str_contains($clean, '"')) {
            return '"' . str_replace('"', '""', $clean) . '"';
        }
        return $clean;
    }

    private static function contextLabelFr(string $ctx): string
    {
        return match ($ctx) {
            'fasting' => 'À jeun',
            'pre_meal' => 'Avant repas',
            'post_meal' => 'Après repas (+2h)',
            'bedtime' => 'Coucher',
            default => 'Routine',
        };
    }

    private static function alertLevelFr(string $level): string
    {
        return match ($level) {
            'critical' => 'Critique',
            'warn' => 'Attention',
            default => 'Dans la cible',
        };
    }

    private static function summarizeAuditDiff(mixed $beforeJson, mixed $afterJson): string
    {
        $before = is_string($beforeJson) ? (json_decode($beforeJson, true) ?: []) : [];
        $after = is_string($afterJson) ? (json_decode($afterJson, true) ?: []) : [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $parts = [];
        foreach ($keys as $k) {
            $b = isset($before[$k]) ? (is_scalar($before[$k]) ? (string) $before[$k] : json_encode($before[$k])) : '—';
            $a = isset($after[$k]) ? (is_scalar($after[$k]) ? (string) $after[$k] : json_encode($after[$k])) : '—';
            if ($b !== $a) {
                $parts[] = "{$k}: {$b} -> {$a}";
            }
        }
        return implode(' | ', $parts);
    }
}
