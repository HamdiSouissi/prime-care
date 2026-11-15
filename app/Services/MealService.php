<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Uploader;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Manages meal type inference from local time, photo uploads (up to 3 photos with 400px thumbnails),
 * and automatic linking with pre-meal (-60 min) and post-meal (+90..+150 min) glycemia measures.
 */
final class MealService
{
    public const MEAL_TYPES = [
        'breakfast' => 'Petit-déjeuner',
        'lunch' => 'Déjeuner',
        'snack' => 'Collation / Goûter',
        'dinner' => 'Dîner',
        'night_snack' => 'Collation nocturne',
    ];

    public const PORTIONS = [
        0 => 'Rien (0 %)',
        25 => 'Un quart (25 %)',
        50 => 'Moitié (50 %)',
        75 => 'Trois quarts (75 %)',
        100 => 'Tout mangé (100 %)',
    ];

    /**
     * Deduce the meal_type from a local HH:MM time (or current Europe/Paris time).
     */
    public static function deduceMealType(?string $localTimeHhMm = null): string
    {
        if ($localTimeHhMm === null || !preg_match('/^(\d{1,2}):(\d{2})/', trim($localTimeHhMm), $m)) {
            $tz = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Paris'));
            $nowLocal = new DateTimeImmutable('now', $tz);
            $minutesOfDay = ((int) $nowLocal->format('G')) * 60 + (int) $nowLocal->format('i');
        } else {
            $minutesOfDay = ((int) $m[1]) * 60 + (int) $m[2];
        }

        // 05:00 (300) .. 10:29 (629) -> breakfast
        if ($minutesOfDay >= 300 && $minutesOfDay < 630) {
            return 'breakfast';
        }
        // 10:30 (630) .. 14:29 (869) -> lunch
        if ($minutesOfDay >= 630 && $minutesOfDay < 870) {
            return 'lunch';
        }
        // 14:30 (870) .. 17:59 (1079) -> snack
        if ($minutesOfDay >= 870 && $minutesOfDay < 1080) {
            return 'snack';
        }
        // 18:00 (1080) .. 21:29 (1289) -> dinner
        if ($minutesOfDay >= 1080 && $minutesOfDay < 1290) {
            return 'dinner';
        }

        return 'night_snack';
    }

    /**
     * Find the pre-meal glycemia (within 60 minutes before eaten_at) and post-meal glycemia
     * (between 90 and 150 minutes after eaten_at).
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>|null}
     */
    public static function findLinkedGlycemias(string $eatenAtUtc, int $patientId = 1): array
    {
        $pdo = Database::connection();
        $utcTz = new DateTimeZone('UTC');
        $eatenDt = new DateTimeImmutable($eatenAtUtc, $utcTz);

        $beforeMin = $eatenDt->modify('-60 minutes')->format('Y-m-d H:i:s');
        $beforeMax = $eatenDt->format('Y-m-d H:i:s');

        $afterMin = $eatenDt->modify('+90 minutes')->format('Y-m-d H:i:s');
        $afterMax = $eatenDt->modify('+150 minutes')->format('Y-m-d H:i:s');

        $beforeStmt = $pdo->prepare(
            'SELECT m.*, mt.unit
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = :pid
               AND mt.code = \'glycemia\'
               AND m.deleted_at IS NULL
               AND m.measured_at BETWEEN :min_utc AND :max_utc
             ORDER BY m.measured_at DESC
             LIMIT 1'
        );
        $beforeStmt->execute([
            'pid' => $patientId,
            'min_utc' => $beforeMin,
            'max_utc' => $beforeMax,
        ]);
        $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $afterStmt = $pdo->prepare(
            'SELECT m.*, mt.unit
             FROM `measures` m
             INNER JOIN `measure_types` mt ON mt.id = m.measure_type_id
             WHERE m.patient_id = :pid
               AND mt.code = \'glycemia\'
               AND m.deleted_at IS NULL
               AND m.measured_at BETWEEN :min_utc AND :max_utc
             ORDER BY m.measured_at ASC
             LIMIT 1'
        );
        $afterStmt->execute([
            'pid' => $patientId,
            'min_utc' => $afterMin,
            'max_utc' => $afterMax,
        ]);
        $after = $afterStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'before' => $before,
            'after' => $after,
        ];
    }

    /**
     * Store up to 3 meal photos ('avant', 'apres', 'autre') with 400px thumbnails via Uploader.
     *
     * @param array<string, array<string, mixed>> $filesByKind
     * @return array<int, array<string, mixed>>
     */
    public static function saveMealPhotos(
        int $mealId,
        array $filesByKind,
        int $userId,
        int $patientId = 1
    ): array {
        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $saved = [];
        $order = 1;

        foreach (['avant', 'apres', 'autre'] as $kind) {
            if (!isset($filesByKind[$kind])) {
                continue;
            }
            $file = $filesByKind[$kind];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($order > 3) {
                break;
            }

            $uploaded = Uploader::uploadImage($file, 'meals', 1600, 400);

            // Replace any previous photo of the same kind on this meal (soft-delete old)
            $delOld = $pdo->prepare(
                'UPDATE `meal_photos`
                 SET `deleted_at` = :now_utc
                 WHERE `meal_id` = :meal_id AND `kind` = :kind AND `deleted_at` IS NULL'
            );
            $delOld->execute([
                'now_utc' => $nowUtc,
                'meal_id' => $mealId,
                'kind' => $kind,
            ]);

            $ins = $pdo->prepare(
                'INSERT INTO `meal_photos`
                 (`patient_id`, `meal_id`, `kind`, `photo_path`, `thumb_path`, `sort_order`, `created_at`, `updated_at`, `created_by`)
                 VALUES (:pid, :meal_id, :kind, :photo, :thumb, :ord, :created_at, :updated_at, :by)'
            );
            $ins->execute([
                'pid' => $patientId,
                'meal_id' => $mealId,
                'kind' => $kind,
                'photo' => $uploaded['filename'],
                'thumb' => $uploaded['thumb'],
                'ord' => $order,
                'created_at' => $nowUtc,
                'updated_at' => $nowUtc,
                'by' => $userId,
            ]);

            $saved[] = [
                'id' => (int) $pdo->lastInsertId(),
                'kind' => $kind,
                'photo_path' => $uploaded['filename'],
                'thumb_path' => $uploaded['thumb'],
            ];
            $order++;
        }

        return $saved;
    }
}
