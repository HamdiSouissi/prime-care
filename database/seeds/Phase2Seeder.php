<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Config;
use App\Core\Database;
use App\Services\InsulinService;
use App\Services\ScheduleService;
use App\Services\ThresholdService;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Seeds Phase 2 medical data over the last 14 days:
 * - 9 default measure types + schedules (glycemia 4x/day + morning blood pressure)
 * - 5 medications & prescriptions (Metformine, Insuline lente 18 UI, Insuline rapide variable, Amlodipine, Paracétamol si besoin)
 * - 14 days of realistic measures (including 2 hypoglycemias and several hyperglycemias), ~95% given intakes,
 *   4 meals/day with GD-generated before/after photos, care items/checks, notes, and shift handovers.
 */
final class Phase2Seeder
{
    public static function run(): void
    {
        $pdo = Database::connection();
        $nowUtc = gmdate('Y-m-d H:i:s');
        $tzName = (string) Config::get('app.timezone', 'Europe/Paris');
        $parisTz = new DateTimeZone($tzName);
        $utcTz = new DateTimeZone('UTC');

        $nowLocalDt = (new DateTimeImmutable('now', $utcTz))->setTimezone($parisTz);
        $todayLocalStr = $nowLocalDt->format('Y-m-d');
        $start14DaysStr = $nowLocalDt->sub(new DateInterval('P13D'))->format('Y-m-d');

        // Clean existing Phase 2 data idempotently
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'note_reads', 'notes', 'care_checks', 'care_items',
            'meal_photos', 'meals', 'food_favorites',
            'medication_intakes', 'prescription_times', 'prescriptions', 'medications',
            'measure_expectations', 'measure_schedules', 'measures', 'measure_types',
        ] as $tbl) {
            $pdo->exec("TRUNCATE TABLE `{$tbl}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Additional Phase 2 settings
        $setStmt = $pdo->prepare(
            'INSERT INTO `settings` (`patient_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :k, :v, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = VALUES(`updated_at`)'
        );
        $setStmt->execute([
            'k' => 'intake_window_min',
            'v' => json_encode(['minutes' => 30], JSON_UNESCAPED_UNICODE),
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
        ]);

        // 1. Seed the 9 default measure types
        $measureTypes = [
            [
                'code' => 'glycemia',
                'label' => 'Glycémie capillaire',
                'unit' => 'g/L',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 2,
                'min_value' => 0.20,
                'max_value' => 6.00,
                'crit_low' => 0.70,
                'low' => 0.80,
                'high' => 1.80,
                'crit_high' => 2.50,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'droplet',
                'is_secondary' => 0,
                'sort_order' => 1,
            ],
            [
                'code' => 'blood_pressure',
                'label' => 'Tension artérielle',
                'unit' => 'mmHg',
                'secondary_label' => 'Diastolique',
                'secondary_unit' => 'mmHg',
                'tertiary_label' => 'Pouls',
                'tertiary_unit' => 'bpm',
                'decimals' => 0,
                'min_value' => 50,
                'max_value' => 260,
                'crit_low' => 90,
                'low' => 100,
                'high' => 145,
                'crit_high' => 180,
                'secondary_crit_low' => 50,
                'secondary_low' => 60,
                'secondary_high' => 90,
                'secondary_crit_high' => 110,
                'icon_name' => 'heart-pulse',
                'is_secondary' => 0,
                'sort_order' => 2,
            ],
            [
                'code' => 'temperature',
                'label' => 'Température corporelle',
                'unit' => '°C',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 1,
                'min_value' => 33.0,
                'max_value' => 42.5,
                'crit_low' => 35.0,
                'low' => 36.0,
                'high' => 37.8,
                'crit_high' => 38.5,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'thermometer',
                'is_secondary' => 0,
                'sort_order' => 3,
            ],
            [
                'code' => 'spo2',
                'label' => 'Saturation en oxygène (SpO2)',
                'unit' => '%',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 0,
                'min_value' => 50,
                'max_value' => 100,
                'crit_low' => 90,
                'low' => 94,
                'high' => 100,
                'crit_high' => 101,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'wind',
                'is_secondary' => 0,
                'sort_order' => 4,
            ],
            [
                'code' => 'weight',
                'label' => 'Poids',
                'unit' => 'kg',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 1,
                'min_value' => 35.0,
                'max_value' => 180.0,
                'crit_low' => 66.0,
                'low' => 69.5,
                'high' => 75.5,
                'crit_high' => 79.0,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'activity',
                'is_secondary' => 0,
                'sort_order' => 5,
            ],
            [
                'code' => 'heart_rate',
                'label' => 'Fréquence cardiaque',
                'unit' => 'bpm',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 0,
                'min_value' => 30,
                'max_value' => 220,
                'crit_low' => 45,
                'low' => 55,
                'high' => 95,
                'crit_high' => 120,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'heart-pulse',
                'is_secondary' => 1,
                'sort_order' => 6,
            ],
            [
                'code' => 'hydration',
                'label' => 'Hydratation journalière',
                'unit' => 'mL',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 0,
                'min_value' => 50,
                'max_value' => 4000,
                'crit_low' => 700,
                'low' => 1200,
                'high' => 2500,
                'crit_high' => 3500,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'droplet',
                'is_secondary' => 1,
                'sort_order' => 7,
            ],
            [
                'code' => 'pain',
                'label' => 'Douleur (EVA)',
                'unit' => '/10',
                'secondary_label' => null,
                'secondary_unit' => null,
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 0,
                'min_value' => 0,
                'max_value' => 10,
                'crit_low' => null,
                'low' => null,
                'high' => 3,
                'crit_high' => 6,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'alert-triangle',
                'is_secondary' => 1,
                'sort_order' => 8,
            ],
            [
                'code' => 'bowel_diuresis',
                'label' => 'Selles & Diurèse',
                'unit' => 'passages',
                'secondary_label' => 'Mictions',
                'secondary_unit' => 'fois',
                'tertiary_label' => null,
                'tertiary_unit' => null,
                'decimals' => 0,
                'min_value' => 0,
                'max_value' => 15,
                'crit_low' => null,
                'low' => 1,
                'high' => 5,
                'crit_high' => 8,
                'secondary_crit_low' => null,
                'secondary_low' => null,
                'secondary_high' => null,
                'secondary_crit_high' => null,
                'icon_name' => 'clipboard-list',
                'is_secondary' => 1,
                'sort_order' => 9,
            ],
        ];

        $mtStmt = $pdo->prepare(
            'INSERT INTO `measure_types`
             (`patient_id`, `code`, `label`, `unit`, `secondary_label`, `secondary_unit`, `tertiary_label`, `tertiary_unit`,
              `decimals`, `min_value`, `max_value`, `crit_low`, `low`, `high`, `crit_high`,
              `secondary_crit_low`, `secondary_low`, `secondary_high`, `secondary_crit_high`,
              `icon_name`, `is_secondary`, `is_custom`, `is_active`, `sort_order`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :code, :label, :unit, :secondary_label, :secondary_unit, :tertiary_label, :tertiary_unit,
              :decimals, :min_value, :max_value, :crit_low, :low, :high, :crit_high,
              :secondary_crit_low, :secondary_low, :secondary_high, :secondary_crit_high,
              :icon_name, :is_secondary, 0, 1, :sort_order, :created_at, :updated_at, 1)'
        );

        $typeIdsByCode = [];
        foreach ($measureTypes as $mt) {
            $mtStmt->execute(array_merge($mt, ['created_at' => $nowUtc, 'updated_at' => $nowUtc]));
            $typeIdsByCode[$mt['code']] = (int) $pdo->lastInsertId();
        }

        // 2. Seed measure_schedules: glycemia at 07:00, 11:30, 18:30, 22:00 + blood_pressure at 07:30
        $schedules = [
            ['type_id' => $typeIdsByCode['glycemia'], 'time' => '07:00', 'context' => 'fasting', 'label' => 'Glycémie à jeun (7h00)'],
            ['type_id' => $typeIdsByCode['blood_pressure'], 'time' => '07:30', 'context' => 'routine', 'label' => 'Tension artérielle du matin (7h30)'],
            ['type_id' => $typeIdsByCode['glycemia'], 'time' => '11:30', 'context' => 'pre_meal', 'label' => 'Glycémie avant déjeuner (11h30)'],
            ['type_id' => $typeIdsByCode['glycemia'], 'time' => '18:30', 'context' => 'pre_meal', 'label' => 'Glycémie avant dîner (18h30)'],
            ['type_id' => $typeIdsByCode['glycemia'], 'time' => '22:00', 'context' => 'bedtime', 'label' => 'Glycémie au coucher (22h00)'],
        ];

        $msStmt = $pdo->prepare(
            'INSERT INTO `measure_schedules`
             (`patient_id`, `measure_type_id`, `scheduled_time`, `days_mask`, `context`, `label`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :type_id, :time, \'1,2,3,4,5,6,7\', :context, :label, 1, :created_at, :updated_at, 1)'
        );
        foreach ($schedules as $s) {
            $msStmt->execute(array_merge($s, ['created_at' => $nowUtc, 'updated_at' => $nowUtc]));
        }

        // 3. Generate demo images in GD (meal photos + prescription document)
        $demoPhotos = self::generateDemoPhotos();

        // 4. Seed medications, prescriptions & prescription_times
        $correctionTable = [
            ['min' => 0.00, 'max' => 0.69, 'dose' => 0,  'note' => 'Hypoglycémie (< 0,70 g/L) : NE PAS INJECTER, appliquer le protocole de resucrage immédiat.'],
            ['min' => 0.70, 'max' => 1.30, 'dose' => 4,  'note' => 'Cible pré-prandiale atteinte : dose habituelle de base (4 UI).'],
            ['min' => 1.31, 'max' => 1.80, 'dose' => 6,  'note' => 'Correction légère (+2 UI soit 6 UI).'],
            ['min' => 1.81, 'max' => 2.50, 'dose' => 8,  'note' => 'Correction modérée (+4 UI soit 8 UI) + vérifier l’hydratation.'],
            ['min' => 2.51, 'max' => 6.00, 'dose' => 10, 'note' => 'Hyperglycémie (> 2,50 g/L) : injecter 10 UI et prévenir Karim Benali.'],
        ];

        $medsData = [
            [
                'name' => 'Metformine 1000 mg',
                'dci' => 'Metformine chlorhydrate',
                'form' => 'comprimé pelliculé',
                'dosage' => 1000,
                'unit' => 'mg',
                'instructions' => 'Prendre au cours ou à la fin du repas avec un grand verre d’eau.',
                'rx' => [
                    'dose' => 1000,
                    'unit' => 'mg',
                    'as_needed' => 0,
                    'min_interval_h' => null,
                    'insulin_kind' => null,
                    'dose_mode' => 'fixe',
                    'correction_table' => null,
                    'times' => [
                        ['time' => '08:00', 'moment' => 'matin', 'order' => 1],
                        ['time' => '19:30', 'moment' => 'soir', 'order' => 2],
                    ],
                ],
            ],
            [
                'name' => 'Insuline lente Lantus SoloStar',
                'dci' => 'Insuline glargine 100 UI/mL',
                'form' => 'stylo prérempli injectable',
                'dosage' => 18,
                'unit' => 'UI',
                'instructions' => 'Injection sous-cutanée à heure fixe le soir (21h00). Alterner systématiquement le site d’injection.',
                'rx' => [
                    'dose' => 18,
                    'unit' => 'UI',
                    'as_needed' => 0,
                    'min_interval_h' => null,
                    'insulin_kind' => 'lente',
                    'dose_mode' => 'fixe',
                    'correction_table' => null,
                    'times' => [
                        ['time' => '21:00', 'moment' => 'coucher', 'order' => 1],
                    ],
                ],
            ],
            [
                'name' => 'Insuline rapide NovoRapid FlexPen',
                'dci' => 'Insuline asparte 100 UI/mL',
                'form' => 'stylo prérempli injectable',
                'dosage' => 4,
                'unit' => 'UI',
                'instructions' => 'Injecter immédiatement avant le repas selon la glycémie capillaire et le tableau de correction du responsable.',
                'rx' => [
                    'dose' => 4,
                    'unit' => 'UI',
                    'as_needed' => 0,
                    'min_interval_h' => null,
                    'insulin_kind' => 'rapide',
                    'dose_mode' => 'tableau',
                    'correction_table' => json_encode($correctionTable, JSON_UNESCAPED_UNICODE),
                    'times' => [
                        ['time' => '07:45', 'moment' => 'matin', 'order' => 1],
                        ['time' => '12:00', 'moment' => 'midi', 'order' => 2],
                        ['time' => '19:00', 'moment' => 'soir', 'order' => 3],
                    ],
                ],
            ],
            [
                'name' => 'Amlodipine 5 mg',
                'dci' => 'Amlodipine bésilate',
                'form' => 'gélule',
                'dosage' => 5,
                'unit' => 'mg',
                'instructions' => 'Traitement antihypertenseur à prendre le matin après le contrôle tensionnel.',
                'rx' => [
                    'dose' => 5,
                    'unit' => 'mg',
                    'as_needed' => 0,
                    'min_interval_h' => null,
                    'insulin_kind' => null,
                    'dose_mode' => 'fixe',
                    'correction_table' => null,
                    'times' => [
                        ['time' => '08:00', 'moment' => 'matin', 'order' => 1],
                    ],
                ],
            ],
            [
                'name' => 'Paracétamol 1 g',
                'dci' => 'Paracétamol',
                'form' => 'comprimé sécable',
                'dosage' => 1000,
                'unit' => 'mg',
                'instructions' => 'En cas de douleurs articulaires (arthrose) ou fièvre. Respecter impérativement 6 heures minimum entre 2 prises (max 3 g/24h).',
                'rx' => [
                    'dose' => 1000,
                    'unit' => 'mg',
                    'as_needed' => 1,
                    'min_interval_h' => 6,
                    'insulin_kind' => null,
                    'dose_mode' => 'fixe',
                    'correction_table' => null,
                    'times' => [],
                ],
            ],
        ];

        $medStmt = $pdo->prepare(
            'INSERT INTO `medications`
             (`patient_id`, `name`, `dci`, `form`, `default_dosage`, `default_unit`, `instructions`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :name, :dci, :form, :dosage, :unit, :instructions, 1, :created_at, :updated_at, 1)'
        );
        $rxStmt = $pdo->prepare(
            'INSERT INTO `prescriptions`
             (`patient_id`, `medication_id`, `dose`, `unit`, `start_date`, `end_date`, `ends_on_rx`, `days_mask`,
              `as_needed`, `min_interval_h`, `max_per_day`, `insulin_kind`, `dose_mode`, `correction_table`,
              `instructions`, `document_path`, `document_mime`, `document_original_name`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :med_id, :dose, :unit, :start_date, NULL, 1, \'1,2,3,4,5,6,7\',
              :as_needed, :min_interval_h, :max_per_day, :insulin_kind, :dose_mode, :correction_table,
              :instructions, :doc_path, \'image/jpeg\', \'Ordonnance_CHU_Mondor_Benali.jpg\', 1, :created_at, :updated_at, 1)'
        );
        $ptStmt = $pdo->prepare(
            'INSERT INTO `prescription_times`
             (`patient_id`, `prescription_id`, `scheduled_time`, `moment_label`, `dose_override`, `sort_order`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :rx_id, :time, :moment, NULL, :ord, :created_at, :updated_at, 1)'
        );

        $rxStartDate = $nowLocalDt->sub(new DateInterval('P60D'))->format('Y-m-d');
        $paracetamolRxId = null;

        foreach ($medsData as $m) {
            $medStmt->execute([
                'name' => $m['name'],
                'dci' => $m['dci'],
                'form' => $m['form'],
                'dosage' => $m['dosage'],
                'unit' => $m['unit'],
                'instructions' => $m['instructions'],
                'created_at' => $nowUtc,
                'updated_at' => $nowUtc,
            ]);
            $medId = (int) $pdo->lastInsertId();

            $rx = $m['rx'];
            $rxStmt->execute([
                'med_id' => $medId,
                'dose' => $rx['dose'],
                'unit' => $rx['unit'],
                'start_date' => $rxStartDate,
                'as_needed' => $rx['as_needed'],
                'min_interval_h' => $rx['min_interval_h'],
                'max_per_day' => $rx['as_needed'] ? 3 : null,
                'insulin_kind' => $rx['insulin_kind'],
                'dose_mode' => $rx['dose_mode'],
                'correction_table' => $rx['correction_table'],
                'instructions' => $m['instructions'],
                'doc_path' => $demoPhotos['rx_doc'],
                'created_at' => $nowUtc,
                'updated_at' => $nowUtc,
            ]);
            $rxId = (int) $pdo->lastInsertId();
            if ($rx['as_needed'] === 1) {
                $paracetamolRxId = $rxId;
            }

            foreach ($rx['times'] as $t) {
                $ptStmt->execute([
                    'rx_id' => $rxId,
                    'time' => $t['time'],
                    'moment' => $t['moment'],
                    'ord' => $t['order'],
                    'created_at' => $nowUtc,
                    'updated_at' => $nowUtc,
                ]);
            }
        }

        // 5. Generate all scheduled occurrences from -13 days to +48h via ScheduleService
        $rangeFromUtc = ScheduleService::localParisToUtc($start14DaysStr, '00:00');
        $rangeToUtc = (new DateTimeImmutable('now', $utcTz))->add(new DateInterval('PT48H'))->format('Y-m-d H:i:s');
        ScheduleService::generateForRange($rangeFromUtc, $rangeToUtc, 1);

        // 6. Seed food_favorites & care_items
        $favorites = [
            ['label' => 'Porridge d’avoine sans sucre ajouté + compote de pommes + thé vert', 'type' => 'breakfast', 'carbs' => 38, 'drink' => 'Thé vert sans sucre (200 mL)', 'ord' => 1],
            ['label' => 'Pain complet grillé (2 tranches) + beurre doux + fromage blanc + café', 'type' => 'breakfast', 'carbs' => 32, 'drink' => 'Café léger + eau (250 mL)', 'ord' => 2],
            ['label' => 'Filet de cabillaud vapeur + haricots verts & quinoa + yaourt nature', 'type' => 'lunch', 'carbs' => 45, 'drink' => 'Eau plate (300 mL)', 'ord' => 3],
            ['label' => 'Blanc de poulet rôti + purée de patate douce & courgettes + poire', 'type' => 'lunch', 'carbs' => 48, 'drink' => 'Eau plate (300 mL)', 'ord' => 4],
            ['label' => 'Compote pomme-cannelle sans sucre ajouté + 2 amandes + tisane', 'type' => 'snack', 'carbs' => 18, 'drink' => 'Tisane tilleul (200 mL)', 'ord' => 5],
            ['label' => 'Velouté de légumes maison + omelette aux fines herbes + pain complet', 'type' => 'dinner', 'carbs' => 35, 'drink' => 'Eau plate + infusion camomille (250 mL)', 'ord' => 6],
            ['label' => 'Pavé de saumon au four + brocolis & lentilles corail + fromage blanc', 'type' => 'dinner', 'carbs' => 42, 'drink' => 'Eau plate (250 mL)', 'ord' => 7],
        ];
        $favStmt = $pdo->prepare(
            'INSERT INTO `food_favorites`
             (`patient_id`, `label`, `meal_type`, `default_carbs_g`, `default_drink`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :label, :type, :carbs, :drink, :ord, 1, :created_at, :updated_at, 1)'
        );
        foreach ($favorites as $f) {
            $favStmt->execute(array_merge($f, ['created_at' => $nowUtc, 'updated_at' => $nowUtc]));
        }

        $careItems = [
            ['code' => 'morning_wash', 'label' => 'Toilette complète & habillage', 'icon' => 'sparkles', 'cat' => 'hygiene', 'ord' => 1],
            ['code' => 'diabetic_feet', 'label' => 'Inspection & séchage des pieds diabétiques', 'icon' => 'shield-check', 'cat' => 'medical', 'ord' => 2],
            ['code' => 'hydration_glass', 'label' => 'Verre d’eau proposé (200 mL)', 'icon' => 'droplet', 'cat' => 'hydration', 'ord' => 3],
            ['code' => 'change_protection', 'label' => 'Change & prévention escarres', 'icon' => 'heart-pulse', 'cat' => 'hygiene', 'ord' => 4],
            ['code' => 'walker_walk', 'label' => 'Marche accompagnée (déambulateur 15 min)', 'icon' => 'activity', 'cat' => 'mobility', 'ord' => 5],
            ['code' => 'bedtime_comfort', 'label' => 'Installation nuit & sécurisation du lit', 'icon' => 'moon', 'cat' => 'sleep', 'ord' => 6],
        ];
        $ciStmt = $pdo->prepare(
            'INSERT INTO `care_items`
             (`patient_id`, `code`, `label`, `icon_name`, `category`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :code, :label, :icon, :cat, :ord, 1, :created_at, :updated_at, 1)'
        );
        $careItemIds = [];
        foreach ($careItems as $ci) {
            $ciStmt->execute(array_merge($ci, ['created_at' => $nowUtc, 'updated_at' => $nowUtc]));
            $careItemIds[$ci['code']] = (int) $pdo->lastInsertId();
        }

        // 7. Populate 14 days of measures, meals, care checks, notes & fulfill expectations/intakes
        $measureInsertStmt = $pdo->prepare(
            'INSERT INTO `measures`
             (`patient_id`, `measure_type_id`, `value`, `value2`, `value3`, `unit_recorded`, `context`, `measured_at`,
              `comment`, `alert_level`, `threshold_level`, `recorded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :type_id, :val1, :val2, :val3, :unit, :context, :measured_at,
              :comment, :alert_level, :threshold_level, :by, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)'
        );

        $mealInsertStmt = $pdo->prepare(
            'INSERT INTO `meals`
             (`patient_id`, `meal_type`, `eaten_at`, `description`, `portion_eaten`, `carbs_g`, `drink_type`, `drink_ml`,
              `comment`, `recorded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :type, :eaten_at, :desc, :portion, :carbs, :drink, :drink_ml, :comment, :by, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)'
        );
        $mealPhotoStmt = $pdo->prepare(
            'INSERT INTO `meal_photos`
             (`patient_id`, `meal_id`, `kind`, `photo_path`, `thumb_path`, `sort_order`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :meal_id, :kind, :photo, :thumb, :ord, :at, UTC_TIMESTAMP(), :by)'
        );

        $careCheckStmt = $pdo->prepare(
            'INSERT INTO `care_checks`
             (`patient_id`, `care_item_id`, `checked_at`, `comment`, `performed_by`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :item_id, :at, :comment, :by, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 1)'
        );

        $noteStmt = $pdo->prepare(
            'INSERT INTO `notes`
             (`patient_id`, `category`, `priority`, `title`, `content`, `is_handover`, `handover_summary`, `recorded_by`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :cat, :prio, :title, :content, :handover, :summary, :by, :at, UTC_TIMESTAMP(), 1)'
        );

        $glycemiaSeries = [
            // 14 days × 4 readings/day (includes 2 hypoglycemias on dayIdx 8 and dayIdx 11, and hyperglycemias on dayIdx 3, 6, 10)
            0  => [1.04, 1.18, 1.42, 1.24],
            1  => [0.98, 1.22, 1.36, 1.19],
            2  => [1.12, 1.28, 1.54, 1.31],
            3  => [1.15, 1.92, 1.48, 1.26], // Hyper 1,92 g/L
            4  => [0.95, 1.14, 1.38, 1.21],
            5  => [1.08, 1.25, 1.44, 1.29],
            6  => [1.21, 1.34, 2.58, 1.62], // Crit hyper 2,58 g/L
            7  => [1.02, 1.19, 1.41, 1.22],
            8  => [0.96, 0.62, 1.35, 1.18], // Hypo #1 : 0,62 g/L at 11:30
            9  => [1.05, 1.21, 1.46, 1.25],
            10 => [1.18, 2.12, 1.52, 1.33], // Hyper 2,12 g/L
            11 => [1.01, 1.17, 0.65, 1.24], // Hypo #2 : 0,65 g/L at 18:30
            12 => [0.99, 1.23, 1.39, 1.20],
            13 => [1.06, 1.19, 1.34, 1.21], // Today
        ];

        $glycemiaMeasuresByUtc = [];
        $nowUtcComparison = gmdate('Y-m-d H:i:s');

        // Cut off "completed" entries on today at the earlier of current UTC time or 10:30 local so today's schedule has both Done and Due/Upcoming tasks!
        $todayMorningCutoffUtc = ScheduleService::localParisToUtc($todayLocalStr, '10:30');
        $effectivePastCutoffUtc = min($nowUtcComparison, $todayMorningCutoffUtc);

        for ($dayIdx = 0; $dayIdx < 14; $dayIdx++) {
            $dayDt = $nowLocalDt->sub(new DateInterval('P' . (13 - $dayIdx) . 'D'));
            $dayStr = $dayDt->format('Y-m-d');
            $isToday = ($dayIdx === 13);
            $caregiverMorning = ($dayIdx % 2 === 0) ? 3 : 2; // Fatou (3) or Sofia (2)
            $caregiverEvening = 4; // Marie (4)

            // A. Glycemia 4x/day
            $gSlots = [
                ['time' => '07:02', 'ctx' => 'fasting',  'val' => $glycemiaSeries[$dayIdx][0], 'by' => $caregiverMorning],
                ['time' => '11:32', 'ctx' => 'pre_meal', 'val' => $glycemiaSeries[$dayIdx][1], 'by' => $caregiverMorning],
                ['time' => '18:32', 'ctx' => 'pre_meal', 'val' => $glycemiaSeries[$dayIdx][2], 'by' => $caregiverEvening],
                ['time' => '22:02', 'ctx' => 'bedtime',  'val' => $glycemiaSeries[$dayIdx][3], 'by' => $caregiverEvening],
            ];

            foreach ($gSlots as $slot) {
                $mUtc = ScheduleService::localParisToUtc($dayStr, $slot['time']);
                if ($isToday && $mUtc > $effectivePastCutoffUtc) {
                    continue;
                }

                $val = (float) $slot['val'];
                $eval = ThresholdService::evaluate($measureTypes[0], $val, $slot['ctx']);
                $comment = null;
                if ($val < 0.70) {
                    $comment = 'Sueurs légères constatées : resucrage immédiat avec 15 g de glucides (1 verre de jus d’orange) + recontrôle à +15 min.';
                } elseif ($val > 2.50) {
                    $comment = 'Hyperglycémie importante : hydratation renforcée (250 mL d’eau) et correction NovoRapid selon tableau.';
                }

                $measureInsertStmt->execute([
                    'type_id' => $typeIdsByCode['glycemia'],
                    'val1' => $val,
                    'val2' => null,
                    'val3' => null,
                    'unit' => 'g/L',
                    'context' => $slot['ctx'],
                    'measured_at' => $mUtc,
                    'comment' => $comment,
                    'alert_level' => $eval['alert_level'],
                    'threshold_level' => $eval['threshold_level'],
                    'by' => $slot['by'],
                ]);
                $mId = (int) $pdo->lastInsertId();
                $glycemiaMeasuresByUtc[$mUtc] = ['id' => $mId, 'value' => $val];

                ScheduleService::fulfillExpectationWithMeasure($mId, $typeIdsByCode['glycemia'], $mUtc, 1);

                // If hypoglycemia, insert the +15 min re-control measure and post-meal checks
                if ($val < 0.70) {
                    $recheckTime = $slot['time'] === '11:32' ? '11:48' : '18:48';
                    $recheckUtc = ScheduleService::localParisToUtc($dayStr, $recheckTime);
                    $recheckVal = 1.08;
                    $reEval = ThresholdService::evaluate($measureTypes[0], $recheckVal, 'pre_meal');
                    $measureInsertStmt->execute([
                        'type_id' => $typeIdsByCode['glycemia'],
                        'val1' => $recheckVal,
                        'val2' => null,
                        'val3' => null,
                        'unit' => 'g/L',
                        'context' => 'pre_meal',
                        'measured_at' => $recheckUtc,
                        'comment' => 'Contrôle +15 min après resucrage : retour dans la cible (1,08 g/L), M. Benali se sent bien.',
                        'alert_level' => $reEval['alert_level'],
                        'threshold_level' => $reEval['threshold_level'],
                        'by' => $slot['by'],
                    ]);
                    $reId = (int) $pdo->lastInsertId();
                    $glycemiaMeasuresByUtc[$recheckUtc] = ['id' => $reId, 'value' => $recheckVal];
                }
            }

            // Post-prandial glycemia (+2h after lunch at 14:15) on alternate days so meals show linked before AND after glycemias
            if (!$isToday && ($dayIdx % 2 === 1)) {
                $ppUtc = ScheduleService::localParisToUtc($dayStr, '14:15');
                $ppVal = 1.56 + (($dayIdx % 3) * 0.08);
                $ppEval = ThresholdService::evaluate($measureTypes[0], $ppVal, 'post_meal');
                $measureInsertStmt->execute([
                    'type_id' => $typeIdsByCode['glycemia'],
                    'val1' => $ppVal,
                    'val2' => null,
                    'val3' => null,
                    'unit' => 'g/L',
                    'context' => 'post_meal',
                    'measured_at' => $ppUtc,
                    'comment' => 'Contrôle post-prandial (+2h après déjeuner).',
                    'alert_level' => $ppEval['alert_level'],
                    'threshold_level' => $ppEval['threshold_level'],
                    'by' => $caregiverMorning,
                ]);
            }

            // B. Morning Blood Pressure (07:32) + Temperature + SpO2 + Secondary vitals
            $bpUtc = ScheduleService::localParisToUtc($dayStr, '07:32');
            if (!$isToday || $bpUtc <= $effectivePastCutoffUtc) {
                $sys = 128 + (($dayIdx % 5) - 2) * 3;
                $dia = 76 + (($dayIdx % 4) - 1) * 2;
                $pulse = 68 + ($dayIdx % 6);
                $bpEval = ThresholdService::evaluate($measureTypes[1], (float) $sys, 'routine', (float) $dia);
                $measureInsertStmt->execute([
                    'type_id' => $typeIdsByCode['blood_pressure'],
                    'val1' => $sys,
                    'val2' => $dia,
                    'val3' => $pulse,
                    'unit' => 'mmHg',
                    'context' => 'routine',
                    'measured_at' => $bpUtc,
                    'comment' => 'Mesure assis au repos après 5 minutes.',
                    'alert_level' => $bpEval['alert_level'],
                    'threshold_level' => $bpEval['threshold_level'],
                    'by' => $caregiverMorning,
                ]);
                $bpId = (int) $pdo->lastInsertId();
                ScheduleService::fulfillExpectationWithMeasure($bpId, $typeIdsByCode['blood_pressure'], $bpUtc, 1);

                // Temperature
                $tempVal = round(36.5 + (($dayIdx % 3) * 0.1), 1);
                $tempEval = ThresholdService::evaluate($measureTypes[2], $tempVal, 'routine');
                $measureInsertStmt->execute([
                    'type_id' => $typeIdsByCode['temperature'],
                    'val1' => $tempVal,
                    'val2' => null,
                    'val3' => null,
                    'unit' => '°C',
                    'context' => 'routine',
                    'measured_at' => ScheduleService::localParisToUtc($dayStr, '07:35'),
                    'comment' => null,
                    'alert_level' => $tempEval['alert_level'],
                    'threshold_level' => $tempEval['threshold_level'],
                    'by' => $caregiverMorning,
                ]);

                // SpO2
                $spo2Val = 96 + ($dayIdx % 3);
                $spo2Eval = ThresholdService::evaluate($measureTypes[3], (float) $spo2Val, 'routine');
                $measureInsertStmt->execute([
                    'type_id' => $typeIdsByCode['spo2'],
                    'val1' => $spo2Val,
                    'val2' => null,
                    'val3' => null,
                    'unit' => '%',
                    'context' => 'routine',
                    'measured_at' => ScheduleService::localParisToUtc($dayStr, '07:36'),
                    'comment' => null,
                    'alert_level' => $spo2Eval['alert_level'],
                    'threshold_level' => $spo2Eval['threshold_level'],
                    'by' => $caregiverMorning,
                ]);

                // Weight & secondary measures every 2 days
                if ($dayIdx % 2 === 0) {
                    $wVal = round(72.3 + (($dayIdx % 4) - 1) * 0.2, 1);
                    $wEval = ThresholdService::evaluate($measureTypes[4], $wVal, 'routine');
                    $measureInsertStmt->execute([
                        'type_id' => $typeIdsByCode['weight'],
                        'val1' => $wVal,
                        'val2' => null,
                        'val3' => null,
                        'unit' => 'kg',
                        'context' => 'fasting',
                        'measured_at' => ScheduleService::localParisToUtc($dayStr, '07:40'),
                        'comment' => 'Pesée matinale à jeun.',
                        'alert_level' => $wEval['alert_level'],
                        'threshold_level' => $wEval['threshold_level'],
                        'by' => $caregiverMorning,
                    ]);
                }

                // Heart rate, Hydration, Pain, Bowel/Diuresis
                $hrEval = ThresholdService::evaluate($measureTypes[5], (float) $pulse, 'routine');
                $measureInsertStmt->execute([
                    'type_id' => $typeIdsByCode['heart_rate'],
                    'val1' => $pulse,
                    'val2' => null,
                    'val3' => null,
                    'unit' => 'bpm',
                    'context' => 'routine',
                    'measured_at' => ScheduleService::localParisToUtc($dayStr, '07:33'),
                    'comment' => 'Pouls régulier.',
                    'alert_level' => $hrEval['alert_level'],
                    'threshold_level' => $hrEval['threshold_level'],
                    'by' => $caregiverMorning,
                ]);

                if (!$isToday) {
                    $hydVal = 1450 + ($dayIdx % 4) * 100;
                    $hydEval = ThresholdService::evaluate($measureTypes[6], (float) $hydVal, 'routine');
                    $measureInsertStmt->execute([
                        'type_id' => $typeIdsByCode['hydration'],
                        'val1' => $hydVal,
                        'val2' => null,
                        'val3' => null,
                        'unit' => 'mL',
                        'context' => 'routine',
                        'measured_at' => ScheduleService::localParisToUtc($dayStr, '20:30'),
                        'comment' => 'Bilan hydrique de la journée.',
                        'alert_level' => $hydEval['alert_level'],
                        'threshold_level' => $hydEval['threshold_level'],
                        'by' => $caregiverEvening,
                    ]);

                    $painVal = ($dayIdx === 5 || $dayIdx === 9) ? 4 : 1;
                    $painEval = ThresholdService::evaluate($measureTypes[7], (float) $painVal, 'routine');
                    $measureInsertStmt->execute([
                        'type_id' => $typeIdsByCode['pain'],
                        'val1' => $painVal,
                        'val2' => null,
                        'val3' => null,
                        'unit' => '/10',
                        'context' => 'routine',
                        'measured_at' => ScheduleService::localParisToUtc($dayStr, '15:00'),
                        'comment' => $painVal > 2 ? 'Raideur genou droit à la marche.' : 'Aucune douleur signalée.',
                        'alert_level' => $painEval['alert_level'],
                        'threshold_level' => $painEval['threshold_level'],
                        'by' => $caregiverMorning,
                    ]);

                    $bowelEval = ThresholdService::evaluate($measureTypes[8], 1.0, 'routine');
                    $measureInsertStmt->execute([
                        'type_id' => $typeIdsByCode['bowel_diuresis'],
                        'val1' => 1,
                        'val2' => 5,
                        'val3' => null,
                        'unit' => 'passages',
                        'context' => 'routine',
                        'measured_at' => ScheduleService::localParisToUtc($dayStr, '18:00'),
                        'comment' => 'Transit normal (1 selle), diurèse conservée (5 mictions).',
                        'alert_level' => $bowelEval['alert_level'],
                        'threshold_level' => $bowelEval['threshold_level'],
                        'by' => $caregiverEvening,
                    ]);
                }
            }

            // C. Meals 4x/day with before/after photos
            $mealSlots = [
                [
                    'type' => 'breakfast',
                    'time' => '08:05',
                    'desc' => $favorites[$dayIdx % 2]['label'],
                    'portion' => 100,
                    'carbs' => $favorites[$dayIdx % 2]['carbs'],
                    'drink' => $favorites[$dayIdx % 2]['drink'],
                    'ml' => 250,
                    'by' => $caregiverMorning,
                ],
                [
                    'type' => 'lunch',
                    'time' => '12:15',
                    'desc' => $favorites[2 + ($dayIdx % 2)]['label'],
                    'portion' => ($dayIdx === 8) ? 75 : 100,
                    'carbs' => $favorites[2 + ($dayIdx % 2)]['carbs'],
                    'drink' => $favorites[2 + ($dayIdx % 2)]['drink'],
                    'ml' => 300,
                    'by' => $caregiverMorning,
                ],
                [
                    'type' => 'snack',
                    'time' => '16:15',
                    'desc' => $favorites[4]['label'],
                    'portion' => 100,
                    'carbs' => $favorites[4]['carbs'],
                    'drink' => $favorites[4]['drink'],
                    'ml' => 200,
                    'by' => $caregiverMorning,
                ],
                [
                    'type' => 'dinner',
                    'time' => '19:15',
                    'desc' => $favorites[5 + ($dayIdx % 2)]['label'],
                    'portion' => ($dayIdx === 4) ? 50 : 75,
                    'carbs' => $favorites[5 + ($dayIdx % 2)]['carbs'],
                    'drink' => $favorites[5 + ($dayIdx % 2)]['drink'],
                    'ml' => 250,
                    'by' => $caregiverEvening,
                ],
            ];

            foreach ($mealSlots as $ms) {
                $eatenUtc = ScheduleService::localParisToUtc($dayStr, $ms['time']);
                if ($isToday && $eatenUtc > $effectivePastCutoffUtc) {
                    continue;
                }

                $mealInsertStmt->execute([
                    'type' => $ms['type'],
                    'eaten_at' => $eatenUtc,
                    'desc' => $ms['desc'],
                    'portion' => $ms['portion'],
                    'carbs' => $ms['carbs'],
                    'drink' => $ms['drink'],
                    'drink_ml' => $ms['ml'],
                    'comment' => $ms['portion'] < 75 ? 'Appétit modéré ce soir, bonne hydratation.' : 'Bon appétit, repas pris à table.',
                    'by' => $ms['by'],
                ]);
                $mealId = (int) $pdo->lastInsertId();

                // Attach before & after photos on lunch/breakfast/dinner
                if ($ms['type'] === 'lunch' || $ms['type'] === 'breakfast' || $dayIdx >= 11) {
                    $mealPhotoStmt->execute([
                        'meal_id' => $mealId,
                        'kind' => 'avant',
                        'photo' => $demoPhotos['meal_before_main'],
                        'thumb' => $demoPhotos['meal_before_thumb'],
                        'ord' => 1,
                        'at' => $eatenUtc,
                        'by' => $ms['by'],
                    ]);
                    $mealPhotoStmt->execute([
                        'meal_id' => $mealId,
                        'kind' => 'apres',
                        'photo' => $demoPhotos['meal_after_main'],
                        'thumb' => $demoPhotos['meal_after_thumb'],
                        'ord' => 2,
                        'at' => $eatenUtc,
                        'by' => $ms['by'],
                    ]);
                }
            }

            // D. Care checks
            $morningCareUtc = ScheduleService::localParisToUtc($dayStr, '08:35');
            if (!$isToday || $morningCareUtc <= $effectivePastCutoffUtc) {
                $careCheckStmt->execute([
                    'item_id' => $careItemIds['morning_wash'],
                    'at' => $morningCareUtc,
                    'comment' => 'Toilette complète au lavabo, peau saine.',
                    'by' => $caregiverMorning,
                ]);
                $careCheckStmt->execute([
                    'item_id' => $careItemIds['diabetic_feet'],
                    'at' => ScheduleService::localParisToUtc($dayStr, '08:45'),
                    'comment' => 'Inspection interdigitale RAS, hydratation cutanée réalisée.',
                    'by' => $caregiverMorning,
                ]);
                $careCheckStmt->execute([
                    'item_id' => $careItemIds['hydration_glass'],
                    'at' => ScheduleService::localParisToUtc($dayStr, '09:30'),
                    'comment' => 'Verre d’eau 200 mL bu entièrement.',
                    'by' => $caregiverMorning,
                ]);
            }

            if (!$isToday) {
                $careCheckStmt->execute([
                    'item_id' => $careItemIds['walker_walk'],
                    'at' => ScheduleService::localParisToUtc($dayStr, '15:30'),
                    'comment' => 'Marche accompagnée dans le salon et le couloir (15 min).',
                    'by' => $caregiverMorning,
                ]);
                $careCheckStmt->execute([
                    'item_id' => $careItemIds['bedtime_comfort'],
                    'at' => ScheduleService::localParisToUtc($dayStr, '21:30'),
                    'comment' => 'Installation pour la nuit, sonnette et verre d’eau à portée de main.',
                    'by' => $caregiverEvening,
                ]);
            }

            // E. Notes & Shift Handovers
            if (!$isToday && ($dayIdx % 2 === 0 || $dayIdx === 12)) {
                $handoverUtc = ScheduleService::localParisToUtc($dayStr, '20:15');
                $summaryJson = json_encode([
                    'from_local' => '08:00',
                    'to_local' => '20:15',
                    'measures_total' => 6,
                    'measures_out_of_target' => ($dayIdx === 8 || $dayIdx === 6) ? 1 : 0,
                    'measures_critical' => ($dayIdx === 8 || $dayIdx === 6) ? 1 : 0,
                    'intakes_given' => 7,
                    'intakes_skipped' => 0,
                    'meals_count' => 4,
                    'avg_portion_pct' => 94,
                    'care_checks_count' => 5,
                    'important_notes_count' => 0,
                    'summary_text' => '6 mesures · 7 prises données · 4 repas (94 %) · 5 soins réalisés',
                ], JSON_UNESCAPED_UNICODE);

                $noteStmt->execute([
                    'cat' => 'handover',
                    'prio' => $dayIdx === 12 ? 'important' : 'normal',
                    'title' => 'Transmission de fin de poste — Garde du soir',
                    'content' => $dayIdx === 12
                        ? 'Bonne journée pour M. Benali. Dîner pris avec appétit (velouté + omelette). Lantus 18 UI injectée à 21h dans la cuisse gauche. Penser à bien contrôler la glycémie à jeun demain matin et à vérifier la livraison de bandelettes par la pharmacie.'
                        : 'Journée calme, constantes stables, marche de 15 min avec déambulateur bien tolérée.',
                    'handover' => 1,
                    'summary' => $summaryJson,
                    'by' => $caregiverEvening,
                    'at' => $handoverUtc,
                ]);
            }
        }

        // Add a clinical note on hypoglycemia day
        $hypoNoteUtc = ScheduleService::localParisToUtc(
            $nowLocalDt->sub(new DateInterval('P2D'))->format('Y-m-d'),
            '18:55'
        );
        $noteStmt->execute([
            'cat' => 'medical',
            'prio' => 'important',
            'title' => 'Épisode d’hypoglycémie à 18h30 résolu après resucrage',
            'content' => 'Glycémie mesurée à 0,65 g/L avant le dîner. Application immédiate du protocole : 1 verre de jus d’orange (15 g de glucides rapides). Contrôle à 18h48 remonté à 1,08 g/L. Dîner avancé à 19h00.',
            'handover' => 0,
            'summary' => null,
            'by' => 4,
            'at' => $hypoNoteUtc,
        ]);

        // 8. Process generated medication_intakes up to effectivePastCutoffUtc (~95% given, ~5% skipped with reason)
        $allPastIntakesStmt = $pdo->prepare(
            'SELECT mi.*, p.dose, p.insulin_kind, p.dose_mode
             FROM `medication_intakes` mi
             INNER JOIN `prescriptions` p ON p.id = mi.prescription_id
             WHERE mi.patient_id = 1
               AND mi.scheduled_at <= :cutoff_utc
               AND mi.deleted_at IS NULL
             ORDER BY mi.scheduled_at ASC, mi.id ASC'
        );
        $allPastIntakesStmt->execute(['cutoff_utc' => $effectivePastCutoffUtc]);
        $pastIntakes = $allPastIntakesStmt->fetchAll(PDO::FETCH_ASSOC);

        $siteKeys = array_keys(InsulinService::INJECTION_SITES);
        $siteIdx = 0;

        $updGivenStmt = $pdo->prepare(
            'UPDATE `medication_intakes`
             SET `status` = \'given\',
                 `given_at` = :given_at,
                 `given_dose` = :given_dose,
                 `injection_site` = :site,
                 `related_measure_id` = :rel_id,
                 `given_by` = :by,
                 `version` = 2,
                 `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :id'
        );

        $updSkippedStmt = $pdo->prepare(
            'UPDATE `medication_intakes`
             SET `status` = \'skipped\',
                 `given_at` = :given_at,
                 `reason` = :reason,
                 `comment` = :comment,
                 `given_by` = :by,
                 `version` = 2,
                 `updated_at` = UTC_TIMESTAMP()
             WHERE `id` = :id'
        );

        foreach ($pastIntakes as $idx => $intake) {
            $schedUtc = (string) $intake['scheduled_at'];
            $localHour = (int) ScheduleService::utcToLocalParis($schedUtc, 'G');
            $giverId = ($localHour < 16) ? 3 : 4; // Fatou morning/noon, Marie evening

            // ~5% skipped (every 21st intake) with explicit reason
            if ($idx > 0 && ($idx % 21 === 0)) {
                $skipReason = ($idx % 42 === 0)
                    ? 'Patient profondément endormi'
                    : 'Refus ponctuel du patient (fatigue)';
                $updSkippedStmt->execute([
                    'given_at' => $schedUtc,
                    'reason' => $skipReason,
                    'comment' => 'Observé et consigné dans le journal de bord.',
                    'by' => $giverId,
                    'id' => (int) $intake['id'],
                ]);
                continue;
            }

            $doseToGive = (float) $intake['dose'];
            $site = null;
            $relMeasureId = null;

            if ($intake['insulin_kind'] !== null) {
                $site = $siteKeys[$siteIdx % count($siteKeys)];
                $siteIdx++;
            }

            if ((string) $intake['dose_mode'] === 'tableau') {
                // Find the closest glycemia measured within 90 minutes before scheduled_at
                $gMatchStmt = $pdo->prepare(
                    'SELECT `id`, `value`
                     FROM `measures`
                     WHERE `patient_id` = 1
                       AND `measure_type_id` = :gtid
                       AND `measured_at` BETWEEN DATE_SUB(:sched, INTERVAL 90 MINUTE) AND DATE_ADD(:sched2, INTERVAL 30 MINUTE)
                     ORDER BY ABS(TIMESTAMPDIFF(SECOND, `measured_at`, :sched3)) ASC
                     LIMIT 1'
                );
                $gMatchStmt->execute([
                    'gtid' => $typeIdsByCode['glycemia'],
                    'sched' => $schedUtc,
                    'sched2' => $schedUtc,
                    'sched3' => $schedUtc,
                ]);
                $gMatch = $gMatchStmt->fetch(PDO::FETCH_ASSOC);
                if ($gMatch) {
                    $relMeasureId = (int) $gMatch['id'];
                    $lookup = InsulinService::lookupDose($correctionTable, (float) $gMatch['value']);
                    if ($lookup['proposed_dose'] !== null) {
                        $doseToGive = (float) $lookup['proposed_dose'];
                    }
                }
            }

            $updGivenStmt->execute([
                'given_at' => $schedUtc,
                'given_dose' => $doseToGive,
                'site' => $site,
                'rel_id' => $relMeasureId,
                'by' => $giverId,
                'id' => (int) $intake['id'],
            ]);
        }

        // Also seed 2 historical 'as-needed' Paracetamol intakes
        if ($paracetamolRxId !== null) {
            foreach ([5, 9] as $dAgo) {
                $asNeededUtc = ScheduleService::localParisToUtc(
                    $nowLocalDt->sub(new DateInterval("P{$dAgo}D"))->format('Y-m-d'),
                    '15:15'
                );
                $asStmt = $pdo->prepare(
                    'INSERT INTO `medication_intakes`
                     (`patient_id`, `prescription_id`, `prescription_time_id`, `scheduled_at`, `status`,
                      `given_at`, `given_dose`, `reason`, `comment`, `given_by`, `version`, `created_at`, `updated_at`, `created_by`)
                     VALUES (1, :rx_id, NULL, :at, \'given\', :given_at, 1000, \'Douleur arthrose genou droit (EVA 4/10)\', \'Soulagement rapide en 45 min.\', 3, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), 3)'
                );
                $asStmt->execute(['rx_id' => $paracetamolRxId, 'at' => $asNeededUtc, 'given_at' => $asNeededUtc]);
            }
        }

        ScheduleService::syncExpectationStatuses($nowUtc, 1);
    }

    /**
     * Generate clean, realistic demo JPEG images for meals (before/after) and prescription document using GD.
     *
     * @return array{
     *   meal_before_main: string,
     *   meal_before_thumb: string,
     *   meal_after_main: string,
     *   meal_after_thumb: string,
     *   rx_doc: string
     * }
     */
    private static function generateDemoPhotos(): array
    {
        $uploadsRoot = (string) Config::get('paths.uploads', dirname(__DIR__, 2) . '/storage/uploads');
        foreach (['meals', 'prescriptions'] as $dir) {
            if (!is_dir($uploadsRoot . '/' . $dir)) {
                mkdir($uploadsRoot . '/' . $dir, 0775, true);
            }
        }

        // Helper to draw a meal tray card image (main 800x600 + thumb 400x400)
        $drawMeal = static function (string $basename, bool $isAfter) use ($uploadsRoot): array {
            $w = 800;
            $h = 600;
            $img = imagecreatetruecolor($w, $h);

            // Warm table background
            $bg = imagecolorallocate($img, 240, 244, 250);
            $tray = imagecolorallocate($img, 255, 255, 255);
            $navy = imagecolorallocate($img, 10, 22, 51);
            $plateRim = imagecolorallocate($img, 224, 231, 243);
            $plateIn = imagecolorallocate($img, 248, 250, 253);
            $green = imagecolorallocate($img, 52, 199, 89);
            $orange = imagecolorallocate($img, 255, 149, 0);
            $salmon = imagecolorallocate($img, 255, 105, 97);
            $water = imagecolorallocate($img, 90, 200, 250);

            imagefilledrectangle($img, 0, 0, $w, $h, $bg);
            imagefilledrectangle($img, 50, 50, $w - 50, $h - 50, $tray);

            // Header bar on tray
            imagefilledrectangle($img, 50, 50, $w - 50, 105, $navy);
            imagestring($img, 5, 76, 70, $isAfter ? 'PRIME CARE - APRES LE REPAS (100%)' : 'PRIME CARE - AVANT LE REPAS', $tray);

            // Plate
            imagefilledellipse($img, 340, 330, 340, 340, $plateRim);
            imagefilledellipse($img, 340, 330, 290, 290, $plateIn);

            if (!$isAfter) {
                // Full balanced meal compartments (vegetables + protein + starch)
                imagefilledellipse($img, 285, 285, 110, 95, $green);
                imagefilledellipse($img, 395, 295, 105, 90, $salmon);
                imagefilledellipse($img, 340, 390, 120, 80, $orange);
            } else {
                // Clean finished plate with small trace
                imagefilledellipse($img, 345, 340, 28, 20, $green);
            }

            // Water glass on the right
            imagefilledellipse($img, 610, 270, 95, 95, $plateRim);
            imagefilledellipse($img, 610, 270, 75, 75, $water);

            // Compote / dairy bowl bottom right
            imagefilledellipse($img, 610, 420, 110, 110, $plateRim);
            imagefilledellipse($img, 610, 420, 86, 86, $isAfter ? $plateIn : $orange);

            $mainRel = 'meals/' . $basename . '.jpg';
            $thumbRel = 'meals/' . $basename . '_thumb.jpg';

            imagejpeg($img, $uploadsRoot . '/' . $mainRel, 86);

            $thumb = imagecreatetruecolor(400, 400);
            imagecopyresampled($thumb, $img, 0, 0, 100, 0, 400, 400, 600, 600);
            imagejpeg($thumb, $uploadsRoot . '/' . $thumbRel, 85);

            imagedestroy($thumb);
            imagedestroy($img);

            return ['main' => $mainRel, 'thumb' => $thumbRel];
        };

        $before = $drawMeal('demo_repas_avant', false);
        $after = $drawMeal('demo_repas_apres', true);

        // Demo prescription image
        $rxImg = imagecreatetruecolor(800, 1000);
        $white = imagecolorallocate($rxImg, 255, 255, 255);
        $navy = imagecolorallocate($rxImg, 10, 22, 51);
        $indigo = imagecolorallocate($rxImg, 94, 92, 230);
        $gray = imagecolorallocate($rxImg, 100, 116, 139);
        imagefilledrectangle($rxImg, 0, 0, 800, 1000, $white);
        imagefilledrectangle($rxImg, 0, 0, 800, 110, $navy);
        imagestring($rxImg, 5, 40, 35, 'CHU HENRI-MONDOR - SERVICE DIABETOLOGIE (CRETEIL)', $white);
        imagestring($rxImg, 4, 40, 65, 'Dr Claire MOREAU - Endocrinologue-Diabetologue', $white);
        imagestring($rxImg, 5, 40, 150, 'ORDONNANCE MEDICALE - M. Henri BENALI (ne le 12/05/1942)', $navy);
        imagefilledrectangle($rxImg, 40, 185, 760, 188, $indigo);
        $lines = [
            '1. METFORMINE 1000 mg : 1 comprime matin (8h) et soir (19h30) au repas.',
            '2. LANTUS SoloStar 100 UI/mL : 18 UI en sous-cutanee chaque soir a 21h00.',
            '3. NOVORAPID FlexPen 100 UI/mL : avant chaque repas selon glycemie capillaire :',
            '   - < 0,70 g/L : 0 UI + protocole resucrage 15 g glucides rapides',
            '   - 0,70 a 1,30 g/L : 4 UI',
            '   - 1,31 a 1,80 g/L : 6 UI',
            '   - 1,81 a 2,50 g/L : 8 UI',
            '   - > 2,50 g/L : 10 UI + controle renforce',
            '4. AMLODIPINE 5 mg : 1 gelule chaque matin a 8h00.',
            '5. PARACETAMOL 1000 mg : 1 comprime si douleur (intervalle min. 6 heures).',
        ];
        $y = 225;
        foreach ($lines as $line) {
            imagestring($rxImg, 4, 50, $y, $line, $navy);
            $y += 46;
        }
        imagestring($rxImg, 3, 50, 880, 'Ordonnance valable 6 mois - Renouvelable - Signature : Dr C. Moreau', $gray);
        $rxRel = 'prescriptions/ordonnance_benali_demo.jpg';
        imagejpeg($rxImg, $uploadsRoot . '/' . $rxRel, 88);
        imagedestroy($rxImg);

        return [
            'meal_before_main' => $before['main'],
            'meal_before_thumb' => $before['thumb'],
            'meal_after_main' => $after['main'],
            'meal_after_thumb' => $after['thumb'],
            'rx_doc' => $rxRel,
        ];
    }
}
