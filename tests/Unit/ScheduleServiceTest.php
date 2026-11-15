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
    }
}
