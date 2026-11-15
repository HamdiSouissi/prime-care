<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * HoursCalculatorService — Calcul exact des heures planifiées et pointées (E18) :
 * - Heures totales réelles (respectant les changements d'heure d'été / d'hiver Europe/Paris : 11h en mars, 13h en octobre)
 * - Heures de nuit (21h00 – 06h00 heure locale)
 * - Heures du dimanche (00h00 – 24h00 heure locale)
 * - Heures des jours fériés français (8 fixes + 3 mobiles : lundi de Pâques, Ascension, lundi de Pentecôte)
 * - Découpage par jour civil local pour l'export CSV de paie (UTF-8 avec BOM, séparateur ';').
 */
final class HoursCalculatorService
{
    /**
     * Retourne la date du dimanche de Pâques (calendrier grégorien, algorithme de Meeus/Jones/Butcher)
     * pour l'année donnée dans le fuseau local.
     */
    public static function easterSunday(int $year, ?DateTimeZone $tz = null): DateTimeImmutable
    {
        $tz ??= new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day), $tz);
    }

    /**
     * Retourne la liste des 11 jours fériés officiels en France métropolitaine pour une année donnée
     * sous la forme ['YYYY-MM-DD' => 'Libellé du jour férié'].
     *
     * @return array<string, string>
     */
    public static function frenchHolidaysForYear(int $year): array
    {
        $tz = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
        $easter = self::easterSunday($year, $tz);
        $easterMonday = $easter->modify('+1 day')->format('Y-m-d');
        $ascension = $easter->modify('+39 days')->format('Y-m-d');
        $whitMonday = $easter->modify('+50 days')->format('Y-m-d');

        $holidays = [
            sprintf('%04d-01-01', $year) => "Jour de l'An",
            $easterMonday => 'Lundi de Pâques',
            sprintf('%04d-05-01', $year) => 'Fête du Travail',
            sprintf('%04d-05-08', $year) => 'Victoire 1945',
            $ascension => 'Ascension',
            $whitMonday => 'Lundi de Pentecôte',
            sprintf('%04d-07-14', $year) => 'Fête nationale',
            sprintf('%04d-08-15', $year) => 'Assomption',
            sprintf('%04d-11-01', $year) => 'Toussaint',
            sprintf('%04d-11-11', $year) => 'Armistice 1918',
            sprintf('%04d-12-25', $year) => 'Noël',
        ];
        ksort($holidays);

        return $holidays;
    }

    /**
     * Vérifie si une date locale (YYYY-MM-DD ou DateTimeImmutable) est un jour férié français.
     */
    public static function isFrenchHoliday(DateTimeImmutable|string $date): bool
    {
        return self::getFrenchHolidayLabel($date) !== null;
    }

    /**
     * Retourne le libellé du jour férié français ou null si jour ouvrable/ordinaire.
     */
    public static function getFrenchHolidayLabel(DateTimeImmutable|string $date): ?string
    {
        if ($date instanceof DateTimeImmutable) {
            $ymd = $date->format('Y-m-d');
            $year = (int) $date->format('Y');
        } else {
            $ymd = substr($date, 0, 10);
            $year = (int) substr($ymd, 0, 4);
        }

        $holidays = self::frenchHolidaysForYear($year);
        return $holidays[$ymd] ?? null;
    }

    /**
     * Découpe un intervalle [startUtc, endUtc] minute par minute en heure locale Europe/Paris.
     * Gère exactement :
     * - les gardes à cheval sur minuit (ex. 20h00 -> 08h00)
     * - la plage de nuit (21h00 <= heure locale < 06h00)
     * - le dimanche (jour ISO = 7 en heure locale)
     * - les jours fériés français (fixes + Pâques / Ascension / Pentecôte)
     * - les changements d'heure d'été (mars : 11h réelles) et d'hiver (octobre : 13h réelles)
     *
     * @return array{
     *   total_minutes: int,
     *   night_minutes: int,
     *   sunday_minutes: int,
     *   holiday_minutes: int,
     *   total_hours: float,
     *   night_hours: float,
     *   sunday_hours: float,
     *   holiday_hours: float,
     *   by_day: array<string, array{
     *     total_minutes: int,
     *     night_minutes: int,
     *     sunday_minutes: int,
     *     holiday_minutes: int,
     *     is_holiday: bool,
     *     holiday_label: ?string,
     *     is_sunday: bool
     *   }>
     * }
     */
    public static function splitInterval(
        DateTimeImmutable|string $startUtc,
        DateTimeImmutable|string $endUtc,
        string $timezone = 'Europe/Paris'
    ): array {
        $utcTz = new DateTimeZone('UTC');
        $localTz = new DateTimeZone($timezone);

        $start = $startUtc instanceof DateTimeImmutable
            ? $startUtc->setTimezone($utcTz)
            : new DateTimeImmutable($startUtc, $utcTz);
        $end = $endUtc instanceof DateTimeImmutable
            ? $endUtc->setTimezone($utcTz)
            : new DateTimeImmutable($endUtc, $utcTz);

        $totalMin = 0;
        $nightMin = 0;
        $sundayMin = 0;
        $holidayMin = 0;
        $byDay = [];

        if ($end <= $start) {
            return [
                'total_minutes' => 0,
                'night_minutes' => 0,
                'sunday_minutes' => 0,
                'holiday_minutes' => 0,
                'total_hours' => 0.0,
                'night_hours' => 0.0,
                'sunday_hours' => 0.0,
                'holiday_hours' => 0.0,
                'by_day' => [],
            ];
        }

        // Cache holidays by year for speed
        $holidayCache = [];

        $cursorTs = $start->getTimestamp();
        $endTs = $end->getTimestamp();

        // Step in 60-second increments of real elapsed UTC time
        while ($cursorTs < $endTs) {
            $stepSeconds = min(60, $endTs - $cursorTs);
            $stepMinutes = (int) round($stepSeconds / 60);
            if ($stepMinutes <= 0) {
                break;
            }

            $localDt = (new DateTimeImmutable('@' . $cursorTs))->setTimezone($localTz);
            $ymd = $localDt->format('Y-m-d');
            $year = (int) $localDt->format('Y');
            $hour = (int) $localDt->format('G'); // 0..23
            $isoDow = (int) $localDt->format('N'); // 1 (Mon) .. 7 (Sun)

            if (!isset($holidayCache[$year])) {
                $holidayCache[$year] = self::frenchHolidaysForYear($year);
            }
            $holidayLabel = $holidayCache[$year][$ymd] ?? null;
            $isHoliday = $holidayLabel !== null;
            $isSunday = ($isoDow === 7);
            $isNight = ($hour >= 21 || $hour < 6);

            if (!isset($byDay[$ymd])) {
                $byDay[$ymd] = [
                    'total_minutes' => 0,
                    'night_minutes' => 0,
                    'sunday_minutes' => 0,
                    'holiday_minutes' => 0,
                    'is_holiday' => $isHoliday,
                    'holiday_label' => $holidayLabel,
                    'is_sunday' => $isSunday,
                ];
            }

            $totalMin += $stepMinutes;
            $byDay[$ymd]['total_minutes'] += $stepMinutes;

            if ($isNight) {
                $nightMin += $stepMinutes;
                $byDay[$ymd]['night_minutes'] += $stepMinutes;
            }
            if ($isSunday) {
                $sundayMin += $stepMinutes;
                $byDay[$ymd]['sunday_minutes'] += $stepMinutes;
            }
            if ($isHoliday) {
                $holidayMin += $stepMinutes;
                $byDay[$ymd]['holiday_minutes'] += $stepMinutes;
            }

            $cursorTs += $stepSeconds;
        }

        return [
            'total_minutes' => $totalMin,
            'night_minutes' => $nightMin,
            'sunday_minutes' => $sundayMin,
            'holiday_minutes' => $holidayMin,
            'total_hours' => round($totalMin / 60, 2),
            'night_hours' => round($nightMin / 60, 2),
            'sunday_hours' => round($sundayMin / 60, 2),
            'holiday_hours' => round($holidayMin / 60, 2),
            'by_day' => $byDay,
        ];
    }

    /**
     * Calcule le récapitulatif mensuel par aide-soignante sur le mois (année, mois 1..12) en heure locale Europe/Paris.
     * Seuls les pointages au statut `valid` sont comptabilisés dans les heures pointées (`pending` et `rejected` exclus).
     *
     * @return array{
     *   year: int,
     *   month: int,
     *   month_label: string,
     *   start_local: string,
     *   end_local: string,
     *   caregivers: array<int, array<string, mixed>>,
     *   daily_rows: array<int, array<string, mixed>>,
     *   totals: array<string, mixed>
     * }
     */
    public static function calculateMonthlySummary(int $year, int $month, ?int $onlyUserId = null, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $localTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $monthStartLocal = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $localTz);
        $monthEndLocal = $monthStartLocal->modify('first day of next month');

        // Include shifts/attendances starting slightly before month start in case a night shift crosses into the 1st of the month
        $queryStartUtc = $monthStartLocal->modify('-1 day')->setTimezone($utcTz)->format('Y-m-d H:i:s');
        $queryEndUtc = $monthEndLocal->modify('+1 day')->setTimezone($utcTz)->format('Y-m-d H:i:s');

        $monthStartUtc = $monthStartLocal->setTimezone($utcTz);
        $monthEndUtc = $monthEndLocal->setTimezone($utcTz);

        // Load active caregivers & coordinators
        $userSql = "SELECT id, first_name, last_name, role, contract_type, weekly_hours, hourly_rate, color_hex
                    FROM users
                    WHERE patient_id = :pid AND deleted_at IS NULL AND role IN ('caregiver', 'coordinator')";
        $userParams = ['pid' => $patientId];
        if ($onlyUserId !== null) {
            $userSql .= ' AND id = :uid';
            $userParams['uid'] = $onlyUserId;
        }
        $userSql .= ' ORDER BY role DESC, first_name ASC';
        $uStmt = $pdo->prepare($userSql);
        $uStmt->execute($userParams);
        $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

        $caregivers = [];
        $perUserDay = [];
        foreach ($users as $u) {
            $uid = (int) $u['id'];
            $caregivers[$uid] = [
                'user_id' => $uid,
                'first_name' => (string) $u['first_name'],
                'last_name' => (string) $u['last_name'],
                'full_name' => trim($u['first_name'] . ' ' . $u['last_name']),
                'role' => (string) $u['role'],
                'contract_type' => (string) ($u['contract_type'] ?? 'CDI'),
                'weekly_hours' => (float) ($u['weekly_hours'] ?? 35.0),
                'hourly_rate' => $u['hourly_rate'] !== null ? (float) $u['hourly_rate'] : null,
                'planned_minutes' => 0,
                'punched_minutes' => 0,
                'night_minutes' => 0,
                'sunday_minutes' => 0,
                'holiday_minutes' => 0,
                'pending_count' => 0,
                'shifts_count' => 0,
            ];
            $perUserDay[$uid] = [];
        }

        if ($caregivers === []) {
            return [
                'year' => $year,
                'month' => $month,
                'month_label' => self::frenchMonthLabel($year, $month),
                'start_local' => $monthStartLocal->format('Y-m-d'),
                'end_local' => $monthEndLocal->modify('-1 day')->format('Y-m-d'),
                'caregivers' => [],
                'daily_rows' => [],
                'totals' => [
                    'planned_minutes' => 0,
                    'punched_minutes' => 0,
                    'night_minutes' => 0,
                    'sunday_minutes' => 0,
                    'holiday_minutes' => 0,
                    'diff_minutes' => 0,
                ],
            ];
        }

        // 1. Planned shifts (status != 'cancelled')
        $shiftStmt = $pdo->prepare(
            "SELECT id, user_id, start_at, end_at, status
             FROM shifts
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status != 'cancelled'
               AND user_id IS NOT NULL
               AND end_at > :qstart
               AND start_at < :qend
             ORDER BY start_at ASC"
        );
        $shiftStmt->execute([
            'pid' => $patientId,
            'qstart' => $queryStartUtc,
            'qend' => $queryEndUtc,
        ]);
        $shifts = $shiftStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($shifts as $s) {
            $uid = (int) $s['user_id'];
            if (!isset($caregivers[$uid])) {
                continue;
            }
            $sStart = new DateTimeImmutable((string) $s['start_at'], $utcTz);
            $sEnd = new DateTimeImmutable((string) $s['end_at'], $utcTz);

            // Clip to the target month boundaries in local time (= monthStartUtc .. monthEndUtc)
            $clipStart = $sStart < $monthStartUtc ? $monthStartUtc : $sStart;
            $clipEnd = $sEnd > $monthEndUtc ? $monthEndUtc : $sEnd;
            if ($clipEnd <= $clipStart) {
                continue;
            }

            $split = self::splitInterval($clipStart, $clipEnd, $tzName);
            $caregivers[$uid]['planned_minutes'] += $split['total_minutes'];
            $caregivers[$uid]['shifts_count']++;

            foreach ($split['by_day'] as $ymd => $dayData) {
                if (!isset($perUserDay[$uid][$ymd])) {
                    $perUserDay[$uid][$ymd] = self::emptyDayRow($ymd, $dayData);
                }
                $perUserDay[$uid][$ymd]['planned_minutes'] += $dayData['total_minutes'];
            }
        }

        // 2. Count pending attendances in month for information
        $pendStmt = $pdo->prepare(
            "SELECT user_id, COUNT(*) AS cnt
             FROM attendances
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status = 'pending'
               AND COALESCE(declared_at, recorded_at) >= :mstart
               AND COALESCE(declared_at, recorded_at) < :mend
             GROUP BY user_id"
        );
        $pendStmt->execute([
            'pid' => $patientId,
            'mstart' => $monthStartUtc->format('Y-m-d H:i:s'),
            'mend' => $monthEndUtc->format('Y-m-d H:i:s'),
        ]);
        foreach ($pendStmt->fetchAll(PDO::FETCH_ASSOC) as $prow) {
            $uid = (int) $prow['user_id'];
            if (isset($caregivers[$uid])) {
                $caregivers[$uid]['pending_count'] = (int) $prow['cnt'];
            }
        }

        // 3. Valid attendances (`status = 'valid'`) paired IN -> OUT per user
        $attStmt = $pdo->prepare(
            "SELECT id, user_id, shift_id, type, COALESCE(declared_at, recorded_at) AS effective_at
             FROM attendances
             WHERE patient_id = :pid
               AND deleted_at IS NULL
               AND status = 'valid'
               AND COALESCE(declared_at, recorded_at) >= :qstart
               AND COALESCE(declared_at, recorded_at) <= :qend
             ORDER BY user_id ASC, effective_at ASC, id ASC"
        );
        $attStmt->execute([
            'pid' => $patientId,
            'qstart' => $queryStartUtc,
            'qend' => $queryEndUtc,
        ]);
        $attendances = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        $pairsByUser = self::pairAttendances($attendances);

        foreach ($pairsByUser as $uid => $pairs) {
            if (!isset($caregivers[$uid])) {
                continue;
            }
            foreach ($pairs as $pair) {
                $inDt = new DateTimeImmutable($pair['in_at'], $utcTz);
                $outDt = new DateTimeImmutable($pair['out_at'], $utcTz);

                $clipStart = $inDt < $monthStartUtc ? $monthStartUtc : $inDt;
                $clipEnd = $outDt > $monthEndUtc ? $monthEndUtc : $outDt;
                if ($clipEnd <= $clipStart) {
                    continue;
                }

                $split = self::splitInterval($clipStart, $clipEnd, $tzName);
                $caregivers[$uid]['punched_minutes'] += $split['total_minutes'];
                $caregivers[$uid]['night_minutes'] += $split['night_minutes'];
                $caregivers[$uid]['sunday_minutes'] += $split['sunday_minutes'];
                $caregivers[$uid]['holiday_minutes'] += $split['holiday_minutes'];

                foreach ($split['by_day'] as $ymd => $dayData) {
                    if (!isset($perUserDay[$uid][$ymd])) {
                        $perUserDay[$uid][$ymd] = self::emptyDayRow($ymd, $dayData);
                    }
                    $perUserDay[$uid][$ymd]['punched_minutes'] += $dayData['total_minutes'];
                    $perUserDay[$uid][$ymd]['night_minutes'] += $dayData['night_minutes'];
                    $perUserDay[$uid][$ymd]['sunday_minutes'] += $dayData['sunday_minutes'];
                    $perUserDay[$uid][$ymd]['holiday_minutes'] += $dayData['holiday_minutes'];
                }
            }
        }

        // Finalize caregiver stats and daily rows
        $totals = [
            'planned_minutes' => 0,
            'punched_minutes' => 0,
            'night_minutes' => 0,
            'sunday_minutes' => 0,
            'holiday_minutes' => 0,
            'diff_minutes' => 0,
        ];

        $dailyRows = [];
        foreach ($caregivers as $uid => &$cg) {
            $cg['diff_minutes'] = $cg['punched_minutes'] - $cg['planned_minutes'];
            $cg['planned_hours'] = round($cg['planned_minutes'] / 60, 2);
            $cg['punched_hours'] = round($cg['punched_minutes'] / 60, 2);
            $cg['night_hours'] = round($cg['night_minutes'] / 60, 2);
            $cg['sunday_hours'] = round($cg['sunday_minutes'] / 60, 2);
            $cg['holiday_hours'] = round($cg['holiday_minutes'] / 60, 2);
            $cg['diff_hours'] = round($cg['diff_minutes'] / 60, 2);

            $totals['planned_minutes'] += $cg['planned_minutes'];
            $totals['punched_minutes'] += $cg['punched_minutes'];
            $totals['night_minutes'] += $cg['night_minutes'];
            $totals['sunday_minutes'] += $cg['sunday_minutes'];
            $totals['holiday_minutes'] += $cg['holiday_minutes'];
            $totals['diff_minutes'] += $cg['diff_minutes'];

            ksort($perUserDay[$uid]);
            foreach ($perUserDay[$uid] as $ymd => $dRow) {
                $dRow['user_id'] = $uid;
                $dRow['full_name'] = $cg['full_name'];
                $dRow['contract_type'] = $cg['contract_type'];
                $dRow['diff_minutes'] = $dRow['punched_minutes'] - $dRow['planned_minutes'];
                $dailyRows[] = $dRow;
            }
        }
        unset($cg);

        $totals['planned_hours'] = round($totals['planned_minutes'] / 60, 2);
        $totals['punched_hours'] = round($totals['punched_minutes'] / 60, 2);
        $totals['night_hours'] = round($totals['night_minutes'] / 60, 2);
        $totals['sunday_hours'] = round($totals['sunday_minutes'] / 60, 2);
        $totals['holiday_hours'] = round($totals['holiday_minutes'] / 60, 2);
        $totals['diff_hours'] = round($totals['diff_minutes'] / 60, 2);

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => self::frenchMonthLabel($year, $month),
            'start_local' => $monthStartLocal->format('Y-m-d'),
            'end_local' => $monthEndLocal->modify('-1 day')->format('Y-m-d'),
            'caregivers' => array_values($caregivers),
            'daily_rows' => $dailyRows,
            'totals' => $totals,
        ];
    }

    /**
     * Génère le CSV de paie mensuel (séparateur ';', encodage UTF-8 avec BOM, 1 ligne par personne/jour + ligne TOTAL).
     */
    public static function generatePayrollCsv(int $year, int $month, ?int $onlyUserId = null, int $patientId = 1): string
    {
        $summary = self::calculateMonthlySummary($year, $month, $onlyUserId, $patientId);
        $bom = "\xEF\xBB\xBF";
        $lines = [];

        $headers = [
            'Salariée',
            'Contrat',
            'Date',
            'Jour férié / Dimanche',
            'Heures planifiées (h)',
            'Heures pointées validées (h)',
            'Heures de nuit 21h-6h (h)',
            'Heures dimanche (h)',
            'Heures jour férié (h)',
            'Écart (h)',
        ];
        $lines[] = implode(';', $headers);

        foreach ($summary['daily_rows'] as $row) {
            $specials = [];
            if (!empty($row['is_sunday'])) {
                $specials[] = 'Dimanche';
            }
            if (!empty($row['is_holiday']) && !empty($row['holiday_label'])) {
                $specials[] = (string) $row['holiday_label'];
            }

            $lines[] = implode(';', [
                self::escapeCsvField((string) $row['full_name']),
                self::escapeCsvField((string) $row['contract_type']),
                (string) $row['date'],
                self::escapeCsvField(implode(' / ', $specials)),
                self::formatDecimalFr($row['planned_minutes'] / 60),
                self::formatDecimalFr($row['punched_minutes'] / 60),
                self::formatDecimalFr($row['night_minutes'] / 60),
                self::formatDecimalFr($row['sunday_minutes'] / 60),
                self::formatDecimalFr($row['holiday_minutes'] / 60),
                self::formatDecimalFr($row['diff_minutes'] / 60),
            ]);
        }

        $t = $summary['totals'];
        $lines[] = implode(';', [
            'TOTAL MENSUEL',
            '',
            sprintf('%04d-%02d', $year, $month),
            '',
            self::formatDecimalFr($t['planned_minutes'] / 60),
            self::formatDecimalFr($t['punched_minutes'] / 60),
            self::formatDecimalFr($t['night_minutes'] / 60),
            self::formatDecimalFr($t['sunday_minutes'] / 60),
            self::formatDecimalFr($t['holiday_minutes'] / 60),
            self::formatDecimalFr($t['diff_minutes'] / 60),
        ]);

        return $bom . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Formate une durée en minutes au format lisible français "12h00" ou "+0h15".
     */
    public static function formatMinutes(int $minutes, bool $signed = false): string
    {
        $sign = '';
        if ($minutes < 0) {
            $sign = '-';
            $minutes = abs($minutes);
        } elseif ($signed && $minutes > 0) {
            $sign = '+';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%s%dh%02d', $sign, $h, $m);
    }

    /**
     * Apparie les pointages d'entrée (`in`) et de sortie (`out`) par utilisateur.
     *
     * @param array<int, array<string, mixed>> $attendances
     * @return array<int, array<int, array{in_at: string, out_at: string, shift_id: ?int}>>
     */
    public static function pairAttendances(array $attendances): array
    {
        $pairsByUser = [];
        $openInByUser = [];

        foreach ($attendances as $att) {
            $uid = (int) $att['user_id'];
            $type = (string) $att['type'];
            $effectiveAt = (string) $att['effective_at'];
            $shiftId = isset($att['shift_id']) && $att['shift_id'] !== null ? (int) $att['shift_id'] : null;

            if ($type === 'in') {
                $openInByUser[$uid] = [
                    'in_at' => $effectiveAt,
                    'shift_id' => $shiftId,
                ];
            } elseif ($type === 'out' && isset($openInByUser[$uid])) {
                $inAt = $openInByUser[$uid]['in_at'];
                if ($effectiveAt > $inAt) {
                    $pairsByUser[$uid][] = [
                        'in_at' => $inAt,
                        'out_at' => $effectiveAt,
                        'shift_id' => $shiftId ?? $openInByUser[$uid]['shift_id'],
                    ];
                }
                unset($openInByUser[$uid]);
            }
        }

        return $pairsByUser;
    }

    /**
     * @param array<string, mixed> $dayData
     * @return array<string, mixed>
     */
    private static function emptyDayRow(string $ymd, array $dayData): array
    {
        return [
            'date' => $ymd,
            'planned_minutes' => 0,
            'punched_minutes' => 0,
            'night_minutes' => 0,
            'sunday_minutes' => 0,
            'holiday_minutes' => 0,
            'diff_minutes' => 0,
            'is_holiday' => (bool) ($dayData['is_holiday'] ?? self::isFrenchHoliday($ymd)),
            'holiday_label' => $dayData['holiday_label'] ?? self::getFrenchHolidayLabel($ymd),
            'is_sunday' => (bool) ($dayData['is_sunday'] ?? false),
        ];
    }

    private static function formatDecimalFr(float $hours): string
    {
        return number_format(round($hours, 2), 2, ',', '');
    }

    private static function escapeCsvField(string $value): string
    {
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    public static function frenchMonthLabel(int $year, int $month): string
    {
        $names = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];
        return ($names[$month] ?? 'Mois') . ' ' . $year;
    }
}
