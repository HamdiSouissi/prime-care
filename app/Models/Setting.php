<?php

declare(strict_types=1);

namespace App\Models;

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
     * Get a decoded setting value by key.
     */
    public function getValue(string $key, mixed $default = null, int $patientId = 1): mixed
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
}
