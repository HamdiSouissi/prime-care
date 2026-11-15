# Plan d’exécution — Phase 2 : Cœur médical (mesures, traitements, repas, journal)

> **Branche de travail** : `arena/1c2a1551-prime-care` (créée depuis `main` `30f7d42`)  
> **Périmètre** : Phase 2 / 4 — Mesures médicales, seuils & courbes ApexCharts, plan de traitement & validation des prises (dont insuline à dose variable et rotation des sites d’injection), repas avec photos avant/après, journal unifié, transmissions de fin de poste avec lecture tracée, soins rapides, écran « Aujourd’hui », loader ≥ 2 s et installation PWA plein écran (`standalone` + QR code serveur).

---

## 1. Objectif de la Phase 2

Permettre aux aides-soignantes (`caregiver`, `coordinator`) de saisir en quelques secondes — en respectant la **règle des 3 clics** — l’ensemble du suivi quotidien de M. Henri Benali (constantes vitales, prises de médicaments et d’insuline, repas avec photos avant/après, soins rapides, notes et transmissions de fin de poste), et permettre au responsable (`admin`) ainsi qu’au médecin (`viewer`, en lecture seule) de suivre en un coup d’œil l’état de santé, l’observance, les points d’attention et les courbes sur 7, 30 et 90 jours, tout en conservant intactes toutes les exigences validées en Phase 1 (sécurité C1–C6, appareils de confiance PIN, CSP stricte sans `unsafe-inline`, zéro attribut `style=`, Design B « Bleu nuit », crédit éditeur et test de fumée HTTP).

---

## 2. Ordre d’exécution et découpage technique

### Étape 0 — Préparation & Bibliothèque graphique (`docs/retours/PLAN_PHASE_2.md`, `public/assets/vendor/`)
1. Rédaction du présent plan `docs/retours/PLAN_PHASE_2.md` et enrichissement du §8 de `docs/ADDENDUM_DESIGN_B.md`.
2. Intégration locale de **ApexCharts** (bundle UMD auto-hébergé dans `public/assets/vendor/apexcharts.min.js`, 0 CDN, conforme à la CSP stricte sans `unsafe-inline` via attributs SVG natifs et feuille de style noncée) et mise à jour de `public/assets/vendor/LICENSES.md` (licence MIT).

### Étape 1 — Base de données : Migrations `003` à `006` & `Phase2Seeder` (14 jours)
1. **`database/migrations/003_vitals.sql`** :
   - `measure_types` : `id`, `patient_id`, `code` (unique par patient), `label`, `unit`, `secondary_label`, `secondary_unit`, `tertiary_label`, `tertiary_unit`, `decimals`, `min_value`, `max_value` (bornes de vraisemblance), `crit_low`, `low`, `high`, `crit_high`, `secondary_low`, `secondary_high`, `icon_name`, `is_secondary`, `is_custom`, `is_active`, `sort_order`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `measures` : `id`, `patient_id`, `measure_type_id`, `value`, `value2`, `value3`, `unit_recorded`, `context` (`fasting`, `pre_meal`, `post_meal`, `bedtime`, `night`, `routine`, `symptom`), `measured_at` (UTC), `comment`, `photo_path`, `photo_thumb_path`, `alert_level` (`ok`, `warn`, `critical`), `alert_Code`, `out_of_bounds_confirmed`, `recorded_by`, `modified_reason`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `measure_schedules` : `id`, `patient_id`, `measure_type_id`, `scheduled_time` (`HH:MM`), `days_mask` (ex. `1,2,3,4,5,6,7`), `context`, `label`, `is_active`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `measure_expectations` : `id`, `patient_id`, `measure_schedule_id`, `measure_type_id`, `scheduled_at` (UTC), `status` (`upcoming`, `done`, `late`), `measure_id`, `created_at`, `updated_at`, `created_by`, `deleted_at`, avec index unique `uniq_schedule_occurrence (measure_schedule_id, scheduled_at)`.
2. **`database/migrations/004_treatments.sql`** :
   - `medications` : `id`, `patient_id`, `name`, `dci`, `form` (`comprimé`, `gélule`, `stylo injectable`, `solution buvable`, `sachet`, `collyre`, `autre`), `default_dosage`, `default_unit`, `box_photo_path`, `box_photo_thumb_path`, `instructions`, `is_active`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `prescriptions` : `id`, `patient_id`, `medication_id`, `dose`, `unit`, `start_date`, `end_date`, `ends_on_rx`, `days_mask`, `as_needed`, `min_interval_h`, `max_per_day`, `insulin_kind` (`rapide`, `lente`, `NULL`), `dose_mode` (`fixe`, `tableau`), `correction_table` (JSON), `instructions`, `document_path`, `document_mime`, `document_original_name`, `is_active`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `prescription_times` : `id`, `patient_id`, `prescription_id`, `scheduled_time` (`HH:MM`), `moment_label` (`matin`, `midi`, `soir`, `coucher`), `dose_override`, `sort_order`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `medication_intakes` : `id`, `patient_id`, `prescription_id`, `prescription_time_id`, `scheduled_at` (UTC), `status` (`pending`, `given`, `skipped`), `given_at` (UTC), `given_dose`, `injection_site`, `reason`, `comment`, `related_measure_id`, `given_by`, `modified_reason`, `version` (INT UNSIGNED DEFAULT 1, verrou optimiste), `created_at`, `updated_at`, `created_by`, `deleted_at`, avec index unique `uniq_ptime_scheduled (prescription_time_id, scheduled_at)`.
3. **`database/migrations/005_meals.sql`** :
   - `meals` : `id`, `patient_id`, `meal_type` (`breakfast`, `lunch`, `snack`, `dinner`, `night_snack`), `eaten_at` (UTC), `description`, `portion_eaten` (`0`, `25`, `50`, `75`, `100`), `carbs_g`, `drink_type`, `drink_ml`, `comment`, `recorded_by`, `modified_reason`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `meal_photos` : `id`, `patient_id`, `meal_id`, `kind` (`avant`, `apres`, `autre`), `photo_path`, `thumb_path`, `sort_order`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `food_favorites` : `id`, `patient_id`, `label`, `meal_type`, `default_carbs_g`, `default_drink`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
4. **`database/migrations/006_journal.sql`** :
   - `notes` : `id`, `patient_id`, `category` (`general`, `medical`, `behavior`, `hygiene`, `family`, `supply`, `handover`), `priority` (`normal`, `important`, `urgent`), `title`, `content`, `photo_path`, `photo_thumb_path`, `is_handover`, `handover_summary` (JSON), `recorded_by`, `modified_reason`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `note_reads` : `id`, `patient_id`, `note_id`, `user_id`, `read_at` (UTC), `created_at`, `updated_at`, `created_by`, `deleted_at`, avec index unique `uniq_note_user (note_id, user_id)`.
   - `care_items` : `id`, `patient_id`, `code`, `label`, `icon_name`, `category`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
   - `care_checks` : `id`, `patient_id`, `care_item_id`, `checked_at` (UTC), `comment`, `performed_by`, `modified_reason`, `created_at`, `updated_at`, `created_by`, `deleted_at`.
5. **`database/seeds/Phase2Seeder.php`** (appelé par `bin/migrate.php --seed` et `bin/seed.php`) :
   - 9 types de mesures par défaut (`glycemia`, `blood_pressure`, `temperature`, `spo2`, `weight`, `heart_rate`, `hydration`, `pain`, `bowel_diuresis`) avec les seuils exacts de M. Henri Benali.
   - Planification des mesures attendues : glycémie à `07:00`, `11:30`, `18:30`, `22:00` ; tension chaque matin à `07:30`.
   - Plan de traitement complet : Metformine 1000 mg (matin `08:00`, soir `19:30`), Insuline lente Lantus 18 UI (`21:00`), Insuline rapide Novorapid avant chaque repas (`07:45`, `12:00`, `19:00`) avec tableau de correction en 5 tranches, Amlodipine 5 mg (`08:00`), Paracétamol 1000 mg « si besoin » (intervalle min 6 h).
   - Historique réaliste sur **14 jours glissants** : ~95 % de prises données, quelques « non donné » motivés, 2 hypoglycémies (`0,62 g/L` et `0,66 g/L`) avec resucrage tracé, quelques hyperglycémies, 4 repas/jour avec miniatures et photos avant/après générées en GD dans `storage/uploads/meals/`, notes, transmissions de fin de poste et soins rapides cochés.

### Étape 2 — Modèles & Services métier (`app/Models/`, `app/Services/`, `cron/run.php`)
1. **Services métier** :
   - `ThresholdService` : classification (`crit_low`, `low`, `ok`, `high`, `crit_high` → `alert_level` `ok` / `warn` / `critical`) selon le type et le contexte glycémique (`fasting` / `pre_meal` vs `post_meal`), contrôle des bornes de vraisemblance (`min_value` / `max_value` avec confirmation requise), conversion `g/L` ↔ `mg/dL` (`× 100`).
   - `ScheduleService` : génération idempotente sur 48 h (`measure_expectations` et `medication_intakes`) avec conversion `Europe/Paris` → `UTC` respectant les changements d’heure d’été et d’hiver (DST), et régénération ciblée des occurrences futures `pending` lors de la modification d’une prescription.
   - `IntakeService` : calcul du statut affiché (`upcoming`, `due` dans la fenêtre `±intake_window_min`, `late`, `given`, `skipped`), validation « Donné » et « Non donné » (motif obligatoire), **protection anti-double don** par transaction PDO `SELECT … FOR UPDATE` + verrou optimiste `version` (retourne HTTP `409 Conflict` avec message explicite *« Déjà donné par Fatou à 08h02 »*), contrôle de l’intervalle minimal des prises « si besoin » (`min_interval_h`) avec calcul de l’heure de la prochaine prise autorisée.
   - `InsulinService` : lecture stricte de la dose proposée dans le tableau de correction du responsable en fonction de la glycémie associée obligatoire, mention *« Dose selon le tableau du responsable »*, suggestion de rotation des sites d’injection (abdomen G/D, cuisse G/D, bras G/D — dernier site utilisé grisé).
   - `MealService` : déduction du type de repas selon l’heure locale, sauvegarde jusqu’à 3 photos (`avant`, `apres`, `autre`) avec miniatures 400 px via `Uploader`, liaison automatique avec la glycémie avant (≤ 60 min avant) et après (90 à 150 min après).
   - `TimelineService` : agrégation chronologique unifiée d’une journée (mesures, prises, repas, notes/transmissions, soins rapides), filtrable par type et paginée.
   - `HandoverService` : calcul automatique du résumé de fin de poste (depuis la dernière transmission ou sur les 12 dernières heures : mesures et valeurs hors cible, prises données/non données, repas, soins et notes importantes).
   - `AttentionService` : détection temps réel des points d’attention (valeur critique non suivie d’un contrôle normal, prise en retard > 30 min, mesure attendue en retard, transmission urgente/récente non lue par l’utilisateur courant).
   - `EditWindowService` : application transverse de la **règle des 30 minutes** (l’aide-soignante peut modifier sa propre saisie pendant 30 min ; au-delà, seuls `admin` et `coordinator` peuvent modifier avec motif obligatoire ; toute écriture et suppression logique est tracée dans `audit_logs` avec `before_data` et `after_data`).
   - `QrCodeService` : générateur SVG de QR code ISO/IEC 18004 côté serveur en PHP pur (zéro service externe, zéro CDN) pour l’écran « Installer l’application ».
2. **Tâche planifiée `cron/run.php`** : verrou exclusif `flock()` dans `storage/cache/cron.lock`, exécution de `schedule:generate` sur 48 h, et déclenchement idempotent de secours à l’ouverture de `/` et `/today`.

### Étape 3 — API JSON & Contrôleurs / Vues Phase 2 (`routes/`, `app/Controllers/`, `app/Views/`)
1. **Routes API JSON (`routes/api.php`)** :
   - `POST /api/measures`, `POST /api/intakes/{id}/give`, `POST /api/intakes/{id}/skip`, `POST /api/intakes/as-needed`, `POST /api/meals`, `POST /api/notes`, `POST /api/notes/{id}/read`, `POST /api/care-checks`, `GET /api/timeline`.
   - Format unique `{"ok": true, "data": ...}` / `{"ok": false, "error": {"code": "...", "message": "..."}}`, vérification CSRF et RBAC côté serveur, codes HTTP `200`/`201`/`403`/`409`/`422`.
2. **Contrôleurs & Écrans Phase 2** :
   - **Accueil (`HomeController`, `app/Views/home/index.php`)** :
     - Vue Aide-soignante (`caregiver`, `coordinator`) : bannière de transmission non lue en tête (bouton « Marquer comme lue » en 1 tap), carte d’installation PWA, **chronologie du poste** (fait avec coche, en retard avec accent rouge + bouton « Saisir », **tâche en cours mise en avant** dans une carte Bleu nuit avec action directe « Donné » / « Non donné » ou « Saisir la glycémie », à venir), soins rapides en 1 tap.
     - Vue Responsable (`admin`, `viewer`) : phrase de synthèse en tête (« Tout va bien » ou « X point(s) d’attention »), dernières valeurs clés (glycémie, tension, température) avec icône + texte de statut, observance du jour (« X/Y prises »), mini-courbe ApexCharts de glycémie sur 7 jours avec résumé textuel, liste des points d’attention.
   - **Aujourd’hui (`TodayController`, `/today` — E04)** : programme complet de la journée regroupé par moment (`Matin`, `Midi`, `Après-midi / Soir`, `Coucher`), navigation jour précédent / suivant / aujourd’hui, actions rapides en 1 tap.
   - **Santé & Mesures (`HealthController`, `/health`, `/health/{code}` — E06, E07)** :
     - Vue d’ensemble `/health` : cartes des types principaux avec dernière valeur et statut (icône + texte), carrousel horizontal des mesures secondaires, accès rapide à la saisie.
     - Détail `/health/{code}` : **bloc héros Bleu nuit** avec la dernière valeur en graisse `800` ; pour la glycémie, **jauge à zones** (hypo, cible, hyper) avec curseur positionné par classe CSS ; **courbe ApexCharts** sur `7`, `30` ou `90` jours avec **bandes de seuils** et **résumé textuel accessible** sous le graphique ; historique paginé et filtrable avec édition soumise à la règle des 30 minutes.
     - Bottom sheet de saisie de mesure (E06) : couleur et badge de statut immédiats pendant la frappe, affichage instantané du **protocole d’hypoglycémie** avec boutons d’appel `15`, `112` et responsable en cas de valeur critique, demande de confirmation si hors bornes de vraisemblance.
   - **Traitements & Prises (`TreatmentController`, `/treatments`, `/treatments/history`, `/admin/treatments`, `/admin/measures` — E08, E09, E20, E21)** :
     - Validation d’une prise (E08) : « Donné » en 1 tap, « Non donné » avec motif obligatoire, bottom sheet dédiée pour l’insuline variable (sélection/saisie de la glycémie obligatoire, dose lue dans le tableau du responsable, dose administrée modifiable, rotation des sites d’injection avec dernier site grisé), coche animée + retour haptique.
     - Historique & Observance (`/treatments/history` — E09) : journal par jour et par médicament, taux d’observance sur 7 et 30 jours.
     - Administration du Plan de traitement (`/admin/treatments` — E20, `admin`) : CRUD médicaments (avec photo de boîte), prescriptions (heures, jours, dates, « si besoin » avec intervalle), insuline et **éditeur du tableau de correction** par tranches de glycémie, upload et consultation sécurisée d’ordonnance (`PDF`, `JPG`, `PNG`).
     - Administration des Mesures & Seuils (`/admin/measures` — E21, `admin`) : configuration des 9 types et création de types personnalisés, seuils, bornes de vraisemblance, planification des mesures attendues, unité de glycémie (`g/L` / `mg/dL`).
   - **Repas (`MealController`, `/meals`, `/meals/{id}` — E10, E11)** :
     - Saisie d’un repas (E10) : type déduit de l’heure, favoris en 1 tap, **barre de portion en 4 segments** (`0 %` / `25 %` / `50 %` / `75 %` / `100 %`), glucides, boisson, photos **avant et après côte à côte** (`capture="environment"`, compression Canvas 1600 px JPEG 0.8 + barre de progression).
     - Galerie par jour & Fiche repas (`/meals`, `/meals/{id}` — E11) : miniatures par journée, détail avec photos avant/après, portion et glycémies avant/après liées automatiquement.
   - **Journal, Transmissions & Soins rapides (`JournalController`, `/journal` — E12, E13)** :
     - Fil unifié de la journée filtrable (`Tout`, `Mesures`, `Prises`, `Repas`, `Notes & Transmissions`, `Soins`), paginé.
     - Saisie de note / **Transmission de fin de poste** (E13) avec résumé automatique généré par `HandoverService` en tête, bannière « Transmission non lue » et accusé de lecture tracé par utilisateur (`note_reads`).
     - Grille de **Soins rapides** en 1 tap (toilette, change, hydratation, inspection pieds diabétiques, mobilisation, sommeil…).
   - **Réglages (`SettingsController`, `/settings`, `admin`)** : gestion des soins rapides, des aliments favoris, de la fenêtre de prise (`intake_window_min`) et de l’unité de glycémie.

### Étape 4 — Loader ≥ 2 s & Mode plein écran PWA (§4 bis)
1. **Loader (`public/assets/js/modules/loader.js`, `config/config.example.php`)** :
   - Réglage `app.loader_min_ms = 2000` (2 secondes minimum, 4 secondes maximum, sortie en fondu 300 ms).
   - Affichage à l’ouverture initiale de la session, **à chaque actualisation de la page** (`performance.getEntriesByType('navigation')[0]?.type === 'reload'`), et après connexion (mot de passe ou PIN), mais jamais lors de la navigation interne entre écrans.
2. **Manifeste, écrans de démarrage iOS & guide d’installation (`public/manifest.webmanifest`, `bin/make-icons.php`, `/install`)** :
   - `manifest.webmanifest` : `"id": "/"`, `"start_url": "/?source=pwa"`, `"scope": "/"`, `"display": "standalone"`, `"display_override": ["standalone"]`, `"orientation": "portrait"`, `"theme_color": "#0A1633"`, `"background_color": "#0A1633"`, raccourcis vers `/?quick=measure&type=glycemia` et `/today`, et 2 captures `screenshots` (`390×844`).
   - Balises iOS sur tous les layouts (`apple-mobile-web-app-capable=yes`, `mobile-web-app-capable=yes`, `apple-mobile-web-app-title=Prime Care`, `apple-mobile-web-app-status-bar-style=black-translucent`) et génération par `bin/make-icons.php` des écrans de démarrage iOS Bleu nuit (`#0A1633` + monogramme centré) pour iPhone SE, iPhone 13/14/15, Pro et Pro Max.
   - Carte « Installer Prime Care » en haut de l’accueil (refermable pour 7 jours, masquée définitivement en mode `standalone`), invite native Android (`beforeinstallprompt`), bottom sheet illustrée en 3 étapes pour Safari iPhone (et détection Chrome iOS invitant à ouvrir dans Safari), plus écran **Plus → « Installer l’application » (`/install`)** avec **QR code SVG généré côté serveur**.

### Étape 5 — Tests automatisés, Audit PWA, Captures 390×844 & Rapport `RETOUR_PHASE_2.md`
1. Extension de `php tests/run.php` avec les suites unitaires et d’intégration Phase 2 :
   - `ThresholdServiceTest`, `ScheduleServiceTest` (idempotence + changements d’heure d’été et d’hiver Paris/UTC + régénération après édition), `IntakeServiceTest` (statuts, double don `409`, intervalle « si besoin », motif obligatoire), `InsulinServiceTest`, `EditWindowTest` (règle des 30 min + audit admin), `UploadMediaTest` (faux fichier image refusé, EXIF supprimé, miniature créée), `Phase2ApiTest` (format JSON, CSRF, RBAC 403) et extension de `RoutesSmokeTest` à toutes les nouvelles routes GET pour les 4 rôles (`0 réponse 500`).
2. Vérification headless Chromium (0 erreur console, 0 erreur CSP, 0 débordement de `320px` à `1280px`, audit d’installabilité PWA Manifest + Service Worker + mode `standalone` émulé) et génération de toutes les captures Clair & Sombre dans `docs/retours/captures/phase-2/`.
3. Mise à jour de `README.md` (crontab + procédure HTTPS sur téléphone), `docs/DESIGN_SYSTEM.md` et `/design`, rédaction complète de `docs/retours/RETOUR_PHASE_2.md`, commit et `git push origin arena/1c2a1551-prime-care`.
