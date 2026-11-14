<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Patient model managing medical profile, glycemic targets, permanent instructions, and hypoglycemia protocol.
 */
final class Patient extends Model
{
    protected string $table = 'patients';

    /** @var array<int, string> */
    protected array $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'photo_path',
        'address',
        'access_code',
        'ssn_masked',
        'diabetes_type',
        'blood_group',
        'reference_weight_kg',
        'height_cm',
        'target_glycemia_fasting_min',
        'target_glycemia_fasting_max',
        'target_glycemia_postmeal_max',
        'hypo_threshold_g_l',
        'hyper_threshold_g_l',
        'allergies',
        'medical_history',
        'permanent_instructions',
        'hypo_protocol',
        'created_by',
    ];

    /**
     * Get the primary active patient record (patient_id = 1 by default).
     *
     * @return array<string, mixed>|null
     */
    public function current(int $patientId = 1): ?array
    {
        return $this->find($patientId);
    }
}
