<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Config;
use App\Core\Database;
use App\Models\Setting;
use App\Services\CronService;
use App\Services\DocumentService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Seeds Phase 4 data (Medical Documents E22 with real PDF/PNG files on disk,
 * Phase 2 prescription document sync, retention settings, cron heartbeat,
 * and rich audit log entries with field-by-field before/after diffs).
 */
final class Phase4Seeder
{
    public static function run(?string $referenceNowUtc = null): void
    {
        $pdo = Database::connection();
        $nowUtc = $referenceNowUtc
            ? new DateTimeImmutable($referenceNowUtc, new DateTimeZone('UTC'))
            : new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowStr = $nowUtc->format('Y-m-d H:i:s');
        $todayYmd = $nowUtc->format('Y-m-d');

        // 1. Ensure storage/uploads/documents and storage/uploads/prescriptions exist with real PDF/PNG files
        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        $docsDir = $uploadsRoot . '/documents';
        $prescDir = $uploadsRoot . '/prescriptions';
        if (!is_dir($docsDir)) {
            @mkdir($docsDir, 0775, true);
        }
        if (!is_dir($prescDir)) {
            @mkdir($prescDir, 0775, true);
        }

        // Ensure the Phase 2 prescription PDF file exists on disk so it can be viewed/downloaded
        $prescPdfPath = $prescDir . '/ordonnance-henri-benali-2026.pdf';
        if (!is_file($prescPdfPath)) {
            file_put_contents($prescPdfPath, self::buildMinimalPdf(
                'Ordonnance medicale - Dr Philippe Moreau - Patient : M. Henri Benali (Diabete type 2)'
            ));
        }

        // Sync Phase 2 prescriptions into `documents` (Category: ordonnance)
        DocumentService::syncPrescriptionDocuments(1);

        // 2. Seed additional medical documents across categories
        $pdo->exec('DELETE FROM `documents` WHERE `prescription_id` IS NULL');

        $seedDocs = [
            [
                'category' => 'compte_rendu',
                'title' => 'Compte rendu de consultation diabétologie — Hôpital Henri-Mondor',
                'doc_date' => $nowUtc->modify('-5 days')->format('Y-m-d'),
                'description' => 'Consultation trimestrielle avec le Dr Philippe Moreau. Adaptation de la dose de Lantus à 18 UI le soir.',
                'filename' => 'cr-diabetologie-henri-mondor.pdf',
                'mime' => 'application/pdf',
                'type' => 'pdf',
            ],
            [
                'category' => 'analyse_biologique',
                'title' => 'Bilan biologique HbA1c (7,1 %) & Fonction rénale — BioParisEst',
                'doc_date' => $nowUtc->modify('-8 days')->format('Y-m-d'),
                'description' => 'HbA1c : 7,1 % · Créatininémie : 88 µmol/L · DFG CKD-EPI : 68 mL/min/1,73m².',
                'filename' => 'bilan-biologique-hba1c-nov2026.pdf',
                'mime' => 'application/pdf',
                'type' => 'pdf',
            ],
            [
                'category' => 'courrier',
                'title' => 'Courrier d’adressage cardiologique — Dr Moreau vers Dr Garnier',
                'doc_date' => $nowUtc->modify('-12 days')->format('Y-m-d'),
                'description' => 'Suivi annuel HTA et contrôle ECG de repos.',
                'filename' => 'courrier-adressage-cardiologie.pdf',
                'mime' => 'application/pdf',
                'type' => 'pdf',
            ],
            [
                'category' => 'imagerie',
                'title' => 'Cliché Écho-Doppler artériel des membres inférieurs',
                'doc_date' => $nowUtc->modify('-18 days')->format('Y-m-d'),
                'description' => 'Axes fémoro-poplités perméables, absence de sténose hémodynamiquement significative.',
                'filename' => 'echo-doppler-membres-inferieurs.png',
                'mime' => 'image/png',
                'type' => 'png',
            ],
            [
                'category' => 'autre',
                'title' => 'Protocole d’urgence diabétologique et conduite à tenir (Hypo / Hyper)',
                'doc_date' => $nowUtc->modify('-24 days')->format('Y-m-d'),
                'description' => 'Fiche plastifiée validée par le Dr Moreau : resucrage 15 g et contrôle capillaire à +15 min.',
                'filename' => 'protocole-urgence-hypo-hyper.pdf',
                'mime' => 'application/pdf',
                'type' => 'pdf',
            ],
        ];

        $insDoc = $pdo->prepare(
            'INSERT INTO `documents`
             (`patient_id`, `prescription_id`, `category`, `title`, `doc_date`, `description`,
              `file_path`, `mime`, `size`, `sha256`, `uploaded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, NULL, :cat, :title, :doc_date, :desc, :path, :mime, :size, :sha, 1, :c, :u, 1)'
        );

        foreach ($seedDocs as $sd) {
            $fullPath = $docsDir . '/' . $sd['filename'];
            if ($sd['type'] === 'pdf') {
                $bytes = self::buildMinimalPdf($sd['title'] . ' - ' . $sd['description']);
                file_put_contents($fullPath, $bytes);
            } else {
                self::buildSamplePng($fullPath);
            }

            $size = (int) filesize($fullPath);
            $sha = (string) hash_file('sha256', $fullPath);

            $insDoc->execute([
                'cat' => $sd['category'],
                'title' => $sd['title'],
                'doc_date' => $sd['doc_date'],
                'desc' => $sd['description'],
                'path' => 'documents/' . $sd['filename'],
                'mime' => $sd['mime'],
                'size' => $size,
                'sha' => $sha,
                'c' => $nowStr,
                'u' => $nowStr,
            ]);
        }

        // 3. Seed default retention & unified settings
        Setting::setValue('data_retention', CronService::DEFAULT_RETENTION, 1);
        Setting::setValue('attendance_rules_extra', [
            'late_tolerance_min' => 15,
            'missing_alert_delay_min' => 15,
            'night_surcharge_pct' => 25,
            'sunday_surcharge_pct' => 50,
            'holiday_surcharge_pct' => 100,
        ], 1);
        Setting::setValue('alert_defaults', [
            'escalation_delay_min' => 30,
            'repeat_interval_min' => 15,
            'default_quiet_start' => '22:00',
            'default_quiet_end' => '07:00',
        ], 1);
        Setting::setValue('app_preferences', [
            'app_name' => 'Prime Care',
            'timezone' => 'Europe/Paris',
            'date_format' => 'DD/MM/YYYY HH:mm',
        ], 1);

        // Fresh cron heartbeat so the system is healthy after seeding
        CronService::recordHeartbeat($nowUtc->getTimestamp(), 1);

        // 4. Seed rich audit_logs entries with clear before/after field diffs (E26)
        $insAudit = $pdo->prepare(
            'INSERT INTO `audit_logs`
             (`patient_id`, `user_id`, `action`, `entity_type`, `entity_id`, `before_data`, `after_data`, `ip_address`, `user_agent`, `created_at`)
             VALUES (1, :uid, :act, :ent, :eid, :old, :new, :ip, :ua, :created)'
        );

        $sampleAudits = [
            [
                'uid' => 3, // Aïcha
                'act' => 'vital.update',
                'ent' => 'vital_measurements',
                'eid' => 12,
                'old' => json_encode(['value_numeric' => '1.42', 'context' => 'fasting', 'comment' => 'Lecture initiale lecteur'], JSON_UNESCAPED_UNICODE),
                'new' => json_encode(['value_numeric' => '1.28', 'context' => 'fasting', 'comment' => 'Seconde bandelette de contrôle après lavage des mains'], JSON_UNESCAPED_UNICODE),
                'created' => $nowUtc->modify('-2 hours')->format('Y-m-d H:i:s'),
            ],
            [
                'uid' => 1, // Karim
                'act' => 'document.upload',
                'ent' => 'documents',
                'eid' => 2,
                'old' => null,
                'new' => json_encode(['category' => 'compte_rendu', 'title' => 'Compte rendu de consultation diabétologie — Hôpital Henri-Mondor', 'doc_date' => $todayYmd], JSON_UNESCAPED_UNICODE),
                'created' => $nowUtc->modify('-4 hours')->format('Y-m-d H:i:s'),
            ],
            [
                'uid' => 2, // Sophie
                'act' => 'attendance.validate',
                'ent' => 'attendances',
                'eid' => 4,
                'old' => json_encode(['validation_status' => 'pending', 'distance_in_m' => 240], JSON_UNESCAPED_UNICODE),
                'new' => json_encode(['validation_status' => 'validated', 'distance_in_m' => 240, 'comment' => 'Stationnement rue adjacente confirmé'], JSON_UNESCAPED_UNICODE),
                'created' => $nowUtc->modify('-6 hours')->format('Y-m-d H:i:s'),
            ],
            [
                'uid' => 1, // Karim
                'act' => 'export.csv',
                'ent' => 'exports',
                'eid' => null,
                'old' => null,
                'new' => json_encode(['type' => 'measures', 'from' => $nowUtc->modify('-30 days')->format('Y-m-d'), 'to' => $todayYmd, 'rows' => 42], JSON_UNESCAPED_UNICODE),
                'created' => $nowUtc->modify('-1 day')->format('Y-m-d H:i:s'),
            ],
            [
                'uid' => 1, // Karim
                'act' => 'auth.login',
                'ent' => 'users',
                'eid' => 1,
                'old' => null,
                'new' => json_encode(['email' => 'karim@primecare.test', 'method' => 'password', 'remember' => true], JSON_UNESCAPED_UNICODE),
                'created' => $nowUtc->modify('-1 day -2 hours')->format('Y-m-d H:i:s'),
            ],
        ];

        foreach ($sampleAudits as $sa) {
            $insAudit->execute([
                'uid' => $sa['uid'],
                'act' => $sa['act'],
                'ent' => $sa['ent'],
                'eid' => $sa['eid'],
                'old' => $sa['old'],
                'new' => $sa['new'],
                'ip' => '192.168.1.24',
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148',
                'created' => $sa['created'],
            ]);
        }

        // Seed 30 days of historical audit entries (>= 100 entries over 30 days — §8)
        for ($day = 1; $day <= 30; $day++) {
            $dayDt = $nowUtc->modify("-{$day} days");
            $ymd = $dayDt->format('Y-m-d');
            $caregiverId = ($day % 2 === 0) ? 3 : 4; // Fatou or Marie

            // 1. Morning attendance clock-in
            $insAudit->execute([
                'uid' => $caregiverId,
                'act' => 'attendance.clock_in',
                'ent' => 'attendances',
                'eid' => $day * 2,
                'old' => null,
                'new' => json_encode(['type' => 'in', 'method' => 'slide', 'status' => 'valid', 'distance_m' => 18], JSON_UNESCAPED_UNICODE),
                'ip' => '192.168.1.24',
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148',
                'created' => $ymd . ' 07:01:00',
            ]);

            // 2. Morning fasting glycemia measurement
            $insAudit->execute([
                'uid' => $caregiverId,
                'act' => 'create',
                'ent' => 'measures',
                'eid' => $day * 5,
                'old' => null,
                'new' => json_encode(['type_code' => 'glycemia', 'value' => number_format(1.05 + (($day % 5) * 0.08), 2, '.', ''), 'context' => 'fasting'], JSON_UNESCAPED_UNICODE),
                'ip' => '192.168.1.24',
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148',
                'created' => $ymd . ' 07:15:00',
            ]);

            // 3. Medication intake validation
            $insAudit->execute([
                'uid' => $caregiverId,
                'act' => 'intake.validate',
                'ent' => 'medication_intakes',
                'eid' => $day * 3,
                'old' => json_encode(['status' => 'pending'], JSON_UNESCAPED_UNICODE),
                'new' => json_encode(['status' => 'given', 'given_dose' => '1000'], JSON_UNESCAPED_UNICODE),
                'ip' => '192.168.1.24',
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148',
                'created' => $ymd . ' 08:05:00',
            ]);

            // 4. Handover note or evening update
            $insAudit->execute([
                'uid' => $caregiverId,
                'act' => 'create',
                'ent' => 'notes',
                'eid' => $day,
                'old' => null,
                'new' => json_encode(['category' => 'observation', 'is_handover' => 1, 'title' => 'Relève du ' . $dayDt->format('d/m/Y')], JSON_UNESCAPED_UNICODE),
                'ip' => '192.168.1.24',
                'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148',
                'created' => $ymd . ' 18:45:00',
            ]);
        }
    }

    /**
     * Build a valid minimal PDF 1.4 binary string starting with %PDF-1.4.
     */
    public static function buildMinimalPdf(string $text): string
    {
        $safeText = preg_replace('/[^A-Za-z0-9 .,;:()\/\-]/', ' ', $text) ?: 'Document Prime Care';
        return "%PDF-1.4\n"
            . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
            . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
            . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
            . "4 0 obj << /Length " . (44 + strlen($safeText)) . " >> stream\n"
            . "BT /F1 12 Tf 40 780 Td (" . $safeText . ") Tj ET\n"
            . "endstream endobj\n"
            . "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
            . "xref\n0 6\n0000000000 65535 f \n"
            . "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n320\n%%EOF\n";
    }

    private static function buildSamplePng(string $targetPath): void
    {
        $im = imagecreatetruecolor(640, 420);
        if ($im === false) {
            return;
        }
        $bg = imagecolorallocate($im, 12, 22, 51);
        $accent = imagecolorallocate($im, 94, 92, 230);
        $white = imagecolorallocate($im, 240, 244, 255);
        imagefilledrectangle($im, 0, 0, 640, 420, (int) $bg);
        imagefilledrectangle($im, 30, 30, 610, 390, (int) $accent);
        imagestring($im, 5, 56, 190, 'Prime Care - Imagerie medicale de suivi (M. Henri Benali)', (int) $white);
        imagepng($im, $targetPath, 6);
        imagedestroy($im);
    }
}
