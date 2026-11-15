<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Config;
use App\Core\Database;
use App\Services\AlertService;
use App\Services\AttendanceService;
use App\Services\PlanningService;
use App\Services\WebPushService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Phase3Seeder — Données de démonstration Phase 3 :
 * - Modèles de créneaux (Garde de jour 08h00–20h00, Garde de nuit 20h00–08h00, Demi-journée 08h00–14h00)
 * - Planning complet sur 4 semaines passées et 2 semaines à venir (Fatou, Marie, Sofia)
 * - 1 créneau non couvert dans les prochaines 36 h, 1 absence déclarée, 1 remplacement tracé
 * - Pointages réalistes sur 4 semaines (à l'heure, retards, 1 oubli déclaré en attente de validation, 1 hors zone)
 * - Alertes de chaque type et de chaque statut (new, seen, handled)
 * - Coordonnées GPS fictives du domicile sur la fiche patient & jeton QR code HMAC actif.
 */
final class Phase3Seeder
{
    public static function run(): void
    {
        $pdo = Database::connection();
        $localTz = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
        $utcTz = new DateTimeZone('UTC');
        $nowUtcDt = new DateTimeImmutable('now', $utcTz);
        $nowLocalDt = $nowUtcDt->setTimezone($localTz);
        $nowUtc = $nowUtcDt->format('Y-m-d H:i:s');

        // Clean existing Phase 3 tables in reverse FK order
        $pdo->exec('DELETE FROM notification_queue');
        $pdo->exec('DELETE FROM push_subscriptions');
        $pdo->exec('DELETE FROM notification_settings');
        $pdo->exec('DELETE FROM alerts');
        $pdo->exec('DELETE FROM attendances');
        $pdo->exec('DELETE FROM attendance_qr_tokens');
        $pdo->exec('DELETE FROM absences');
        $pdo->exec('DELETE FROM shifts');
        $pdo->exec('DELETE FROM shift_templates');

        // 1. Update patient fictional GPS coordinates (18 avenue de la République, 94000 Créteil)
        $homeLat = 48.7903600;
        $homeLon = 2.4555700;
        $pdo->prepare(
            'UPDATE patients SET home_latitude = :lat, home_longitude = :lon, home_radius_m = 150, updated_at = :now WHERE id = 1'
        )->execute([
            'lat' => $homeLat,
            'lon' => $homeLon,
            'now' => $nowUtc,
        ]);

        // Ensure GDPR accepted on demo caregivers so punch works immediately while still testable
        $pdo->prepare('UPDATE users SET attendance_gdpr_accepted_at = :now WHERE id IN (2, 3, 4)')->execute(['now' => $nowUtc]);

        // Save default attendance settings (GPS mode, 150m radius, require_clocked_in = false by default)
        AttendanceService::updateSettings([
            'verification_mode' => 'gps',
            'radius_m' => 150,
            'require_clocked_in' => false,
            'home_latitude' => $homeLat,
            'home_longitude' => $homeLon,
        ], 1, 1);

        // Generate active QR code token for the home and VAPID keys
        AttendanceService::regenerateQrToken(1, 1, 'Domicile M. Henri Benali — Entrée principale');
        WebPushService::getOrCreateVapidKeys();

        // 2. Seed shift templates
        $tplStmt = $pdo->prepare(
            'INSERT INTO shift_templates
             (patient_id, code, label, start_time, end_time, spans_next_day, duration_minutes, color_class, is_active, sort_order, created_at, updated_at, created_by)
             VALUES (1, :code, :label, :st, :et, :spans, :dur, :cc, 1, :sort, :c_at, :u_at, 1)'
        );

        $templates = [
            ['code' => 'day_12h', 'label' => 'Garde de jour (08h–20h)', 'st' => '08:00', 'et' => '20:00', 'spans' => 0, 'dur' => 720, 'cc' => 'day', 'sort' => 10],
            ['code' => 'night_12h', 'label' => 'Garde de nuit (20h–08h)', 'st' => '20:00', 'et' => '08:00', 'spans' => 1, 'dur' => 720, 'cc' => 'night', 'sort' => 20],
            ['code' => 'morning_6h', 'label' => 'Matinée renfort (08h–14h)', 'st' => '08:00', 'et' => '14:00', 'spans' => 0, 'dur' => 360, 'cc' => 'morning', 'sort' => 30],
        ];

        $tplIds = [];
        foreach ($templates as $t) {
            $tplStmt->execute([
                'code' => $t['code'],
                'label' => $t['label'],
                'st' => $t['st'],
                'et' => $t['et'],
                'spans' => $t['spans'],
                'dur' => $t['dur'],
                'cc' => $t['cc'],
                'sort' => $t['sort'],
                'c_at' => $nowUtc,
                'u_at' => $nowUtc,
            ]);
            $tplIds[$t['code']] = (int) $pdo->lastInsertId();
        }

        // 3. Seed shifts over past 28 days (-28..-1), today (0), and next 14 days (+1..+14)
        // Team IDs: 3 = Fatou Diallo (caregiver), 4 = Marie Lefèvre (caregiver), 2 = Sofia Marchetti (coordinator)
        $todayLocalMidnight = new DateTimeImmutable($nowLocalDt->format('Y-m-d') . ' 00:00:00', $localTz);

        $shiftIns = $pdo->prepare(
            'INSERT INTO shifts
             (patient_id, template_id, user_id, replaced_user_id, start_at, end_at, status, notes, created_at, updated_at, created_by)
             VALUES (1, :tid, :uid, :ruid, :start_at, :end_at, :status, :notes, :c_at, :u_at, 1)'
        );

        $attIns = $pdo->prepare(
            'INSERT INTO attendances
             (patient_id, user_id, shift_id, type, recorded_at, declared_at, method,
              latitude, longitude, accuracy_m, distance_m, ip, status, validated_by, validated_at, reason, created_at, updated_at, created_by)
             VALUES
             (1, :uid, :sid, :type, :rec_at, :dec_at, :method,
              :lat, :lon, :acc, :dist, :ip, :status, :vby, :vat, :reason, :c_at, :u_at, :by)'
        );

        $uncoveredShiftId = null;
        $UncoveredStartLocal = null;
        $pendingForgotAttId = null;
        $pendingOutZoneAttId = null;

        // Rotation pattern by day offset
        $dayCycle = [3, 3, 4, 3, 2, 4, 4];   // Day shifts: Fatou, Marie, Sofia
        $nightCycle = [4, 4, 2, 4, 3, 2, 3]; // Night shifts: Marie, Sofia, Fatou

        for ($offset = -28; $offset <= 14; $offset++) {
            $dayDt = $todayLocalMidnight->modify(($offset >= 0 ? "+{$offset}" : (string) $offset) . ' days');
            $ymd = $dayDt->format('Y-m-d');
            $cycleIdx = (($offset % 7) + 7) % 7;

            $dayUserId = $dayCycle[$cycleIdx];
            $nightUserId = $nightCycle[$cycleIdx];

            // Ensure no same-user overlap between previous night (ending 08:00) and day (starting 08:00 is fine: end == start)
            $dayPair = PlanningService::localDateTimesToUtc($ymd, '08:00', '20:00');
            $nightPair = PlanningService::localDateTimesToUtc($ymd, '20:00', '08:00');

            // Make sure today's active shift is assigned to Fatou (ID 3) so Fatou's home screen shows her active/upcoming shift
            if ($offset === 0) {
                $dayUserId = 3;
                $nightUserId = 4;
            }

            // Replacement example on offset +2 day shift: Fatou (3) replaces Marie (4)
            $dayReplacedUser = ($offset === 2) ? 4 : null;
            if ($offset === 2) {
                $dayUserId = 3;
            }

            // Uncovered shift within the next 36 hours: tomorrow's night shift (offset +1 at 20:00 local)
            $isUncoveredNight = ($offset === 1);
            $effectiveNightUser = $isUncoveredNight ? null : $nightUserId;

            // Insert Day Shift (08:00 - 20:00)
            $shiftIns->execute([
                'tid' => $tplIds['day_12h'],
                'uid' => $dayUserId,
                'ruid' => $dayReplacedUser,
                'start_at' => $dayPair['start_utc'],
                'end_at' => $dayPair['end_utc'],
                'status' => $offset <= 2 ? 'confirmed' : 'planned',
                'notes' => $dayReplacedUser !== null ? 'Remplacement suite à indisponibilité de Marie' : null,
                'c_at' => $nowUtc,
                'u_at' => $nowUtc,
            ]);
            $dayShiftId = (int) $pdo->lastInsertId();

            // Insert Night Shift (20:00 - 08:00+1)
            $shiftIns->execute([
                'tid' => $tplIds['night_12h'],
                'uid' => $effectiveNightUser,
                'ruid' => null,
                'start_at' => $nightPair['start_utc'],
                'end_at' => $nightPair['end_utc'],
                'status' => $isUncoveredNight ? 'planned' : ($offset <= 2 ? 'confirmed' : 'planned'),
                'notes' => $isUncoveredNight ? 'Créneau de nuit non couvert — recherche remplaçante' : null,
                'c_at' => $nowUtc,
                'u_at' => $nowUtc,
            ]);
            $nightShiftId = (int) $pdo->lastInsertId();

            if ($isUncoveredNight) {
                $uncoveredShiftId = $nightShiftId;
                $UncoveredStartLocal = $dayDt;
            }

            // Seed realistic attendances for past days ($offset < 0)
            if ($offset < 0) {
                // Day shift attendances
                $dayStartTs = (new DateTimeImmutable($dayPair['start_utc'], $utcTz))->getTimestamp();
                $dayEndTs = (new DateTimeImmutable($dayPair['end_utc'], $utcTz))->getTimestamp();

                if ($offset === -1) {
                    // Yesterday day shift: declared forgot-punch on arrival (pending validation!)
                    $declaredInUtc = gmdate('Y-m-d H:i:s', $dayStartTs);
                    $recordedInUtc = gmdate('Y-m-d H:i:s', $dayStartTs + 3 * 3600);
                    $attIns->execute([
                        'uid' => $dayUserId,
                        'sid' => $dayShiftId,
                        'type' => 'in',
                        'rec_at' => $recordedInUtc,
                        'dec_at' => $declaredInUtc,
                        'method' => 'declared',
                        'lat' => null,
                        'lon' => null,
                        'acc' => null,
                        'dist' => null,
                        'ip' => '192.168.1.24',
                        'status' => 'pending',
                        'vby' => null,
                        'vat' => null,
                        'reason' => 'Oubli de pointer à 08h00 (prise en charge immédiate de M. Benali au réveil)',
                        'c_at' => $recordedInUtc,
                        'u_at' => $recordedInUtc,
                        'by' => $dayUserId,
                    ]);
                    $pendingForgotAttId = (int) $pdo->lastInsertId();

                    $outUtc = gmdate('Y-m-d H:i:s', $dayEndTs + 120);
                    $attIns->execute([
                        'uid' => $dayUserId,
                        'sid' => $dayShiftId,
                        'type' => 'out',
                        'rec_at' => $outUtc,
                        'dec_at' => null,
                        'method' => 'slide',
                        'lat' => $homeLat + 0.0001,
                        'lon' => $homeLon + 0.0001,
                        'acc' => 14.0,
                        'dist' => 18,
                        'ip' => '192.168.1.24',
                        'status' => 'valid',
                        'vby' => null,
                        'vat' => null,
                        'reason' => null,
                        'c_at' => $outUtc,
                        'u_at' => $outUtc,
                        'by' => $dayUserId,
                    ]);
                } elseif ($offset === -2) {
                    // 2 days ago day shift: out-of-radius GPS punch (320 m -> pending validation)
                    $inUtc = gmdate('Y-m-d H:i:s', $dayStartTs - 60);
                    $attIns->execute([
                        'uid' => $dayUserId,
                        'sid' => $dayShiftId,
                        'type' => 'in',
                        'rec_at' => $inUtc,
                        'dec_at' => null,
                        'method' => 'slide',
                        'lat' => $homeLat + 0.00285,
                        'lon' => $homeLon + 0.00040,
                        'acc' => 25.0,
                        'dist' => 320,
                        'ip' => '192.168.1.25',
                        'status' => 'pending',
                        'vby' => null,
                        'vat' => null,
                        'reason' => 'Hors du domicile (320 m)',
                        'c_at' => $inUtc,
                        'u_at' => $inUtc,
                        'by' => $dayUserId,
                    ]);
                    $pendingOutZoneAttId = (int) $pdo->lastInsertId();

                    $outUtc = gmdate('Y-m-d H:i:s', $dayEndTs + 60);
                    $attIns->execute([
                        'uid' => $dayUserId,
                        'sid' => $dayShiftId,
                        'type' => 'out',
                        'rec_at' => $outUtc,
                        'dec_at' => null,
                        'method' => 'slide',
                        'lat' => $homeLat,
                        'lon' => $homeLon,
                        'acc' => 12.0,
                        'dist' => 12,
                        'ip' => '192.168.1.25',
                        'status' => 'valid',
                        'vby' => null,
                        'vat' => null,
                        'reason' => null,
                        'c_at' => $outUtc,
                        'u_at' => $outUtc,
                        'by' => $dayUserId,
                    ]);
                } else {
                    // Regular past day shifts: mostly on time, occasional 18-minute delay on offset -4 & -11
                    $delaySec = ($offset === -4 || $offset === -11) ? (18 * 60) : (-2 * 60);
                    $inUtc = gmdate('Y-m-d H:i:s', $dayStartTs + $delaySec);
                    $outUtc = gmdate('Y-m-d H:i:s', $dayEndTs + 3 * 60);

                    $attIns->execute([
                        'uid' => $dayUserId,
                        'sid' => $dayShiftId,
                        'type' => 'in',
                        'rec_at' => $inUtc,
                        'dec_at' => null,
                        'method' => ($offset % 3 === 0) ? 'qr' : 'slide',
                        'lat' => $homeLat + 0.00012,
                        'lon' => $homeLon - 0.00010,
                        'acc' => 15.0,
                        'dist' => 22,
                        'ip' => '192.168.1.20',
                        'status' => 'valid',
                        'vby' => null,
                        'vat' => null,
                        'reason' => null,
                        'c_at' => $inUtc,
                        'u_at' => $inUtc,
                        'by' => $dayUserId,
                    ]);

                    $attIns->execute([
                        'uid' => $dayUserId,
                        'sid' => $dayShiftId,
                        'type' => 'out',
                        'rec_at' => $outUtc,
                        'dec_at' => null,
                        'method' => 'slide',
                        'lat' => $homeLat + 0.00010,
                        'lon' => $homeLon - 0.00008,
                        'acc' => 15.0,
                        'dist' => 19,
                        'ip' => '192.168.1.20',
                        'status' => 'valid',
                        'vby' => null,
                        'vat' => null,
                        'reason' => null,
                        'c_at' => $outUtc,
                        'u_at' => $outUtc,
                        'by' => $dayUserId,
                    ]);
                }

                // Night shift attendances (for completed night shifts)
                $nightStartTs = (new DateTimeImmutable($nightPair['start_utc'], $utcTz))->getTimestamp();
                $nightEndTs = (new DateTimeImmutable($nightPair['end_utc'], $utcTz))->getTimestamp();
                if ($nightEndTs <= $nowUtcDt->getTimestamp()) {
                    $nInUtc = gmdate('Y-m-d H:i:s', $nightStartTs - 3 * 60);
                    $nOutUtc = gmdate('Y-m-d H:i:s', $nightEndTs + 2 * 60);

                    $attIns->execute([
                        'uid' => $nightUserId,
                        'sid' => $nightShiftId,
                        'type' => 'in',
                        'rec_at' => $nInUtc,
                        'dec_at' => null,
                        'method' => 'slide',
                        'lat' => $homeLat + 0.00008,
                        'lon' => $homeLon + 0.00009,
                        'acc' => 16.0,
                        'dist' => 15,
                        'ip' => '192.168.1.21',
                        'status' => 'valid',
                        'vby' => null,
                        'vat' => null,
                        'reason' => null,
                        'c_at' => $nInUtc,
                        'u_at' => $nInUtc,
                        'by' => $nightUserId,
                    ]);

                    $attIns->execute([
                        'uid' => $nightUserId,
                        'sid' => $nightShiftId,
                        'type' => 'out',
                        'rec_at' => $nOutUtc,
                        'dec_at' => null,
                        'method' => 'slide',
                        'lat' => $homeLat + 0.00008,
                        'lon' => $homeLon + 0.00009,
                        'acc' => 16.0,
                        'dist' => 15,
                        'ip' => '192.168.1.21',
                        'status' => 'valid',
                        'vby' => null,
                        'vat' => null,
                        'reason' => null,
                        'c_at' => $nOutUtc,
                        'u_at' => $nOutUtc,
                        'by' => $nightUserId,
                    ]);
                }
            }
        }

        // 4. Seed 1 declared absence (Marie Lefèvre ID 4 on +4..+5 days -> marks her shifts on those days as "À remplacer")
        $absStart = $todayLocalMidnight->modify('+4 days')->format('Y-m-d');
        $absEnd = $todayLocalMidnight->modify('+5 days')->format('Y-m-d');
        $pdo->prepare(
            "INSERT INTO absences
             (patient_id, user_id, absence_type, start_date, end_date, comment, created_at, updated_at, created_by)
             VALUES (1, 4, 'conge', :sd, :ed, 'Congé familial programmé (2 jours)', :c_at, :u_at, 1)"
        )->execute([
            'sd' => $absStart,
            'ed' => $absEnd,
            'c_at' => $nowUtc,
            'u_at' => $nowUtc,
        ]);

        // 5. Seed default notification settings for all 5 users
        for ($uid = 1; $uid <= 5; $uid++) {
            AlertService::getUserNotificationSettings($uid);
        }

        // 6. Seed realistic alerts of every type and every status (new, seen, handled)
        $alertIns = $pdo->prepare(
            'INSERT INTO alerts
             (patient_id, type, level, source_type, source_id, dedupe_key, open_dedupe_key,
              title, message, target_url, status, escalated_at, seen_at, seen_by,
              handled_by, handled_at, resolution, created_at, updated_at, created_by)
             VALUES
             (1, :type, :level, :st, :sid, :dk, :odk,
              :title, :msg, :url, :status, :esc, :seen_at, :seen_by,
              :hby, :hat, :res, :c_at, :u_at, :by)'
        );

        $uncovDateLabel = $UncoveredStartLocal !== null ? $UncoveredStartLocal->format('d/m à 20h00') : 'demain à 20h00';
        $uncovWeekParam = $UncoveredStartLocal !== null ? $UncoveredStartLocal->format('Y-m-d') : $todayLocalMidnight->format('Y-m-d');

        $demoAlerts = [
            // 1. Open NEW critical alert (vital_critical)
            [
                'type' => 'vital_critical',
                'level' => 'critical',
                'st' => 'measure',
                'sid' => 1,
                'dk' => 'vital_critical:1:seed_hypo',
                'odk' => 'vital_critical:1:seed_hypo',
                'title' => 'Glycémie critique : 0,66 g/L (Hypoglycémie)',
                'msg' => 'Glycémie capillaire mesurée à 0,66 g/L (seuil critique < 0,70 g/L). Resucrage immédiat 15 g et recontrôle à 15 min.',
                'url' => '/health/glycemia',
                'status' => 'new',
                'esc' => null,
                'seen_at' => null,
                'seen_by' => null,
                'hby' => null,
                'hat' => null,
                'res' => null,
                'c_at' => $nowUtcDt->modify('-25 minutes')->format('Y-m-d H:i:s'),
                'by' => 3,
            ],
            // 2. Open NEW warning alert (shift_uncovered in < 48h)
            [
                'type' => 'shift_uncovered',
                'level' => 'warning',
                'st' => 'shift',
                'sid' => $uncoveredShiftId,
                'dk' => 'shift_uncovered:' . ($uncoveredShiftId ?? 999),
                'odk' => 'shift_uncovered:' . ($uncoveredShiftId ?? 999),
                'title' => 'Créneau non couvert le ' . $uncovDateLabel,
                'msg' => 'Aucune aide-soignante n’est affectée à la garde de nuit (20h00 – 08h00) débutant dans moins de 36 heures.',
                'url' => '/planning?week=' . $uncovWeekParam,
                'status' => 'new',
                'esc' => null,
                'seen_at' => null,
                'seen_by' => null,
                'hby' => null,
                'hat' => null,
                'res' => null,
                'c_at' => $nowUtcDt->modify('-40 minutes')->format('Y-m-d H:i:s'),
                'by' => 1,
            ],
            // 3. Open NEW info alert (attendance_pending — forgot punch)
            [
                'type' => 'attendance_pending',
                'level' => 'info',
                'st' => 'attendance',
                'sid' => $pendingForgotAttId,
                'dk' => 'attendance_pending:' . ($pendingForgotAttId ?? 998),
                'odk' => 'attendance_pending:' . ($pendingForgotAttId ?? 998),
                'title' => 'Oubli de pointage déclaré à valider',
                'msg' => 'Pointage d’arrivée déclaré à 08h00 en attente de validation par le responsable ou la coordinatrice.',
                'url' => '/attendance',
                'status' => 'new',
                'esc' => null,
                'seen_at' => null,
                'seen_by' => null,
                'hby' => null,
                'hat' => null,
                'res' => null,
                'c_at' => $nowUtcDt->modify('-2 hours')->format('Y-m-d H:i:s'),
                'by' => 3,
            ],
            // 4. Open SEEN warning alert (intake_late)
            [
                'type' => 'intake_late',
                'level' => 'warning',
                'st' => 'medication_intake',
                'sid' => 1,
                'dk' => 'intake_late:seed_demo_1',
                'odk' => 'intake_late:seed_demo_1',
                'title' => 'Prise en retard : Amlodipine 5 mg (08h00)',
                'msg' => 'La prise matinale d’Amlodipine (1 gélule) a dépassé la fenêtre de 30 minutes.',
                'url' => '/treatments',
                'status' => 'seen',
                'esc' => $nowUtcDt->modify('-50 minutes')->format('Y-m-d H:i:s'),
                'seen_at' => $nowUtcDt->modify('-45 minutes')->format('Y-m-d H:i:s'),
                'seen_by' => 1,
                'hby' => null,
                'hat' => null,
                'res' => null,
                'c_at' => $nowUtcDt->modify('-70 minutes')->format('Y-m-d H:i:s'),
                'by' => 1,
            ],
            // 5. Handled alert (vital_out_of_range)
            [
                'type' => 'vital_out_of_range',
                'level' => 'warning',
                'st' => 'measure',
                'sid' => 2,
                'dk' => 'vital_warn:2:seed_bp',
                'odk' => null,
                'title' => 'Tension artérielle hors cible : 148/92 mmHg',
                'msg' => 'Tension matinale légèrement élevée (148/92 mmHg). Repos au fauteuil 15 min puis contrôle.',
                'url' => '/health/blood_pressure',
                'status' => 'handled',
                'esc' => null,
                'seen_at' => $nowUtcDt->modify('-1 day')->format('Y-m-d H:i:s'),
                'seen_by' => 1,
                'hby' => 1,
                'hat' => $nowUtcDt->modify('-23 hours')->format('Y-m-d H:i:s'),
                'res' => 'Vérifié — tension redescendue à 132/78 mmHg après repos',
                'c_at' => $nowUtcDt->modify('-1 day')->format('Y-m-d H:i:s'),
                'by' => 3,
            ],
            // 6. Handled alert (measure_late — auto-resolved)
            [
                'type' => 'measure_late',
                'level' => 'warning',
                'st' => 'measure_expectation',
                'sid' => 1,
                'dk' => 'measure_late:seed_exp_1',
                'odk' => null,
                'title' => 'Mesure attendue en retard : Glycémie (11h30)',
                'msg' => 'La glycémie pré-prandiale de 11h30 avait plus de 30 minutes de retard.',
                'url' => '/health/glycemia',
                'status' => 'handled',
                'esc' => null,
                'seen_at' => $nowUtcDt->modify('-2 days')->format('Y-m-d H:i:s'),
                'seen_by' => 2,
                'hby' => 3,
                'hat' => $nowUtcDt->modify('-2 days +15 minutes')->format('Y-m-d H:i:s'),
                'res' => 'Résolue automatiquement (mesure saisie)',
                'c_at' => $nowUtcDt->modify('-2 days')->format('Y-m-d H:i:s'),
                'by' => null,
            ],
            // 7. Handled alert (attendance_missing)
            [
                'type' => 'attendance_missing',
                'level' => 'critical',
                'st' => 'shift',
                'sid' => 1,
                'dk' => 'attendance_missing:seed_shift_1',
                'odk' => null,
                'title' => 'Pointage d’arrivée absent : Marie Lefèvre (08h00)',
                'msg' => 'Retard de pointage de 18 minutes signalé sur la garde de jour.',
                'url' => '/attendance',
                'status' => 'handled',
                'esc' => null,
                'seen_at' => $nowUtcDt->modify('-4 days')->format('Y-m-d H:i:s'),
                'seen_by' => 2,
                'hby' => 2,
                'hat' => $nowUtcDt->modify('-4 days +5 minutes')->format('Y-m-d H:i:s'),
                'res' => 'Appelée — retard transports en commun (arrivée à 08h18)',
                'c_at' => $nowUtcDt->modify('-4 days')->format('Y-m-d H:i:s'),
                'by' => null,
            ],
        ];

        foreach ($demoAlerts as $al) {
            $alertIns->execute([
                'type' => $al['type'],
                'level' => $al['level'],
                'st' => $al['st'],
                'sid' => $al['sid'],
                'dk' => $al['dk'],
                'odk' => $al['odk'],
                'title' => $al['title'],
                'msg' => $al['msg'],
                'url' => $al['url'],
                'status' => $al['status'],
                'esc' => $al['esc'],
                'seen_at' => $al['seen_at'],
                'seen_by' => $al['seen_by'],
                'hby' => $al['hby'],
                'hat' => $al['hat'],
                'res' => $al['res'],
                'c_at' => $al['c_at'],
                'u_at' => $al['c_at'],
                'by' => $al['by'],
            ]);
        }
    }
}
