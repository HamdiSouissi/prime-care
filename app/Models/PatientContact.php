<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * PatientContact model managing emergency and medical contacts for a patient.
 */
final class PatientContact extends Model
{
    protected string $table = 'patient_contacts';

    /** @var array<int, string> */
    protected array $fillable = [
        'patient_id',
        'category',
        'full_name',
        'relation_role',
        'organization',
        'phone',
        'email',
        'address',
        'notes',
        'is_emergency_priority',
        'sort_order',
        'created_by',
    ];

    /**
     * Retrieve all active contacts for a patient ordered by priority and sort_order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPatient(int $patientId = 1): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM `patient_contacts`
             WHERE `patient_id` = :pid AND `deleted_at` IS NULL
             ORDER BY `is_emergency_priority` DESC, `sort_order` ASC, `id` ASC'
        );
        $stmt->execute(['pid' => $patientId]);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }
}
