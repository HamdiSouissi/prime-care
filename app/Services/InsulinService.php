<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

/**
 * Looks up variable insulin doses strictly from the administrator's correction table
 * (never calculating any formula on its own) and manages injection site rotation.
 */
final class InsulinService
{
    public const DISCLAIMER = 'Dose selon le tableau du responsable';

    /**
     * Official injection sites in recommended rotation order.
     *
     * @var array<string, string>
     */
    public const INJECTION_SITES = [
        'abdomen_gauche' => 'Abdomen gauche',
        'abdomen_droit' => 'Abdomen droit',
        'cuisse_gauche' => 'Cuisse gauche',
        'cuisse_droite' => 'Cuisse droite',
        'bras_gauche' => 'Bras gauche (haut externe)',
        'bras_droit' => 'Bras droit (haut externe)',
    ];

    /**
     * Look up the prescribed insulin dose in the administrator's correction table
     * for a mandatory associated glycemia (in g/L).
     *
     * @param array<int, array<string, mixed>> $correctionTable
     * @return array{
     *   matched: bool,
     *   proposed_dose: float|null,
     *   bracket_label: string,
     *   note: string,
     *   disclaimer: string
     * }
     */
    public static function lookupDose(array $correctionTable, ?float $glycemiaGL): array
    {
        if ($glycemiaGL === null || $glycemiaGL <= 0.0) {
            throw new InvalidArgumentException('Une glycémie capillaire associée est obligatoire pour valider une insuline à dose variable.');
        }

        foreach ($correctionTable as $row) {
            $min = isset($row['min']) ? (float) $row['min'] : 0.0;
            $max = isset($row['max']) ? (float) $row['max'] : 99.0;
            $dose = isset($row['dose']) ? (float) $row['dose'] : 0.0;
            $note = (string) ($row['note'] ?? '');

            // Inclusive comparison with 0.001 epsilon for decimal boundaries
            if ($glycemiaGL + 0.0001 >= $min && $glycemiaGL - 0.0001 <= $max) {
                return [
                    'matched' => true,
                    'proposed_dose' => $dose,
                    'bracket_label' => sprintf(
                        '%s à %s g/L',
                        number_format($min, 2, ',', ''),
                        number_format($max, 2, ',', '')
                    ),
                    'note' => $note,
                    'disclaimer' => self::DISCLAIMER,
                ];
            }
        }

        return [
            'matched' => false,
            'proposed_dose' => null,
            'bracket_label' => 'Hors tranches du tableau',
            'note' => 'Valeur hors des tranches configurées — contacter le responsable ou le médecin.',
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    /**
     * Return the last used injection site and the recommended next site for rotation.
     *
     * @return array{
     *   last_site: string|null,
     *   last_site_label: string|null,
     *   suggested_site: string,
     *   suggested_site_label: string,
     *   sites: array<int, array{code: string, label: string, is_last: bool, is_suggested: bool}>
     * }
     */
    public static function getRotationSuggestion(int $patientId = 1): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT `injection_site`
             FROM `medication_intakes`
             WHERE `patient_id` = :patient_id
               AND `status` = \'given\'
               AND `injection_site` IS NOT NULL
               AND `injection_site` != \'\'
               AND `deleted_at` IS NULL
             ORDER BY `given_at` DESC, `id` DESC
             LIMIT 1'
        );
        $stmt->execute(['patient_id' => $patientId]);
        $lastRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastSite = $lastRow ? (string) $lastRow['injection_site'] : null;

        $keys = array_keys(self::INJECTION_SITES);
        $suggestedSite = $keys[0];

        if ($lastSite !== null) {
            $idx = array_search($lastSite, $keys, true);
            if ($idx !== false) {
                $nextIdx = ($idx + 1) % count($keys);
                $suggestedSite = $keys[$nextIdx];
            }
        }

        $sitesList = [];
        foreach (self::INJECTION_SITES as $code => $label) {
            $sitesList[] = [
                'code' => $code,
                'label' => $label,
                'is_last' => $code === $lastSite,
                'is_suggested' => $code === $suggestedSite,
            ];
        }

        return [
            'last_site' => $lastSite,
            'last_site_label' => $lastSite !== null && isset(self::INJECTION_SITES[$lastSite])
                ? self::INJECTION_SITES[$lastSite]
                : null,
            'suggested_site' => $suggestedSite,
            'suggested_site_label' => self::INJECTION_SITES[$suggestedSite],
            'sites' => $sitesList,
        ];
    }
}
