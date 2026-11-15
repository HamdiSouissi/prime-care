<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Model;

/**
 * Key/value JSON settings model.
 */
final class Setting extends Model
{
    protected string $table = 'settings';

    /** @var array<int, string> */
    protected array $fillable = [
        'patient_id',
        'setting_key',
        'setting_value',
        'created_by',
    ];

    /**
     * Get a decoded setting value by key (callable both statically and on an instance).
     */
    public static function getValue(string $key, mixed $default = null, int $patientId = 1): mixed
    {
        $stmt = Database::connection()->prepare(
            'SELECT `setting_value` FROM `settings`
             WHERE `patient_id` = :pid AND `setting_key` = :key AND `deleted_at` IS NULL
             LIMIT 1'
        );
        $stmt->execute(['pid' => $patientId, 'key' => $key]);
        $val = $stmt->fetchColumn();

        if (!is_string($val)) {
            return $default;
        }

        return json_decode($val, true) ?? $default;
    }

    /**
     * Upsert a JSON setting value by key.
     */
    public static function setValue(string $key, mixed $value, int $patientId = 1): void
    {
        $pdo = Database::connection();
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $nowUtc = gmdate('Y-m-d H:i:s');
        $userId = Auth::id() ?? 1;

        $stmt = $pdo->prepare(
            'SELECT `id` FROM `settings` WHERE `patient_id` = :pid AND `setting_key` = :key LIMIT 1'
        );
        $stmt->execute(['pid' => $patientId, 'key' => $key]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            $upd = $pdo->prepare(
                'UPDATE `settings`
                 SET `setting_value` = :val, `updated_at` = :u, `deleted_at` = NULL
                 WHERE `id` = :id'
            );
            $upd->execute(['val' => $json, 'u' => $nowUtc, 'id' => (int) $id]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO `settings`
                 (`patient_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`, `created_by`)
                 VALUES (:pid, :key, :val, :c, :u, :by)'
            );
            $ins->execute([
                'pid' => $patientId,
                'key' => $key,
                'val' => $json,
                'c' => $nowUtc,
                'u' => $nowUtc,
                'by' => $userId,
            ]);
        }
    }

    public static function get(string $key, mixed $default = null, int $patientId = 1): mixed
    {
        return self::getValue($key, $default, $patientId);
    }

    public static function set(string $key, mixed $value, int $actorId = 1, int $patientId = 1): void
    {
        self::setValue($key, $value, $patientId);
    }
}
