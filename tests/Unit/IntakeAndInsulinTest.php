<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\InsulinService;
use App\Services\IntakeService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class IntakeAndInsulinTest
{
    public static function run(callable $assert): void
    {
        $pdo = Database::connection();
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // 1. Dynamic intake status calculation (upcoming / due ±30m / late / given / skipped)
        $in2Hours = $nowUtc->modify('+120 minutes')->format('Y-m-d H:i:s');
        $in10Min = $nowUtc->modify('+10 minutes')->format('Y-m-d H:i:s');
        $ago45Min = $nowUtc->modify('-45 minutes')->format('Y-m-d H:i:s');

        $stUpcoming = IntakeService::computeDisplayStatus(['status' => 'pending', 'scheduled_at' => $in2Hours], $nowUtc);
        $stDue = IntakeService::computeDisplayStatus(['status' => 'pending', 'scheduled_at' => $in10Min], $nowUtc);
        $stLate = IntakeService::computeDisplayStatus(['status' => 'pending', 'scheduled_at' => $ago45Min], $nowUtc);
        $stGiven = IntakeService::computeDisplayStatus(['status' => 'given', 'scheduled_at' => $ago45Min], $nowUtc);

        $assert($stUpcoming['display_status'] === 'upcoming', 'IntakeService: prise dans +2h a le statut upcoming (À venir)');
        $assert($stDue['display_status'] === 'due', 'IntakeService: prise dans ±30 min a le statut due (À donner)');
        $assert($stLate['display_status'] === 'late', 'IntakeService: prise dépassée de >30 min a le statut late (En retard)');
        $assert($stGiven['display_status'] === 'given', 'IntakeService: prise validée a le statut given (Donné)');

        // 2. InsulinService: reads the admin's correction table without calculating formulas
        $table = [
            ['min' => 0.00, 'max' => 0.70, 'dose' => 0, 'note' => 'Hypo'],
            ['min' => 0.70, 'max' => 1.30, 'dose' => 4, 'note' => 'Base'],
            ['min' => 1.31, 'max' => 1.80, 'dose' => 6, 'note' => '+2 UI'],
            ['min' => 1.81, 'max' => 2.50, 'dose' => 8, 'note' => '+4 UI'],
            ['min' => 2.51, 'max' => 6.00, 'dose' => 10, 'note' => '+6 UI'],
        ];
        $lookup = InsulinService::lookupDoseFromTable($table, 1.52);
        $assert(
            $lookup['matched'] === true
            && $lookup['dose'] === 6.0
            && str_contains($lookup['label'], 'Dose selon le tableau du responsable'),
            'InsulinService: lit 6 UI pour 1,52 g/L avec mention « Dose selon le tableau du responsable »'
        );

        // 3. Injection site rotation suggestion
        $rot = InsulinService::getRotationSuggestion(1);
        $assert(
            isset($rot['suggested_code'], $rot['suggested_label']) && $rot['suggested_code'] !== $rot['last_code'],
            'InsulinService: suggère le site d’injection suivant dans la rotation'
        );

        // 4. Anti-double-give protection (HTTP 409 CONFLICT "Déjà donné par Fatou à ...")
        // Find or create a pending fixed-dose intake
        $pendingStmt = $pdo->query(
            'SELECT mi.id FROM `medication_intakes` mi
             INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
             WHERE mi.status = \'pending\' AND p.dose_mode = \'fixe\' AND mi.deleted_at IS NULL
             ORDER BY mi.id DESC LIMIT 1'
        );
        $intakeId = (int) $pendingStmt->fetchColumn();

        $give1 = IntakeService::giveIntake($intakeId, 3, ['comment' => 'Prise du matin']); // Fatou (id=3)
        $assert($give1['ok'] === true && $give1['http_status'] === 200, 'IntakeService: première validation réussit (200)');

        // Second caregiver (Marie id=4) attempts to give the exact same intake
        $give2 = IntakeService::giveIntake($intakeId, 4, []);
        $assert(
            $give2['ok'] === false
            && $give2['http_status'] === 409
            && str_contains($give2['message'], 'Déjà donné par Fatou'),
            'IntakeService: anti-double-prise retourne HTTP 409 « Déjà donné par Fatou à ... »'
        );

        // 5. Variable insulin requires associated glycemia
        $varStmt = $pdo->query(
            'SELECT mi.id FROM `medication_intakes` mi
             INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
             WHERE mi.status = \'pending\' AND p.dose_mode = \'tableau\' AND mi.deleted_at IS NULL
             ORDER BY mi.id DESC LIMIT 1'
        );
        $varIntakeId = (int) $varStmt->fetchColumn();
        if ($varIntakeId > 0) {
            $missingGly = IntakeService::giveIntake($varIntakeId, 3, []);
            $assert(
                $missingGly['ok'] === false && $missingGly['code'] === 'GLYCEMIA_REQUIRED' && $missingGly['http_status'] === 422,
                'IntakeService: insuline à dose variable sans glycémie associée est refusée (422 GLYCEMIA_REQUIRED)'
            );

            $withGly = IntakeService::giveIntake($varIntakeId, 3, [
                'glycemia_value' => 1.45,
                'injection_site' => 'abdomen_g',
            ]);
            $assert(
                $withGly['ok'] === true && (float) ($withGly['data']['dose_given'] ?? 0) === 6.0,
                'IntakeService: insuline à dose variable avec glycémie 1,45 g/L valide 6 UI selon le tableau'
            );
        }

        // 6. Skip intake requires a mandatory reason
        $skipStmt = $pdo->query(
            'SELECT mi.id FROM `medication_intakes` mi
             WHERE mi.status = \'pending\' AND mi.deleted_at IS NULL
             ORDER BY mi.id DESC LIMIT 1'
        );
        $skipIntakeId = (int) $skipStmt->fetchColumn();
        if ($skipIntakeId > 0) {
            $noReason = IntakeService::skipIntake($skipIntakeId, 3, '', '');
            $assert(
                $noReason['ok'] === false
                && $noReason['http_status'] === 422
                && ($noReason['code'] ?? '') === 'REASON_REQUIRED',
                'E2 IntakeService: marquer « Non donné » sans motif retourne 422 REASON_REQUIRED'
            );

            $validSkip = IntakeService::skipIntake($skipIntakeId, 3, 'refus_patient', 'Nausée passagère');
            $assert($validSkip['ok'] === true, 'IntakeService: marquer « Non pris » avec motif valide réussit');
        }

        // 7. E2: Prise « si besoin » (seconde prise avant min_interval_h retourne 422 MIN_INTERVAL_VIOLATION + heure prochaine prise)
        $asNeededRxId = (int) $pdo->query(
            'SELECT `id` FROM `prescriptions` WHERE `as_needed` = 1 AND `is_active` = 1 AND `deleted_at` IS NULL LIMIT 1'
        )->fetchColumn();

        if ($asNeededRxId > 0) {
            $t1Utc = '2026-11-14 10:00:00'; // 11h00 Paris
            $firstAsNeeded = IntakeService::giveAsNeeded($asNeededRxId, 3, [
                'reason' => 'Douleur genou',
                'comment' => 'Première prise',
            ], $t1Utc);
            $assert(
                $firstAsNeeded['ok'] === true && $firstAsNeeded['http_status'] === 201,
                'E2 Prise « si besoin »: première prise de Paracétamol 1 g acceptée (201)'
            );

            // Second attempt 2 hours later (12:00 UTC = 13h00 Paris), before min_interval_h = 6h (next allowed: 16:00 UTC = 17h00 Paris)
            $t2Utc = '2026-11-14 12:00:00';
            $secondAsNeeded = IntakeService::giveAsNeeded($asNeededRxId, 3, [
                'reason' => 'Douleur persistante',
            ], $t2Utc);
            $assert(
                $secondAsNeeded['ok'] === false
                && $secondAsNeeded['http_status'] === 422
                && ($secondAsNeeded['code'] ?? '') === 'MIN_INTERVAL_VIOLATION'
                && ($secondAsNeeded['next_allowed_local'] ?? '') === '17h00'
                && str_contains($secondAsNeeded['message'], '17h00'),
                'E2 Prise « si besoin »: seconde prise avant min_interval_h retourne 422 MIN_INTERVAL_VIOLATION avec prochaine prise à 17h00'
            );
        }
    }
}
