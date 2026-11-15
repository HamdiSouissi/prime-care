<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\ScheduleService;
use DateTimeImmutable;
use DateTimeZone;

final class ScheduleServiceTest
{
    public static function run(callable $assert): void
    {
        // 1. Europe/Paris -> UTC across DST transitions
        // Winter time (CET = UTC+1): 2026-01-15 08:00 Paris -> 07:00 UTC
        $winterUtc = ScheduleService::localDateAndTimeToUtc('2026-01-15', '08:00');
        $assert($winterUtc === '2026-01-15 07:00:00', 'ScheduleService: 08:00 Europe/Paris en hiver (CET) = 07:00 UTC');

        // Summer time (CEST = UTC+2): 2026-07-15 08:00 Paris -> 06:00 UTC
        $summerUtc = ScheduleService::localDateAndTimeToUtc('2026-07-15', '08:00');
        $assert($summerUtc === '2026-07-15 06:00:00', 'ScheduleService: 08:00 Europe/Paris en été (CEST) = 06:00 UTC');

        // Round-trip UTC -> Local Paris
        $localFormatted = ScheduleService::utcToLocalParis('2026-07-15 06:00:00', 'H:i');
        $assert($localFormatted === '08:00', 'ScheduleService: conversion retour 06:00 UTC été -> 08:00 Europe/Paris');

        // 2. Idempotence of 48h schedule generation
        $pdo = Database::connection();
        $parisTz = new DateTimeZone('Europe/Paris');
        $from = new DateTimeImmutable('+1 day', $parisTz);
        $to = new DateTimeImmutable('+2 days', $parisTz);

        ScheduleService::generateForWindow($from, $to, 1);
        $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM `medication_intakes`')->fetchColumn();
        $expBefore = (int) $pdo->query('SELECT COUNT(*) FROM `measure_expectations`')->fetchColumn();

        // Second and third runs on the exact same window must insert 0 rows
        $run2 = ScheduleService::generateForWindow($from, $to, 1);
        $run3 = ScheduleService::generateForWindow($from, $to, 1);
        $countAfter = (int) $pdo->query('SELECT COUNT(*) FROM `medication_intakes`')->fetchColumn();
        $expAfter = (int) $pdo->query('SELECT COUNT(*) FROM `measure_expectations`')->fetchColumn();

        $assert(
            $run2['intakes_created'] === 0 && $run3['intakes_created'] === 0 && $countBefore === $countAfter,
            'ScheduleService: génération idempotente des medication_intakes (0 doublon sur appels répétés)'
        );
        $assert(
            $run2['expectations_created'] === 0 && $run3['expectations_created'] === 0 && $expBefore === $expAfter,
            'ScheduleService: génération idempotente des measure_expectations (0 doublon sur appels répétés)'
        );

        // 3. E2: Changement d'heure bout-en-bout (28–30 mars 2026 et 24–26 octobre 2026 à 21:00 Paris)
        $nowUtc = gmdate('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO `medications` (`patient_id`, `name`, `dci`, `form`, `default_dosage`, `default_unit`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, \'Test DST Lantus\', \'Insuline glargine\', \'stylo\', 18, \'UI\', 1, :c, :u, 1)'
        )->execute(['c' => $nowUtc, 'u' => $nowUtc]);
        $dstMedId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO `prescriptions` (`patient_id`, `medication_id`, `dose`, `unit`, `start_date`, `ends_on_rx`, `days_mask`, `as_needed`, `dose_mode`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :mid, 18, \'UI\', \'2026-01-01\', 1, \'1,2,3,4,5,6,7\', 0, \'fixe\', 1, :c, :u, 1)'
        )->execute(['mid' => $dstMedId, 'c' => $nowUtc, 'u' => $nowUtc]);
        $dstRxId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO `prescription_times` (`patient_id`, `prescription_id`, `scheduled_time`, `moment_label`, `sort_order`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :rx_id, \'21:00\', \'coucher\', 1, :c, :u, 1)'
        )->execute(['rx_id' => $dstRxId, 'c' => $nowUtc, 'u' => $nowUtc]);
        $dstPtId = (int) $pdo->lastInsertId();

        // Spring DST transition: 28 -> 30 March 2026
        $springFromUtc = ScheduleService::localParisToUtc('2026-03-28', '00:00');
        $springToUtc = ScheduleService::localParisToUtc('2026-03-30', '23:59');
        ScheduleService::generateForRange($springFromUtc, $springToUtc, 1, $dstRxId);
        // Re-run to verify 0 duplicates across DST boundary
        ScheduleService::generateForRange($springFromUtc, $springToUtc, 1, $dstRxId);

        $springStmt = $pdo->prepare(
            'SELECT `scheduled_at` FROM `medication_intakes`
             WHERE `prescription_id` = :rx_id AND `scheduled_at` BETWEEN :f AND :t
             ORDER BY `scheduled_at` ASC'
        );
        $springStmt->execute(['rx_id' => $dstRxId, 'f' => $springFromUtc, 't' => $springToUtc]);
        $springSlots = $springStmt->fetchAll(\PDO::FETCH_COLUMN);

        $assert(
            $springSlots === [
                '2026-03-28 20:00:00', // Winter (UTC+1) -> 21:00 Paris
                '2026-03-29 19:00:00', // Summer (UTC+2) -> 21:00 Paris
                '2026-03-30 19:00:00', // Summer (UTC+2) -> 21:00 Paris
            ]
            && ScheduleService::utcToLocalParis($springSlots[0], 'H:i') === '21:00'
            && ScheduleService::utcToLocalParis($springSlots[1], 'H:i') === '21:00'
            && ScheduleService::utcToLocalParis($springSlots[2], 'H:i') === '21:00',
            'E2 DST Printemps (28–30 mars 2026): prise à 21:00 Paris passe de 20:00 UTC à 19:00 UTC sans doublon ni trou'
        );

        // Autumn DST transition: 24 -> 26 October 2026
        $autumnFromUtc = ScheduleService::localParisToUtc('2026-10-24', '00:00');
        $autumnToUtc = ScheduleService::localParisToUtc('2026-10-26', '23:59');
        ScheduleService::generateForRange($autumnFromUtc, $autumnToUtc, 1, $dstRxId);
        ScheduleService::generateForRange($autumnFromUtc, $autumnToUtc, 1, $dstRxId);

        $autumnStmt = $pdo->prepare(
            'SELECT `scheduled_at` FROM `medication_intakes`
             WHERE `prescription_id` = :rx_id AND `scheduled_at` BETWEEN :f AND :t
             ORDER BY `scheduled_at` ASC'
        );
        $autumnStmt->execute(['rx_id' => $dstRxId, 'f' => $autumnFromUtc, 't' => $autumnToUtc]);
        $autumnSlots = $autumnStmt->fetchAll(\PDO::FETCH_COLUMN);

        $assert(
            $autumnSlots === [
                '2026-10-24 19:00:00', // Summer (UTC+2) -> 21:00 Paris
                '2026-10-25 20:00:00', // Winter (UTC+1) -> 21:00 Paris
                '2026-10-26 20:00:00', // Winter (UTC+1) -> 21:00 Paris
            ]
            && ScheduleService::utcToLocalParis($autumnSlots[0], 'H:i') === '21:00'
            && ScheduleService::utcToLocalParis($autumnSlots[1], 'H:i') === '21:00'
            && ScheduleService::utcToLocalParis($autumnSlots[2], 'H:i') === '21:00',
            'E2 DST Automne (24–26 oct. 2026): prise à 21:00 Paris passe de 19:00 UTC à 20:00 UTC sans doublon ni trou'
        );

        // 4. E2: Régénération des créneaux après modification d'une prescription (given & skipped intacts, pending régénérés)
        // Mark 2026-03-28 as 'given', 2026-03-29 as 'skipped', leave 2026-03-30 as 'pending'
        $pdo->prepare(
            'UPDATE `medication_intakes` SET `status` = \'given\', `given_at` = `scheduled_at`, `given_dose` = 18, `given_by` = 3
             WHERE `prescription_id` = :rx_id AND `scheduled_at` = \'2026-03-28 20:00:00\''
        )->execute(['rx_id' => $dstRxId]);

        $pdo->prepare(
            'UPDATE `medication_intakes` SET `status` = \'skipped\', `given_at` = `scheduled_at`, `reason` = \'Refus du patient\', `given_by` = 4
             WHERE `prescription_id` = :rx_id AND `scheduled_at` = \'2026-03-29 19:00:00\''
        )->execute(['rx_id' => $dstRxId]);

        // Change prescription time from 21:00 to 21:30 and regenerate from 2026-03-28 12:00:00 UTC
        $pdo->prepare(
            'UPDATE `prescription_times` SET `scheduled_time` = \'21:30\' WHERE `id` = :ptid'
        )->execute(['ptid' => $dstPtId]);

        $regen = ScheduleService::regenerateForPrescription($dstRxId, '2026-03-28 19:30:00', 1);

        $afterRegenStmt = $pdo->prepare(
            'SELECT `scheduled_at`, `status` FROM `medication_intakes`
             WHERE `prescription_id` = :rx_id AND `scheduled_at` BETWEEN :f AND :t
             ORDER BY `scheduled_at` ASC'
        );
        $afterRegenStmt->execute(['rx_id' => $dstRxId, 'f' => $springFromUtc, 't' => $springToUtc]);
        $afterRegenRows = $afterRegenStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $assert(
            $regen['deleted_pending'] >= 1
            && ($afterRegenRows['2026-03-28 20:00:00'] ?? '') === 'given'
            && ($afterRegenRows['2026-03-29 19:00:00'] ?? '') === 'skipped'
            && !isset($afterRegenRows['2026-03-30 19:00:00'])
            && ($afterRegenRows['2026-03-30 19:30:00'] ?? '') === 'pending',
            'E2 Régénération: les occurrences futures pending sont régénérées au nouvel horaire (21:30) et given/skipped restent intactes'
        );

        // Clean up temporary DST test prescription so seed state stays clean
        $pdo->prepare('DELETE FROM `medication_intakes` WHERE `prescription_id` = :rx_id')->execute(['rx_id' => $dstRxId]);
        $pdo->prepare('DELETE FROM `prescription_times` WHERE `prescription_id` = :rx_id')->execute(['rx_id' => $dstRxId]);
        $pdo->prepare('DELETE FROM `prescriptions` WHERE `id` = :rx_id')->execute(['rx_id' => $dstRxId]);
        $pdo->prepare('DELETE FROM `medications` WHERE `id` = :mid')->execute(['mid' => $dstMedId]);
    }
}
