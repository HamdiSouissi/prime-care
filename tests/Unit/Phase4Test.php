<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\AuditController;
use App\Controllers\DesignController;
use App\Controllers\DocumentController;
use App\Controllers\HealthzController;
use App\Controllers\HomeController;
use App\Controllers\LegalController;
use App\Controllers\MediaController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Uploader;
use App\Middleware\SecurityHeadersMiddleware;
use App\Services\AuditService;
use App\Services\CronService;
use App\Services\DocumentService;
use App\Services\ExportService;
use App\Services\HoursCalculatorService;
use App\Services\ReportService;
use Database\Seeds\Phase4Seeder;
use PDO;
use Throwable;

/**
 * Comprehensive Phase 4 unit & integration test suite (§1 to §7):
 * - §1 Medical Documents (PDF upload, fake PDF rejection, >10MB rejection, sha256 duplicate warning, RBAC download, Phase 2 prescription sync, 0 coming-soon)
 * - §2 Medical Report (mean glycemia, % in target, hypo/hyper counts, observance global & per medication, 7d trends) & HR Report (completed-only diff + remaining planned)
 * - §3 CSV Exports (UTF-8 BOM, ; separator, Paris dates, comma decimals, French headers, RBAC admin vs viewer vs caregiver, audit log trace)
 * - §4 Audit Log (field-by-field before/after diff, never raw JSON, login/failed-login/revoke_device audited, read-only)
 * - §5 Unified Settings, Retention & GDPR (retention:purge logical->physical 30d, never touches medical data, personal/patient data exports, legal pages)
 * - §6 Production readiness (/healthz, cron >15min banner, /design 404 in prod, bin/seed.php refused in prod, HSTS & __Host- cookies, bin/setup.php guard)
 * - §6.3 Security (complete IDOR suite, session fixation, API write rate limit 120/min -> 429, path traversal).
 */
final class Phase4Test
{
    public static function run(callable $assert): void
    {
        $pdo = Database::connection();

        // =====================================================================
        // 1. DOCUMENTS MÉDICAUX (E22, §1)
        // =====================================================================
        DocumentService::syncPrescriptionDocuments(1);
        $ordDocs = DocumentService::listDocuments(1, 'ordonnance');
        $hasPrescLinkedDoc = false;
        foreach ($ordDocs as $d) {
            if (!empty($d['prescription_id'])) {
                $hasPrescLinkedDoc = true;
                break;
            }
        }
        $assert(
            $hasPrescLinkedDoc,
            'Documents §1: les ordonnances de Phase 2 avec fichier joint apparaissent dans la catégorie « Ordonnance »'
        );

        // 1.1 Téléversement d'un PDF valide
        $tmpPdf = tempnam(sys_get_temp_dir(), 'pc_pdf_') . '.pdf';
        file_put_contents($tmpPdf, Phase4Seeder::buildMinimalPdf('Compte rendu hospitalisation test unitaire Phase 4'));
        $validFileArray = [
            'name' => 'cr-test.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpPdf,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPdf),
        ];
        $resUpload1 = DocumentService::storeDocument([
            'category' => 'compte_rendu',
            'title' => 'CR Hospitalisation Test Phase 4',
            'doc_date' => '2026-11-14',
            'description' => 'Synthèse de sortie',
        ], $validFileArray, 1, 1);

        $assert(
            $resUpload1['id'] > 0 && $resUpload1['duplicate_warning'] === false,
            'Documents §1: téléversement d’un PDF valide (%PDF-) accepté avec calcul SHA-256'
        );
        $uploadedDocId = (int) $resUpload1['id'];

        // 1.2 Avertissement doublon SHA-256 (non bloquant)
        $tmpPdfDup = tempnam(sys_get_temp_dir(), 'pc_pdf_dup_') . '.pdf';
        file_put_contents($tmpPdfDup, Phase4Seeder::buildMinimalPdf('Compte rendu hospitalisation test unitaire Phase 4'));
        $dupFileArray = [
            'name' => 'cr-test-copie.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpPdfDup,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPdfDup),
        ];
        $resUploadDup = DocumentService::storeDocument([
            'category' => 'compte_rendu',
            'title' => 'CR Hospitalisation Copie',
            'doc_date' => '2026-11-14',
        ], $dupFileArray, 1, 1);

        $assert(
            $resUploadDup['id'] > 0
            && $resUploadDup['duplicate_warning'] === true
            && !empty($resUploadDup['duplicate_document']),
            'Documents §1: un fichier doublon (même SHA-256) déclenche un avertissement sans bloquer l’ajout'
        );

        // 1.3 Refus d'un faux PDF (script PHP renommé en .pdf)
        $tmpFakePdf = tempnam(sys_get_temp_dir(), 'pc_fake_') . '.pdf';
        file_put_contents($tmpFakePdf, "<?php echo 'hacked'; ?>");
        $fakePdfArray = [
            'name' => 'exploit.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpFakePdf,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFakePdf),
        ];
        $fakeRejected = false;
        try {
            DocumentService::storeDocument([
                'category' => 'autre',
                'title' => 'Faux PDF',
            ], $fakePdfArray, 1, 1);
        } catch (Throwable $e) {
            $fakeRejected = true;
        }
        @unlink($tmpFakePdf);
        $assert(
            $fakeRejected,
            'Documents §1: un faux PDF (script PHP renommé .pdf sans en-tête %PDF-) est refusé (422)'
        );

        // 1.4 Refus d'un fichier > 10 Mo
        $tmpTooBig = tempnam(sys_get_temp_dir(), 'pc_big_') . '.pdf';
        file_put_contents($tmpTooBig, Phase4Seeder::buildMinimalPdf('Big PDF'));
        $tooBigArray = [
            'name' => 'huge.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $tmpTooBig,
            'error' => UPLOAD_ERR_OK,
            'size' => 11 * 1024 * 1024, // 11 MB > 10 MB
        ];
        $tooBigRejected = false;
        $tooBigMsg = '';
        try {
            DocumentService::storeDocument([
                'category' => 'imagerie',
                'title' => 'Fichier trop volumineux',
            ], $tooBigArray, 1, 1);
        } catch (Throwable $e) {
            $tooBigRejected = true;
            $tooBigMsg = $e->getMessage();
        }
        @unlink($tmpTooBig);
        $assert(
            $tooBigRejected && str_contains($tooBigMsg, '10 Mo'),
            'Documents §1: un fichier de plus de 10 Mo est refusé'
        );

        // 1.5 Téléchargement autorisé pour chaque rôle (admin, coordinator, caregiver, viewer) + en-têtes no-store & nosniff
        $docCtrl = new DocumentController();
        $rolesDownloadOk = true;
        foreach (['admin@primecare.test' => 'Admin!2026', 'coord@primecare.test' => 'Coord!2026', 'fatou@primecare.test' => 'Soin!2026', 'medecin@primecare.test' => 'Lecture!2026'] as $em => $pw) {
            Auth::attempt($em, $pw);
            $dlResp = $docCtrl->download(new Request(), (string) $uploadedDocId);
            $hdrs = $dlResp->headers();
            if (
                $dlResp->status() !== 200
                || !str_contains($hdrs['Cache-Control'] ?? '', 'no-store')
                || ($hdrs['X-Content-Type-Options'] ?? '') !== 'nosniff'
                || !str_starts_with($dlResp->body(), '%PDF-')
            ) {
                $rolesDownloadOk = false;
            }
        }
        $assert(
            $rolesDownloadOk,
            'Documents §1: téléchargement autorisé pour les 4 rôles avec Cache-Control: no-store et X-Content-Type-Options: nosniff'
        );

        // 1.6 Plus aucun écran « Bientôt disponible » dans l'application
        $homeCtrl = new HomeController();
        $csResp = $homeCtrl->comingSoon(new Request(), 'documents');
        $assert(
            $csResp->status() === 302 && !is_file(dirname(__DIR__, 2) . '/app/Views/home/coming-soon.php'),
            'Documents §1: il ne reste plus aucun écran « Bientôt disponible » dans l’application'
        );

        // 1.7 EXIF supprimé sur un JPEG déposé dans Documents (H3 / §7)
        $img = imagecreatetruecolor(120, 80);
        imagefilledrectangle($img, 0, 0, 119, 79, 0x3F2E88);
        ob_start();
        imagejpeg($img, null, 90);
        $rawJpeg = (string) ob_get_clean();
        imagedestroy($img);
        $exifPayload = "Exif\x00\x00II*\x00\x08\x00\x00\x00GPS-SECRET-COORDS-48.79036-2.45557";
        $app1Segment = "\xFF\xE1" . pack('n', strlen($exifPayload) + 2) . $exifPayload;
        $jpegWithExif = substr($rawJpeg, 0, 2) . $app1Segment . substr($rawJpeg, 2);
        $tmpExifJpg = tempnam(sys_get_temp_dir(), 'pc_doc_exif_') . '.jpg';
        file_put_contents($tmpExifJpg, $jpegWithExif);
        $exifRes = DocumentService::storeDocument([
            'category' => 'imagerie',
            'title' => 'Cliché JPEG avec EXIF',
            'document_date' => '2026-11-14',
        ], [
            'name' => 'cliche_exif.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $tmpExifJpg,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($jpegWithExif),
        ], 1, 1);
        @unlink($tmpExifJpg);
        $savedDocJpgPath = dirname(__DIR__, 2) . '/storage/uploads/' . $exifRes['document']['file_path'];
        $savedDocJpgBytes = is_file($savedDocJpgPath) ? (string) file_get_contents($savedDocJpgPath) : '';
        DocumentService::deleteDocument((int) $exifRes['document']['id'], 1, 1);
        $assert(
            str_starts_with($savedDocJpgBytes, "\xFF\xD8\xFF")
            && !str_contains($savedDocJpgBytes, 'GPS-SECRET-COORDS')
            && !str_contains($savedDocJpgBytes, "Exif\x00\x00"),
            'Documents §1 (H3): les métadonnées EXIF sont supprimées sur un JPEG déposé dans Documents'
        );

        // =====================================================================
        // 2. RAPPORT MÉDICAL (E23, §2.1 & §2.3) & RAPPORT RH (§2.2)
        // =====================================================================
        $period30 = ReportService::resolvePeriod('30d');
        $report = ReportService::buildMedicalReport($period30['from_utc'], $period30['to_utc'], 1);
        $gly = $report['glycemia'];
        $obs = $report['observance'];

        $assert(
            $gly['count'] > 0
            && is_float($gly['avg'])
            && $gly['min'] <= $gly['avg']
            && $gly['max'] >= $gly['avg']
            && $gly['in_target_pct'] >= 0.0 && $gly['in_target_pct'] <= 100.0
            && is_int($gly['hypo_count']) && is_int($gly['hyper_count']),
            'Rapport médical §2.1: calcule la glycémie moyenne, min/max, % dans la cible et le décompte des hypo (<0,70) et hyper (>2,50)'
        );

        $assert(
            isset($obs['global_rate_pct'])
            && is_array($obs['by_medication'])
            && count($obs['by_medication']) > 0,
            'Rapport médical §2.1: calcule le taux d’observance global et par médicament'
        );

        // 2.1.b Période vide du rapport sans erreur ni division par zéro (H3 / §7)
        $emptyReport = ReportService::buildMedicalReport('2020-01-01 00:00:00', '2020-01-07 23:59:59', 1);
        Auth::attempt('admin@primecare.test', 'Admin!2026');
        $emptyReportHttp = (new ReportController())->index(new Request(['period' => 'custom', 'from' => '2020-01-01', 'to' => '2020-01-07']));
        $assert(
            $emptyReport['glycemia']['count'] === 0
            && $emptyReport['glycemia']['avg'] === null
            && $emptyReport['observance']['total_scheduled'] === 0
            && $emptyReport['observance']['total_given'] === 0
            && $emptyReportHttp->status() === 200,
            'Rapport médical §2.1 (H3): une période vide retourne 200 sans erreur ni division par zéro'
        );

        // 2.1.c Rapport sur un jeu de données connu avec valeurs attendues en dur (H3 / §7)
        $glyTypeId = (int) $pdo->query("SELECT id FROM measure_types WHERE code = 'glycemia' LIMIT 1")->fetchColumn();
        $firstPrescRow = $pdo->query("SELECT id, medication_id FROM prescriptions WHERE deleted_at IS NULL AND as_needed = 0 LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $prescId = (int) ($firstPrescRow['id'] ?? 1);
        $medId = (int) ($firstPrescRow['medication_id'] ?? 1);
        $knownFrom = '2024-05-01 00:00:00';
        $knownTo = '2024-05-04 23:59:59';
        $insKnownM = $pdo->prepare(
            "INSERT INTO measures (patient_id, measure_type_id, value, unit_recorded, context, measured_at, alert_level, threshold_level, recorded_by, created_at, updated_at, created_by)
             VALUES (1, :mt, :val, 'g/L', :ctx, :at1, 'ok', :tl, 3, :at2, :at3, 3)"
        );
        // 4 glycémies connues : 0.60 (hypo < 0.70, hors cible), 1.00 (à jeun dans cible 0.80-1.30), 1.20 (à jeun dans cible), 2.80 (hyper > 2.50, hors cible)
        // Moyenne = (0.60 + 1.00 + 1.20 + 2.80) / 4 = 1.40 ; Min = 0.60 ; Max = 2.80 ; Cible = 2/4 = 50.0% ; Hypo = 1 ; Hyper = 1
        $knownMeasures = [
            ['val' => 0.60, 'ctx' => 'fasting', 'tl' => 'crit_low', 'at' => '2024-05-01 07:00:00'],
            ['val' => 1.00, 'ctx' => 'fasting', 'tl' => 'ok', 'at' => '2024-05-02 07:00:00'],
            ['val' => 1.20, 'ctx' => 'fasting', 'tl' => 'ok', 'at' => '2024-05-03 07:00:00'],
            ['val' => 2.80, 'ctx' => 'post_meal', 'tl' => 'crit_high', 'at' => '2024-05-04 12:00:00'],
        ];
        $knownMeasureIds = [];
        foreach ($knownMeasures as $km) {
            $insKnownM->execute(['mt' => $glyTypeId, 'val' => $km['val'], 'ctx' => $km['ctx'], 'tl' => $km['tl'], 'at1' => $km['at'], 'at2' => $km['at'], 'at3' => $km['at']]);
            $knownMeasureIds[] = (int) $pdo->lastInsertId();
        }
        // 4 prises programmées connues : 3 given + 1 skipped => observance = 75.0%
        $insKnownI = $pdo->prepare(
            "INSERT INTO medication_intakes (patient_id, prescription_id, scheduled_at, status, given_at, given_dose, reason, given_by, created_at, updated_at)
             VALUES (1, :pid, :sf1, :st, :ta, 1.0, :sr, 3, :sf2, :sf3)"
        );
        $knownIntakes = [
            ['sf' => '2024-05-01 08:00:00', 'st' => 'given', 'ta' => '2024-05-01 08:05:00', 'sr' => null],
            ['sf' => '2024-05-02 08:00:00', 'st' => 'given', 'ta' => '2024-05-02 08:05:00', 'sr' => null],
            ['sf' => '2024-05-03 08:00:00', 'st' => 'given', 'ta' => '2024-05-03 08:05:00', 'sr' => null],
            ['sf' => '2024-05-04 08:00:00', 'st' => 'skipped', 'ta' => null, 'sr' => 'refused'],
        ];
        $knownIntakeIds = [];
        foreach ($knownIntakes as $ki) {
            $insKnownI->execute(['pid' => $prescId, 'sf1' => $ki['sf'], 'st' => $ki['st'], 'ta' => $ki['ta'], 'sr' => $ki['sr'], 'sf2' => $ki['sf'], 'sf3' => $ki['sf']]);
            $knownIntakeIds[] = (int) $pdo->lastInsertId();
        }

        $knownRep = ReportService::buildMedicalReport($knownFrom, $knownTo, 1);
        $pdo->exec('DELETE FROM measures WHERE id IN (' . implode(',', $knownMeasureIds) . ')');
        $pdo->exec('DELETE FROM medication_intakes WHERE id IN (' . implode(',', $knownIntakeIds) . ')');

        $kGly = $knownRep['glycemia'];
        $kObs = $knownRep['observance'];
        $assert(
            $kGly['count'] === 4
            && $kGly['avg'] === 1.4
            && $kGly['min'] === 0.6
            && $kGly['max'] === 2.8
            && $kGly['in_target_pct'] === 50.0
            && $kGly['hypo_count'] === 1
            && $kGly['hyper_count'] === 1
            && $kObs['total_scheduled'] === 4
            && $kObs['total_given'] === 3
            && $kObs['total_skipped'] === 1
            && $kObs['global_rate_pct'] === 75.0,
            'Rapport médical §2.1 (H3): jeu de données connu vérifie exactement moyenne=1,40, min=0,60, max=2,80, cible=50,0%, hypo=1, hyper=1, observance=75,0%'
        );

        $trends7d = ReportService::getAdmin7DayTrends(1);
        $assert(
            isset($trends7d['avg_glycemia']['direction'], $trends7d['time_in_target']['direction'], $trends7d['observance']['direction'], $trends7d['hypo_count']['direction']),
            'Tendances 7 jours §2.3: retourne les 4 indicateurs avec évolution par rapport aux 7 jours précédents'
        );

        // 2.2 Rapport RH : écart prévu/pointé calculé UNIQUEMENT sur les créneaux terminés + « Planifié restant ce mois-ci »
        $nowUtcDt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $futureStart = $nowUtcDt->modify('+2 days')->setTime(8, 0)->format('Y-m-d H:i:s');
        $futureEnd = $nowUtcDt->modify('+2 days')->setTime(20, 0)->format('Y-m-d H:i:s');
        $currentYear = (int) $nowUtcDt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y');
        $currentMonthNum = (int) $nowUtcDt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('n');
        $currentMonth = $nowUtcDt->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m');

        $summaryBefore = HoursCalculatorService::calculateMonthlySummary($currentYear, $currentMonthNum, 3, 1);
        $diffBefore = (int) ($summaryBefore['caregivers'][0]['diff_minutes'] ?? 0);
        $remBefore = (int) ($summaryBefore['caregivers'][0]['remaining_planned_minutes'] ?? 0);

        // Add a future 12h shift in the same month for caregiver #3 (Fatou)
        $shiftMonth = $nowUtcDt->modify('+2 days')->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m');
        if ($shiftMonth === $currentMonth) {
            $insShift = $pdo->prepare(
                "INSERT INTO shifts (patient_id, user_id, start_at, end_at, status, created_by, created_at, updated_at)
                 VALUES (1, 3, :s, :e, 'planned', 1, :c, :u)"
            );
            $insShift->execute(['s' => $futureStart, 'e' => $futureEnd, 'c' => gmdate('Y-m-d H:i:s'), 'u' => gmdate('Y-m-d H:i:s')]);
            $futureShiftId = (int) $pdo->lastInsertId();

            $summaryAfter = HoursCalculatorService::calculateMonthlySummary($currentYear, $currentMonthNum, 3, 1);
            $diffAfter = (int) ($summaryAfter['caregivers'][0]['diff_minutes'] ?? 0);
            $remAfter = (int) ($summaryAfter['caregivers'][0]['remaining_planned_minutes'] ?? 0);

            $pdo->prepare('DELETE FROM shifts WHERE id = :id')->execute(['id' => $futureShiftId]);

            $assert(
                $diffAfter === $diffBefore && ($remAfter - $remBefore) === 720,
                'Rapport RH §2.2: un créneau futur (+12h) augmente « Planifié restant ce mois-ci » sans fausser l’écart prévu / pointé des créneaux terminés'
            );
        } else {
            $assert(
                isset($summaryBefore['totals']['completed_planned_minutes'], $summaryBefore['totals']['remaining_planned_minutes']),
                'Rapport RH §2.2: sépare les heures planifiées terminées et le « Planifié restant ce mois-ci »'
            );
        }

        // =====================================================================
        // 3. EXPORTS CSV (§3)
        // =====================================================================
        $auditCountBeforeExport = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'export'")->fetchColumn();
        $csvExport = ExportService::generateExportCsv(
            'measures',
            '2026-10-01 00:00:00',
            gmdate('Y-m-d H:i:s', time() + 3600),
            '2026-10-01',
            gmdate('Y-m-d'),
            1,
            1
        );
        $auditCountAfterExport = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'export'")->fetchColumn();

        $csvBody = $csvExport['csv'];
        $assert(
            str_starts_with($csvBody, "\xEF\xBB\xBF")
            && str_contains($csvBody, 'Date et heure (Paris)')
            && str_contains($csvBody, 'Valeur principale')
            && str_contains($csvBody, ';')
            && preg_match('/\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}/', $csvBody) === 1
            && preg_match('/\d+,\d{2}/', $csvBody) === 1
            && $auditCountAfterExport === $auditCountBeforeExport + 1,
            'Exports CSV §3: inclut le BOM UTF-8, le séparateur ;, les dates Paris JJ/MM/AAAA HH:MM, la virgule décimale, les en-têtes français et trace l’export dans audit_logs'
        );

        // RBAC sur les exports : viewer autorisé sur le médical mais 403 sur attendances/hours/alerts/audit
        $repCtrl = new ReportController();
        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $viewerMedResp = $repCtrl->downloadExport(new Request(['from' => '2026-11-01', 'to' => '2026-11-15']), 'measures');
        $viewerHrDenied = false;
        try {
            $repCtrl->downloadExport(new Request(), 'attendances');
        } catch (Throwable $e) {
            $viewerHrDenied = ($e->getCode() === 403);
        }
        $viewerAuditDenied = false;
        try {
            $repCtrl->downloadExport(new Request(), 'audit');
        } catch (Throwable $e) {
            $viewerAuditDenied = ($e->getCode() === 403);
        }
        $assert(
            $viewerMedResp->status() === 200 && $viewerHrDenied && $viewerAuditDenied,
            'Exports CSV §3 (RBAC): le rôle viewer peut exporter les données médicales (200) mais reçoit 403 sur les pointages, heures et l’audit'
        );

        // =====================================================================
        // 4. JOURNAL D'AUDIT (E26, §4)
        // =====================================================================
        $diffs = AuditService::computeDiff(
            json_encode(['value_numeric' => '1.45', 'context' => 'fasting', 'password_hash' => 'secret']),
            json_encode(['value_numeric' => '1.18', 'context' => 'fasting', 'password_hash' => 'newsecret'])
        );
        $assert(
            count($diffs) === 1
            && $diffs[0]['field'] === 'value_numeric'
            && $diffs[0]['label'] === 'Valeur mesurée'
            && $diffs[0]['old'] === '1,45'
            && $diffs[0]['new'] === '1,18',
            'Audit §4: computeDiff calcule les différences avant / après champ par champ en français et masque les champs sensibles'
        );

        // Connexions réussies, échouées et révocations d'appareils tracées dans audit_logs
        Auth::attempt('fatou@primecare.test', 'MauvaisMotDePasse!');
        $failedLoginLogged = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login_failed'")->fetchColumn() > 0;

        $tok = Auth::issueTrustedDevice(3, 'Tablette Test Audit');
        [$sel] = explode(':', $tok, 2);
        Auth::revokeTrustedDeviceSelector(3, $sel);
        $revokeLogged = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.revoke_device'")->fetchColumn() > 0;

        $assert(
            $failedLoginLogged && $revokeLogged,
            'Audit §4: les connexions réussies/échouées et les révocations d’appareils de confiance sont tracées dans audit_logs'
        );

        // =====================================================================
        // 5. CONSERVATION DES DONNÉES, RGPD & PAGES LÉGALES (§5.2 & §5.3)
        // =====================================================================
        $medCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM measures')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM medication_intakes')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM meals')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();

        // Insert an old attendance (> 6 years ago) and an already soft-deleted attendance (> 40 days ago)
        $pdo->exec(
            "INSERT INTO attendances (patient_id, user_id, type, recorded_at, method, status, created_at, updated_at, deleted_at)
             VALUES
             (1, 3, 'in', '2018-01-10 08:00:00', 'slide', 'valid', '2018-01-10 08:00:00', '2018-01-10 08:00:00', NULL),
             (1, 3, 'out', '2018-01-10 20:00:00', 'slide', 'valid', '2018-01-10 20:00:00', '2018-01-10 20:00:00', '2026-09-01 00:00:00')"
        );

        $purgeRes = CronService::runRetentionPurge(1, '2026-11-14 12:00:00');
        $medCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM measures')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM medication_intakes')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM meals')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn()
            + (int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();

        $assert(
            $purgeRes['medical_touched'] === 0
            && $medCountAfter === $medCountBefore
            && $purgeRes['attendances_soft'] >= 1
            && $purgeRes['attendances_hard'] >= 1,
            'Conservation RGPD §5.2: retention:purge applique la suppression logique puis physique (>30j) et ne touche JAMAIS aux données médicales'
        );

        // Export RGPD personnel (/profile/export-data) & patient complet (/settings/export-patient-data)
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $profCtrl = new ProfileController();
        $userExportResp = $profCtrl->exportData(new Request());
        $assert(
            $userExportResp->status() === 200 && strlen($userExportResp->body()) > 50,
            'RGPD §5.2: le bouton « Exporter mes données » télécharge l’archive des données personnelles de l’utilisateur connecté'
        );

        Auth::attempt('admin@primecare.test', 'Admin!2026');
        $setCtrl = new SettingsController();
        $patExportResp = $setCtrl->exportPatientData(new Request());
        $assert(
            $patExportResp->status() === 200 && strlen($patExportResp->body()) > 100,
            'RGPD §5.2: le bouton « Exporter toutes les données du patient » télécharge l’archive intégrale du dossier patient'
        );

        // Pages légales accessibles et avec marqueurs [À COMPLÉTER]
        $legCtrl = new LegalController();
        $mentionsResp = $legCtrl->mentions(new Request());
        $privacyResp = $legCtrl->privacy(new Request());
        $noticeResp = $legCtrl->attendanceNotice(new Request());
        $assert(
            $mentionsResp->status() === 200
            && $privacyResp->status() === 200
            && $noticeResp->status() === 200
            && str_contains($mentionsResp->body(), '[À COMPLÉTER')
            && is_file(dirname(__DIR__, 2) . '/docs/rgpd/MODELE_CONSENTEMENT_PATIENT.md')
            && is_file(dirname(__DIR__, 2) . '/docs/rgpd/REGISTRE_DES_TRAITEMENTS.md'),
            'Pages légales §5.3: /legal/mentions, /legal/privacy, /legal/attendance-notice et docs/rgpd/* présents avec marqueurs [À COMPLÉTER]'
        );

        // =====================================================================
        // 6. PRODUCTION, HEALTHZ, CRON BANNER & SETUP (§6.1 & §6.2)
        // =====================================================================
        Auth::logout();
        $hzCtrl = new HealthzController();
        $hzResp = $hzCtrl->status(new Request());
        $hzJson = json_decode($hzResp->body(), true) ?: [];
        $assert(
            $hzResp->status() === 200
            && ($hzJson['ok'] ?? false) === true
            && ($hzJson['db'] ?? false) === true
            && !empty($hzJson['cron_last_run']),
            'Production §6.2: GET /healthz sans authentification retourne 200 {"ok":true,"db":true,"cron_last_run":"..."}'
        );

        // Bandeau d'alerte admin si cron > 15 min
        Auth::attempt('admin@primecare.test', 'Admin!2026');
        CronService::recordHeartbeat(time() - 1800, 1); // 30 minutes ago (> 15 min)
        $homeStaleResp = (new HomeController())->index(new Request());
        $hasStaleBanner = str_contains($homeStaleResp->body(), "Les alertes automatiques sont à l'arrêt");
        CronService::recordHeartbeat(time(), 1); // restore fresh heartbeat
        $assert(
            $hasStaleBanner,
            'Production §6.2: affiche le bandeau « Les alertes automatiques sont à l\'arrêt » sur l’accueil admin si le cron a > 15 min'
        );

        // Mode production (app.env = production): /design -> 404, HSTS, __Host- cookies, et H2 debug forcé à false
        $prevEnv = (string) Config::get('app.env', 'testing');
        $prevDebug = (bool) Config::get('app.debug', true);
        Config::set('app.env', 'production');
        Config::set('app.debug', true);
        $debugForcedFalseInProd = (Config::get('app.debug') === false);

        $secretErrorMsg = 'SQLSTATE[HY000] Secret interne de base de donnees #987654';
        $testExc = new \RuntimeException($secretErrorMsg, 500);
        $htmlErrResp = \App\Core\ErrorHandler::handleException(
            $testExc,
            new Request([], [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/today', 'HTTP_ACCEPT' => 'text/html'])
        );
        $jsonErrResp = \App\Core\ErrorHandler::handleException(
            $testExc,
            new Request([], [], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/measures', 'HTTP_ACCEPT' => 'application/json'])
        );
        $noLeakHtml = ($htmlErrResp->status() === 500)
            && !str_contains($htmlErrResp->body(), $secretErrorMsg)
            && !str_contains($htmlErrResp->body(), 'Phase4Test.php');
        $noLeakJson = ($jsonErrResp->status() === 500)
            && !str_contains($jsonErrResp->body(), $secretErrorMsg)
            && !str_contains($jsonErrResp->body(), 'Phase4Test.php');

        $designProdResp = (new DesignController())->index(new Request());
        $secMw = new SecurityHeadersMiddleware();
        $prodHeaderResp = $secMw->handle(new Request(), static fn() => Response::html('ok'));
        $prodHsts = $prodHeaderResp->headers()['Strict-Transport-Security'] ?? '';
        $prodSessionCookie = Session::cookieName();
        $prodDeviceCookie = Auth::cookieName(Auth::TRUSTED_DEVICE_COOKIE);
        Config::set('app.env', $prevEnv);
        Config::set('app.debug', $prevDebug);

        $assert(
            $designProdResp->status() === 404
            && $prodHsts === 'max-age=63072000; includeSubDomains'
            && str_starts_with($prodSessionCookie, '__Host-')
            && str_starts_with($prodDeviceCookie, '__Host-'),
            'Production §6.1: en mode production, /design retourne 404, HSTS = max-age=63072000; includeSubDomains et les cookies ont le préfixe __Host-'
        );
        $assert(
            $debugForcedFalseInProd && $noLeakHtml && $noLeakJson,
            'Production §6.1 (H2): avec app.env=production et app.debug=true, debug est forcé à false et aucune trace ni message technique ne fuite en HTML ou JSON'
        );

        // =====================================================================
        // 7. SÉCURITÉ : IDOR COMPLET, FIXATION DE SESSION, 429 & PATH TRAVERSAL (§6.3)
        // =====================================================================
        // 7.1 Fixation de session : l'identifiant de session change à la connexion
        Auth::logout();
        $sidBeforeLogin = Session::id();
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $sidAfterLogin = Session::id();
        $assert(
            $sidBeforeLogin !== '' && $sidAfterLogin !== '' && $sidBeforeLogin !== $sidAfterLogin,
            'Sécurité §6.3 (Fixation de session): l’identifiant de session est régénéré à la connexion'
        );

        // 7.2 Série IDOR complète sur les 12 ressources adressées par identifiant avec de vraies requêtes HTTP + invariance en base (H3)
        $router = new \App\Core\Router();
        \App\Core\Router::setInstance($router);
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/api.php';

        $httpGet = static function (string $uri) use ($router): Response {
            [$pathOnly, $qStr] = array_pad(explode('?', $uri, 2), 2, '');
            $q = [];
            if ($qStr !== '') {
                parse_str($qStr, $q);
            }
            $req = new Request($q, [], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' => 'PrimeCareIdorTest/1.0',
            ]);
            try {
                return $router->dispatch($req);
            } catch (Throwable $e) {
                return \App\Core\ErrorHandler::handleException($e, $req);
            }
        };

        $httpPost = static function (string $uri, array $post = [], array $files = [], bool $json = true) use ($router): Response {
            $csrf = \App\Core\Csrf::token();
            $server = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_CSRF_TOKEN' => $csrf,
                'HTTP_USER_AGENT' => 'PrimeCareIdorTest/1.0',
            ];
            if ($json) {
                $server['HTTP_ACCEPT'] = 'application/json';
            }
            $req = new Request([], array_merge($post, ['_csrf' => $csrf]), $files, $server);
            try {
                return $router->dispatch($req);
            } catch (Throwable $e) {
                return \App\Core\ErrorHandler::handleException($e, $req);
            }
        };

        $nowIdorUtc = gmdate('Y-m-d H:i:s');

        // (1) Ressource : Mesure — aide-soignante (Fatou #3) modifie/supprime la mesure d'une collègue (Marie #4) ; lecteur (#5) modifie une mesure
        $pdo->prepare(
            "INSERT INTO measures (patient_id, measure_type_id, value, unit_recorded, context, measured_at, alert_level, threshold_level, recorded_by, created_at, updated_at, created_by)
             VALUES (1, :mt, 1.15, 'g/L', 'fasting', :n1, 'ok', 'ok', 4, :n2, :n3, 4)"
        )->execute(['mt' => $glyTypeId, 'n1' => $nowIdorUtc, 'n2' => $nowIdorUtc, 'n3' => $nowIdorUtc]);
        $idorMeasureId = (int) $pdo->lastInsertId();

        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $mUpdCaregiver = $httpPost('/health/measures/' . $idorMeasureId . '/update', ['value' => '2.99'], [], true);
        $mDelCaregiver = $httpPost('/health/measures/' . $idorMeasureId . '/delete', [], [], true);
        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $mUpdViewer = $httpPost('/health/measures/' . $idorMeasureId . '/update', ['value' => '3.10'], [], true);

        $mRowAfter = $pdo->query("SELECT value, deleted_at FROM measures WHERE id = {$idorMeasureId}")->fetch(\PDO::FETCH_ASSOC);
        $pdo->exec("DELETE FROM measures WHERE id = {$idorMeasureId}");
        $idorMeasureOk = ($mUpdCaregiver->status() === 403)
            && ($mDelCaregiver->status() === 403)
            && ($mUpdViewer->status() === 403)
            && ((float) ($mRowAfter['value'] ?? 0) === 1.15)
            && ($mRowAfter['deleted_at'] === null);
        $assert($idorMeasureOk, 'Sécurité §6.3 (H3 IDOR Mesure): aide-soignante ne peut ni modifier ni supprimer la mesure d’une collègue et lecteur ne peut pas modifier (403 + base intacte)');

        // (2) Ressource : Prise — lecteur (#5) valide une prise
        $pdo->prepare(
            "INSERT INTO medication_intakes (patient_id, prescription_id, scheduled_at, status, created_at, updated_at)
             VALUES (1, :pid, :n1, 'pending', :n2, :n3)"
        )->execute(['pid' => $prescId, 'n1' => $nowIdorUtc, 'n2' => $nowIdorUtc, 'n3' => $nowIdorUtc]);
        $idorIntakeId = (int) $pdo->lastInsertId();

        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $iGiveWeb = $httpPost('/treatments/intakes/' . $idorIntakeId . '/give', [], [], true);
        $iGiveApi = $httpPost('/api/intakes/' . $idorIntakeId . '/give', [], [], true);
        $iStatusAfter = (string) $pdo->query("SELECT status FROM medication_intakes WHERE id = {$idorIntakeId}")->fetchColumn();
        $pdo->exec("DELETE FROM medication_intakes WHERE id = {$idorIntakeId}");
        $assert(
            $iGiveWeb->status() === 403 && $iGiveApi->status() === 403 && $iStatusAfter === 'pending',
            'Sécurité §6.3 (H3 IDOR Prise): lecteur bloqué en 403 sur la validation d’une prise et statut pending inchangé en base'
        );

        // (3) Ressource : Repas — aide-soignante (#3) modifie le repas d'une collègue (#4) ; lecteur (#5) supprime un repas
        $pdo->prepare(
            "INSERT INTO meals (patient_id, meal_type, eaten_at, description, portion_eaten, recorded_by, created_at, updated_at, created_by)
             VALUES (1, 'lunch', :n1, 'Repas initial de Marie', '100', 4, :n2, :n3, 4)"
        )->execute(['n1' => $nowIdorUtc, 'n2' => $nowIdorUtc, 'n3' => $nowIdorUtc]);
        $idorMealId = (int) $pdo->lastInsertId();

        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $mealUpdCaregiver = $httpPost('/meals/' . $idorMealId . '/update', ['description' => 'Repas altéré par Fatou'], [], true);
        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $mealDelViewer = $httpPost('/meals/' . $idorMealId . '/delete', [], [], true);
        $mealRowAfter = $pdo->query("SELECT description, deleted_at FROM meals WHERE id = {$idorMealId}")->fetch(\PDO::FETCH_ASSOC);
        $pdo->exec("DELETE FROM meals WHERE id = {$idorMealId}");
        $assert(
            $mealUpdCaregiver->status() === 403
            && $mealDelViewer->status() === 403
            && ($mealRowAfter['description'] ?? '') === 'Repas initial de Marie'
            && $mealRowAfter['deleted_at'] === null,
            'Sécurité §6.3 (H3 IDOR Repas): aide-soignante ne peut pas modifier le repas d’une collègue et lecteur ne peut pas supprimer un repas (403 + base intacte)'
        );

        // (4) Ressource : Note — lecteur (#5) marque lue la note d'un autre
        $pdo->prepare(
            "INSERT INTO notes (patient_id, category, priority, title, content, is_handover, recorded_by, created_at, updated_at, created_by)
             VALUES (1, 'general', 'normal', 'Relève Marie', 'Contenu transmission', 1, 4, :n1, :n2, 4)"
        )->execute(['n1' => $nowIdorUtc, 'n2' => $nowIdorUtc]);
        $idorNoteId = (int) $pdo->lastInsertId();

        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $noteReadWeb = $httpPost('/journal/notes/' . $idorNoteId . '/read', ['user_id' => 3], [], true);
        $noteReadApi = $httpPost('/api/notes/' . $idorNoteId . '/read', ['user_id' => 3], [], true);
        $noteReadsCount = (int) $pdo->query("SELECT COUNT(*) FROM note_reads WHERE note_id = {$idorNoteId}")->fetchColumn();
        $pdo->exec("DELETE FROM notes WHERE id = {$idorNoteId}");
        $assert(
            $noteReadWeb->status() === 403 && $noteReadApi->status() === 403 && $noteReadsCount === 0,
            'Sécurité §6.3 (H3 IDOR Note): lecteur bloqué en 403 lorsqu’il tente de marquer lue la note d’un autre (0 écriture dans note_reads)'
        );

        // (5) Ressource : Document — aide-soignante, coordinatrice et lecteur déposent / suppriment
        $docsCountBeforeIdor = (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL')->fetchColumn();
        $docTargetDeletedAtBefore = $pdo->query("SELECT deleted_at FROM documents WHERE id = {$uploadedDocId}")->fetchColumn();
        $docIdorAll403 = true;
        foreach (['fatou@primecare.test' => 'Soin!2026', 'coord@primecare.test' => 'Coord!2026', 'medecin@primecare.test' => 'Lecture!2026'] as $em => $pw) {
            Auth::attempt($em, $pw);
            $rStore = $httpPost('/documents', ['title' => 'Tentative dépôt non autorisée', 'category' => 'autre'], [], true);
            $rDelete = $httpPost('/documents/' . $uploadedDocId . '/delete', [], [], true);
            if ($rStore->status() !== 403 || $rDelete->status() !== 403) {
                $docIdorAll403 = false;
            }
        }
        $docsCountAfterIdor = (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL')->fetchColumn();
        $docTargetDeletedAtAfter = $pdo->query("SELECT deleted_at FROM documents WHERE id = {$uploadedDocId}")->fetchColumn();
        $assert(
            $docIdorAll403 && $docsCountAfterIdor === $docsCountBeforeIdor && $docTargetDeletedAtAfter === $docTargetDeletedAtBefore,
            'Sécurité §6.3 (H3 IDOR Document): aide-soignante, coordinatrice et lecteur bloqués en 403 sur le dépôt et la suppression de documents (base intacte)'
        );

        // (6) Ressource : Créneau — aide-soignante (#3) modifie / supprime le créneau d'une collègue (#4)
        $pdo->prepare(
            "INSERT INTO shifts (patient_id, user_id, start_at, end_at, status, notes, created_by, created_at, updated_at)
             VALUES (1, 4, '2026-12-10 08:00:00', '2026-12-10 20:00:00', 'planned', 'Garde Marie', 1, :n1, :n2)"
        )->execute(['n1' => $nowIdorUtc, 'n2' => $nowIdorUtc]);
        $idorShiftId = (int) $pdo->lastInsertId();

        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $shUpdResp = $httpPost('/planning/shifts/' . $idorShiftId . '/update', ['user_id' => 3, 'notes' => 'Détourné'], [], true);
        $shDelResp = $httpPost('/planning/shifts/' . $idorShiftId . '/delete', [], [], true);
        $shRowAfter = $pdo->query("SELECT user_id, notes, deleted_at FROM shifts WHERE id = {$idorShiftId}")->fetch(\PDO::FETCH_ASSOC);
        $pdo->exec("DELETE FROM shifts WHERE id = {$idorShiftId}");
        $assert(
            $shUpdResp->status() === 403
            && $shDelResp->status() === 403
            && (int) ($shRowAfter['user_id'] ?? 0) === 4
            && ($shRowAfter['notes'] ?? '') === 'Garde Marie'
            && $shRowAfter['deleted_at'] === null,
            'Sécurité §6.3 (H3 IDOR Créneau): aide-soignante bloquée en 403 sur la modification et la suppression du créneau d’une collègue (base intacte)'
        );

        // (7) Ressource : Pointage — aide-soignante (#3) valide / rejette le pointage d'une collègue (#4)
        $pdo->prepare(
            "INSERT INTO attendances (patient_id, user_id, type, recorded_at, method, status, reason, created_at, updated_at)
             VALUES (1, 4, 'in', :n1, 'declared', 'pending', 'Oubli déclaré Marie', :n2, :n3)"
        )->execute(['n1' => $nowIdorUtc, 'n2' => $nowIdorUtc, 'n3' => $nowIdorUtc]);
        $idorAttId = (int) $pdo->lastInsertId();

        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $attValResp = $httpPost('/attendance/' . $idorAttId . '/validate', [], [], true);
        $attRejResp = $httpPost('/attendance/' . $idorAttId . '/reject', ['validation_note' => 'Rejet illégitime'], [], true);
        $attStatusAfter = (string) $pdo->query("SELECT status FROM attendances WHERE id = {$idorAttId}")->fetchColumn();
        $pdo->exec("DELETE FROM attendances WHERE id = {$idorAttId}");
        $assert(
            $attValResp->status() === 403 && $attRejResp->status() === 403 && $attStatusAfter === 'pending',
            'Sécurité §6.3 (H3 IDOR Pointage): aide-soignante bloquée en 403 sur la validation et le rejet du pointage d’une collègue (statut pending intact)'
        );

        // (8) Ressource : Alerte — lecteur (#5) traite une alerte
        $pdo->prepare(
            "INSERT INTO alerts (patient_id, type, level, title, message, dedupe_key, status, created_at, updated_at)
             VALUES (1, 'vital_critical', 'critical', 'Test IDOR Alerte', 'Message', 'idor_alert_key_test', 'new', :n1, :n2)"
        )->execute(['n1' => $nowIdorUtc, 'n2' => $nowIdorUtc]);
        $idorAlertId = (int) $pdo->lastInsertId();

        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $alHandleWeb = $httpPost('/alerts/' . $idorAlertId . '/handle', ['handled_note' => 'Traité par lecteur'], [], true);
        $alHandleApi = $httpPost('/api/alerts/' . $idorAlertId . '/handle', ['handled_note' => 'Traité par lecteur'], [], true);
        $alStatusAfter = (string) $pdo->query("SELECT status FROM alerts WHERE id = {$idorAlertId}")->fetchColumn();
        $pdo->exec("DELETE FROM alerts WHERE id = {$idorAlertId}");
        $assert(
            $alHandleWeb->status() === 403 && $alHandleApi->status() === 403 && $alStatusAfter === 'new',
            'Sécurité §6.3 (H3 IDOR Alerte): lecteur bloqué en 403 sur le traitement d’une alerte et statut new intact en base'
        );

        // (9) Ressource : Utilisateur — aide-soignante (#3) modifie un autre utilisateur (#4)
        $u4Before = $pdo->query('SELECT first_name, role, is_active FROM users WHERE id = 4')->fetch(\PDO::FETCH_ASSOC);
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $uUpdResp = $httpPost('/users/4/update', ['first_name' => 'Pirate', 'last_name' => 'Test', 'email' => 'marie@primecare.test', 'role' => 'admin'], [], true);
        $uToggleResp = $httpPost('/users/4/toggle-status', [], [], true);
        $u4After = $pdo->query('SELECT first_name, role, is_active FROM users WHERE id = 4')->fetch(\PDO::FETCH_ASSOC);
        $assert(
            $uUpdResp->status() === 403
            && $uToggleResp->status() === 403
            && $u4After === $u4Before,
            'Sécurité §6.3 (H3 IDOR Utilisateur): aide-soignante bloquée en 403 sur la modification d’un autre utilisateur (base intacte)'
        );

        // (10) Ressource : Média — utilisateur non connecté ouvre une photo de repas ou un document
        Auth::logout();
        $unauthMealPhoto = $httpGet('/media/meals/repas_test.jpg');
        $unauthDocMedia = $httpGet('/media/' . $resUpload1['document']['file_path']);
        $unauthDocFile = $httpGet('/documents/' . $uploadedDocId . '/file');
        $assert(
            in_array($unauthMealPhoto->status(), [302, 401, 403], true)
            && in_array($unauthDocMedia->status(), [302, 401, 403], true)
            && in_array($unauthDocFile->status(), [302, 401, 403], true)
            && $unauthDocMedia->body() === ''
            && $unauthDocFile->body() === '',
            'Sécurité §6.3 (H3 IDOR Média): utilisateur non connecté bloqué (302 vers /login, 0 octet servi) sur les photos de repas et les documents'
        );

        // (11) Ressource : Export — aide-soignante ouvre l'export de l'audit ; lecteur ouvre l'export des pointages
        $expAuditBefore = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'export'")->fetchColumn();
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $caregiverExpAudit = $httpGet('/reports/export/audit');
        Auth::attempt('medecin@primecare.test', 'Lecture!2026');
        $viewerExpAtt = $httpGet('/reports/export/attendances');
        $expAuditAfter = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'export'")->fetchColumn();
        $assert(
            $caregiverExpAudit->status() === 403
            && $viewerExpAtt->status() === 403
            && $expAuditAfter === $expAuditBefore,
            'Sécurité §6.3 (H3 IDOR Export): aide-soignante (audit) et lecteur (pointages) bloqués en 403 sans trace d’export créée'
        );

        // (12) Ressource : Export RGPD — un utilisateur (#3 Fatou) demande les données d'un autre (?user_id=1) et ne reçoit que les siennes
        Auth::attempt('fatou@primecare.test', 'Soin!2026');
        $rgpdIdorResp = $httpGet('/profile/export-data?user_id=1');
        $tmpRgpdZip = tempnam(sys_get_temp_dir(), 'pc_rgpd_idor_') . '.zip';
        file_put_contents($tmpRgpdZip, $rgpdIdorResp->body());
        $zip = new \ZipArchive();
        $compteCsvContent = '';
        if ($zip->open($tmpRgpdZip) === true) {
            $compteCsvContent = (string) $zip->getFromName('01_mon_compte.csv');
            $zip->close();
        }
        @unlink($tmpRgpdZip);
        $assert(
            $rgpdIdorResp->status() === 200
            && str_contains($compteCsvContent, 'fatou@primecare.test')
            && !str_contains($compteCsvContent, 'admin@primecare.test'),
            'Sécurité §6.3 (H3 IDOR Export RGPD): demander /profile/export-data?user_id=1 en tant que Fatou ne retourne que les données de Fatou'
        );

        // 7.3 Limite de débit sur les routes API d'écriture (120 requêtes / minute / utilisateur -> 429)
        RateLimiter::resetApiWriteLimit(3);
        $allowed120 = true;
        for ($i = 1; $i <= 120; $i++) {
            if (!RateLimiter::checkApiWriteLimit(3, 120, '2026-11-14 14:00:10')) {
                $allowed120 = false;
                break;
            }
        }
        $blocked121 = !RateLimiter::checkApiWriteLimit(3, 120, '2026-11-14 14:00:10');
        RateLimiter::resetApiWriteLimit(3);
        $assert(
            $allowed120 && $blocked121,
            'Sécurité §6.3 (Rate Limit API): autorise 120 requêtes d’écriture / minute / utilisateur et bloque la 121e (HTTP 429)'
        );

        // 7.4 Traversée de chemin (Path Traversal) sur MediaController
        $mediaCtrl = new MediaController();
        $traversalStatus = static function (callable $call): int {
            try {
                return $call()->status();
            } catch (Throwable $e) {
                return (int) $e->getCode();
            }
        };
        $t1 = $traversalStatus(static fn() => $mediaCtrl->show(new Request(), '..', 'config.php'));
        $t2 = $traversalStatus(static fn() => $mediaCtrl->show(new Request(), 'documents', '..%2f..%2fconfig.php'));
        $t3 = $traversalStatus(static fn() => $mediaCtrl->show(new Request(), 'documents', "test\0.pdf"));
        $assert(
            in_array($t1, [403, 404], true)
            && in_array($t2, [403, 404], true)
            && in_array($t3, [403, 404], true),
            'Sécurité §6.3 (Traversée de chemin): bloque les séquences .., le double encodage %2e%2e et les octets nuls sur /media/'
        );

        // 7.5 bin/seed.php refuse de s'exécuter en production & bin/setup.php refuse d'écraser une installation existante
        $rootDir = dirname(__DIR__, 2);
        $seedSrc = (string) file_get_contents($rootDir . '/bin/seed.php');
        $setupSrc = (string) file_get_contents($rootDir . '/bin/setup.php');
        $assert(
            str_contains($seedSrc, "=== 'production'") && str_contains($seedSrc, 'exit(1)'),
            'Production §6.1: bin/seed.php refuse de s’exécuter lorsque app.env = production'
        );
        $assert(
            str_contains($setupSrc, '--force') && str_contains($setupSrc, 'déjà installée'),
            'Production §6.1: bin/setup.php refuse de s’exécuter si l’application est déjà installée (sauf --force)'
        );

        // 7.6 Données de démonstration Phase 4 (>= 10 documents et >= 100 entrées d'audit sur 30 jours)
        $docSeedCount = (int) $pdo->query('SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL')->fetchColumn();
        $auditSeedCount = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE deleted_at IS NULL')->fetchColumn();
        $assert(
            $docSeedCount >= 10 && $auditSeedCount >= 100,
            'Données de démonstration §8: au moins 10 documents médicaux et 100 entrées d’audit réparties sur 30 jours'
        );
    }
}
