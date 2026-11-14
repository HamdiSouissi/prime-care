<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Auth;
use App\Core\Database;

/**
 * Seeds Phase 1 users, patient "Henri Benali", contacts, and initial application settings.
 */
final class Phase1Seeder
{
    public static function run(): void
    {
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');

        // 1. Seed the 5 official test users
        $users = [
            [
                'first_name' => 'Karim',
                'last_name' => 'Benali',
                'email' => 'admin@primecare.test',
                'phone' => '06 12 34 56 78',
                'role' => 'admin',
                'password' => 'Admin!2026',
                'pin' => '1111',
                'color_hex' => '#5E5CE6',
                'contract_type' => 'Famille',
                'weekly_hours' => 0.00,
                'hourly_rate' => null,
                'hired_at' => '2025-01-10',
                'emergency_contact_name' => 'Nadia Benali',
                'emergency_contact_phone' => '06 98 76 54 32',
                'emergency_contact_relation' => 'Épouse',
                'notes' => 'Fils responsable de la coordination 24h/24 et référent familial principal.',
            ],
            [
                'first_name' => 'Sofia',
                'last_name' => 'Marchetti',
                'email' => 'coord@primecare.test',
                'phone' => '06 23 45 67 89',
                'role' => 'coordinator',
                'password' => 'Coord!2026',
                'pin' => '2222',
                'color_hex' => '#00C7BE',
                'contract_type' => 'CDI',
                'weekly_hours' => 35.00,
                'hourly_rate' => 17.50,
                'hired_at' => '2025-02-01',
                'emergency_contact_name' => 'Marco Marchetti',
                'emergency_contact_phone' => '06 87 65 43 21',
                'emergency_contact_relation' => 'Conjoint',
                'notes' => 'Aide-soignante référente diplômée d’État, coordonne le planning et valide les pointages.',
            ],
            [
                'first_name' => 'Fatou',
                'last_name' => 'Diallo',
                'email' => 'fatou@primecare.test',
                'phone' => '06 34 56 78 90',
                'role' => 'caregiver',
                'password' => 'Soin!2026',
                'pin' => '3333',
                'color_hex' => '#FF9500',
                'contract_type' => 'CDI',
                'weekly_hours' => 32.00,
                'hourly_rate' => 15.20,
                'hired_at' => '2025-03-15',
                'emergency_contact_name' => 'Moussa Diallo',
                'emergency_contact_phone' => '06 76 54 32 10',
                'emergency_contact_relation' => 'Frère',
                'notes' => 'Garde de jour (matin et midi). Excellente maîtrise du suivi glycémique capillaire.',
            ],
            [
                'first_name' => 'Marie',
                'last_name' => 'Lefèvre',
                'email' => 'marie@primecare.test',
                'phone' => '06 45 67 89 01',
                'role' => 'caregiver',
                'password' => 'Soin!2026',
                'pin' => '4444',
                'color_hex' => '#AF52DE',
                'contract_type' => 'CESU',
                'weekly_hours' => 28.00,
                'hourly_rate' => 14.80,
                'hired_at' => '2025-04-01',
                'emergency_contact_name' => 'Jean Lefèvre',
                'emergency_contact_phone' => '06 65 43 21 09',
                'emergency_contact_relation' => 'Époux',
                'notes' => 'Interventions soir et week-end. Douce et attentive aux repas.',
            ],
            [
                'first_name' => 'Dr Laurent',
                'last_name' => 'Petit',
                'email' => 'medecin@primecare.test',
                'phone' => '01 48 99 10 20',
                'role' => 'viewer',
                'password' => 'Lecture!2026',
                'pin' => '5555',
                'color_hex' => '#007AFF',
                'contract_type' => 'Médecin',
                'weekly_hours' => 0.00,
                'hourly_rate' => null,
                'hired_at' => '2025-01-15',
                'emergency_contact_name' => 'Secrétariat Cabinet Créteil',
                'emergency_contact_phone' => '01 48 99 10 21',
                'emergency_contact_relation' => 'Cabinet médical',
                'notes' => 'Médecin traitant consultant les constantes et le journal de soins à distance.',
            ],
        ];

        $userStmt = $pdo->prepare(
            'INSERT INTO `users`
             (`patient_id`, `first_name`, `last_name`, `email`, `phone`, `role`, `password_hash`, `pin_hash`,
              `color_hex`, `contract_type`, `weekly_hours`, `hourly_rate`, `hired_at`,
              `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`,
              `notes`, `theme`, `locale`, `is_active`, `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :first_name, :last_name, :email, :phone, :role, :password_hash, :pin_hash,
              :color_hex, :contract_type, :weekly_hours, :hourly_rate, :hired_at,
              :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relation,
              :notes, :theme, :locale, 1, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
              `first_name` = VALUES(`first_name`),
              `last_name` = VALUES(`last_name`),
              `role` = VALUES(`role`),
              `password_hash` = VALUES(`password_hash`),
              `pin_hash` = VALUES(`pin_hash`),
              `updated_at` = VALUES(`updated_at`)'
        );

        foreach ($users as $u) {
            $userStmt->execute([
                'first_name' => $u['first_name'],
                'last_name' => $u['last_name'],
                'email' => $u['email'],
                'phone' => $u['phone'],
                'role' => $u['role'],
                'password_hash' => Auth::hashSecret($u['password']),
                'pin_hash' => Auth::hashSecret($u['pin']),
                'color_hex' => $u['color_hex'],
                'contract_type' => $u['contract_type'],
                'weekly_hours' => $u['weekly_hours'],
                'hourly_rate' => $u['hourly_rate'],
                'hired_at' => $u['hired_at'],
                'emergency_contact_name' => $u['emergency_contact_name'],
                'emergency_contact_phone' => $u['emergency_contact_phone'],
                'emergency_contact_relation' => $u['emergency_contact_relation'],
                'notes' => $u['notes'],
                'theme' => 'auto',
                'locale' => 'fr',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. Seed the patient "Henri Benali" (84 years old in 2026 -> born 1942-05-12)
        $pdo->exec('DELETE FROM `patient_contacts` WHERE `patient_id` = 1');

        $patientStmt = $pdo->prepare(
            'INSERT INTO `patients`
             (`id`, `first_name`, `last_name`, `birth_date`, `gender`, `address`, `access_code`, `ssn_masked`,
              `diabetes_type`, `blood_group`, `reference_weight_kg`, `height_cm`,
              `target_glycemia_fasting_min`, `target_glycemia_fasting_max`, `target_glycemia_postmeal_max`,
              `hypo_threshold_g_l`, `hyper_threshold_g_l`,
              `allergies`, `medical_history`, `permanent_instructions`, `hypo_protocol`,
              `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :first_name, :last_name, :birth_date, :gender, :address, :access_code, :ssn_masked,
              :diabetes_type, :blood_group, :reference_weight_kg, :height_cm,
              0.80, 1.30, 1.80, 0.70, 2.50,
              :allergies, :medical_history, :permanent_instructions, :hypo_protocol,
              :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
              `first_name` = VALUES(`first_name`),
              `last_name` = VALUES(`last_name`),
              `birth_date` = VALUES(`birth_date`),
              `hypo_protocol` = VALUES(`hypo_protocol`),
              `updated_at` = VALUES(`updated_at`)'
        );

        $patientStmt->execute([
            'first_name' => 'Henri',
            'last_name' => 'Benali',
            'birth_date' => '1942-05-12',
            'gender' => 'M',
            'address' => '18 avenue de la République, 94000 Créteil',
            'access_code' => 'Portail : 4829A · Interphone : BENALI (2e étage droite)',
            'ssn_masked' => '1 42 05 94 *** *** 28',
            'diabetes_type' => 'Diabète de type 2 (insulinotraité)',
            'blood_group' => 'A+',
            'reference_weight_kg' => 72.50,
            'height_cm' => 171,
            'allergies' => "Pénicilline (urticaire généralisé)\nAnti-inflammatoires non stéroïdiens (AINS — contre-indication rénale)",
            'medical_history' => "Diabète de type 2 diagnostiqué en 1998 sous schéma basal-bolus.\nHypertension artérielle stabilisée.\nInsuffisance rénale chronique modérée (stade 3a).\nArthrose lombaire et gonarthrose bilatérale (marche avec déambulateur à l’intérieur).",
            'permanent_instructions' => "• Hydratation : proposer un verre d’eau toutes les 2 heures (objectif 1,5 L / jour).\n• Pieds diabétiques : inspecter les pieds et espaces interdigitaux chaque matin lors de la toilette ; sécher par tamponnement doux.\n• Mobilisation : accompagner chaque lever avec le déambulateur, chaussures fermées antidérapantes obligatoires.\n• Glycémie : contrôle systématique au réveil (à jeun), avant chaque repas et au coucher.",
            'hypo_protocol' => "SI GLYCÉMIE < 0,70 g/L OU SIGNES D’HYPOGLYCÉMIE (sueurs, tremblements, confusion, pâleur) :\n1. Asseoir ou allonger M. Benali immédiatement.\n2. S’il est conscient et peut avaler : donner 15 g de glucides rapides = 3 morceaux de sucre dissous dans un peu d’eau OU 1 verre de jus de fruits (15 cl).\n3. Recontrôler la glycémie capillaire exactement 15 minutes après.\n4. Si la glycémie reste < 0,70 g/L : renouveler le resucrage (15 g) et prévenir Karim Benali.\n5. URGENCE VITALE : en cas de somnolence sévère, perte de connaissance ou incapacité à avaler, NE RIEN DONNER PAR LA BOUCHE, placer en PLS et APPELER IMMÉDIATEMENT LE 15 (SAMU).",
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Seed the 4 contacts for Henri Benali
        $contacts = [
            [
                'category' => 'family',
                'full_name' => 'Karim Benali',
                'relation_role' => 'Fils & Responsable légal',
                'organization' => 'Famille — Joignable 24h/24',
                'phone' => '06 12 34 56 78',
                'email' => 'admin@primecare.test',
                'address' => '12 rue de Paris, 94220 Charenton-le-Pont',
                'notes' => 'Appeler en priorité pour toute anomalie médicale ou organisationnelle.',
                'is_emergency_priority' => 1,
                'sort_order' => 1,
            ],
            [
                'category' => 'doctor',
                'full_name' => 'Dr Laurent Petit',
                'relation_role' => 'Médecin traitant',
                'organization' => 'Cabinet Médical de l’Échat — Créteil',
                'phone' => '01 48 99 10 20',
                'email' => 'medecin@primecare.test',
                'address' => '45 avenue du Général de Gaulle, 94000 Créteil',
                'notes' => 'Visite à domicile le jeudi matin. Secrétariat ouvert de 8h30 à 19h00.',
                'is_emergency_priority' => 1,
                'sort_order' => 2,
            ],
            [
                'category' => 'specialist',
                'full_name' => 'Dr Claire Moreau',
                'relation_role' => 'Diabétologue — Endocrinologue',
                'organization' => 'CHU Henri-Mondor (Service Diabétologie)',
                'phone' => '01 49 81 21 11',
                'email' => 'diabeto.mondor@primecare.test',
                'address' => '51 avenue du Maréchal de Lattre de Tassigny, 94010 Créteil',
                'notes' => 'Référente pour l’ajustement des doses d’insuline Lantus / Novorapid.',
                'is_emergency_priority' => 0,
                'sort_order' => 3,
            ],
            [
                'category' => 'pharmacy',
                'full_name' => 'Pharmacie Centrale du Village',
                'relation_role' => 'Pharmacie référente (Livraison à domicile)',
                'organization' => 'Dr. S. Haddad — Pharmacien titulaire',
                'phone' => '01 42 07 15 40',
                'email' => 'pharmacie.village@primecare.test',
                'address' => '24 rue du Général Leclerc, 94000 Créteil',
                'notes' => 'Dispose des ordonnances renouvelables et du matériel capteur / bandelettes.',
                'is_emergency_priority' => 0,
                'sort_order' => 4,
            ],
        ];

        $contactStmt = $pdo->prepare(
            'INSERT INTO `patient_contacts`
             (`patient_id`, `category`, `full_name`, `relation_role`, `organization`,
              `phone`, `email`, `address`, `notes`, `is_emergency_priority`, `sort_order`,
              `created_at`, `updated_at`, `created_by`)
             VALUES
             (1, :category, :full_name, :relation_role, :organization,
              :phone, :email, :address, :notes, :is_emergency_priority, :sort_order,
              :created_at, :updated_at, 1)'
        );

        foreach ($contacts as $c) {
            $contactStmt->execute([
                'category' => $c['category'],
                'full_name' => $c['full_name'],
                'relation_role' => $c['relation_role'],
                'organization' => $c['organization'],
                'phone' => $c['phone'],
                'email' => $c['email'],
                'address' => $c['address'],
                'notes' => $c['notes'],
                'is_emergency_priority' => $c['is_emergency_priority'],
                'sort_order' => $c['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. Seed initial settings
        $settingsStmt = $pdo->prepare(
            'INSERT INTO `settings` (`patient_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`, `created_by`)
             VALUES (1, :key, :val, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `updated_at` = VALUES(`updated_at`)'
        );

        $defaultSettings = [
            'glycemia_unit' => ['unit' => 'g/L', 'allow_mmol' => true],
            'home_coordinates' => ['lat' => 48.7904, 'lng' => 2.4556, 'radius_meters' => 150],
            'notifications' => ['email_enabled' => true, 'push_enabled' => true],
        ];

        foreach ($defaultSettings as $key => $val) {
            $settingsStmt->execute([
                'key' => $key,
                'val' => json_encode($val, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
