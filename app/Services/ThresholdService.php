<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Classifies medical measurements against type thresholds and glycemic contexts,
 * checks plausibility bounds, and converts between g/L and mg/dL.
 */
final class ThresholdService
{
    /**
     * Convert g/L to mg/dL (1.00 g/L = 100 mg/dL).
     */
    public static function gToMgDl(float $gPerLiter): int
    {
        return (int) round($gPerLiter * 100.0);
    }

    public static function gPerLToMgPerDl(float $gPerLiter): int
    {
        return self::gToMgDl($gPerLiter);
    }

    /**
     * Convert mg/dL to g/L (100 mg/dL = 1.00 g/L).
     */
    public static function mgDlToG(float $mgPerDeciliter): float
    {
        return round($mgPerDeciliter / 100.0, 2);
    }

    public static function mgPerDlToGPerL(float $mgPerDeciliter): float
    {
        return self::mgDlToG($mgPerDeciliter);
    }

    /**
     * Compute a percentage position (5..95) on the 3-zone glycemia gauge (0.40..3.00 g/L).
     */
    public static function computeGaugePercentage(float $valueGL): float
    {
        $clamped = max(0.40, min(3.00, $valueGL));
        $pct = (($clamped - 0.40) / 2.60) * 100.0;
        return max(5.0, min(95.0, round($pct, 1)));
    }

    /**
     * Normalize an input glycemia value into canonical g/L storage format.
     */
    public static function normalizeGlycemiaToGL(float $inputValue, string $unit = 'g/L'): float
    {
        if (strtolower(trim($unit)) === 'mg/dl') {
            return self::mgDlToG($inputValue);
        }
        return round($inputValue, 2);
    }

    /**
     * Format a canonical g/L glycemia value in the requested display unit.
     */
    public static function formatGlycemia(float $gPerLiter, string $unit = 'g/L'): string
    {
        if (strtolower(trim($unit)) === 'mg/dl') {
            return (string) self::gToMgDl($gPerLiter);
        }
        return number_format($gPerLiter, 2, ',', ' ');
    }

    /**
     * Check whether a value is within the plausibility bounds [min_value, max_value] of its measure type.
     *
     * @param array<string, mixed> $measureType
     * @return array{plausible: bool, requires_confirmation: bool, message: string|null}
     */
    public static function checkPlausibility(array $measureType, float $value, ?float $value2 = null): array
    {
        $min = isset($measureType['min_value']) ? (float) $measureType['min_value'] : 0.0;
        $max = isset($measureType['max_value']) ? (float) $measureType['max_value'] : 999.0;
        $label = (string) ($measureType['label'] ?? 'Mesure');
        $unit = (string) ($measureType['unit'] ?? '');

        if ($value < $min || $value > $max) {
            return [
                'plausible' => false,
                'requires_confirmation' => true,
                'message' => sprintf(
                    'La valeur saisie (%s %s) est hors des bornes habituelles (%s à %s %s) pour « %s ». Confirmez-vous cette saisie ?',
                    str_replace('.', ',', (string) $value),
                    $unit,
                    str_replace('.', ',', (string) $min),
                    str_replace('.', ',', (string) $max),
                    $unit,
                    $label
                ),
            ];
        }

        if (($measureType['code'] ?? '') === 'blood_pressure' && $value2 !== null) {
            if ($value2 < 30.0 || $value2 > 160.0 || $value2 >= $value) {
                return [
                    'plausible' => false,
                    'requires_confirmation' => true,
                    'message' => sprintf(
                        'La tension diastolique (%d mmHg) semble inhabituelle par rapport à la systolique (%d mmHg). Confirmez-vous cette saisie ?',
                        (int) round($value2),
                        (int) round($value)
                    ),
                ];
            }
        }

        return [
            'plausible' => true,
            'requires_confirmation' => false,
            'message' => null,
        ];
    }

    /**
     * Classify a measurement value into threshold_level ('crit_low'|'low'|'ok'|'high'|'crit_high')
     * and alert_level ('ok'|'warn'|'critical'), with accessible icon and French status label.
     *
     * @param array<string, mixed> $measureType
     * @param array<string, mixed>|null $patient
     * @return array{
     *   threshold_level: string,
     *   alert_level: string,
     *   label: string,
     *   icon: string,
     *   badge_class: string,
     *   show_hypo_protocol: bool,
     *   gauge_zone: string,
     *   gauge_step: int
     * }
     */
    public static function evaluate(
        array $measureType,
        float $value,
        string $context = 'routine',
        ?float $value2 = null,
        ?array $patient = null
    ): array {
        $code = (string) ($measureType['code'] ?? '');

        if ($code === 'glycemia') {
            return self::evaluateGlycemia($measureType, $value, $context, $patient);
        }

        $critLow = isset($measureType['crit_low']) && $measureType['crit_low'] !== null ? (float) $measureType['crit_low'] : null;
        $low = isset($measureType['low']) && $measureType['low'] !== null ? (float) $measureType['low'] : null;
        $high = isset($measureType['high']) && $measureType['high'] !== null ? (float) $measureType['high'] : null;
        $critHigh = isset($measureType['crit_high']) && $measureType['crit_high'] !== null ? (float) $measureType['crit_high'] : null;

        $level = self::classifySingleValue($value, $critLow, $low, $high, $critHigh);

        // For blood pressure, also check diastolic (value2) and pick the more severe level
        if ($code === 'blood_pressure' && $value2 !== null) {
            $secCritLow = isset($measureType['secondary_crit_low']) && $measureType['secondary_crit_low'] !== null
                ? (float) $measureType['secondary_crit_low']
                : 50.0;
            $secLow = isset($measureType['secondary_low']) && $measureType['secondary_low'] !== null
                ? (float) $measureType['secondary_low']
                : 60.0;
            $secHigh = isset($measureType['secondary_high']) && $measureType['secondary_high'] !== null
                ? (float) $measureType['secondary_high']
                : 90.0;
            $secCritHigh = isset($measureType['secondary_crit_high']) && $measureType['secondary_crit_high'] !== null
                ? (float) $measureType['secondary_crit_high']
                : 110.0;

            $level2 = self::classifySingleValue($value2, $secCritLow, $secLow, $secHigh, $secCritHigh);
            if (self::severityRank($level2) > self::severityRank($level)) {
                $level = $level2;
            }
        }

        return self::buildResult($level, $code, $value);
    }

    /**
     * Evaluate glycemia (in g/L) taking context ('fasting', 'pre_meal' vs 'post_meal', 'bedtime', 'routine') into account.
     *
     * @param array<string, mixed> $measureType
     * @param array<string, mixed>|null $patient
     * @return array{
     *   threshold_level: string,
     *   alert_level: string,
     *   label: string,
     *   icon: string,
     *   badge_class: string,
     *   show_hypo_protocol: bool,
     *   gauge_zone: string,
     *   gauge_step: int
     * }
     */
    private static function evaluateGlycemia(
        array $measureType,
        float $valueGL,
        string $context,
        ?array $patient = null
    ): array {
        $critLow = isset($patient['hypo_threshold_g_l'])
            ? (float) $patient['hypo_threshold_g_l']
            : (isset($measureType['crit_low']) && $measureType['crit_low'] !== null ? (float) $measureType['crit_low'] : 0.70);

        $fastingMin = isset($patient['target_glycemia_fasting_min'])
            ? (float) $patient['target_glycemia_fasting_min']
            : (isset($measureType['low']) && $measureType['low'] !== null ? (float) $measureType['low'] : 0.80);

        $fastingMax = isset($patient['target_glycemia_fasting_max'])
            ? (float) $patient['target_glycemia_fasting_max']
            : 1.30;

        $postMealMax = isset($patient['target_glycemia_postmeal_max'])
            ? (float) $patient['target_glycemia_postmeal_max']
            : (isset($measureType['high']) && $measureType['high'] !== null ? (float) $measureType['high'] : 1.80);

        $critHigh = isset($patient['hyper_threshold_g_l'])
            ? (float) $patient['hyper_threshold_g_l']
            : (isset($measureType['crit_high']) && $measureType['crit_high'] !== null ? (float) $measureType['crit_high'] : 2.50);

        $isFastingContext = in_array($context, ['fasting', 'pre_meal', 'a_jeun', 'avant_repas'], true);
        $highThreshold = $isFastingContext ? $fastingMax : $postMealMax;

        if ($valueGL < $critLow) {
            $level = 'crit_low';
        } elseif ($valueGL < $fastingMin) {
            $level = 'low';
        } elseif ($valueGL > $critHigh) {
            $level = 'crit_high';
        } elseif ($valueGL > $highThreshold) {
            $level = 'high';
        } else {
            $level = 'ok';
        }

        // Compute discrete gauge step 0..20 (5% increments for CSS classes .pc-gauge__cursor--step-0 .. 20)
        $clamped = max(0.40, min(3.00, $valueGL));
        $step = (int) round((($clamped - 0.40) / 2.60) * 20);
        $step = max(0, min(20, $step));

        $res = self::buildResult($level, 'glycemia', $valueGL);
        $res['gauge_step'] = $step;
        $res['gauge_zone'] = match ($level) {
            'crit_low', 'low' => 'hypo',
            'ok' => 'target',
            default => 'hyper',
        };

        return $res;
    }

    private static function classifySingleValue(
        float $value,
        ?float $critLow,
        ?float $low,
        ?float $high,
        ?float $critHigh
    ): string {
        if ($critLow !== null && $value < $critLow) {
            return 'crit_low';
        }
        if ($low !== null && $value < $low) {
            return 'low';
        }
        if ($critHigh !== null && $value > $critHigh) {
            return 'crit_high';
        }
        if ($high !== null && $value > $high) {
            return 'high';
        }
        return 'ok';
    }

    private static function severityRank(string $thresholdLevel): int
    {
        return match ($thresholdLevel) {
            'crit_low', 'crit_high' => 3,
            'low', 'high' => 2,
            default => 1,
        };
    }

    /**
     * @return array{
     *   threshold_level: string,
     *   alert_level: string,
     *   label: string,
     *   icon: string,
     *   badge_class: string,
     *   show_hypo_protocol: bool,
     *   gauge_zone: string,
     *   gauge_step: int
     * }
     */
    private static function buildResult(string $thresholdLevel, string $code, float $value): array
    {
        $isGlycemia = $code === 'glycemia';

        $alertLevel = match ($thresholdLevel) {
            'crit_low', 'crit_high' => 'critical',
            'low', 'high' => 'warn',
            default => 'ok',
        };

        $label = match ($thresholdLevel) {
            'crit_low' => $isGlycemia ? 'Hypoglycémie critique' : 'Critique bas',
            'low' => $isGlycemia ? 'Un peu basse' : 'Bas (à surveiller)',
            'high' => $isGlycemia ? 'Au-dessus de la cible' : 'Élevé (à surveiller)',
            'crit_high' => $isGlycemia ? 'Hyperglycémie critique' : 'Critique élevé',
            default => 'Dans la cible',
        };

        $icon = match ($alertLevel) {
            'critical' => 'alert-octagon',
            'warn' => 'alert-triangle',
            default => 'check-circle',
        };

        $badgeClass = match ($alertLevel) {
            'critical' => 'pc-status-pill--critical',
            'warn' => 'pc-status-pill--warn',
            default => 'pc-status-pill--ok',
        };

        return [
            'threshold_level' => $thresholdLevel,
            'alert_level' => $alertLevel,
            'label' => $label,
            'icon' => $icon,
            'badge_class' => $badgeClass,
            'show_hypo_protocol' => $isGlycemia && ($thresholdLevel === 'crit_low' || $value < 0.70),
            'gauge_zone' => 'target',
            'gauge_step' => 10,
        ];
    }
}
