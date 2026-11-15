<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ThresholdService;

final class ThresholdServiceTest
{
    public static function run(callable $assert): void
    {
        $glyType = [
            'code' => 'glycemia',
            'crit_low' => 0.70,
            'low' => 0.80,
            'high' => 1.80,
            'crit_high' => 2.50,
            'min_value' => 0.20,
            'max_value' => 6.00,
        ];
        $patient = [
            'target_glycemia_fasting_min' => 0.80,
            'target_glycemia_fasting_max' => 1.30,
            'target_glycemia_postmeal_max' => 1.80,
        ];

        // 1. Hypoglycemia < 0.70 g/L -> crit_low / critical + hypo protocol
        $hypo = ThresholdService::evaluate($glyType, 0.58, 'fasting', null, $patient);
        $assert($hypo['threshold_level'] === 'crit_low', 'ThresholdService: 0,58 g/L est classé crit_low');
        $assert($hypo['alert_level'] === 'critical', 'ThresholdService: crit_low correspond à alert_level=critical');
        $assert($hypo['show_hypo_protocol'] === true, 'ThresholdService: glycémie < 0,70 g/L active le protocole d’hypoglycémie');

        // 2. Mild low (0.70 - 0.79) -> low / warn
        $low = ThresholdService::evaluate($glyType, 0.75, 'fasting', null, $patient);
        $assert($low['threshold_level'] === 'low' && $low['alert_level'] === 'warn', 'ThresholdService: 0,75 g/L est classé low / warn');

        // 3. Normal fasting (1.05 g/L) -> ok / ok
        $ok = ThresholdService::evaluate($glyType, 1.05, 'fasting', null, $patient);
        $assert($ok['threshold_level'] === 'ok' && $ok['alert_level'] === 'ok', 'ThresholdService: 1,05 g/L à jeun est classé ok');

        // 4. Context differentiation: 1.55 g/L is high when fasting (> 1.30) but ok in post_meal (<= 1.80)
        $fasting155 = ThresholdService::evaluate($glyType, 1.55, 'fasting', null, $patient);
        $postMeal155 = ThresholdService::evaluate($glyType, 1.55, 'post_meal', null, $patient);
        $assert($fasting155['threshold_level'] === 'high', 'ThresholdService: 1,55 g/L à jeun (> 1,30) est classé high');
        $assert($postMeal155['threshold_level'] === 'ok', 'ThresholdService: 1,55 g/L en post-prandial (<= 1,80) est classé ok');

        // 5. Severe hyperglycemia (> 2.50 g/L) -> crit_high / critical
        $hyper = ThresholdService::evaluate($glyType, 2.75, 'post_meal', null, $patient);
        $assert($hyper['threshold_level'] === 'crit_high' && $hyper['alert_level'] === 'critical', 'ThresholdService: 2,75 g/L est classé crit_high / critical');

        // 6. Plausibility bounds
        $plausOk = ThresholdService::checkPlausibility($glyType, 1.20);
        $plausOut = ThresholdService::checkPlausibility($glyType, 9.50);
        $assert($plausOk['plausible'] === true, 'ThresholdService: 1,20 g/L est dans les bornes plausibles');
        $assert($plausOut['plausible'] === false, 'ThresholdService: 9,50 g/L (> max 6,00) exige une confirmation');

        // 7. Unit conversion g/L <-> mg/dL
        $assert(ThresholdService::gPerLToMgPerDl(1.10) === 110, 'ThresholdService: 1,10 g/L = 110 mg/dL');
        $assert(abs(ThresholdService::mgPerDlToGPerL(145) - 1.45) < 0.001, 'ThresholdService: 145 mg/dL = 1,45 g/L');
        $assert(ThresholdService::formatGlycemia(1.10, 'mg/dL') === '110', 'ThresholdService: formatGlycemia en mg/dL affiche un entier');
        $assert(ThresholdService::formatGlycemia(1.10, 'g/L') === '1,10', 'ThresholdService: formatGlycemia en g/L affiche 2 décimales virgule');
    }
}
