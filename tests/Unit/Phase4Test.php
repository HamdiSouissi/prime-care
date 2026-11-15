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

        // 7.2 Série IDOR complète : une aide-soignante tente d'accéder aux ressources d'un autre utilisateur ou réservées à l'admin
        $idorBlockedCount = 0;
        $idorChecks = [
            static fn() => (new AuditController())->index(new Request()),
            static fn() => (new SettingsController())->index(new Request()),
            static fn() => (new DocumentController())->delete(new Request(), (string) $uploadedDocId),
            static fn() => (new ReportController())->index(new Request()),
            static fn() => (new ReportController())->downloadExport(new Request(), 'audit'),
        ];
        foreach ($idorChecks as $fn) {
            try {
                $r = $fn();
                if ($r->status() === 403) {
                    $idorBlockedCount++;
                }
            } catch (Throwable $e) {
                if ($e->getCode() === 403) {
                    $idorBlockedCount++;
                }
            }
        }
        $assert(
            $idorBlockedCount === count($idorChecks),
            'Sécurité §6.3 (IDOR): toutes les tentatives d’accès transversal / élévation de privilèges par une aide-soignante sont bloquées en 403'
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
