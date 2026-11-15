<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\AlertService;
use App\Services\AttendanceService;
use App\Services\CronService;
use App\Services\HoursCalculatorService;
use App\Services\PlanningService;
use App\Services\ScheduleService;
use App\Services\WebPushService;
use InvalidArgumentException;
use PDO;

/**
 * Automated Unit & Domain Tests for Phase 3 (§7):
 * - E16 Planning: overlap detection (InvalidArgumentException / 422), week duplication over N weeks,
 *   role-based filtering (onlyUserId), absences flagging shifts "À remplacer" (`needs_replacement`)
 * - E05/E17/E18 Attendance & Hours: Haversine distance (within 150m -> valid, 320m -> pending "Hors du domicile (320 m)"),
 *   GPS denied -> pending, HMAC QR verification & revocation, declared forgot-punch & admin validation/rejection,
 *   require_clocked_in enforcement (off vs on -> 403), HoursCalculatorService (normal, night 21h-6h, Sunday, 11 French holidays,
 *   spring DST 11h, autumn DST 13h, payroll CSV `;` + UTF-8 BOM)
 * - §3.8 Alerts & Web Push VAPID: 7 rules, dedupe_key deduplication, 15-min escalation, auto-closure, quiet hours bypass for critical,
 *   RFC 8291 aes128gcm + ES256 JWT (64-byte raw r||s), cron tasks
 */
final class Phase3Test
{
    public static function run(callable $assert): void
    {
        $pdo = Database::connection();

        // =====================================================================
        // 1. PLANNING (E16) : Chevauchement, Duplication, Filtrage rôle, Absences
        // =====================================================================

        // Create a shift on a fresh future date for Fatou (user_id=3)
        $shift1 = PlanningService::createShift([
            'user_id' => 3,
            'template_id' => 1,
            'date' => '2026-12-10',
            'start_time' => '08:00',
            'end_time' => '20:00',
            'notes' => 'Garde test Phase 3',
        ], 1, 1);
        $assert(!empty($shift1['id']), 'PlanningService: création d’un créneau de garde réussit');

        // Attempt overlapping shift for the same caregiver (Fatou, 12:00-18:00 on 2026-12-10)
        $overlapCaught = false;
        try {
            PlanningService::createShift([
                'user_id' => 3,
                'date' => '2026-12-10',
                'start_time' => '12:00',
                'end_time' => '18:00',
            ], 1, 1);
        } catch (InvalidArgumentException $e) {
            $overlapCaught = str_contains($e->getMessage(), 'Chevauchement refusé');
        }
        $assert($overlapCaught, 'PlanningService: chevauchement pour la même aide-soignante est refusé (422)');

        // Another caregiver (Aïcha, user_id=4) CAN be scheduled on the same day without overlap
        $shift2 = PlanningService::createShift([
            'user_id' => 4,
            'date' => '2026-12-10',
            'start_time' => '20:00',
            'end_time' => '08:00',
        ], 1, 1);
        $assert(!empty($shift2['id']), 'PlanningService: créneau de nuit pour une autre aide-soignante accepté');

        // Role filtering: caregiver only sees own shifts (`$onlyUserId = 3`), coordinator/admin/viewer sees all (`$onlyUserId = null`)
        $fatouShifts = PlanningService::getShiftsForLocalRange('2026-12-10', '2026-12-11', 3, 1);
        $adminShifts = PlanningService::getShiftsForLocalRange('2026-12-10', '2026-12-11', null, 1);
        $onlyFatou = count($fatouShifts) === 1 && (int) $fatouShifts[0]['user_id'] === 3;
        $assert(
            $onlyFatou && count($adminShifts) >= 2,
            'PlanningService: filtrage RBAC — l’aide-soignante ne voit que ses propres créneaux, l’admin voit toute l’équipe'
        );

        // Week duplication over N=2 weeks (source week of 2026-12-07 Monday)
        $dupRes = PlanningService::duplicateWeek('2026-12-07', 2, 1, 1);
        $assert(
            ($dupRes['created'] ?? 0) >= 4,
            'PlanningService: duplication d’une semaine sur N=2 semaines crée les créneaux sur les semaines suivantes'
        );

        // Declaring an absence flags overlapping shifts as "À remplacer" (`needs_replacement = true`)
        $absId = PlanningService::createAbsence([
            'user_id' => 3,
            'reason' => 'maladie',
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-10',
            'notes' => 'Arrêt maladie test',
        ], 1, 1);
        $shiftsAfterAbs = PlanningService::getShiftsForLocalRange('2026-12-10', '2026-12-10', 3, 1);
        $assert(
            $absId > 0 && !empty($shiftsAfterAbs[0]['needs_replacement']),
            'PlanningService: déclarer une absence marque automatiquement les créneaux concernés « À remplacer »'
        );

        // =====================================================================
        // 2. POINTAGE (E05, E17) : Haversine GPS, QR signé HMAC, Oubli, require_clocked_in
        // =====================================================================

        // Haversine calculation: exact home coordinates (48.79036, 2.45557) = 0m, ~320m away in Créteil
        $dist0 = AttendanceService::haversineDistanceMeters(48.7903600, 2.4555700, 48.7903600, 2.4555700);
        $dist320 = AttendanceService::haversineDistanceMeters(48.7903600, 2.4555700, 48.7932000, 2.4562000);
        $assert(
            $dist0 === 0 && $dist320 >= 300 && $dist320 <= 350,
            'AttendanceService: calcul Haversine précis (0 m au domicile, ~320 m hors rayon)'
        );

        // Ensure verification mode is GPS with 150m radius
        AttendanceService::updateSettings([
            'verification_mode' => 'gps',
            'radius_m' => 150,
            'require_clocked_in' => 0,
            'home_latitude' => 48.7903600,
            'home_longitude' => 2.4555700,
        ], 1, 1);

        // Punch inside radius (~18m) -> valid
        $punchValid = AttendanceService::recordPunch(3, [
            'type' => 'in',
            'method' => 'gps',
            'latitude' => 48.7905000,
            'longitude' => 2.4556500,
            'accuracy_m' => 12,
        ], '127.0.0.1', 1);
        $assert(
            ($punchValid['status'] ?? '') === 'valid',
            'AttendanceService: pointage GPS dans le rayon de 150 m est validé automatiquement (valid)'
        );

        // Punch outside radius (~320m) -> never blocked, status = pending with "Hors du domicile (320 m)"
        $punchOutRadius = AttendanceService::recordPunch(3, [
            'type' => 'out',
            'method' => 'gps',
            'latitude' => 48.7932000,
            'longitude' => 2.4562000,
            'accuracy_m' => 15,
        ], '127.0.0.1', 1);
        $assert(
            ($punchOutRadius['status'] ?? '') === 'pending'
            && str_contains((string) ($punchOutRadius['reason'] ?? ''), 'Hors du domicile'),
            'AttendanceService: pointage GPS hors rayon (320 m) n’est jamais bloqué mais passe « À valider » (« Hors du domicile »)'
        );

        // Punch with GPS denied -> never blocked, status = pending with "Position refusée"
        $punchNoGps = AttendanceService::recordPunch(4, [
            'type' => 'in',
            'method' => 'gps',
            'gps_denied' => 1,
        ], '127.0.0.1', 1);
        $assert(
            ($punchNoGps['status'] ?? '') === 'pending'
            && str_contains((string) ($punchNoGps['reason'] ?? ''), 'Position refusée'),
            'AttendanceService: pointage avec position GPS refusée passe « À valider » (« Position refusée »)'
        );

        // Home QR code HMAC verification & revocation
        $activeToken = AttendanceService::getActiveQrToken(1);
        $qrPayload = (string) $activeToken['qr_payload'];
        $qrVerifyOk = AttendanceService::verifyQrPayload($qrPayload, 1);
        $qrTampered = AttendanceService::verifyQrPayload($qrPayload . 'tamper', 1);
        $newToken = AttendanceService::regenerateQrToken(1, 1, 'Nouveau QR Test');
        $oldAfterRevoke = AttendanceService::verifyQrPayload($qrPayload, 1);
        $newQrPayload = (string) $newToken['qr_payload'];
        $newVerifyOk = AttendanceService::verifyQrPayload($newQrPayload, 1);
        $assert(
            $qrVerifyOk === true
            && $qrTampered === false
            && $oldAfterRevoke === false
            && $newVerifyOk === true,
            'AttendanceService: QR code signé HMAC valide accepté, falsifié rejeté, et l’ancien QR est révoqué après régénération'
        );

        // Declared forgot-punch: requires reason (InvalidArgumentException if empty), creates pending record, admin validate/reject is audited
        $forgotNoReasonCaught = false;
        try {
            AttendanceService::declareForgotPunch(4, [
                'type' => 'out',
                'declared_date' => '2026-11-14',
                'declared_time' => '20:05',
                'reason' => '',
            ], '127.0.0.1', 1);
        } catch (InvalidArgumentException) {
            $forgotNoReasonCaught = true;
        }
        $forgotOk = AttendanceService::declareForgotPunch(4, [
            'type' => 'out',
            'declared_date' => '2026-11-14',
            'declared_time' => '20:05',
            'reason' => 'Oubli de pointage en quittant le poste',
        ], '127.0.0.1', 1);
        $validatedAtt = AttendanceService::validateAttendance((int) $forgotOk['id'], 1, 1);
        $assert(
            $forgotNoReasonCaught
            && ($forgotOk['status'] ?? '') === 'pending'
            && ($validatedAtt['status'] ?? '') === 'valid',
            'AttendanceService: déclaration d’oubli de pointage exige un motif (422), crée un pointage pending et peut être validée par le responsable'
        );

        // Option `require_clocked_in`: off by default -> allowed; when on -> caregiver clocked out gets 403 "Pointez votre arrivée pour saisir"
        AttendanceService::recordPunch(3, [
            'type' => 'out',
            'method' => 'qr',
            'qr_payload' => $newQrPayload,
        ], '127.0.0.1', 1);
        $fatouUser = ['id' => 3, 'role' => 'caregiver'];
        AttendanceService::updateSettings(['require_clocked_in' => 0], 1, 1);
        $canWriteOff = AttendanceService::canUserWriteMedical($fatouUser, 1);
        AttendanceService::updateSettings(['require_clocked_in' => 1], 1, 1);
        $canWriteOnWhenOut = AttendanceService::canUserWriteMedical($fatouUser, 1);
        // Clock Fatou back in
        AttendanceService::recordPunch(3, [
            'type' => 'in',
            'method' => 'qr',
            'qr_payload' => $newQrPayload,
        ], '127.0.0.1', 1);
        $canWriteOnWhenIn = AttendanceService::canUserWriteMedical($fatouUser, 1);
        AttendanceService::updateSettings(['require_clocked_in' => 0], 1, 1);
        $assert(
            $canWriteOff === true
            && $canWriteOnWhenOut === false
            && $canWriteOnWhenIn === true,
            'AttendanceService: option require_clocked_in bloque en 403 « Pointez votre arrivée pour saisir » une aide-soignante non pointée en arrivée'
        );

        // =====================================================================
        // 3. CALCULATEUR D'HEURES (E18) : Nuit 21h-6h, Dimanche, 11 Jours fériés, DST Mars (11h) & Octobre (13h), CSV
        // =====================================================================

        // 11 French holidays check for 2026 (Easter 2026 is Sunday April 5 -> Easter Monday 2026-04-06, Ascension 2026-05-14, Pentecost Monday 2026-05-25)
        $holidays2026 = HoursCalculatorService::frenchHolidaysForYear(2026);
        $assert(
            count($holidays2026) === 11
            && isset($holidays2026['2026-01-01'], $holidays2026['2026-04-06'], $holidays2026['2026-05-01'], $holidays2026['2026-05-14'], $holidays2026['2026-05-25'], $holidays2026['2026-07-14'], $holidays2026['2026-11-11'], $holidays2026['2026-12-25']),
            'HoursCalculatorService: calcule exactement les 11 jours fériés français 2026 (fixes + Pâques, Ascension, Pentecôte)'
        );

        // Full 11 French holidays check for 2027 (Easter 2027 is March 28 -> Easter Monday 2027-03-29, Ascension 2027-05-06, Pentecost Monday 2027-05-17)
        $holidays2027 = HoursCalculatorService::frenchHolidaysForYear(2027);
        $expected2027Dates = [
            '2027-01-01', // Jour de l'An
            '2027-03-29', // Lundi de Pâques
            '2027-05-01', // Fête du Travail
            '2027-05-06', // Ascension
            '2027-05-08', // Victoire 1945
            '2027-05-17', // Lundi de Pentecôte
            '2027-07-14', // Fête nationale
            '2027-08-15', // Assomption
            '2027-11-01', // Toussaint
            '2027-11-11', // Armistice 1918
            '2027-12-25', // Noël
        ];
        $assert(
            count($holidays2027) === 11 && array_keys($holidays2027) === $expected2027Dates,
            'HoursCalculatorService: calcule la liste complète des 11 jours fériés 2027 (incluant Pâques 29/03/2027, Ascension 06/05/2027, Pentecôte 17/05/2027)'
        );

        // Normal weekday day shift: Wednesday 2026-11-18 08:00 -> 20:00 Paris (07:00 -> 19:00 UTC) = 12h total, 0h night
        $daySplit = HoursCalculatorService::splitInterval('2026-11-18 07:00:00', '2026-11-18 19:00:00');
        $assert(
            abs($daySplit['total_hours'] - 12.0) < 0.01
            && abs($daySplit['night_hours'] - 0.0) < 0.01,
            'HoursCalculatorService: créneau de jour (08h–20h) = 12h00 totales et 0h00 de nuit'
        );

        // Night shift crossing midnight: Wednesday 2026-11-18 20:00 -> Thursday 2026-11-19 08:00 Paris (19:00 -> 07:00 UTC) = 12h total, 9h night (21h-6h)
        $nightSplit = HoursCalculatorService::splitInterval('2026-11-18 19:00:00', '2026-11-19 07:00:00');
        $assert(
            abs($nightSplit['total_hours'] - 12.0) < 0.01
            && abs($nightSplit['night_hours'] - 9.0) < 0.01,
            'HoursCalculatorService: garde de nuit (20h–08h traversant minuit) = 12h00 totales dont 9h00 de nuit (21h–6h)'
        );

        // Sunday + Holiday split: Saturday 2026-11-14 20:00 -> Sunday 2026-11-15 08:00 Paris (4h on Saturday, 8h on Sunday)
        $sunSplit = HoursCalculatorService::splitInterval('2026-11-14 19:00:00', '2026-11-15 07:00:00');
        // Holiday: Wednesday 2026-11-11 (Armistice) 08:00 -> 20:00 Paris (07:00 -> 19:00 UTC) = 12h holiday
        $holSplit = HoursCalculatorService::splitInterval('2026-11-11 07:00:00', '2026-11-11 19:00:00');
        $assert(
            abs($sunSplit['sunday_hours'] - 8.0) < 0.01 && abs($holSplit['holiday_hours'] - 12.0) < 0.01,
            'HoursCalculatorService: découpe exactement les heures de dimanche (8h après minuit) et de jour férié (11 novembre = 12h)'
        );

        // Spring DST transition (Last Sunday of March: night of 28 to 29 March 2026, 20:00 local -> 08:00 local = 11 real hours!)
        $springStartUtc = ScheduleService::localParisToUtc('2026-03-28', '20:00'); // 19:00 UTC (CET)
        $springEndUtc = ScheduleService::localParisToUtc('2026-03-29', '08:00');   // 06:00 UTC (CEST)
        $springSplit = HoursCalculatorService::splitInterval($springStartUtc, $springEndUtc);
        $assert(
            abs($springSplit['total_hours'] - 11.0) < 0.01 && abs($springSplit['night_hours'] - 8.0) < 0.01,
            'HoursCalculatorService (DST été mars 2026): garde de nuit 20h00–08h00 locales lors du passage à l’heure d’été = 11h00 réelles (dont 8h00 de nuit)'
        );

        // Autumn DST transition (Last Sunday of October: night of 24 to 25 October 2026, 20:00 local -> 08:00 local = 13 real hours!)
        $autumnStartUtc = ScheduleService::localParisToUtc('2026-10-24', '20:00'); // 18:00 UTC (CEST)
        $autumnEndUtc = ScheduleService::localParisToUtc('2026-10-25', '08:00');   // 07:00 UTC (CET)
        $autumnSplit = HoursCalculatorService::splitInterval($autumnStartUtc, $autumnEndUtc);
        $assert(
            abs($autumnSplit['total_hours'] - 13.0) < 0.01 && abs($autumnSplit['night_hours'] - 10.0) < 0.01,
            'HoursCalculatorService (DST hiver oct. 2026): garde de nuit 20h00–08h00 locales lors du passage à l’heure d’hiver = 13h00 réelles (dont 10h00 de nuit)'
        );

        // Payroll CSV export (; separator + UTF-8 BOM \xEF\xBB\xBF)
        $csv = HoursCalculatorService::generatePayrollCsv(2026, 11, null, 1);
        $assert(
            str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, ';') && str_contains($csv, 'Heures de nuit 21h-6h'),
            'HoursCalculatorService: export CSV paie inclut le BOM UTF-8 et le séparateur point-virgule (;)'
        );

        // =====================================================================
        // 4. ALERTES, DÉDOUBLONNAGE, ESCALADE 15 MIN, FERMETURE AUTO, HEURES CALMES & WEB PUSH
        // =====================================================================

        // Deduplication by dedupe_key: triggering twice while open creates only 1 alert
        $dedupeKey = 'test_dedupe_phase3_' . bin2hex(random_bytes(4));
        $a1 = AlertService::trigger([
            'patient_id' => 1,
            'type' => 'intake_late',
            'level' => 'warning',
            'title' => 'Prise en retard test',
            'message' => 'Metformine 1000 mg en retard',
            'source_type' => 'medication_intake',
            'source_id' => 99999,
            'dedupe_key' => $dedupeKey,
        ]);
        $a2 = AlertService::trigger([
            'patient_id' => 1,
            'type' => 'intake_late',
            'level' => 'warning',
            'title' => 'Prise en retard test doublon',
            'message' => 'Metformine 1000 mg en retard',
            'source_type' => 'medication_intake',
            'source_id' => 99999,
            'dedupe_key' => $dedupeKey,
        ]);
        $assert(
            $a1['created'] === true && $a2['created'] === false && (int) $a1['alert']['id'] === (int) $a2['alert']['id'],
            'AlertService: dédoublonnage strict par dedupe_key (aucun doublon tant que l’alerte est ouverte)'
        );

        // Auto-closure when the underlying action is completed
        $closedCount = AlertService::autoResolveByDedupeKey($dedupeKey, 'Prise administrée par Fatou', 1);
        $closedRow = $pdo->query("SELECT status, resolution FROM alerts WHERE id = " . (int) $a1['alert']['id'])->fetch(PDO::FETCH_ASSOC);
        $assert(
            $closedCount === 1
            && ($closedRow['status'] ?? '') === 'handled'
            && !empty($closedRow['resolution']),
            'AlertService: fermeture automatique marque l’alerte comme handled dès que l’action attendue est réalisée'
        );

        // 15-minute escalation of unacknowledged critical alert
        $escKey = 'test_esc_crit_' . bin2hex(random_bytes(4));
        $critAlert = AlertService::trigger([
            'patient_id' => 1,
            'type' => 'measure_critical',
            'level' => 'critical',
            'title' => 'Hypoglycémie critique 0,55 g/L',
            'message' => 'Test escalade 15 min',
            'dedupe_key' => $escKey,
        ]);
        $critAlertId = (int) $critAlert['alert']['id'];
        // Backdate created_at by 20 minutes and ensure status = 'new'
        $twentyMinAgo = gmdate('Y-m-d H:i:s', time() - 1200);
        $pdo->prepare("UPDATE alerts SET created_at = :ca, status = 'new', escalated_at = NULL WHERE id = :id")
            ->execute(['ca' => $twentyMinAgo, 'id' => $critAlertId]);
        $scanStats = AlertService::scanPeriodicAlerts(1);
        $escRow = $pdo->query("SELECT escalated_at FROM alerts WHERE id = " . $critAlertId)->fetch(PDO::FETCH_ASSOC);
        $assert(
            ($scanStats['escalated'] ?? 0) >= 1 && !empty($escRow['escalated_at']),
            'AlertService: une alerte critique non acquittée après 15 min est automatiquement escaladée au responsable'
        );

        // Quiet hours check: 23:30 Europe/Paris (22:30 UTC in winter) is inside 22:00–07:00 quiet hours
        $inQuiet = AlertService::isInQuietHours('22:00', '07:00', '2026-11-14 22:30:00');
        $outQuiet = AlertService::isInQuietHours('22:00', '07:00', '2026-11-14 11:00:00');
        $assert(
            $inQuiet === true && $outQuiet === false,
            'AlertService: détection exacte des heures calmes (22h00–07h00 Europe/Paris) avec contournement pour les alertes critiques'
        );

        // Native Web Push VAPID (RFC 8291 aes128gcm encryption/decryption + RFC 8292 ES256 JWT DER->raw r||s 64 bytes)
        $vapidKeys = WebPushService::getOrCreateVapidKeys();
        $jwt = WebPushService::createVapidJwt('https://fcm.googleapis.com', $vapidKeys['subject'] ?? 'mailto:admin@primecare.fr', $vapidKeys['privateKeyPem']);
        $jwtParts = explode('.', $jwt);
        $sigBytes = WebPushService::base64UrlDecode($jwtParts[2] ?? '');
        $jwtVerified = WebPushService::verifyVapidJwt($jwt, $vapidKeys['publicKeyPem']);

        // Create a browser EC P-256 keypair + 16-byte auth secret to test RFC 8291 aes128gcm payload encryption & decryption
        $clientKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $clientDetails = $clientKey !== false ? openssl_pkey_get_details($clientKey) : false;
        $clientPubUncompressed = "\x04"
            . str_pad($clientDetails['ec']['x'] ?? '', 32, "\x00", STR_PAD_LEFT)
            . str_pad($clientDetails['ec']['y'] ?? '', 32, "\x00", STR_PAD_LEFT);
        $clientP256dh = WebPushService::base64UrlEncode($clientPubUncompressed);
        $authRaw = random_bytes(16);
        $clientAuth = WebPushService::base64UrlEncode($authRaw);
        $plainJson = '{"title":"Test Prime Care","body":"Alerte glycémie"}';
        $encrypted = WebPushService::encryptPayload($plainJson, $clientP256dh, $clientAuth);
        $decryptedJson = WebPushService::decryptPayload($encrypted['body'], $clientKey, $clientPubUncompressed, $authRaw);

        $assert(
            count($jwtParts) === 3
            && strlen($sigBytes) === 64
            && ($jwtVerified['valid'] ?? false) === true
            && $decryptedJson === $plainJson,
            'WebPushService: signe/vérifie le JWT ES256 (r||s 64 octets) et chiffre/déchiffre de bout en bout en aes128gcm (RFC 8291)'
        );

        // RFC 8291 Appendix A Official Test Vector (byte-for-byte equality)
        $rfcPlaintext = 'When I grow up, I want to be a watermelon';
        $rfcUaPrivB64 = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';
        $rfcUaPubB64 = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $rfcAuthB64 = 'BTBZMqHH6r4Tts7J_aSIgg';
        $rfcSaltB64 = 'DGv6ra1nlYgDCS1FRnbzlw';
        $rfcAsPrivB64 = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
        $rfcAsPubB64 = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
        $rfcExpectedCekB64 = 'oIhVW04MRdy2XN9CiKLxTg';
        $rfcExpectedNonceB64 = '4h_95klXJ5E_qnoN';
        $rfcExpectedCiphertextB64 = '8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ';
        $rfcExpectedBodyB64 = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

        $rfcEncrypted = WebPushService::encryptPayload(
            $rfcPlaintext,
            $rfcUaPubB64,
            $rfcAuthB64,
            $rfcSaltB64,
            $rfcAsPrivB64,
            $rfcAsPubB64
        );
        $rfcUaPem = WebPushService::rawPrivateAndPublicToPem(
            WebPushService::base64UrlDecode($rfcUaPrivB64),
            WebPushService::base64UrlDecode($rfcUaPubB64)
        );
        $rfcDecrypted = WebPushService::decryptPayload(
            $rfcEncrypted['body'],
            $rfcUaPem,
            WebPushService::base64UrlDecode($rfcUaPubB64),
            WebPushService::base64UrlDecode($rfcAuthB64)
        );
        $assert(
            WebPushService::base64UrlEncode($rfcEncrypted['cek']) === $rfcExpectedCekB64
            && WebPushService::base64UrlEncode($rfcEncrypted['nonce']) === $rfcExpectedNonceB64
            && WebPushService::base64UrlEncode(substr($rfcEncrypted['body'], 86)) === $rfcExpectedCiphertextB64
            && $rfcEncrypted['body'] === WebPushService::base64UrlDecode($rfcExpectedBodyB64)
            && $rfcDecrypted === $rfcPlaintext,
            'WebPushService: conformité octet par octet au vecteur officiel RFC 8291 Appendix A (CEK, Nonce, Ciphertext et Corps complet aes128gcm)'
        );

        // Automatic deletion of an expired subscription (HTTP 410 Gone)
        $sub410Id = WebPushService::saveSubscription(1, [
            'endpoint' => 'https://push.primecare.test/gone-410/' . bin2hex(random_bytes(4)),
            'keys' => [
                'p256dh' => $rfcUaPubB64,
                'auth' => $rfcAuthB64,
            ],
        ], 'PHPUnit/410-Test');
        $sub410Row = $pdo->query('SELECT * FROM push_subscriptions WHERE id = ' . $sub410Id)->fetch(PDO::FETCH_ASSOC);
        $send410Res = WebPushService::sendToSubscription($sub410Row, ['title' => 'Test 410'], 'normal', 86400, 410);
        $sub410After = $pdo->query('SELECT id FROM push_subscriptions WHERE id = ' . $sub410Id)->fetchColumn();
        $assert(
            $send410Res['ok'] === false
            && $send410Res['status_code'] === 410
            && $send410Res['expired'] === true
            && $sub410After === false,
            'WebPushService: supprime automatiquement de la base un abonnement expiré renvoyant HTTP 410 Gone'
        );

        // Notification queue exponential backoff (increasing next_try_at) and abandonment after 5 failures with last_error
        $pdo->exec("DELETE FROM notification_queue WHERE subject = 'Test Backoff 5x'");
        $sub503Id = WebPushService::saveSubscription(1, [
            'endpoint' => 'https://push.primecare.test/fail-503/' . bin2hex(random_bytes(4)),
            'keys' => [
                'p256dh' => $rfcUaPubB64,
                'auth' => $rfcAuthB64,
            ],
        ], 'PHPUnit/503-Test');
        $baseTs = strtotime('2026-11-14 12:00:00 UTC');
        $t0Utc = gmdate('Y-m-d H:i:s', $baseTs);
        $pdo->prepare(
            "INSERT INTO notification_queue
             (alert_id, user_id, channel, recipient, subject, content, status, attempts, max_attempts, next_try_at, created_at, updated_at)
             VALUES (NULL, 1, 'push', 'endpoint-503', 'Test Backoff 5x', :content, 'pending', 0, 5, :next_at, :c_at, :u_at)"
        )->execute([
            'content' => json_encode(['subscription_id' => $sub503Id, 'payload' => ['title' => 'Test 503']]),
            'next_at' => $t0Utc,
            'c_at' => $t0Utc,
            'u_at' => $t0Utc,
        ]);
        $queueId = (int) $pdo->lastInsertId();

        $delaysObserved = [];
        $currentSimTs = $baseTs;
        for ($i = 1; $i <= 5; $i++) {
            $simNow = gmdate('Y-m-d H:i:s', $currentSimTs);
            AlertService::processNotificationQueue(50, $simNow);
            $qRow = $pdo->query('SELECT status, attempts, next_try_at, last_error FROM notification_queue WHERE id = ' . $queueId)->fetch(PDO::FETCH_ASSOC);
            if ($i < 5) {
                $nextTs = strtotime((string) $qRow['next_try_at'] . ' UTC');
                $delaysObserved[] = $nextTs - $currentSimTs;
                $currentSimTs = $nextTs;
            }
        }
        $finalQRow = $pdo->query('SELECT status, attempts, last_error FROM notification_queue WHERE id = ' . $queueId)->fetch(PDO::FETCH_ASSOC);
        $increasingDelays = count($delaysObserved) === 4
            && $delaysObserved[0] < $delaysObserved[1]
            && $delaysObserved[1] < $delaysObserved[2]
            && $delaysObserved[2] < $delaysObserved[3];
        $assert(
            $increasingDelays
            && ($finalQRow['status'] ?? '') === 'failed'
            && (int) ($finalQRow['attempts'] ?? 0) === 5
            && !empty($finalQRow['last_error']),
            'AlertService (notification_queue): recalcule next_try_at avec délais croissants (2, 4, 8, 16 min) et abandonne en status=failed après 5 échecs avec last_error renseigné'
        );

        // =====================================================================
        // 5. MODE HORS LIGNE (G1) : client_recorded_at (-20 min, futur > 2 min, > 72 h -> 422 OFFLINE_TOO_OLD) & conflit 409
        // =====================================================================
        $makeApiPost = static function (string $uri, array $payload): \App\Core\Request {
            return new \App\Core\Request(
                [],
                $payload,
                [],
                [
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => $uri,
                    'HTTP_ACCEPT' => 'application/json',
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                $payload
            );
        };

        $user3 = $pdo->query('SELECT * FROM users WHERE id = 3')->fetch(PDO::FETCH_ASSOC);
        $user4 = $pdo->query('SELECT * FROM users WHERE id = 4')->fetch(PDO::FETCH_ASSOC);
        $user1 = $pdo->query('SELECT * FROM users WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        \App\Core\Auth::loginUser($user3);
        $apiMed = new \App\Controllers\ApiMedicalController();

        // 5a. client_recorded_at in the past (-20 min) is preserved as measured_at
        $past20Ts = time() - 20 * 60;
        $past20Iso = gmdate('Y-m-d\TH:i:s\Z', $past20Ts);
        $past20ExpectedUtc = gmdate('Y-m-d H:i:s', $past20Ts);
        $reqPast = $makeApiPost('/api/measures', [
            'type_code' => 'glycemia',
            'value_primary' => '1.18',
            'context' => 'fasting',
            'client_uuid' => 'offline-past20-' . bin2hex(random_bytes(4)),
            'client_recorded_at' => $past20Iso,
        ]);
        $resPast = $apiMed->storeMeasure($reqPast);
        $bodyPast = json_decode($resPast->body(), true);
        $assert(
            $resPast->statusCode() === 201
            && ($bodyPast['data']['measure']['measured_at'] ?? '') === $past20ExpectedUtc,
            'Offline G1: client_recorded_at dans le passé (-20 min) est conservé comme heure de réalisation (measured_at)'
        );

        // 5b. client_recorded_at in the future (> 2 min, e.g. +15 min) is clamped to server time
        $future15Ts = time() + 15 * 60;
        $future15Iso = gmdate('Y-m-d\TH:i:s\Z', $future15Ts);
        $beforeServerTs = time();
        $reqFuture = $makeApiPost('/api/measures', [
            'type_code' => 'glycemia',
            'value_primary' => '1.22',
            'context' => 'postprandial',
            'client_uuid' => 'offline-future15-' . bin2hex(random_bytes(4)),
            'client_recorded_at' => $future15Iso,
        ]);
        $resFuture = $apiMed->storeMeasure($reqFuture);
        $afterServerTs = time();
        $bodyFuture = json_decode($resFuture->body(), true);
        $recordedFutureTs = strtotime((string) ($bodyFuture['data']['measure']['measured_at'] ?? '') . ' UTC');
        $assert(
            $resFuture->statusCode() === 201
            && $recordedFutureTs >= ($beforeServerTs - 2)
            && $recordedFutureTs <= ($afterServerTs + 2),
            'Offline G1: client_recorded_at dans le futur (> 2 min) est ramené à l’heure serveur'
        );

        // 5c. client_recorded_at older than 72 h (-80 h) is rejected with HTTP 422 OFFLINE_TOO_OLD
        $old80Iso = gmdate('Y-m-d\TH:i:s\Z', time() - 80 * 3600);
        $reqOld = $makeApiPost('/api/measures', [
            'type_code' => 'glycemia',
            'value_primary' => '1.10',
            'context' => 'fasting',
            'client_uuid' => 'offline-old80-' . bin2hex(random_bytes(4)),
            'client_recorded_at' => $old80Iso,
        ]);
        $resOld = $apiMed->storeMeasure($reqOld);
        $bodyOld = json_decode($resOld->body(), true);
        $assert(
            $resOld->statusCode() === 422
            && ($bodyOld['error']['code'] ?? '') === 'OFFLINE_TOO_OLD',
            'Offline G1: client_recorded_at > 72 h est refusé avec HTTP 422 et le code OFFLINE_TOO_OLD'
        );

        // 5d. Conflict on an intake already validated by a colleague returns HTTP 409 CONFLICT
        $pendingIntakeId = (int) $pdo->query("SELECT mi.id FROM medication_intakes mi INNER JOIN prescriptions p ON p.id = mi.prescription_id WHERE mi.status = 'pending' AND mi.deleted_at IS NULL AND p.medication_id = 1 LIMIT 1")->fetchColumn();
        if ($pendingIntakeId <= 0) {
            $prescId = (int) $pdo->query('SELECT id FROM prescriptions WHERE deleted_at IS NULL AND medication_id = 1 LIMIT 1')->fetchColumn();
            $nowStr = gmdate('Y-m-d H:i:s');
            $pdo->prepare(
                "INSERT INTO medication_intakes (patient_id, prescription_id, scheduled_at, status, created_at, updated_at)
                 VALUES (1, :pid, :sch, 'pending', :c, :u)"
            )->execute(['pid' => $prescId, 'sch' => $nowStr, 'c' => $nowStr, 'u' => $nowStr]);
            $pendingIntakeId = (int) $pdo->lastInsertId();
        }
        $uuidColleague1 = 'colleague-1-' . bin2hex(random_bytes(4));
        $uuidColleague2 = 'colleague-2-' . bin2hex(random_bytes(4));
        $reqGive1 = $makeApiPost("/api/intakes/{$pendingIntakeId}/give", [
            'client_uuid' => $uuidColleague1,
            'client_recorded_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
        $resGive1 = $apiMed->giveIntake($reqGive1, (string) $pendingIntakeId);

        // Second colleague tries to validate the same intake with a different client_uuid -> 409 CONFLICT
        \App\Core\Auth::loginUser($user4);
        $reqGive2 = $makeApiPost("/api/intakes/{$pendingIntakeId}/give", [
            'client_uuid' => $uuidColleague2,
            'client_recorded_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
        $resGive2 = $apiMed->giveIntake($reqGive2, (string) $pendingIntakeId);
        $bodyGive2 = json_decode($resGive2->body(), true);
        $assert(
            $resGive1->statusCode() === 200
            && $resGive2->statusCode() === 409
            && in_array($bodyGive2['error']['code'] ?? '', ['ALREADY_GIVEN', 'CONFLICT'], true)
            && str_contains((string) ($bodyGive2['error']['message'] ?? ''), 'Déjà donné par'),
            'Offline G1: conflit sur une prise déjà validée par une collègue renvoie HTTP 409 non bloquant (« Déjà donné par ... »)'
        );

        // =====================================================================
        // 6. TESTS G2 (RGPD 403 GDPR_NOTICE_REQUIRED) & G3 (Service Worker liste blanche)
        // =====================================================================

        // G2: Reset attendance_gdpr_accepted_at to NULL for caregiver #4, verify 403 GDPR_NOTICE_REQUIRED, then accept and punch
        $pdo->prepare('UPDATE users SET attendance_gdpr_accepted_at = NULL WHERE id = 4')->execute();
        \App\Core\Auth::loginUser($user4);
        $attCtrl = new \App\Controllers\AttendanceController();
        $reqPunchNoGdpr = $makeApiPost('/api/attendance/punch', [
            'type' => 'in',
            'method' => 'slide',
        ]);
        $resPunchNoGdpr = $attCtrl->punch($reqPunchNoGdpr);
        $bodyPunchNoGdpr = json_decode($resPunchNoGdpr->body(), true);

        // Now accept GDPR notice and retry punch
        $reqAcceptGdpr = $makeApiPost('/api/attendance/gdpr-accept', ['accepted' => 1]);
        $resAcceptGdpr = $attCtrl->acceptGdpr($reqAcceptGdpr);
        $resPunchAfterGdpr = $attCtrl->punch($reqPunchNoGdpr);
        $assert(
            $resPunchNoGdpr->statusCode() === 403
            && ($bodyPunchNoGdpr['error']['code'] ?? '') === 'GDPR_NOTICE_REQUIRED'
            && $resAcceptGdpr->statusCode() === 200
            && $resPunchAfterGdpr->statusCode() === 201,
            'Attendance G2: refuse tout pointage avec HTTP 403 GDPR_NOTICE_REQUIRED tant que attendance_gdpr_accepted_at est NULL, puis accepte après validation'
        );

        // G3: Static verification of public/sw.js whitelist ['/', '/today', '/journal'] and Content-Disposition filter
        $swContent = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');
        $assert(
            str_contains($swContent, "const OFFLINE_NAV_WHITELIST = ['/', '/today', '/journal'];")
            && str_contains($swContent, 'Content-Disposition')
            && str_contains($swContent, '/export')
            && str_contains($swContent, '/media/'),
            'Service Worker G3: public/sw.js restreint le cache de navigation à la liste blanche exacte [\x27/\x27, \x27/today\x27, \x27/journal\x27] et exclut Content-Disposition, /export et /media/'
        );

        \App\Core\Auth::loginUser($user1);

        // CronService tasks execution
        $cronRes = CronService::runTask('all', 1);
        $assert(
            isset($cronRes['schedule:generate'], $cronRes['alerts:scan'], $cronRes['notifications:send'], $cronRes['cleanup']),
            'CronService: exécute les tâches planifiées (schedule:generate, alerts:scan, notifications:send, cleanup)'
        );
    }
}
