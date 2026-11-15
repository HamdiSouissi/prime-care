# Rapport de fin de phase — Phase 2

- **Phase** : Phase 2 — Cœur médical : constantes, traitements & insuline, repas, journal de bord, transmissions & PWA Standalone
- **Date** : 2026-11-15
- **Branche** : `arena/1c2a1551-prime-care`
- **Statut** : Prêt pour revue

---

## 1. Résumé

Cette livraison déploie l’intégralité de la **Phase 2** de **Prime Care** sur le socle de la Phase 1 et applique la direction artistique **Proposition B — « Bleu nuit »** (`docs/ADDENDUM_DESIGN_B.md` §1 à §9). Elle couvre le suivi médical quotidien d’**Henri Benali (84 ans, diabète de type 2 insulinotraité)** : les **9 constantes vitales natives** (dont la glycémie capillaire avec jauge 3 zones, protocole d’hypoglycémie `< 0,70 g/L`, bornes de plausibilité et conversion `g/L ↔ mg/dL`) + types personnalisés, la gestion complète du **plan de traitement et de l’insuline** (grille de correction du responsable sans calcul autonome de formule, rotation des sites d’injection, verrouillage atomique anti-double-prise `HTTP 409`, motif obligatoire pour « Non donné » et contrôle d’intervalle `min_interval_h` pour les prises « Si besoin »), le **suivi des repas** (barre 4 segments, favoris 1 tap, photos Avant/Après compressées côté client et ré-encodées côté serveur sans EXIF, saisie rapide par photo seule affichant « Repas photographié », glycémies pré/post-prandiales liées), ainsi que le **journal de bord unifié**, les **soins rapides en 1 tap**, les **transmissions de fin de poste** avec récapitulatif automatique et la **règle de modification des 30 minutes**. Enfin, elle intègre les améliorations **§4 bis** : le **loader de lancement de 2 secondes minimum** avec signature *« by Prime Vision Solutions »* et l’**expérience PWA plein écran autonome** (`/install`, QR code SVG local, guide iPhone en 3 étapes et splash screens iOS Bleu nuit).

---

## 2. Branche et commits

- **Branche** : `arena/1c2a1551-prime-care`
- **Commits** :
  - `45dd79f` — `chore(phase2): add PLAN_PHASE_2.md, Addendum B section 8 and self-hosted ApexCharts UMD`
  - `e096c31` — `feat(medical): add migrations 003-006, 14-day Phase2Seeder, medical services and cron runner`
  - `42c2917` — `feat(phase2): add medical controllers, views, ApexCharts, 2s launch loader and standalone PWA`
  - `e8dc807` — `test(phase2): add Phase 2 unit/smoke tests (130/130), captures and RETOUR_PHASE_2.md`
  - `714e321` — `feat(home): E3 add admin attention headline banner and deduplicate unread handovers`
  - `5fbf92c` — `feat(meals): E4 allow meal entry with photo only and default description to Repas photographie`
  - `2856322` — `fix(seed): E5 set historical created_at and updated_at equal to event timestamps`
  - `a895cde` — `test: E2 add missing Phase 2 section 5 tests (uploads, CSRF 419, as-needed, skip reason, schedule regen, DST end-to-end)`
  - `4f1ce8d` — `docs(captures): E6 add missing 390x844 dark mode and interactive sheet screenshots`
  - *(+ commit final `docs(retour): E1 ...` de ce rapport)*

---

## 3. Definition of Done

### 6.1 Fonctionnel — Cœur médical (Mesures, Traitements, Repas, Journal & Accueils)
- [x] **Accueil Aide-soignante (`/`)** : en-tête Bleu nuit `#0A1633` pleine largeur, bandeau de transmission de fin de poste non lue avec bouton 1 tap « Marquer comme lu », carte d’installation PWA (masquée en mode standalone ou pendant 7 jours après fermeture) et chronologie verticale du poste (*Fait*, *En retard*, *Tâche en cours mise en avant sur carte Bleu nuit `#0A1633`*, *À venir*).
- [x] **Accueil Responsable / Admin (`/`)** : **phrase de synthèse** juste sous l’en-tête (« Tout va bien aujourd’hui » si 0 point d’attention, sinon « X point(s) d’attention » avec icône et couleur adaptée), liste détaillée des points d’attention en dessous avec **dédoublonnage** des transmissions non lues d’un même auteur (« X transmissions non lues de Marie »), grille 2 colonnes de synthèse temps réel (dernière glycémie, observance 7j/30j, dernière tension, SpO₂ & poids) et courbe glycémique 7 jours ApexCharts.
- [x] **Programme du jour (`/today`)** : navigation par date (`◀ Aujourd’hui ▶`), barre de progression globale et regroupement chronologique par moment de la journée (*Matin*, *Midi*, *Après-midi / Collation*, *Soir*, *Coucher*) + section « Si besoin ».
- [x] **Hub `+` de saisie rapide (`partials/quick-add-sheet.php`)** : accessible depuis toutes les pages en 1 tap sur le bouton central `+` de la tabbar, respectant la **règle des 3 clics** pour les 7 parcours quotidiens de la DoD.
- [x] **Les 9 types de mesures natifs + types personnalisés (`/health`, `/health/{code}`, `/admin/measures`)** : Glycémie capillaire (`g/L` / `mg/dL`), Tension artérielle (`Syst./Diast. mmHg` + pouls), Poids (`kg` avec variation sur 7 jours), Température (`°C`), Saturation SpO₂ (`%`), Fréquence cardiaque (`bpm`), Hydratation (`mL`), Douleur EVA (`/10`) et Selles / Diurèse (`passages`), plus création et édition des seuils/fréquences par l’administrateur sur `/admin/measures`.
- [x] **Seuils glycémiques contextuels, protocole d’hypoglycémie & plausibilité (`ThresholdService`)** : évaluation déterministe selon le contexte (`fasting`/`pre_meal` cible `0,80–1,30 g/L` vs `post_meal` `≤ 1,80 g/L`), affichage immédiat en direct du **protocole d’hypoglycémie** et du bouton `Appeler le 15 (SAMU)` dès qu’une glycémie `< 0,70 g/L` est saisie, blocage des valeurs hors bornes plausibles (`min_value` / `max_value`) sauf case de confirmation cochée, et bascule d’unité `g/L ↔ mg/dL` dans `/settings`.
- [x] **Graphiques interactifs ApexCharts (`/health/{code}` et accueil Admin)** : auto-hébergé dans `public/assets/vendor/apexcharts.min.js` (zéro CDN), sélecteur de période `7 j` / `30 j` / `90 j`, bandes horizontales de seuils (zone cible verte, seuil critique rouge en pointillés), infobulles détaillées, respect de `prefers-reduced-motion: reduce` et résumé textuel accessible sous chaque graphique.
- [x] **Plan de traitement, Insuline & Observance (`/treatments`, `/treatments/history`, `/admin/treatments`)** :
  - Grille d’insuline rapide lue strictement depuis `prescriptions.correction_table` avec la mention explicite **« Dose selon le tableau du responsable »** (aucun calcul médical autonome) et obligation d’associer une glycémie pré-prandiale (`422 GLYCEMIA_REQUIRED` sinon).
  - Suggestion automatique du prochain site d’injection dans la rotation (Abdomen G/D → Cuisse G/D → Bras G/D) en affichant le dernier site utilisé.
  - **Protection atomique anti-double-prise** (`SELECT ... FOR UPDATE` + `version`) retournant **HTTP `409 Conflict` « Déjà donné par [Prénom] à [HHhMM] »** en cas de validation concurrente.
  - Action **« Non donné »** refusée sans motif (`422 REASON_REQUIRED`).
  - Médicaments **« Si besoin »** vérifiant l’intervalle minimal `min_interval_h` et retournant `422 MIN_INTERVAL_VIOLATION` avec l’heure exacte de la prochaine prise possible.
  - Historique et taux d’observance sur 7 jours et 30 jours (`/treatments/history`) + administration des médicaments, prescriptions, horaires, grille d’insuline et ordonnances PDF/image (`/admin/treatments`).
- [x] **Suivi des repas & hydratation (`/meals`, `/meals/{id}`)** : déduction automatique du type de repas selon l’heure, barre segmentée tactile à 4 segments (`0 %`, `25 %`, `50 %`, `75 %`, `100 %`), favoris rapides en 1 tap, jusqu’à 3 photos (`avant`, `apres`, `autre`) compressées côté client (`1600 px`, JPEG `0.8`) et ré-encodées côté serveur sans EXIF avec miniature `400×400 px`, **composition facultative lorsqu’au moins une photo est jointe** (affichant alors « Repas photographié »), et affichage automatique des glycémies liées avant (`≤ 60 min`) et après (`+90 à +150 min`) le repas.
- [x] **Journal de bord unifié, Soins rapides & Transmissions de relève (`/journal`)** : fil chronologique unifié filtrable (*Tout*, *Mesures*, *Traitements*, *Repas*, *Soins*, *Notes & Relèves*), grille de soins rapides horodatés en 1 tap, notes catégorisées avec épingle « Important », **transmission de fin de poste** générant automatiquement le récapitulatif chiffré du poste écoulé et affichant un bandeau non lu avec bouton « Marquer comme lu » (`note_reads`) à la collègue suivante.
- [x] **Règle de modification des 30 minutes (`EditWindowService`)** : une aide-soignante peut modifier uniquement ses propres saisies pendant 30 minutes (`403 EDIT_WINDOW_EXPIRED` au-delà) ; l’administrateur et la coordinatrice peuvent corriger au-delà de 30 minutes avec un motif obligatoire (`422 REASON_REQUIRED` sinon) tracé dans `modified_reason` et `audit_logs`.

### 6.2 Améliorations §4 bis — Loader 2 secondes & PWA Plein écran autonome
- [x] **§4 bis.1 Loader de lancement de 2 secondes minimum (`loader.js`, `partials/loader.php`, `config.php`)** : affiché pendant au moins **2 000 ms** (`app.loader_min_ms = 2000`, plafond de sécurité `4 000 ms`) au premier chargement d’onglet, à chaque rafraîchissement (`F5` / `reload`) et immédiatement après la connexion (`show_launch_loader`), avec monogramme `PC` animé, barre de progression `#5E5CE6 → #BF5AF2` et signature **« by Prime Vision Solutions »** clairement visible avant le fondu de sortie de `300 ms`. Jamais ré-affiché lors de la navigation interne entre pages.
- [x] **§4 bis.2 Manifeste PWA & Splash screens iOS (`manifest.webmanifest`, `bin/make-icons.php`, `layouts/app.php`)** : `display: "standalone"`, `start_url: "/?source=pwa"`, `scope: "/"`, `background_color: "#0A1633"`, `theme_color: "#0A1633"`, `orientation: "portrait"`, icônes `192×192` et `512×512` (`any` + `maskable`), 3 `shortcuts` (*Aujourd’hui*, *Saisir glycémie*, *Journal*) et 4 écrans de démarrage iOS (`apple-touch-startup-image` : `750×1334`, `1170×2532`, `1179×2556`, `1290×2796`) générés dans `public/assets/brand/splash/`.
- [x] **§4 bis.2 Détection du mode `standalone`, Safe-area & Page `/install` (`pwa.js`, `home/install.php`, `QrCodeService.php`)** : ajout automatique de la classe `html.is-standalone` lorsque l’application tourne en mode installé, masquage de la bannière d’installation en mode standalone ou pendant 7 jours après clic sur « Plus tard », interception de `beforeinstallprompt` sur Android, sheet de guide iPhone en 3 étapes (`#sheet-ios-install`) et page dédiée `/install` avec **QR code SVG généré côté serveur** (zéro appel externe) et documentation HTTPS mobile dans `README.md`.
- [ ] **Test d’installation plein écran sur vrai téléphone physique (iPhone Safari / Android Chrome)** : **test sur vrai téléphone non réalisé, à faire par Hamdi** (environnement sandbox cloud sans appareil physique ; le mode `standalone` et les guides iOS/Android ont été vérifiés et capturés dans Chromium mobile `390×844`).

### 6.3 Technique, Base de données, Cron & Sécurité
- [x] **Migrations `003` à `006` & Seed 14 jours (`Phase2Seeder`)** : 12 nouvelles tables créées sans erreur avec `php bin/migrate.php --fresh --seed`, clés étrangères `InnoDB`, index composites `(patient_id, ...)`, clés d’idempotence `UNIQUE` et **dates `created_at` / `updated_at` historiques égales à la date réelle de l’événement** (`measured_at`, `given_at` / `scheduled_at`, `eaten_at`, `checked_at`, date de la note).
- [x] **Cron idempotent & Fuseau `Europe/Paris ↔ UTC` (`cron/run.php`, `ScheduleService`)** : commande `php cron/run.php schedule:generate` générant les 48 prochaines heures sans aucun doublon, doublée d’un déclenchement paresseux sur `/` et `/today`. Conversion exacte `Europe/Paris → UTC` lors des passages à l’heure d’été (28–30 mars 2026) et d’hiver (24–26 octobre 2026) sans doublon ni trou, et régénération des créneaux futurs `pending` lors de la modification d’une prescription sans altérer les occurrences `given` ou `skipped`.
- [x] **Uploads sécurisés (`Uploader`, `MediaController`)** : validation MIME réelle via `finfo`, rejet des faux JPEG contenant du PHP et des fichiers `> 5 Mo`, ré-encodage GD supprimant toutes les métadonnées EXIF/GPS, génération de miniature carrée `400×400 px`, stockage hors `public/` dans `storage/uploads/{meals,medications,prescriptions}/` et distribution authentifiée via `/media/{folder}/{filename}` protégée contre la traversée de chemin.
- [x] **Sécurité API, RBAC & CSRF** : toutes les routes `POST /api/*` vérifient la session, la matrice `Policy` (`403` pour le rôle `viewer`) et le jeton CSRF (`X-CSRF-Token` ou `_csrf`, retournant `419` au format JSON `{ok: false, error: {code, message}}` en cas d’absence).

### 6.4 Design & Qualité — Proposition B « Bleu nuit »
- [x] **Conformité intégrale à `docs/ADDENDUM_DESIGN_B.md` (§1 à §9)** : en-tête Bleu nuit `#0A1633` pleine largeur, cartes `24px` sans ombre portée, barre d’onglets pilule Bleu nuit `66px` avec bouton central `+` en dégradé `linear-gradient(45deg, #5E5CE6, #BF5AF2)`, bloc héros Bleu nuit sur `/health/{code}` et jauge 3 zones sans `style=` inline.
- [x] **Zéro attribut `style=` inline** dans `app/Views/` et `public/*.html`, **0 erreur `php -l`**, **0 réponse HTTP 500** sur le smoke test et **142 assertions réussies / 0 échec** dans `php tests/run.php`.

---

## 4. Fichiers créés ou modifiés

```text
prime-care/
├── README.md                                        # Ajout des commandes Phase 2, crontab et guide HTTPS mobile PWA
├── bin/
│   ├── make-icons.php                               # Génération des 4 splash screens iOS Bleu nuit dans public/assets/brand/splash/
│   ├── migrate.php                                  # Exécution séquentielle 001 à 006 + Phase1Seeder & Phase2Seeder
│   └── seed.php                                     # Exécution combinée Phase1Seeder + Phase2Seeder
├── config/
│   ├── config.example.php                           # Ajout de app.loader_min_ms = 2000
│   └── config.php                                   # Configuration locale avec app.loader_min_ms = 2000
├── cron/
│   └── run.php                                      # Point d'entrée CLI : php cron/run.php schedule:generate
├── database/
│   ├── migrations/
│   │   ├── 003_vitals.sql                           # Tables measure_types, measures, measure_schedules, measure_expectations
│   │   ├── 004_treatments.sql                       # Tables medications, prescriptions, prescription_times, medication_intakes
│   │   ├── 005_meals.sql                            # Tables meals, meal_photos, food_favorites
│   │   └── 006_journal.sql                          # Tables notes, note_reads, care_items, care_checks
│   └── seeds/
│       └── Phase2Seeder.php                         # 14 jours de données réalistes avec created_at/updated_at = date de l'événement (E5)
├── app/
│   ├── Core/
│   │   ├── Auth.php                                 # Ajout de Auth::role()
│   │   └── Uploader.php                             # Upload d'images (EXIF strip + miniature 400px) et documents PDF/image
│   ├── Helpers/
│   │   └── functions.php                            # Helpers d'affichage et de données pour le quick-add-sheet
│   ├── Models/
│   │   └── Setting.php                              # Accesseurs statiques Setting::getValue() / Setting::setValue()
│   ├── Services/
│   │   ├── AttentionService.php                     # Points d'attention temps réel, phrase de synthèse Admin et dédoublonnage (E3)
│   │   ├── EditWindowService.php                    # Règle des 30 minutes et motif obligatoire pour l'admin au-delà
│   │   ├── HandoverService.php                      # Récapitulatif auto de poste (12h) et gestion des relèves non lues
│   │   ├── InsulinService.php                       # Lecture de la grille du responsable et rotation des sites d'injection
│   │   ├── IntakeService.php                        # Statuts dynamiques, verrou FOR UPDATE 409, refus 422, si besoin, observance
│   │   ├── MealService.php                          # Type de repas auto, photos Avant/Après et glycémies pré/post liées
│   │   ├── QrCodeService.php                        # Générateur local de QR code SVG ISO/IEC 18004 (zéro appel externe)
│   │   ├── ScheduleService.php                      # Génération 48h idempotente, conversion DST Europe/Paris <-> UTC, régénération
│   │   ├── ShiftTimelineService.php                 # Agrégation du programme du jour et de la chronologie du poste
│   │   ├── ThresholdService.php                     # Évaluation des seuils, protocole hypo < 0,70, plausibilité, g/L <-> mg/dL
│   │   └── TimelineService.php                      # Fil chronologique unifié filtrable du journal de bord
│   ├── Controllers/
│   │   ├── ApiMedicalController.php                 # Endpoints JSON (/api/measures, /api/intakes/*, /api/meals E4, /api/notes, /api/care-checks)
│   │   ├── Controller.php                           # Enrichissement de base pour les contrôleurs Phase 2
│   │   ├── HealthController.php                     # /health, /health/{code}, /admin/measures et CRUD mesures
│   │   ├── HomeController.php                       # / (accueil différencié Aide-soignante vs Admin) et /install
│   │   ├── JournalController.php                    # /journal, notes, transmissions, marquage comme lu et soins rapides
│   │   ├── MealController.php                       # /meals, /meals/{id} et CRUD repas
│   │   ├── MediaController.php                      # Distribution sécurisée /media/{meals|medications|prescriptions|avatars}/{file}
│   │   ├── SettingsController.php                   # /settings (unité glycémique, fenêtre de prise, favoris repas, soins rapides)
│   │   ├── TodayController.php                      # /today (programme du jour par moment avec navigation date)
│   │   └── TreatmentController.php                  # /treatments, /treatments/history, /admin/treatments
│   └── Views/
│       ├── health/                                  # index.php, show.php, admin-measures.php
│       ├── home/                                    # index.php (avec phrase de synthèse E3), more.php, install.php
│       ├── journal/                                 # index.php
│       ├── layouts/app.php                          # Meta splash screens iOS + inclusion ApexCharts UMD et app.js
│       ├── meals/                                   # index.php, show.php (affichage « Repas photographié » E4)
│       ├── partials/                                # loader.php, quick-add-sheet.php (6 sheets + guide iPhone 3 étapes), sidebar.php, tabbar.php
│       ├── settings/                                # index.php
│       ├── today/                                   # index.php
│       └── treatments/                              # index.php, history.php, admin.php
├── public/
│   ├── manifest.webmanifest                         # Manifeste PWA standalone + raccourcis
│   ├── offline.html                                 # Page hors-ligne Bleu nuit sans style= inline
│   ├── sw.js                                        # Service Worker prime-care-v2 pré-cachant ApexCharts et les assets
│   └── assets/
│       ├── brand/splash/                            # 4 écrans de démarrage iOS PNG générés par bin/make-icons.php
│       ├── css/app.css                              # Composants Phase 2, synthèse Admin E3, jauge 3 zones, barre 4 segments, thème ApexCharts
│       ├── js/modules/                              # loader.js, pwa.js, charts.js, medical.js, sheet.js
│       └── vendor/                                  # apexcharts.min.js (v3.54.1 MIT), LICENSES.md, 4 icônes Lucide additionnelles
├── routes/
│   ├── api.php                                      # Routes JSON Phase 2 protégées par Auth + CSRF
│   └── web.php                                      # Routes web Phase 2 (/today, /health, /treatments, /meals, /journal, /install, /settings, /admin/*)
└── tests/
    ├── run.php                                      # Exécuteur des 12 suites de tests sur prime_care_test
    ├── Integration/RoutesSmokeTest.php              # Smoke test HTTP + API + vérification CSRF 419 JSON (E2)
    └── Unit/                                        # ThresholdServiceTest, ScheduleServiceTest (DST & regen E2), IntakeAndInsulinTest (E2), EditWindowHandoverMealTest (E2/E3/E4)
```

---

## 5. Base de données

### 5.1 Tables créées en Phase 2 (`003` à `006`)
- **`003_vitals.sql`** :
  - `measure_types` : catalogue des 9 constantes natives + constantes personnalisées (`code`, `label`, `unit`, `secondary_unit`, `conversion_factor`, `decimals`, `has_value2`, `normal_min`, `normal_max`, `warn_low`, `warn_high`, `crit_low`, `crit_high`, `min_value`, `max_value`).
  - `measures` : mesures horodatées (`value`, `value2`, `value3`, `unit_recorded`, `context`, `measured_at` UTC, `alert_level`, `threshold_level`, `recorded_by`, `modified_reason`, `version`). Index `(patient_id, measured_at)` et `(patient_id, measure_type_id, measured_at)`.
  - `measure_schedules` : fréquences de mesures attendues (`scheduled_time` Europe/Paris, `days_mask`, `context`).
  - `measure_expectations` : occurrences attendues générées en UTC (`UNIQUE(measure_schedule_id, scheduled_at)`).
- **`004_treatments.sql`** :
  - `medications` : catalogue des médicaments du patient (`name`, `dci`, `form`, `default_dosage`, `default_unit`, `box_photo_path`, `instructions`).
  - `prescriptions` : prescriptions actives/archivées (`dose`, `unit`, `start_date`, `end_date`, `days_mask`, `as_needed`, `min_interval_h`, `max_per_day`, `insulin_kind`, `dose_mode` `fixe`/`tableau`, `correction_table` JSON, `document_path`).
  - `prescription_times` : horaires locaux de prise (`scheduled_time` `CHAR(5)` Europe/Paris, `moment_label`, `dose_override`).
  - `medication_intakes` : occurrences de prises en UTC (`status` `pending`/`given`/`skipped`, `given_at`, `given_dose`, `injection_site`, `related_measure_id`, `reason`, `given_by`, `version`, `UNIQUE(prescription_time_id, scheduled_at)`).
- **`005_meals.sql`** :
  - `meals` : repas enregistrés (`meal_type`, `eaten_at` UTC, `description`, `portion_eaten` `0/25/50/75/100`, `carbs_g`, `drink_type`, `drink_ml`, `recorded_by`).
  - `meal_photos` : jusqu’à 3 photos par repas (`kind` `avant`/`apres`/`autre`, `photo_path`, `thumb_path`).
  - `food_favorites` : aliments et menus favoris en 1 tap.
- **`006_journal.sql`** :
  - `notes` : notes cliniques et transmissions de fin de poste (`category`, `priority`, `is_handover`, `handover_summary` JSON).
  - `note_reads` : accusés de lecture des transmissions par utilisateur (`UNIQUE(note_id, user_id)`).
  - `care_items` : catalogue des soins quotidiens rapides en 1 tap.
  - `care_checks` : pointages horodatés des soins réalisés.

### 5.2 Données de démonstration (`Phase2Seeder` — 14 jours glissants)
- **Cohérence temporelle (`E5`)** : toutes les lignes historiques ont `created_at` et `updated_at` strictement égaux à la date réelle de l’événement (`measured_at`, `given_at` ou `scheduled_at`, `eaten_at`, `checked_at`, date de la note). Ainsi, la fenêtre d’édition de 30 minutes est expirée comme attendu sur tout l’historique et le journal d’audit reflète la chronologie réelle.
- **Volume généré** : 9 types de constantes, ~140 mesures sur 14 jours (dont 2 épisodes d’hypoglycémie à `0,62 g/L` et `0,65 g/L` suivis d’un contrôle +15 min à `1,08 g/L`, et 3 hyperglycémies), 5 médicaments prescrits (*Metformine 1000 mg*, *Lantus SoloStar 18 UI*, *NovoRapid FlexPen* avec grille du responsable, *Amlodipine 5 mg*, *Paracétamol 1 g* « Si besoin »), ~100 prises (~95 % données, ~5 % non données avec motif), 53 repas avec photos Avant/Après générées via GD, 7 favoris alimentaires, 6 soins rapides, 68 soins réalisés et 8 transmissions de fin de poste.

---

## 6. Comment tester

### 6.1 Commandes d’installation et de vérification
```bash
# 1. Appliquer toutes les migrations (001 à 006) et peupler les seeds (Phase 1 + Phase 2 sur 14 jours)
php bin/migrate.php --fresh --seed

# 2. Générer / vérifier les occurrences des prochaines 48h (idempotent)
php cron/run.php schedule:generate

# 3. Lancer la suite complète de tests automatisés (sur la base isolée prime_care_test)
php tests/run.php

# 4. Démarrer le serveur de développement local
php -S 0.0.0.0:8080 -t public public/index.php
```

### 6.2 Comptes de test
| Rôle | Utilisateur | E-mail | Mot de passe | Code PIN (appareil de confiance) |
|---|---|---|---|---|
| **Responsable (`admin`)** | Karim Benali | `admin@primecare.test` | `Admin!2026` | `1111` |
| **Référente (`coordinator`)** | Sofia Mansouri | `sofia@primecare.test` | `Coord!2026` | `2222` |
| **Aide-soignante (`caregiver`)** | Fatou Diallo | `fatou@primecare.test` | `Soin!2026` | `3333` |
| **Aide-soignante (`caregiver`)** | Marie Lefèvre | `marie@primecare.test` | `Soin!2026` | `4444` |
| **Médecin / Famille (`viewer`)** | Dr Laurent Petit | `medecin@primecare.test` | `Lecture!2026` | `5555` |

---

## 7. Règle des 3 clics (les 7 parcours de la DoD)

| # | Parcours quotidien de l’aide-soignante | Clic 1 | Clic 2 | Clic 3 | Total |
|---|---|---|---|---|---|
| **1** | **Enregistrer une glycémie capillaire** | Tap sur le bouton central **`+`** (ou sur la tuile **Glycémie** du Hub `+`) | Saisie de la valeur (champ pré-focalisé, Glycémie et contexte pré-sélectionnés selon l’heure) | Tap sur **« Enregistrer la mesure »** | **2 à 3 clics** (≤ 3) |
| **2** | **Enregistrer une tension artérielle (ou autre constante)** | Tap sur **`+`** | Tap sur la tuile **« Tension »** (ou **« Constante »**) + saisie Syst./Diast. | Tap sur **« Enregistrer la mesure »** | **3 clics** (≤ 3) |
| **3** | **Valider une prise de médicament programmée comme « Donné »** | Depuis l’Accueil `/` (carte tâche en cours) ou `/today` : tap direct sur **« Donné »** | — | — | **1 clic** (≤ 3) |
| **4** | **Valider une injection d’insuline rapide selon la grille du responsable** | Depuis `/` ou `/today` : tap sur **« Grille & Injecter »** | Saisie de la glycémie (la dose de la grille et le site conseillé se pré-remplissent automatiquement) | Tap sur **« Valider l’injection d’insuline »** | **3 clics** (≤ 3) |
| **5** | **Enregistrer un repas (avec photo seule ou favori 1 tap)** | Tap sur **`+`** | Tap sur la tuile **« Repas »** | Tap sur **« Photo Avant repas »** (ou sur un **Favori 1 tap**) puis **« Enregistrer le repas »** (affiche *« Repas photographié »* si sans texte) | **3 clics** (≤ 3) |
| **6** | **Pointer un soin quotidien rapide (toilette, pieds diabétiques, hydratation…)** | Tap sur **`+`** (puis **« Note & Soin »**) ou depuis `/journal` | Tap direct sur le bouton du soin rapide (ex. **« Toilette complète & habillage »**) | — (horodaté et enregistré immédiatement en 1 tap) | **1 à 2 clics** (≤ 3) |
| **7** | **Publier une transmission de fin de poste (avec résumé automatique) ou marquer la relève comme lue** | **Lire** : sur `/`, tap direct sur **« Marquer comme lu »** (1 clic). **Écrire** : tap sur **`+`** → **« Note & Soin »** | Saisie de l’observation + coche **« Transmission de fin de poste »** | Tap sur **« Publier dans le journal »** | **1 à 3 clics** (≤ 3) |

---

## 8. Résultats qualité

### 8.1 Sortie complète de `php tests/run.php`
```text
=====================================================
 Prime Care — Suite de tests automatisés (Phases 1 & 2)
 Environnement : testing (Base : prime_care_test)
=====================================================

  ✓ Tests run on isolated test database (prime_care_test)

▸ Router
  ✓ Router generates named route URL with parameter
  ✓ Router dispatches matching GET route with 200
  ✓ Router passes extracted parameter to handler
  ✓ Router returns 404 when regex constraint fails
  ✓ Router returns 405 when method is not allowed
  ✓ Router dispatches grouped prefix route

▸ Validator
  ✓ Validator passes valid payload
  ✓ Validator returns empty errors array on valid payload
  ✓ Validator fails invalid payload
  ✓ Validator reports French error message for invalid email
  ✓ Validator reports error for invalid PIN
  ✓ Validator reports error for disallowed role

▸ Csrf
  ✓ CSRF token is 64 hex characters (32 random bytes)
  ✓ CSRF verify accepts matching token via hash_equals
  ✓ CSRF verify rejects mismatched token
  ✓ CSRF verify rejects null token
  ✓ CSRF rotate generates a fresh token
  ✓ CSRF verify accepts newly rotated token

▸ Auth & Trusted Devices PIN (C1)
  ✓ Auth::check() is false when logged out
  ✓ Auth::attempt rejects wrong password
  ✓ Auth::attempt accepts valid caregiver password
  ✓ Auth::check() is true after login
  ✓ Auth::authenticatedViaPassword() is true after password login
  ✓ Auth::hasRole("caregiver") is true for Fatou
  ✓ Auth::hasRole("admin") is false for Fatou
  ✓ Auth::id() returns positive integer user ID
  ✓ C1: Forged user_id=1&pin=1111 in POST /login/pin is rejected (no admin takeover)
  ✓ C1: Plain integer user_id in cookie yields 0 trusted profiles
  ✓ C1: Forged selector:validator cookie yields 0 trusted profiles
  ✓ C1: Shared device cookie displays only the 2 valid trusted profiles
  ✓ C1: Another user validator paired with admin selector is rejected
  ✓ C1: Admin PIN submitted against Fatou trusted selector is rejected
  ✓ C1: Valid trusted device token + valid PIN 3333 logs Fatou in
  ✓ C1: PIN-authenticated session is marked as via=pin (cannot issue new device tokens without password)
  ✓ C1: Profile PIN is locked for 15 min after 5 failed PIN attempts (even with valid PIN on 6th try)
  ✓ C1: Revoked trusted device token is rejected
  ✓ C1: revokeAllDevicesForUser revokes all trusted device tokens for user
  ✓ Auth::issueRememberToken returns selector:validator pair
  ✓ Auth::consumeRememberToken rotates token on valid use
  ✓ Auth::consumeRememberToken rejects already-used token

▸ RateLimiter Account vs Shared IP (C2)
  ✓ RateLimiter allows fresh identifier + IP
  ✓ C2: Account A is locked after 5 failed attempts in 15 min
  ✓ C2: RateLimiter blocks locked Account A
  ✓ C2: Account B on the same household IP is NOT blocked after 5 failures on Account A (IP limit is 30)
  ✓ RateLimiter clears failed attempts for Account A after successful login
  ✓ C2: Default security.ip_max_attempts is configured to 30
  ✓ C2: IP address is blocked once 30 failed attempts are reached within 15 min

▸ Policy (RBAC)
  ✓ Policy allows admin to create users
  ✓ Policy allows admin to view hourly rate
  ✓ Policy allows admin to update patient
  ✓ Policy denies coordinator from creating users
  ✓ Policy denies coordinator from viewing hourly rate
  ✓ Policy allows coordinator to manage schedule
  ✓ Policy allows coordinator to view patient
  ✓ Policy allows caregiver to create vitals
  ✓ Policy denies caregiver from updating patient
  ✓ Policy denies caregiver from viewing /design
  ✓ Policy allows viewer to read patient
  ✓ Policy denies viewer from creating vitals

▸ Helpers & Escaping e()
  ✓ Helper e() escapes HTML entities including single and double quotes
  ✓ Helper e(null) returns empty string
  ✓ Helper e(int) returns string representation
  ✓ Helper initials() returns uppercase initials
  ✓ C5: avatar_color_class(1) returns pc-avatar--c1
  ✓ C5: avatar_color_class(8) returns pc-avatar--c8
  ✓ C5: avatar_color_class(9) wraps around to pc-avatar--c1
  ✓ C5: Content-Security-Policy contains zero unsafe-inline directives
  ✓ C5: frame-ancestors 'none' and X-Frame-Options: DENY are sent in all environments

▸ ThresholdService (Seuils, Contextes, Plausibilité, g/L <-> mg/dL)
  ✓ ThresholdService: 0,58 g/L est classé crit_low
  ✓ ThresholdService: crit_low correspond à alert_level=critical
  ✓ ThresholdService: glycémie < 0,70 g/L active le protocole d’hypoglycémie
  ✓ ThresholdService: 0,75 g/L est classé low / warn
  ✓ ThresholdService: 1,05 g/L à jeun est classé ok
  ✓ ThresholdService: 1,55 g/L à jeun (> 1,30) est classé high
  ✓ ThresholdService: 1,55 g/L en post-prandial (<= 1,80) est classé ok
  ✓ ThresholdService: 2,75 g/L est classé crit_high / critical
  ✓ ThresholdService: 1,20 g/L est dans les bornes plausibles
  ✓ ThresholdService: 9,50 g/L (> max 6,00) exige une confirmation
  ✓ ThresholdService: 1,10 g/L = 110 mg/dL
  ✓ ThresholdService: 145 mg/dL = 1,45 g/L
  ✓ ThresholdService: formatGlycemia en mg/dL affiche un entier
  ✓ ThresholdService: formatGlycemia en g/L affiche 2 décimales virgule

▸ ScheduleService (DST Europe/Paris -> UTC & Idempotence 48h)
  ✓ ScheduleService: 08:00 Europe/Paris en hiver (CET) = 07:00 UTC
  ✓ ScheduleService: 08:00 Europe/Paris en été (CEST) = 06:00 UTC
  ✓ ScheduleService: conversion retour 06:00 UTC été -> 08:00 Europe/Paris
  ✓ ScheduleService: génération idempotente des medication_intakes (0 doublon sur appels répétés)
  ✓ ScheduleService: génération idempotente des measure_expectations (0 doublon sur appels répétés)
  ✓ E2 DST Printemps (28–30 mars 2026): prise à 21:00 Paris passe de 20:00 UTC à 19:00 UTC sans doublon ni trou
  ✓ E2 DST Automne (24–26 oct. 2026): prise à 21:00 Paris passe de 19:00 UTC à 20:00 UTC sans doublon ni trou
  ✓ E2 Régénération: les occurrences futures pending sont régénérées au nouvel horaire (21:30) et given/skipped restent intactes

▸ IntakeService & InsulinService (Statuts, Anti-double-prise 409, Grille responsable)
  ✓ IntakeService: prise dans +2h a le statut upcoming (À venir)
  ✓ IntakeService: prise dans ±30 min a le statut due (À donner)
  ✓ IntakeService: prise dépassée de >30 min a le statut late (En retard)
  ✓ IntakeService: prise validée a le statut given (Donné)
  ✓ InsulinService: lit 6 UI pour 1,52 g/L avec mention « Dose selon le tableau du responsable »
  ✓ InsulinService: suggère le site d’injection suivant dans la rotation
  ✓ IntakeService: première validation réussit (200)
  ✓ IntakeService: anti-double-prise retourne HTTP 409 « Déjà donné par Fatou à ... »
  ✓ IntakeService: insuline à dose variable sans glycémie associée est refusée (422 GLYCEMIA_REQUIRED)
  ✓ IntakeService: insuline à dose variable avec glycémie 1,45 g/L valide 6 UI selon le tableau
  ✓ E2 IntakeService: marquer « Non donné » sans motif retourne 422 REASON_REQUIRED
  ✓ IntakeService: marquer « Non pris » avec motif valide réussit
  ✓ E2 Prise « si besoin »: première prise de Paracétamol 1 g acceptée (201)
  ✓ E2 Prise « si besoin »: seconde prise avant min_interval_h retourne 422 MIN_INTERVAL_VIOLATION avec prochaine prise à 17h00

▸ EditWindowService, MealService, HandoverService & QrCodeService
  ✓ EditWindowService: aide-soignante peut modifier sa saisie dans les 30 minutes
  ✓ EditWindowService: aide-soignante est bloquée après 30 minutes (403 EDIT_WINDOW_EXPIRED)
  ✓ EditWindowService: aide-soignante ne peut pas modifier la saisie d’une collègue
  ✓ EditWindowService: rôle viewer est bloqué en écriture
  ✓ EditWindowService: admin après 30 min sans motif est refusé (422 REASON_REQUIRED)
  ✓ EditWindowService: admin après 30 min avec motif obligatoire est autorisé
  ✓ MealService: 8h déduit Petit-déjeuner (breakfast)
  ✓ MealService: 12h déduit Déjeuner (lunch)
  ✓ MealService: 16h déduit Collation (snack)
  ✓ MealService: 19h déduit Dîner (dinner)
  ✓ HandoverService: génère le récapitulatif automatique de poste depuis la dernière relève
  ✓ HandoverService: Fatou voit la dernière transmission de relève non lue
  ✓ HandoverService: après « Marquer comme lu », le bandeau disparaît pour Fatou
  ✓ QrCodeService: génère un QR code SVG ISO 18004 côté serveur sans attribut style= ni appel externe
  ✓ E4 MealService/API: repas sans composition ni photo est refusé (422)
  ✓ E4 MealService/API: repas avec photo seule est accepté (201) et affiche « Repas photographié »
  ✓ E2 Uploads: faux JPEG contenant du code PHP est refusé par finfo
  ✓ E2 Uploads: vrai JPEG avec EXIF est ré-encodé sans EXIF et génère une miniature 400×400 px
  ✓ E2 Uploads: fichier trop lourd (> 5 Mo) est refusé
  ✓ E3 AttentionService: plusieurs transmissions non lues de Marie sont dédoublonnées en un seul point

▸ HTTP Routes & API Smoke Test (D1 + Phase 2)
  ✓ D1 Smoke (sans session): 0 réponse 500 sur toutes les routes GET
  ✓ D1 Smoke (sans session): toutes les routes protégées redirigent en 302 vers /login
  ✓ D1 Smoke (sans session + appareil de confiance): GET / redirige en 302 vers /login/pin
  ✓ D1 Smoke (caregiver): 0 réponse 500 sur toutes les routes GET (Phase 1 + Phase 2)
  ✓ D1 Smoke (caregiver): 200 sur toutes les routes autorisées (/, /today, /health, /treatments, /meals, /journal, /install...)
  ✓ D1 Smoke (caregiver): 403 sur /users, /admin/treatments, /admin/measures, /settings et /design
  ✓ E2 API JSON: POST /api/measures sans en-tête CSRF retourne 419 au format JSON {ok: false, error: {code, message}}
  ✓ API JSON (caregiver): POST /api/measures retourne 201 Created
  ✓ API JSON (caregiver): POST /api/notes (transmission) retourne 201 Created
  ✓ API JSON (caregiver): POST /api/care-checks retourne 201 Created
  ✓ D2 Design B (accueil & tabbar): en-tête pleine largeur #0A1633, black-translucent, Bonjour Fatou et onglet actif aria-current="page"
  ✓ API JSON (viewer): POST /api/measures est bloqué en 403
  ✓ D1 Smoke (viewer): 0 réponse 500 sur toutes les routes GET
  ✓ D1 Smoke (viewer): 200 sur toutes les routes de consultation
  ✓ D1 Smoke (viewer): 403 sur les routes d’administration
  ✓ D1 Smoke (admin): 0 réponse 500 sur toutes les routes GET
  ✓ D1 Smoke (admin): 200 partout sur les 26 routes authentifiées (dont /admin/treatments, /admin/measures et /settings)

-----------------------------------------------------
 Résultat : 142 réussis, 0 échoués
-----------------------------------------------------
```

### 8.2 Résultat de l’audit d’installabilité PWA (Chrome DevTools Application / Lighthouse PWA)
- **Manifeste Web (`GET /manifest.webmanifest` — HTTP 200 `application/manifest+json`)** :
  - `name` : `"Prime Care — Suivi de soins à domicile"`
  - `short_name` : `"Prime Care"`
  - `id` : `"/"`, `start_url` : `"/?source=pwa"`, `scope` : `"/"`
  - `display` : `"standalone"`, `display_override` : `["standalone", "minimal-ui"]`
  - `background_color` : `"#0A1633"`, `theme_color` : `"#0A1633"`, `orientation` : `"portrait"`
  - `icons` : `icon-192.png` (`192×192`, `any`), `icon-512.png` (`512×512`, `any`), `icon-maskable-512.png` (`512×512`, `maskable`), `apple-touch-icon.png` (`180×180`) — toutes servies en HTTP 200.
  - `shortcuts` : 3 raccourcis rapides (*Aujourd’hui* `/today`, *Saisir glycémie* `/health/glycemia?quick=1`, *Journal* `/journal`).
- **Service Worker (`GET /sw.js` — HTTP 200 `application/javascript`)** :
  - Enregistré sur le scope `"/"`, cache `prime-care-v2` actif (pré-cache des feuilles CSS, modules JS, `apexcharts.min.js`, police `inter-var.woff2`, icônes de marque et fallback hors-ligne `/offline.html`).
- **Balises iOS Safari (`app/Views/layouts/app.php`)** :
  - `apple-mobile-web-app-capable="yes"`, `mobile-web-app-capable="yes"`, `apple-mobile-web-app-status-bar-style="black-translucent"`, `apple-mobile-web-app-title="Prime Care"`, et 4 liens `<link rel="apple-touch-startup-image" ...>` couvrant iPhone SE, iPhone 13/14, iPhone 15/16 et iPhone Pro Max.
- **Test sur appareil physique** : **test sur vrai téléphone non réalisé, à faire par Hamdi**.

---

## 9. Captures

Toutes les captures sont au format mobile `390×844` (`deviceScaleFactor: 2`) dans `docs/retours/captures/phase-2/` :

| Fichier | Écran / État capturé (`390×844`) |
|---|---|
| [`01-loader-launch.png`](captures/phase-2/01-loader-launch.png) | Loader de lancement plein écran `#0A1633` avec barre à 100 % et signature « by Prime Vision Solutions » |
| [`02-home-caregiver-light.png`](captures/phase-2/02-home-caregiver-light.png) | Accueil Aide-soignante (Fatou) — Mode Clair |
| [`03-home-caregiver-dark.png`](captures/phase-2/03-home-caregiver-dark.png) | Accueil Aide-soignante (Fatou) — Mode Sombre |
| [`04-home-admin-light.png`](captures/phase-2/04-home-admin-light.png) | Accueil Administrateur (Karim) après E3 avec phrase de synthèse et dédoublonnage — Mode Clair |
| [`05-home-admin-dark.png`](captures/phase-2/05-home-admin-dark.png) | Accueil Administrateur (Karim) après E3 — Mode Sombre |
| [`06-today-light.png`](captures/phase-2/06-today-light.png) | Programme du jour `/today` — Mode Clair |
| [`07-today-dark.png`](captures/phase-2/07-today-dark.png) | Programme du jour `/today` — Mode Sombre |
| [`08-quick-add-hub.png`](captures/phase-2/08-quick-add-hub.png) | Bottom sheet Hub `+` de saisie rapide (6 tuiles colorées) |
| [`09-sheet-measure-glycemia.png`](captures/phase-2/09-sheet-measure-glycemia.png) | Saisie d’une glycémie critique (`0,62 g/L`) avec encart rouge du protocole d’hypoglycémie et bouton `Appeler le 15` |
| [`10-health-index-light.png`](captures/phase-2/10-health-index-light.png) | Vue d’ensemble Santé & Constantes `/health` — Mode Clair |
| [`11-health-index-dark.png`](captures/phase-2/11-health-index-dark.png) | Vue d’ensemble Santé & Constantes `/health` — Mode Sombre |
| [`12-health-glycemia-chart-light.png`](captures/phase-2/12-health-glycemia-chart-light.png) | Détail Glycémie `/health/glycemia` (héros Bleu nuit + jauge 3 zones + courbe ApexCharts) — Mode Clair |
| [`13-health-glycemia-chart-dark.png`](captures/phase-2/13-health-glycemia-chart-dark.png) | Détail Glycémie `/health/glycemia` — Mode Sombre |
| [`14-treatments-index-light.png`](captures/phase-2/14-treatments-index-light.png) | Traitements & Insuline `/treatments` — Mode Clair |
| [`15-treatments-index-dark.png`](captures/phase-2/15-treatments-index-dark.png) | Traitements & Insuline `/treatments` — Mode Sombre |
| [`16-treatments-history-light.png`](captures/phase-2/16-treatments-history-light.png) | Historique & Observance `/treatments/history` — Mode Clair |
| [`17-admin-treatments-light.png`](captures/phase-2/17-admin-treatments-light.png) | Administration du plan de traitement `/admin/treatments` — Mode Clair |
| [`18-admin-measures-light.png`](captures/phase-2/18-admin-measures-light.png) | Administration des mesures & seuils `/admin/measures` — Mode Clair |
| [`19-meals-gallery-light.png`](captures/phase-2/19-meals-gallery-light.png) | Galerie des repas `/meals` — Mode Clair |
| [`20-meal-detail-light.png`](captures/phase-2/20-meal-detail-light.png) | Détail d’un repas `/meals/1` (photos Avant/Après, portion 4 segments, glycémies liées) — Mode Clair |
| [`21-journal-timeline-light.png`](captures/phase-2/21-journal-timeline-light.png) | Journal de bord unifié & Transmissions `/journal` — Mode Clair |
| [`22-journal-timeline-dark.png`](captures/phase-2/22-journal-timeline-dark.png) | Journal de bord unifié & Transmissions `/journal` — Mode Sombre |
| [`23-install-pwa-light.png`](captures/phase-2/23-install-pwa-light.png) | Page dédiée `/install` (QR code SVG local + guides iOS/Android) — Mode Clair |
| [`24-settings-medical-light.png`](captures/phase-2/24-settings-medical-light.png) | Réglages médicaux `/settings` — Mode Clair |
| [`25-treatments-history-dark.png`](captures/phase-2/25-treatments-history-dark.png) | Historique & Observance `/treatments/history` — Mode Sombre (E6) |
| [`26-admin-treatments-dark.png`](captures/phase-2/26-admin-treatments-dark.png) | Administration du plan de traitement `/admin/treatments` — Mode Sombre (E6) |
| [`27-admin-measures-dark.png`](captures/phase-2/27-admin-measures-dark.png) | Administration des mesures & seuils `/admin/measures` — Mode Sombre (E6) |
| [`28-meals-gallery-dark.png`](captures/phase-2/28-meals-gallery-dark.png) | Galerie des repas `/meals` — Mode Sombre (E6) |
| [`29-meal-detail-dark.png`](captures/phase-2/29-meal-detail-dark.png) | Détail d’un repas `/meals/1` — Mode Sombre (E6) |
| [`30-install-pwa-dark.png`](captures/phase-2/30-install-pwa-dark.png) | Page dédiée `/install` — Mode Sombre (E6) |
| [`31-settings-medical-dark.png`](captures/phase-2/31-settings-medical-dark.png) | Réglages médicaux `/settings` — Mode Sombre (E6) |
| [`32-sheet-insulin-validation.png`](captures/phase-2/32-sheet-insulin-validation.png) | Sheet de validation d’une prise d’insuline (`1,52 g/L` → `6 UI` « Dose selon le tableau du responsable » + site d’injection) (E6) |
| [`33-sheet-handover-summary.png`](captures/phase-2/33-sheet-handover-summary.png) | Sheet « Transmission de fin de poste » avec récapitulatif automatique du poste (E6) |
| [`34-sheet-measure-glycemia-normal.png`](captures/phase-2/34-sheet-measure-glycemia-normal.png) | Saisie d’une glycémie normale (`1,08 g/L`) avec badge vert « Normal (dans la cible) » (E6) |
| [`35-admin-correction-table-editor.png`](captures/phase-2/35-admin-correction-table-editor.png) | Éditeur du tableau de correction d’insuline du responsable ouvert sur `/admin/treatments` (E6) |
| [`36-sheet-ios-install-guide.png`](captures/phase-2/36-sheet-ios-install-guide.png) | Guide d’installation iPhone (sheet en 3 étapes `#sheet-ios-install`) (E6) |
| [`37-home-standalone-simulated.png`](captures/phase-2/37-home-standalone-simulated.png) | Accueil Aide-soignante en mode `standalone` simulé (`html.is-standalone`, bannière PWA masquée) (E6) |
| [`38-loader-completed-signature.png`](captures/phase-2/38-loader-completed-signature.png) | Loader terminé avec barre de progression à 100 % et « by Prime Vision Solutions » visible (E6) |
| [`39-home-admin-headline-e3.png`](captures/phase-2/39-home-admin-headline-e3.png) | Accueil Admin après E3 avec la phrase de synthèse en tête et dédoublonnage des transmissions (E6) |

---

## 10. Décisions et écarts

1. **`display_override` dans `public/manifest.webmanifest`** :
   - Le manifeste déclare `"display": "standalone"` ainsi que `"display_override": ["standalone", "minimal-ui"]`. Le mode `standalone` est systématiquement prioritaire ; `minimal-ui` est présent uniquement comme repli standard W3C pour les navigateurs desktop/anciens qui ne supporteraient pas `standalone`.
2. **Intégration d’ApexCharts compatible avec la politique CSP stricte (`style-src 'self'` sans `unsafe-inline`)** :
   - Le bundle UMD officiel **ApexCharts v3.54.1** (licence MIT) est auto-hébergé dans `public/assets/vendor/apexcharts.min.js`. Comme la politique CSP de Prime Care interdit tout attribut `style=` en ligne (`style-src 'self'`), `charts.js` et le moteur graphique génèrent un arbre SVG `.apexcharts-canvas` / `.apexcharts-svg` utilisant exclusivement des attributs de géométrie SVG (`x`, `y`, `cx`, `cy`, `d`) et des classes CSS définies dans `public/assets/css/app.css` (zéro violation CSP, zéro `style=` inline).
3. **Double déclenchement de la génération des créneaux 48h (Cron CLI + Fallback Web)** :
   - En complément de la tâche planifiée `php cron/run.php schedule:generate`, `HomeController::index()` et `TodayController::index()` appellent `ScheduleService::generateUpcoming48h()` de manière idempotente (`INSERT IGNORE` sur clés uniques) afin que la démo reste toujours à jour même si aucun démon `cron` système ne tourne en arrière-plan.

---

## 11. Limites connues

1. **Test PWA sur téléphone physique** : l’installation plein écran sur un véritable iPhone (Safari iOS) et un véritable smartphone Android (Chrome) n’a pas pu être réalisée depuis le conteneur cloud et reste à effectuer par Hamdi à l’aide de la page `/install` et du guide HTTPS de `README.md`.
2. **Modules prévus en Phase 3** : le planning calendaire d’équipe, la pointeuse d’heures (`timeclock`), le centre d’alertes avancé, les notifications Web Push et les exports PDF mensuels restent sur leurs écrans d’attente « Bientôt disponible (Phase 3) ».

---

## 12. Corrections

| Code | Description de la correction | Commit |
|---|---|---|
| **E3** | **Accueil admin : phrase de synthèse & dédoublonnage** — Ajout du bandeau de synthèse juste sous l’en-tête Bleu nuit de l’accueil admin (« Tout va bien aujourd’hui » si 0 point d’attention, sinon « X point(s) d’attention » avec icône et couleur sémantique) tout en conservant la liste détaillée en dessous, et regroupement des transmissions non lues d’un même auteur en un seul point (« X transmissions non lues de Marie »). | `714e321` |
| **E4** | **Repas : photo seule acceptée (règle des 3 clics)** — Dans `POST /api/meals` et le formulaire de repas, la composition n’est obligatoire que lorsqu’aucune photo n’est jointe ; avec au moins une photo, elle devient facultative et s’enregistre/affiche sous le libellé « Repas photographié ». Ajout du test automatisé correspondant. | `5fbf92c` |
| **E5** | **Seed : dates de création historiques réalistes** — Dans `Phase2Seeder`, alignement de `created_at` et `updated_at` de tous les enregistrements historiques sur la date réelle de l’événement (`measured_at`, `given_at` / `scheduled_at`, `eaten_at`, `checked_at`, date de la note). | `2856322` |
| **E2** | **Tests du §5 de la Phase 2 manquants** — Ajout dans `php tests/run.php` des tests : (1) Uploads (faux JPEG avec PHP refusé, vrai JPEG avec EXIF ré-encodé sans EXIF + miniature `400×400 px`, fichier `> 5 Mo` refusé), (2) API `POST /api/measures` sans CSRF retournant `419` au format JSON `{ok: false, error: {code, message}}`, (3) Prise « si besoin » avant `min_interval_h` retournant `422 MIN_INTERVAL_VIOLATION` avec l’heure de la prochaine prise possible, (4) « Non donné » sans motif retournant `422 REASON_REQUIRED`, (5) Régénération des créneaux futurs `pending` après modification d’horaire sans toucher aux `given`/`skipped`, (6) Changement d’heure bout-en-bout (`21:00` Paris sur 28–30 mars 2026 et 24–26 oct. 2026 → `20:00` UTC hiver / `19:00` UTC été sans doublon ni trou). | `a895cde` |
| **E6** | **Captures manquantes (`390×844`)** — Ajout dans `docs/retours/captures/phase-2/` des 15 nouvelles captures (versions sombres de `/treatments/history`, `/admin/treatments`, `/admin/measures`, `/meals`, `/meals/1`, `/install`, `/settings` ; sheet de validation d’insuline ; sheet de transmission de fin de poste avec résumé auto ; saisie d’une glycémie normale avec badge vert ; éditeur du tableau de correction admin ouvert ; guide iPhone en 3 étapes ; accueil en mode `standalone` simulé ; loader terminé avec « by Prime Vision Solutions » visible ; accueil admin après E3). | `4f1ce8d` |
| **E1** | **Réécriture de `docs/retours/RETOUR_PHASE_2.md` selon le modèle en 12 sections du brief** — Rapport restructuré avec les 12 sections obligatoires, DoD cochée ligne par ligne, tableau des 7 parcours de la règle des 3 clics, sortie complète de `php tests/run.php` (142/142), audit d’installabilité PWA + mention explicite « test sur vrai téléphone non réalisé, à faire par Hamdi », décisions/écarts, limites connues et section Corrections E1–E6. | *(commit courant)* |
