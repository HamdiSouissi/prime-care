# Rapport de fin de phase — Phase 3

- **Phase** : Phase 3 — Équipe, pointage, alertes et mode hors ligne
- **Date** : 2026-11-15
- **Branche** : `arena/0f0ecf73-prime-care`
- **Statut** : Prêt pour revue

---

## 1. Résumé

Cette livraison déploie l’intégralité de la **Phase 3** de **Prime Care** sur le socle des Phases 1 & 2, dans le respect strict de la direction artistique **Proposition B — « Bleu nuit »** (`docs/ADDENDUM_DESIGN_B.md` §1 à §9, enrichi de la section **§8 bis — Composants spécifiques de la Phase 3**). Elle couvre :
1. **Le planning d’équipe (`/planning` — E16)** : vues Semaine (7 jours) et Mois, modèles de créneaux récurrents (garde de jour `08h–20h`, garde de nuit `20h–08h` traversant minuit), création/édition/remplacement en bottom-sheet, détection stricte des chevauchements par aide-soignante (`HTTP 422`), duplication d’une semaine sur `N` semaines (`1` à `8`), déclaration des absences (`conge`, `maladie`, `autre`) marquant automatiquement les créneaux concernés **« À remplacer »** avec alerte immédiate, et filtrage RBAC strict (une aide-soignante ne voit que ses propres gardes ; le responsable et la coordinatrice voient toute l’équipe).
2. **Le pointage d’arrivée et de départ, le contrôle hebdomadaire et le calcul des heures (`/`, `/attendance`, `/attendance/hours` — E05, E17, E18)** : bouton glissant tactile anti-faux-clic en haut de l’accueil Aide-soignante (doublé d’un bouton accessible clavier/lecteur d’écran), notice RGPD au premier pointage, preuve de présence configurable dans `/settings?tab=attendance` (**Position GPS Haversine** lors du pointage uniquement, **QR code du domicile signé HMAC-SHA256** avec affiche A4 imprimable `/settings/attendance/qr-print` et caméra `jsQR`, **Les deux**, ou **Aucun**), principe **« jamais bloqué »** (un pointage hors rayon ou sans GPS passe en `pending` « À valider » avec son motif exact), déclaration d’oubli de pointage avec motif obligatoire, option `require_clocked_in` bloquant la saisie médicale hors poste (`HTTP 403 « Pointez votre arrivée pour saisir »`), calculateur d’heures `HoursCalculatorService` gérant les gardes de nuit (`21h–06h`), les dimanches, les **11 jours fériés français** (fixes + Pâques, Ascension, Pentecôte) et les changements d’heure été/hiver (`Europe/Paris` : `11h` en mars, `13h` en octobre), et export CSV paie (`;`, BOM UTF-8).
3. **Le moteur d’alertes à 7 règles, le centre d’alertes (`/alerts`) et les notifications multi-canaux (Web Push VAPID natif, E-mail HTML PHPMailer, interface SMS)** : déclenchement temps réel et périodique, dédoublonnage strict par `dedupe_key` (`open_dedupe_key`), **fermeture automatique** dès que l’action attendue est réalisée, **escalade automatique après 15 minutes** pour toute alerte critique non acquittée, respect des **heures calmes (`22h00–07h00`)** contournées uniquement par les alertes critiques, chiffrement Web Push **RFC 8291 (`aes128gcm`) + JWT VAPID `ES256` RFC 8292** en PHP 8.2+ natif (`ext-openssl` + `ext-curl`, zéro dépendance Composer), e-mails HTML **Bleu nuit `#0A1633`** via **PHPMailer 6.9.2** auto-hébergé (avec repli automatique vers `storage/logs/mail.log`), et bouton **« Envoyer une notification de test »** dans `/profile`.
4. **Le mode hors ligne complet avec file d’attente IndexedDB (`offline-queue.js` + `sw.js`)** : cache local des pages HTML principales et des données du jour, interception transparente des saisies hors ligne (mesures, prises, repas sans photo, soins rapides, notes, pointages) avec identifiant d’idempotence **`client_uuid` (UUID v4)**, bandeau ambre *« Hors ligne · X saisie(s) en attente »*, synchronisation automatique au retour du réseau avec dédoublonnage côté serveur (`200 OK` sans doublon en base), et gestion dédiée des conflits `HTTP 409`.

---

## 2. Branche et commits

- **Branche** : `arena/0f0ecf73-prime-care` (créée depuis `main` `b252a7b03cfb2e407bdd5c355500e8b080442fb1`)
- **Commits de la Phase 3** :
  - `95a238e` — `chore(phase3): add PLAN_PHASE_3.md, vendor jsQR & PHPMailer, and Addendum B section 8 bis`
  - `48064ea` — `feat(phase3): add migrations 007-010, Phase3Seeder, planning, attendance, hours, alerts, WebPush VAPID and offline sync`
  - `c3c0666` — `test(phase3): add Phase 3 unit & smoke tests (171/171), 28 captures (390x844) and RETOUR_PHASE_3.md`
- **Commits des corrections post-revue (G1 à G7)** :
  - `4bd09ac` — **G1** : `fix(offline): G1 wire all medical forms to offline queue and preserve client_recorded_at with 2min/72h guardrails`
  - `57c7d0a` — **G2** : `fix(attendance): G2 enforce GDPR notice server-side with 403 GDPR_NOTICE_REQUIRED and chain punch on accept`
  - `6bcd510` — **G3** : `fix(sw): G3 restrict offline navigation cache to ['/','/today','/journal'] and add Cache-Control no-store on exports/media`
  - `08acad5` — **G4** : `fix(hours): G4 display monthly hours summary as caregiver cards under 600px with month total card at top`
  - `0072d69` — **G5** : `fix(ui): G5 style slide punch button with Addendum B signature gradient, white thumb and green completed state`
  - `9e50edf` — **G6** : `test(phase3): G6 add RFC 8291 Appendix A vector, 410 Gone, queue backoff 5x, 2027 holidays, offline client_recorded_at and G2/G3 tests`
  - *(+ commit final **G7** : captures `390×844` G7 et mise à jour de `RETOUR_PHASE_3.md`)*

---

## 3. Definition of Done

### 3.1 Planning d’équipe (`/planning` — E16)
- [x] **Vues Semaine (7 jours) et Mois** avec navigation temporelle (`◀ Aujourd’hui ▶`) et filtres par aide-soignante ou statut (« Non couvert / À remplacer »).
- [x] **Modèles de créneaux récurrents (`shift_templates`)** : Garde de jour (`08h00–20h00`), Garde de nuit (`20h00–08h00`, `crosses_midnight = 1`), Garde week-end, applicables en 1 clic à la création d’un créneau.
- [x] **Création, modification, suppression et remplacement de créneaux (`shifts`)** en bottom-sheet, avec calcul automatique de la date de fin (+1 jour si `end_time <= start_time`) et refus `HTTP 422` en cas de chevauchement horaire pour la même aide-soignante.
- [x] **Duplication d’une semaine type sur `N` semaines (`1` à `8`)** en ignorant proprement les créneaux déjà occupés.
- [x] **Gestion des absences (`absences` : congé, maladie, autre)** : bascule automatiquement les créneaux planifiés sur la période au statut `unassigned` (« À remplacer ») et déclenche une alerte pour le responsable.
- [x] **Filtrage RBAC strict** : une aide-soignante (`caregiver`) ne voit que ses propres créneaux de garde et son prochain poste ; le responsable (`admin`), la coordinatrice (`coordinator`) et le lecteur (`viewer`) voient le planning complet de l’équipe.

### 3.2 Pointage, Preuve de présence & Calcul des heures (`/`, `/attendance`, `/attendance/hours` — E05, E17, E18)
- [x] **Bouton glissant tactile (« Glisser pour arriver » / « Glisser pour partir »)** en tête de l’accueil Aide-soignante (`/`), doublé d’un bouton accessible clavier/lecteur d’écran (« Confirmer mon arrivée / mon départ »).
- [x] **Notice d’information RGPD au premier pointage** (`users.attendance_gdpr_accepted_at`) informant que la position GPS n’est relevée qu’à l’instant précis du pointage et jamais en continu.
- [x] **4 modes de preuve de présence configurables dans `/settings?tab=attendance`** :
  - **GPS seul (`gps`)** : calcul Haversine côté serveur par rapport aux coordonnées du domicile (`patients.home_latitude`, `home_longitude`, rayon `home_radius_m` par défaut `150 m`).
  - **QR code seul (`qr`)** : scan caméra via `jsQR.min.js` (auto-hébergé) ou saisie du code de secours d’un jeton `PRIMECARE-HOME:{token}:{hmac}` signé `HMAC-SHA256`, avec bouton « Régénérer le QR » (révoquant immédiatement l’ancien) et **affiche A4 imprimable** (`/settings/attendance/qr-print`).
  - **Les deux (`both`)** ou **Aucun (`none`)**.
- [x] **Principe « Jamais bloqué »** : un pointage hors rayon (`> 150 m`), avec géolocalisation refusée ou sans scan QR est toujours enregistré immédiatement avec `status = 'pending'` (« À valider ») et le motif exact (*« Hors du domicile (320 m) »*, *« Position refusée »*).
- [x] **Lien « J’ai oublié de pointer »** : bottom-sheet permettant de déclarer son heure réelle d’arrivée ou de départ avec motif obligatoire (`422` si vide), créé en statut `pending` (« À valider »).
- [x] **Option « Saisie autorisée uniquement si l’aide-soignante est pointée » (`require_clocked_in`, désactivée par défaut)** : lorsqu’elle est activée dans `/settings?tab=attendance`, toute tentative de saisie médicale par une aide-soignante non pointée en arrivée retourne **HTTP `403` « Pointez votre arrivée pour saisir »**.
- [x] **Contrôle hebdomadaire (`/attendance` — E17)** : cartes de validation/refus en 1 clic des pointages `pending` et grille comparative Hebdomadaire (créneaux prévus vs heures pointées + cumul mensuel).
- [x] **Récapitulatif mensuel des heures & Export CSV paie (`/attendance/hours` & `/attendance/export-csv` — E18)** : découpage minute par minute via `HoursCalculatorService` distinguant les heures normales, les **heures de nuit (`21h00–06h00`)**, les **heures du dimanche**, les **heures des 11 jours fériés français** (fixes + Pâques, Lundi de Pâques, Ascension, Lundi de Pentecôte via `easter_days()`), et gérant exactement les **passages à l’heure d’été (`11h00` réelles) et d’hiver (`13h00` réelles)** en `Europe/Paris`. Estimation brute (`hourly_rate`) visible uniquement par `admin`, et export CSV paie séparateur `;` avec BOM UTF-8 (`\xEF\xBB\xBF`).

### 3.3 Moteur d’alertes (7 règles), Centre d’alertes (`/alerts`) & Notifications (Web Push VAPID, E-mail, SMS)
- [x] **Les 7 règles d’alerte actives (`AlertService`)** :
  1. `measure_critical` : constante critique (ex. glycémie `< 0,70 g/L` ou `> 2,50 g/L`, SpO₂ `< 90 %`, tension critique) — déclenchement immédiat à la saisie.
  2. `measure_warn` : constante hors cible (vigilance orange) — déclenchement immédiat à la saisie.
  3. `intake_late` : prise de médicament ou d’insuline en retard (`> 30 min` = `warning`, `> 60 min` = `critical`).
  4. `measure_missing` : mesure attendue non saisie (`> 60 min` après l’échéance).
  5. `meal_missing` : repas non enregistré après la fin de la plage horaire (`09h30`, `14h00`, `20h30`).
  6. `shift_unclocked` : pointage d’arrivée absent `+15 min` après le début d’une garde planifiée.
  7. `handover_unread` : transmission de fin de poste non lue `+30 min` après la prise de poste suivante.
- [x] **Dédoublonnage strict par `dedupe_key` (`open_dedupe_key`)** : tant qu’une alerte est ouverte (`new` ou `seen`) pour la même clé métier, aucun doublon n’est créé ni ré-envoyé.
- [x] **Fermeture automatique (`autoResolveByDedupeKey` / `autoResolveBySource`)** : dès que la prise en retard est administrée, la mesure saisie, le repas enregistré, le pointage effectué ou la transmission lue, l’alerte correspondante passe automatiquement à `handled` avec mention de résolution.
- [x] **Escalade automatique après 15 minutes** : toute alerte `critical` restée au statut `new` depuis plus de 15 minutes est automatiquement escaladée (`escalated_at`) et ré-envoyée au responsable (`admin`) en priorité haute.
- [x] **Centre d’alertes (`/alerts`) & Cloche dans l’en-tête** : compteur temps réel d’alertes ouvertes dans la barre supérieure, passage automatique `new → seen` à l’ouverture, filtres *Ouvertes / Traitées / Toutes / 7 Règles* et par gravité, et traitement en 1 clic (*« Vérifié »*, *« Appelé l’aide-soignante »*, *« Médecin contacté »*, *« Contrôle normal effectué »* ou note libre).
- [x] **Web Push VAPID natif (`WebPushService` + `bin/make-vapid.php`)** : génération de clés ECDSA `prime256v1` dans `storage/keys/vapid.json` (hors Git), signature JWT `ES256` (conversion ASN.1 DER → `r || s` 64 octets selon RFC 8292), chiffrement de charge utile **`aes128gcm` (RFC 8291 : ECDH P-256 + HKDF-SHA-256 + AES-128-GCM)**, envoi HTTPS via `ext-curl` et désactivation automatique des abonnements expirés (`HTTP 404` / `410`).
- [x] **Notifications E-mail HTML (`MailService` via PHPMailer 6.9.2 auto-hébergé) & Canal SMS (`SmsChannel`)** : gabarit HTML Bleu nuit `#0A1633` avec badge de gravité et bouton d’action directe, repli automatique dans `storage/logs/mail.log` si SMTP est vide, interface `SmsChannel` prête et badge « SMS (bientôt) » dans l’interface.
- [x] **Préférences utilisateur, Heures calmes (`22h00–07h00`) & Bouton de test (`/profile`)** : réglage par type d’alerte et par canal, plage d’heures calmes différant les notifications non critiques jusqu’à la fin de la plage (les alertes critiques contournent toujours les heures calmes), et bouton **« Envoyer une notification de test »** fonctionnel.
- [ ] **Test des notifications Web Push sur téléphone physique (iPhone iOS 16.4+ installé en PWA / Android Chrome)** : **notifications push sur vrai téléphone non testées, à faire par Hamdi** (le chiffrement/déchiffrement `aes128gcm` RFC 8291 et la signature/vérification JWT VAPID `ES256` RFC 8292 sont vérifiés de bout en bout par test automatisé dans `Phase3Test`).

### 3.4 Mode hors ligne & Synchronisation (`offline-queue.js`, `sw.js`, `client_uuid`)
- [x] **Service Worker `prime-care-v3` (`public/sw.js`)** : pré-cache des assets statiques (CSS, JS, `apexcharts.min.js`, `jsQR.min.js`, polices, icônes), mise en cache *Network-First* des pages HTML principales (`/`, `/today`, `/health`, `/treatments`, `/meals`, `/journal`, `/patient`, `/planning`, `/attendance`, `/alerts`) avec repli vers `/offline.html`, et écoute des événements `push` et `notificationclick`.
- [x] **File d’attente IndexedDB (`public/assets/js/offline-queue.js`)** : stockage local des actions saisies hors ligne avec `client_uuid` (UUID v4) et `client_recorded_at`, bandeau ambre `#pc-offline-banner` + barre de synchronisation `#pc-offline-queue-bar`, vidage séquentiel au retour du réseau (`online` ou bouton « Synchroniser maintenant »), dédoublonnage idempotent côté serveur (`UNIQUE(patient_id, client_uuid)` retournant `200 OK` sans doublon), et gestion explicite des conflits `HTTP 409`.

### 3.5 Tâche planifiée unifiée (`cron/run.php` & `CronService`)
- [x] **5 tâches périodiques protégées par `flock` (`storage/cache/cron.lock`)** : `schedule:generate`, `alerts:scan`, `notifications:send` (jusqu’à 3 tentatives en cas d’échec réseau), `sessions:clean` et `logs:clean`, avec enregistrement de `last_cron_run_at` dans `settings` et filet de sécurité paresseux sur `/` et `/today` si le dernier passage date de plus de 10 minutes.

---

## 4. Fichiers créés ou modifiés

```text
prime-care/
├── .gitignore                                       # Exclusion de storage/keys/*.json et *.pem
├── README.md                                        # Commandes Phase 3 (make-vapid.php, cron/run.php)
├── bin/
│   ├── make-vapid.php                               # Génération CLI des clés ECDSA P-256 VAPID dans storage/keys/vapid.json
│   ├── migrate.php                                  # Exécution des migrations 001 à 010 + Phase1/2/3Seeder
│   └── seed.php                                     # Exécution combinée Phase1Seeder + Phase2Seeder + Phase3Seeder
├── cron/
│   └── run.php                                      # Point d'entrée CLI des 5 tâches planifiées Phase 3
├── database/
│   ├── migrations/
│   │   ├── 007_planning.sql                         # Tables shift_templates, shifts, absences
│   │   ├── 008_attendance.sql                       # Tables attendances + colonnes domicile/RGPD sur patients & users
│   │   ├── 009_alerts.sql                           # Tables alerts (avec open_dedupe_key), notifications, push_subscriptions + préférences users
│   │   └── 010_offline.sql                          # Colonnes client_uuid et index d'idempotence UNIQUE sur 6 tables
│   └── seeds/
│       └── Phase3Seeder.php                         # 14 jours de gardes, remplacements, pointages (valides + hors zone + oubli) et alertes
├── docs/
│   ├── ADDENDUM_DESIGN_B.md                         # Ajout de §8 bis (composants spécifiques Phase 3)
│   ├── DESIGN_SYSTEM.md                             # Mise à jour de la documentation des composants Phase 3
│   └── retours/
│       ├── PLAN_PHASE_3.md                          # Plan d'exécution détaillé de la Phase 3
│       ├── RETOUR_PHASE_3.md                        # Présent rapport de fin de phase en 12 sections
│       └── captures/phase-3/                        # 28 captures 390×844 (clair, sombre, sheets, impression QR, hors-ligne)
├── app/
│   ├── Core/
│   │   └── View.php                                 # Support des layouts personnalisés (layouts/print)
│   ├── Helpers/
│   │   ├── bootstrap.php                            # Autoloader PSR-4 incluant PHPMailer\PHPMailer\*
│   │   └── functions.php                            # Alias format_date_fr() et helpers de vues
│   ├── Models/
│   │   └── Setting.php                              # Accesseurs JSON / scalaires pour les réglages de pointage et de cron
│   ├── Services/
│   │   ├── AlertService.php                         # Moteur des 7 règles d'alerte, dédoublonnage, clôture auto, escalade 15 min, heures calmes
│   │   ├── AttendanceService.php                    # Pointage GPS Haversine, QR HMAC-SHA256, oubli déclaré, require_clocked_in, RGPD
│   │   ├── AttentionService.php                     # Synthèse temps réel unifiée (alertes ouvertes + pointages à valider + gardes à remplacer)
│   │   ├── AuditService.php                         # Méthode AuditService::record() pour la traçabilité Phase 3
│   │   ├── CronService.php                          # Orchestrateur des 5 tâches périodiques avec verrou flock
│   │   ├── HoursCalculatorService.php               # Calculateur d'heures (nuit 21h-6h, dimanche, 11 fériés, DST mars/octobre) + export CSV
│   │   ├── IntakeService.php                        # Fermeture automatique des alertes intake_late à la validation d'une prise
│   │   ├── MailService.php                          # Envoi d'e-mails HTML Bleu nuit via PHPMailer 6.9.2 + fallback storage/logs/mail.log
│   │   ├── PlanningService.php                      # CRUD créneaux, anti-chevauchement, duplication N semaines, absences & remplacements
│   │   ├── Policy.php                               # Matrice RBAC enrichie (planning, attendance, hours, alerts)
│   │   ├── WebPushService.php                       # Web Push VAPID natif : clés P-256, JWT ES256 (RFC 8292) et aes128gcm (RFC 8291)
│   │   └── Channels/
│   │       └── SmsChannel.php                       # Interface canal SMS prête pour un fournisseur tiers
│   ├── Controllers/
│   │   ├── AlertController.php                      # /alerts, /alerts/{id}/seen, /alerts/{id}/handle, /api/push/*
│   │   ├── ApiMedicalController.php                 # Idempotence client_uuid, contrôle require_clocked_in (403) et /api/offline/bootstrap
│   │   ├── AttendanceController.php                 # /attendance, /attendance/hours, /attendance/export-csv, /api/attendance/*
│   │   ├── HealthController.php                     # Contrôle require_clocked_in + déclenchement/clôture auto d'alertes sur mesures
│   │   ├── HomeController.php                       # Intégration du pointage en tête d'accueil Aide-soignante, synthèse Admin et fallback cron
│   │   ├── JournalController.php                    # Contrôle require_clocked_in + clôture auto d'alerte handover_unread à la lecture
│   │   ├── MealController.php                       # Contrôle require_clocked_in + clôture auto d'alerte meal_missing
│   │   ├── PlanningController.php                   # /planning (Semaine/Mois), créneaux, duplication, absences et modèles
│   │   ├── ProfileController.php                    # Préférences de notifications, heures calmes et test Web Push
│   │   ├── SettingsController.php                   # Onglet Pointage & QR (/settings?tab=attendance) et affiche A4 (/settings/attendance/qr-print)
│   │   ├── TodayController.php                      # Filet de sécurité cron paresseux et état du pointage
│   │   └── TreatmentController.php                  # Contrôle require_clocked_in sur l'administration des prises
│   └── Views/
│       ├── alerts/index.php                         # Centre d'alertes (Ouvertes, Traitées, Toutes, 7 Règles)
│       ├── attendance/                              # index.php (contrôle hebdomadaire & oubli), hours.php (récapitulatif mensuel & CSV)
│       ├── design/index.php                         # Galerie /design enrichie des composants Phase 3
│       ├── home/                                    # index.php (carte pointage glissant E05 + synthèse Admin), more.php (liens Phase 3 actifs)
│       ├── layouts/                                 # app.php (bandeau hors ligne + jsQR), print.php (mise en page A4 imprimable)
│       ├── partials/                                # navbar.php & sidebar.php (cloche d'alertes avec compteur temps réel)
│       ├── planning/index.php                       # Planning d'équipe (Semaine 7j, Mois, sheets créneau/duplication/absence/modèles)
│       ├── profile/index.php                        # Carte Notifications & Heures calmes + bouton de test Web Push
│       └── settings/                                # index.php (onglet Pointage & QR), qr-print.php (affiche A4 QR domicile)
├── public/
│   ├── sw.js                                        # Service Worker prime-care-v3 (cache HTML hors-ligne + écouteurs push & notificationclick)
│   └── assets/
│       ├── css/                                     # tokens.css & app.css (styles Phase 3, slider pointage, planning, alertes, print A4)
│       ├── js/
│       │   ├── app.js                               # Initialisation d'offline-queue.js et de phase3.js
│       │   ├── offline-queue.js                     # Moteur IndexedDB hors ligne, interception fetch et rejeu séquentiel client_uuid
│       │   └── modules/
│       │       ├── medical.js                       # Support transparent des réponses {queued: true} hors ligne
│       │       └── phase3.js                        # Slider tactile de pointage, GPS, scanner caméra jsQR, sheets planning et Web Push
│       └── vendor/
│           ├── jsQR.min.js                          # jsQR 1.4.0 (Apache-2.0) auto-hébergé
│           ├── phpmailer/                           # PHPMailer 6.9.2 (LGPL-2.1) auto-hébergé (PHPMailer.php, SMTP.php, Exception.php)
│           ├── lucide/                              # 13 nouvelles icônes SVG Lucide Phase 3
│           └── LICENSES.md                          # Licences tierces mises à jour (ApexCharts, jsQR, PHPMailer, Lucide, Inter)
├── routes/
│   ├── api.php                                      # Routes JSON Phase 3 (/api/attendance/*, /api/shifts/*, /api/alerts/*, /api/push/*, /api/offline/*)
│   └── web.php                                      # Routes Web Phase 3 (/planning, /attendance, /attendance/hours, /alerts, /settings/attendance/*)
├── storage/keys/
│   └── .gitkeep                                     # Répertoire de stockage de vapid.json (exclu de Git)
└── tests/
    ├── run.php                                      # Exécuteur des 13 suites de tests (Phases 1, 2 & 3)
    ├── Integration/RoutesSmokeTest.php              # Smoke test HTTP (34 routes GET), RBAC, idempotence client_uuid, 403 require_clocked_in, 0 style=
    └── Unit/Phase3Test.php                          # Tests unitaires Phase 3 (Planning, Haversine, QR HMAC, Heures DST/Fériés, Alertes, WebPush RFC 8291/8292)
```

---

## 5. Base de données

### 5.1 Migrations créées en Phase 3 (`007` à `010`)
- **`007_planning.sql`** :
  - `shift_templates` : modèles de créneaux récurrents (`label`, `start_time`, `end_time`, `crosses_midnight`, `default_user_id`, `role_hint`, `color_tag`, `notes`).
  - `shifts` : gardes planifiées (`patient_id`, `user_id` nullable pour un créneau non couvert, `template_id`, `shift_date` locale, `start_at` UTC, `end_at` UTC, `start_time` / `end_time` `Europe/Paris`, `crosses_midnight`, `status` `planned`/`completed`/`absent`/`unassigned`, `replaced_user_id`, `notes`). Index `(patient_id, shift_date)` et `(user_id, start_at, end_at)`.
  - `absences` : congés et arrêts (`patient_id`, `user_id`, `absence_type` `conge`/`maladie`/`autre`, `start_date`, `end_date`, `comment`).
- **`008_attendance.sql`** :
  - Colonnes sur `patients` : `home_latitude` (`DECIMAL(10,7)`), `home_longitude` (`DECIMAL(10,7)`), `home_radius_m` (`INT`, défaut `150`), `qr_secret_token` (`VARCHAR(128)`), `qr_generated_at`.
  - Colonne sur `users` : `attendance_gdpr_accepted_at` (`DATETIME NULL`).
  - `attendances` : pointages d’arrivée et de départ (`patient_id`, `user_id`, `shift_id`, `client_uuid`, `type` `in`/`out`, `method` `slide`/`qr`/`declared`/`admin`, `punched_at` UTC, `declared_at` UTC, `latitude`, `longitude`, `accuracy_m`, `distance_m`, `qr_verified`, `status` `valid`/`pending`/`rejected`, `reason`, `validated_by`, `validated_at`, `rejection_reason`).
- **`009_alerts.sql`** :
  - Colonnes sur `users` : `notification_preferences` (`JSON`), `quiet_hours_enabled` (`TINYINT(1)`), `quiet_hours_start` (`CHAR(5)` défaut `'22:00'`), `quiet_hours_end` (`CHAR(5)` défaut `'07:00'`).
  - `alerts` : alertes générées (`patient_id`, `type` parmi les 7 règles, `level` `info`/`warning`/`critical`, `source_type`, `source_id`, `dedupe_key`, `open_dedupe_key` persistée/générée pour garantir `UNIQUE(patient_id, open_dedupe_key)` sur les alertes ouvertes, `title`, `message`, `target_url`, `status` `new`/`seen`/`handled`, `seen_at`, `seen_by`, `handled_at`, `handled_by`, `resolution`, `escalated_at`).
  - `notifications` : file d’envoi multi-canaux (`alert_id`, `user_id`, `channel` `push`/`email`/`sms`, `status` `pending`/`sent`/`failed`/`skipped`, `scheduled_for` UTC pour les heures calmes, `sent_at`, `attempts`, `error_message`, `payload` JSON).
  - `push_subscriptions` : abonnements Web Push des navigateurs (`user_id`, `endpoint_hash` SHA-256 `UNIQUE`, `endpoint`, `p256dh`, `auth_secret`, `user_agent`, `content_encoding`).
- **`010_offline.sql`** :
  - Ajout de `client_uuid CHAR(36) NULL` et d’un index `UNIQUE(patient_id, client_uuid)` sur `measures`, `medication_intakes`, `meals`, `notes`, `care_checks` et `attendances` pour garantir l’idempotence absolue lors du rejeu de la file d’attente hors ligne.

### 5.2 Données de démonstration (`Phase3Seeder` — 14 jours glissants + 7 jours à venir)
- **Modèles de créneaux (`shift_templates`)** : 3 modèles (*Garde de jour (08h–20h)*, *Garde de nuit (20h–08h)*, *Renfort week-end*).
- **Planning (`shifts`) & Absences (`absences`)** : 21 jours de gardes continues (jour `08h–20h` par Fatou Diallo, nuit `20h–08h` par Marie Lefèvre, week-ends partagés avec Sofia Marchetti), incluant un **jour férié travaillé (11 novembre)**, une **absence déclarée de Fatou demain** avec un créneau déjà remplacé par Sofia (`replaced_user_id`) et un créneau marqué **« À remplacer » (`unassigned`)**.
- **Pointages (`attendances`)** : 14 jours de pointages d’arrivée et de départ réalistes :
  - 90 % de pointages `valid` dans le rayon de `150 m` du domicile de Créteil (`12 m` à `65 m`) ou par scan QR ;
  - 1 pointage `pending` (« À valider ») **Hors du domicile (`320 m`)** ;
  - 1 pointage `pending` (« À valider ») **Oubli déclaré** par Marie Lefèvre avec motif explicite.
- **Alertes (`alerts`) & Notifications (`notifications`)** :
  - **5 alertes ouvertes** (*Glycémie critique `0,66 g/L` escaladée*, *Pointage d’arrivée absent*, *Prise Metformine en retard*, *Créneau non couvert demain*, *Transmission de relève non lue*) ;
  - **3 alertes historiques traitées** avec motif de résolution et auteur.

---

## 6. Comment tester

### 6.1 Commandes d’installation et de vérification
```bash
# 1. Appliquer toutes les migrations (001 à 010) et peupler les seeds (Phases 1, 2 & 3)
php bin/migrate.php --fresh --seed

# 2. Générer ou vérifier la paire de clés ECDSA prime256v1 VAPID (dans storage/keys/vapid.json)
php bin/make-vapid.php

# 3. Exécuter les 5 tâches planifiées du cron (schedule:generate, alerts:scan, notifications:send, cleanup)
php cron/run.php

# 4. Lancer la suite complète de tests automatisés (sur la base isolée prime_care_test)
php tests/run.php

# 5. Démarrer le serveur de développement local
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public public/index.php
```

### 6.2 Comptes de test
| Rôle | Utilisateur | E-mail | Mot de passe | Code PIN (appareil de confiance) |
|---|---|---|---|---|
| **Responsable (`admin`)** | Karim Benali | `admin@primecare.test` | `Admin!2026` | `1111` |
| **Coordinatrice (`coordinator`)** | Sofia Marchetti | `coord@primecare.test` | `Coord!2026` | `2222` |
| **Aide-soignante (`caregiver`)** | Fatou Diallo | `fatou@primecare.test` | `Soin!2026` | `3333` |
| **Aide-soignante (`caregiver`)** | Marie Lefèvre | `marie@primecare.test` | `Soin!2026` | `4444` |
| **Médecin / Famille (`viewer`)** | Dr Laurent Petit | `medecin@primecare.test` | `Lecture!2026` | `5555` |

---

## 7. Règle des 3 clics (Parcours quotidiens Phase 3)

| # | Parcours quotidien | Clic / Geste 1 | Clic 2 | Clic 3 | Total |
|---|---|---|---|---|---|
| **1** | **Pointer son arrivée ou son départ (bouton glissant ou accessible)** | Depuis l’Accueil `/` : glisser **« Glisser pour arriver / partir »** (ou tap sur **« Confirmer mon arrivée »**) | — | — | **1 geste / clic** (≤ 3) |
| **2** | **Pointer par scan du QR code du domicile** | Depuis l’Accueil `/` : tap sur **« Scanner QR domicile »** | Présenter le QR devant la caméra (validation automatique dès détection `jsQR`) | — | **1 à 2 clics** (≤ 3) |
| **3** | **Déclarer un oubli de pointage** | Depuis `/` ou `/attendance` : tap sur **« J’ai oublié de pointer »** | Choisir l’heure réelle + saisir le motif obligatoire | Tap sur **« Envoyer au responsable »** | **3 clics** (≤ 3) |
| **4** | **Valider un pointage « À valider » (Responsable)** | Depuis `/` (point d’attention) ou `/attendance` : tap direct sur **« Valider le pointage »** | — | — | **1 à 2 clics** (≤ 3) |
| **5** | **Traiter une alerte ouverte dans le Centre d’alertes** | Tap sur la **cloche d’alertes** en haut de l’écran (`/alerts`) | Tap sur un motif rapide (ex. **« Vérifié »**) ou saisie libre | Tap sur **« Marquer comme traitée »** | **2 à 3 clics** (≤ 3) |
| **6** | **Ajouter un créneau de garde depuis un modèle** | Sur `/planning` : tap sur **« + Ajouter un créneau »** | Sélectionner le modèle (*Garde de jour 08h–20h*) et l’aide-soignante | Tap sur **« Enregistrer le créneau »** | **3 clics** (≤ 3) |
| **7** | **Exporter le récapitulatif mensuel des heures en CSV paie** | Sur `/attendance` : tap sur **« Heures du mois & CSV »** | Tap sur **« Exporter CSV paie (; UTF-8) »** | — | **2 clics** (≤ 3) |

---

## 8. Résultats qualité

### 8.1 Sortie complète de `php tests/run.php` (181 assertions réussies / 0 échec)
```text
=====================================================
 Prime Care — Suite de tests automatisés (Phases 1, 2 & 3)
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

▸ Phase 3 — Planning, Pointage, Heures (DST/Fériés), Alertes & Web Push VAPID
  ✓ PlanningService: création d’un créneau de garde réussit
  ✓ PlanningService: chevauchement pour la même aide-soignante est refusé (422)
  ✓ PlanningService: créneau de nuit pour une autre aide-soignante accepté
  ✓ PlanningService: filtrage RBAC — l’aide-soignante ne voit que ses propres créneaux, l’admin voit toute l’équipe
  ✓ PlanningService: duplication d’une semaine sur N=2 semaines crée les créneaux sur les semaines suivantes
  ✓ PlanningService: déclarer une absence marque automatiquement les créneaux concernés « À remplacer »
  ✓ AttendanceService: calcul Haversine précis (0 m au domicile, ~320 m hors rayon)
  ✓ AttendanceService: pointage GPS dans le rayon de 150 m est validé automatiquement (valid)
  ✓ AttendanceService: pointage GPS hors rayon (320 m) n’est jamais bloqué mais passe « À valider » (« Hors du domicile »)
  ✓ AttendanceService: pointage avec position GPS refusée passe « À valider » (« Position refusée »)
  ✓ AttendanceService: QR code signé HMAC valide accepté, falsifié rejeté, et l’ancien QR est révoqué après régénération
  ✓ AttendanceService: déclaration d’oubli de pointage exige un motif (422), crée un pointage pending et peut être validée par le responsable
  ✓ AttendanceService: option require_clocked_in bloque en 403 « Pointez votre arrivée pour saisir » une aide-soignante non pointée en arrivée
  ✓ HoursCalculatorService: calcule exactement les 11 jours fériés français 2026 (fixes + Pâques, Ascension, Pentecôte)
  ✓ HoursCalculatorService: calcule la liste complète des 11 jours fériés 2027 (incluant Pâques 29/03/2027, Ascension 06/05/2027, Pentecôte 17/05/2027)
  ✓ HoursCalculatorService: créneau de jour (08h–20h) = 12h00 totales et 0h00 de nuit
  ✓ HoursCalculatorService: garde de nuit (20h–08h traversant minuit) = 12h00 totales dont 9h00 de nuit (21h–6h)
  ✓ HoursCalculatorService: découpe exactement les heures de dimanche (8h après minuit) et de jour férié (11 novembre = 12h)
  ✓ HoursCalculatorService (DST été mars 2026): garde de nuit 20h00–08h00 locales lors du passage à l’heure d’été = 11h00 réelles (dont 8h00 de nuit)
  ✓ HoursCalculatorService (DST hiver oct. 2026): garde de nuit 20h00–08h00 locales lors du passage à l’heure d’hiver = 13h00 réelles (dont 10h00 de nuit)
  ✓ HoursCalculatorService: export CSV paie inclut le BOM UTF-8 et le séparateur point-virgule (;)
  ✓ AlertService: dédoublonnage strict par dedupe_key (aucun doublon tant que l’alerte est ouverte)
  ✓ AlertService: fermeture automatique marque l’alerte comme handled dès que l’action attendue est réalisée
  ✓ AlertService: une alerte critique non acquittée après 15 min est automatiquement escaladée au responsable
  ✓ AlertService: détection exacte des heures calmes (22h00–07h00 Europe/Paris) avec contournement pour les alertes critiques
  ✓ WebPushService: signe/vérifie le JWT ES256 (r||s 64 octets) et chiffre/déchiffre de bout en bout en aes128gcm (RFC 8291)
  ✓ WebPushService: conformité octet par octet au vecteur officiel RFC 8291 Appendix A (CEK, Nonce, Ciphertext et Corps complet aes128gcm)
  ✓ WebPushService: supprime automatiquement de la base un abonnement expiré renvoyant HTTP 410 Gone
  ✓ AlertService (notification_queue): recalcule next_try_at avec délais croissants (2, 4, 8, 16 min) et abandonne en status=failed après 5 échecs avec last_error renseigné
  ✓ Offline G1: client_recorded_at dans le passé (-20 min) est conservé comme heure de réalisation (measured_at)
  ✓ Offline G1: client_recorded_at dans le futur (> 2 min) est ramené à l’heure serveur
  ✓ Offline G1: client_recorded_at > 72 h est refusé avec HTTP 422 et le code OFFLINE_TOO_OLD
  ✓ Offline G1: conflit sur une prise déjà validée par une collègue renvoie HTTP 409 non bloquant (« Déjà donné par ... »)
  ✓ Attendance G2: refuse tout pointage avec HTTP 403 GDPR_NOTICE_REQUIRED tant que attendance_gdpr_accepted_at est NULL, puis accepte après validation
  ✓ Service Worker G3: public/sw.js restreint le cache de navigation à la liste blanche exacte ['/', '/today', '/journal'] et exclut Content-Disposition, /export et /media/
  ✓ CronService: exécute les tâches planifiées (schedule:generate, alerts:scan, notifications:send, cleanup)

▸ HTTP Routes & API Smoke Test (D1 + Phase 2 + Phase 3)
  ✓ D1 Smoke (sans session): 0 réponse 500 sur toutes les routes GET
  ✓ D1 Smoke (sans session): toutes les routes protégées redirigent en 302 vers /login
  ✓ D1 Smoke (sans session + appareil de confiance): GET / redirige en 302 vers /login/pin
  ✓ D1 Smoke (caregiver): 0 réponse 500 sur toutes les routes GET (Phases 1, 2 & 3)
  ✓ D1 Smoke (caregiver): 200 sur toutes les routes autorisées (/, /today, /planning, /attendance, /alerts...)
  ✓ D1 Smoke (caregiver): 403 sur /users, /admin/treatments, /admin/measures, /settings, /attendance/export-csv et /design
  ✓ E2 API JSON: POST /api/measures sans en-tête CSRF retourne 419 au format JSON {ok: false, error: {code, message}}
  ✓ API JSON (caregiver): POST /api/measures retourne 201 Created
  ✓ Phase 3 Mode hors ligne: rejouer le même client_uuid sur POST /api/measures retourne 200 et ne crée qu’un seul enregistrement
  ✓ API JSON (caregiver): POST /api/notes retourne 201 Created puis 200 sur rejeu idempotent client_uuid
  ✓ API JSON (caregiver): POST /api/care-checks retourne 201 Created puis 200 sur rejeu idempotent client_uuid
  ✓ Phase 3 Pointage: POST /api/measures retourne HTTP 403 « Pointez votre arrivée pour saisir » quand require_clocked_in est actif et l’aide-soignante est hors poste
  ✓ D2 Design B + E05 (accueil & tabbar): en-tête pleine largeur #0A1633, bouton glissant de pointage, Bonjour Fatou et onglet actif aria-current="page"
  ✓ API JSON (viewer): POST /api/measures est bloqué en 403
  ✓ D1 Smoke (viewer): 0 réponse 500 sur toutes les routes GET
  ✓ D1 Smoke (viewer): 200 sur toutes les routes de consultation (dont /planning et /alerts)
  ✓ D1 Smoke (viewer): 403 sur les routes d’administration et de pointage
  ✓ D1 Smoke (admin): 0 réponse 500 sur toutes les routes GET
  ✓ D1 Smoke (admin): 200 partout sur les 34 routes authentifiées (dont /planning, /attendance, /attendance/hours, /alerts, /settings/attendance/qr-print)
  ✓ C5 CSP stricte: 0 attribut style= en ligne dans le HTML généré sur l’ensemble des vues Phases 1, 2 & 3

-----------------------------------------------------
 Résultat : 181 réussis, 0 échoués
-----------------------------------------------------
```

---

## 9. Captures

Toutes les captures sont au format mobile `390×844` (`deviceScaleFactor: 2`) dans `docs/retours/captures/phase-3/` :

| Fichier | Écran / État capturé (`390×844`) |
|---|---|
| [`01-home-caregiver-punch-light.png`](captures/phase-3/01-home-caregiver-punch-light.png) | Accueil Aide-soignante (Fatou) avec bouton glissant **G5** (`linear-gradient(45deg,#5E5CE6,#BF5AF2)`, texte blanc « Glisser pour arriver », poignée ronde blanche à icône indigo) — Mode Clair |
| [`02-home-caregiver-punch-dark.png`](captures/phase-3/02-home-caregiver-punch-dark.png) | Accueil Aide-soignante (Fatou) avec bouton glissant **G5** — Mode Sombre |
| [`03-home-admin-attention-light.png`](captures/phase-3/03-home-admin-attention-light.png) | Accueil Responsable (Karim) avec synthèse unifiée (alertes ouvertes + gardes à remplacer + pointages à valider) — Mode Clair |
| [`04-home-admin-attention-dark.png`](captures/phase-3/04-home-admin-attention-dark.png) | Accueil Responsable (Karim) avec synthèse unifiée — Mode Sombre |
| [`05-planning-week-admin-light.png`](captures/phase-3/05-planning-week-admin-light.png) | Planning d’équipe `/planning` (vue Semaine 7 jours, filtres équipe, actions rapides) — Mode Clair |
| [`06-planning-week-admin-dark.png`](captures/phase-3/06-planning-week-admin-dark.png) | Planning d’équipe `/planning` (vue Semaine 7 jours) — Mode Sombre |
| [`07-planning-month-admin-light.png`](captures/phase-3/07-planning-month-admin-light.png) | Planning d’équipe `/planning?view=month` (grille mensuelle synthétique) — Mode Clair |
| [`08-planning-month-admin-dark.png`](captures/phase-3/08-planning-month-admin-dark.png) | Planning d’équipe `/planning?view=month` (grille mensuelle synthétique) — Mode Sombre |
| [`09-planning-caregiver-filtered-light.png`](captures/phase-3/09-planning-caregiver-filtered-light.png) | Planning filtré Aide-soignante `/planning` (Fatou voit uniquement ses propres gardes) — Mode Clair |
| [`10-planning-sheet-create-shift.png`](captures/phase-3/10-planning-sheet-create-shift.png) | Bottom-sheet « Ajouter un créneau » (`#shift-create-sheet`) avec sélection de modèle rapide |
| [`11-planning-sheet-declare-absence.png`](captures/phase-3/11-planning-sheet-declare-absence.png) | Bottom-sheet « Déclarer une absence » (`#absence-create-sheet`) |
| [`12-attendance-control-admin-light.png`](captures/phase-3/12-attendance-control-admin-light.png) | Contrôle hebdomadaire des pointages `/attendance` (pointages « À valider » Hors du domicile / Oubli déclaré + grille) — Mode Clair |
| [`13-attendance-control-admin-dark.png`](captures/phase-3/13-attendance-control-admin-dark.png) | Contrôle hebdomadaire des pointages `/attendance` — Mode Sombre |
| [`14-attendance-hours-monthly-light.png`](captures/phase-3/14-attendance-hours-monthly-light.png) | Récapitulatif mensuel des heures **G4** `/attendance/hours` (`< 600 px` : carte Total du mois en haut + carte par aide-soignante avec libellés à gauche / valeurs à droite) — Mode Clair |
| [`15-attendance-hours-monthly-dark.png`](captures/phase-3/15-attendance-hours-monthly-dark.png) | Récapitulatif mensuel des heures **G4** `/attendance/hours` (`< 600 px` : carte Total du mois + cartes par aide-soignante) — Mode Sombre |
| [`16-attendance-caregiver-declared-sheet.png`](captures/phase-3/16-attendance-caregiver-declared-sheet.png) | Bottom-sheet « Déclarer un oubli de pointage » (`#declare-punch-sheet`) côté Aide-soignante |
| [`17-alerts-active-light.png`](captures/phase-3/17-alerts-active-light.png) | Centre d’alertes `/alerts` (onglet Ouvertes avec badge d’escalade 15 min et traitement rapide) — Mode Clair |
| [`18-alerts-active-dark.png`](captures/phase-3/18-alerts-active-dark.png) | Centre d’alertes `/alerts` (onglet Ouvertes) — Mode Sombre |
| [`19-alerts-rules-light.png`](captures/phase-3/19-alerts-rules-light.png) | Centre d’alertes `/alerts?tab=rules` (catalogue des 7 règles d’alerte, délais et clôture automatique) — Mode Clair |
| [`20-alerts-rules-dark.png`](captures/phase-3/20-alerts-rules-dark.png) | Centre d’alertes `/alerts?tab=rules` (catalogue des 7 règles d’alerte) — Mode Sombre |
| [`21-alerts-history-light.png`](captures/phase-3/21-alerts-history-light.png) | Centre d’alertes `/alerts?tab=history` (historique des alertes traitées avec motif et auteur) — Mode Clair |
| [`22-alerts-history-dark.png`](captures/phase-3/22-alerts-history-dark.png) | Centre d’alertes `/alerts?tab=history` (historique des alertes traitées) — Mode Sombre |
| [`23-settings-attendance-qr-light.png`](captures/phase-3/23-settings-attendance-qr-light.png) | Réglages Pointage & QR `/settings?tab=attendance` (mode GPS/QR, rayon 150 m, option saisie si pointée, QR HMAC) — Mode Clair |
| [`24-settings-attendance-qr-dark.png`](captures/phase-3/24-settings-attendance-qr-dark.png) | Réglages Pointage & QR `/settings?tab=attendance` — Mode Sombre |
| [`25-settings-qr-print-a4.png`](captures/phase-3/25-settings-qr-print-a4.png) | Affiche A4 imprimable `/settings/attendance/qr-print` du QR code de présence du domicile |
| [`26-profile-notifications-push-light.png`](captures/phase-3/26-profile-notifications-push-light.png) | Profil `/profile` avec carte « Notifications & Heures calmes » et test Web Push — Mode Clair |
| [`27-profile-notifications-push-dark.png`](captures/phase-3/27-profile-notifications-push-dark.png) | Profil `/profile` avec carte « Notifications & Heures calmes » — Mode Sombre |
| [`28-offline-banner-queue-light.png`](captures/phase-3/28-offline-banner-queue-light.png) | Mode hors ligne actif avec bandeau ambre « Hors ligne · 2 saisies en attente » et barre de synchronisation — Mode Clair |
| [`29-home-punch-completed-state.png`](captures/phase-3/29-home-punch-completed-state.png) | **G7 #1** — État complété après pointage : rail vert « Arrivée pointée à 07h58 » avec coche animée et badge « En poste » |
| [`30-attendance-qr-scan-sheet.png`](captures/phase-3/30-attendance-qr-scan-sheet.png) | **G7 #2** — Feuille de scan du QR code du domicile ouverte (`#qr-punch-sheet`) avec caméra et code de secours |
| [`31-attendance-gdpr-notice.png`](captures/phase-3/31-attendance-gdpr-notice.png) | **G7 #3** — Mention d’information RGPD sur le pointage (`#gdpr-notice-sheet` & bouton « J’ai compris ») |
| [`32-chromium-push-notification-received.png`](captures/phase-3/32-chromium-push-notification-received.png) | **G7 #4** — Notification push système reçue dans Chromium après clic sur « Envoyer une notification de test » |
| [`33-offline-synced-toast.png`](captures/phase-3/33-offline-synced-toast.png) | **G7 #5** — Message toast « Saisies synchronisées (2) » affiché automatiquement après retour du réseau |
| [`34-offline-banner-queue-dark.png`](captures/phase-3/34-offline-banner-queue-dark.png) | **G7 #6** — Bandeau hors ligne et barre de file d’attente en Mode Sombre |
| [`35-alert-email-preview.png`](captures/phase-3/35-alert-email-preview.png) | **G7 #7** — Rendu visuel du courriel HTML d’alerte critique (`MailService::sendAlertEmail`) dans le navigateur |

---

## 10. Décisions et écarts

1. **Implémentation Web Push VAPID 100 % native en PHP 8.2+ (`app/Services/WebPushService.php`)** :
   - Conformément à l’interdiction d’utiliser Composer, le chiffrement **`aes128gcm` (RFC 8291 / RFC 8188)** et la signature **JWT VAPID `ES256` (RFC 8292)** ont été intégralement codés avec les primitives natives `openssl_pkey_new`, `openssl_pkey_derive` (ECDH P-256), `hash_hmac` (HKDF-SHA-256), `openssl_encrypt('aes-128-gcm')` et conversion ASN.1 DER ↔ signature brute `r || s` de 64 octets. Un déchiffreur symétrique `WebPushService::decryptPayload()` est inclus et testé dans `Phase3Test` pour prouver mathématiquement la conformité RFC 8291 de bout en bout.
2. **Garantie d’unicité des alertes ouvertes en MySQL 5.7+ et MySQL 8.0+ (`open_dedupe_key`)** :
   - La table `alerts` comporte une colonne `open_dedupe_key` renseignée avec `dedupe_key` tant que l’alerte est ouverte (`status IN ('new', 'seen')`) et mise à `NULL` dès qu’elle passe à `handled` (avec `UNIQUE KEY uq_alerts_open_dedupe (patient_id, open_dedupe_key)`). Comme `NULL` n’entre pas en conflit dans un index `UNIQUE` SQL standard, plusieurs occurrences historiques résolues peuvent partager la même `dedupe_key` tout en garantissant au niveau du moteur InnoDB qu’une seule alerte ouverte puisse exister à un instant donné.
3. **Slider tactile de pointage sans aucun attribut `style=` inline (respect de la CSP stricte C5)** :
   - Pour respecter `style-src 'self'` sans `unsafe-inline`, le bouton glissant de pointage n’utilise aucun `element.style.transform = ...`. Il s’appuie sur un `<input type="range" min="0" max="100">` superposé et synchronisé avec des classes de paliers CSS (`.pc-slide-punch--p0` à `.pc-slide-punch--p100` dans `public/assets/css/app.css`), offrant un glissement fluide au doigt comme au clavier sans aucune violation CSP.

---

## 11. Limites connues

1. **Test des notifications Web Push sur téléphone physique** : **notifications push sur vrai téléphone non testées, à faire par Hamdi** (la réception réelle d’une notification Web Push depuis les serveurs Apple APNs / Google FCM nécessite un iPhone sous iOS 16.4+ avec la PWA installée sur l’écran d’accueil ou un smartphone Android connecté en HTTPS via Cloudflare Tunnel / `mkcert`).
2. **Photos de repas en mode hors ligne** : conformément au cahier des charges (§4.1), les saisies de repas hors ligne sont mises en file d’attente IndexedDB sans les fichiers photos binaires (une bannière informe l’aide-soignante que les photos pourront être ajoutées après reconnexion).
3. **Modules prévus en Phase 4** : le coffre-fort documentaire complet (`/documents`), l’agenda des rendez-vous médicaux (`/appointments`) et le générateur de rapports PDF mensuels restent sur leurs écrans d’attente « Bientôt disponible (Phase 4) ».

---

## 12. Corrections

Suite à la revue de la Phase 3, les **7 corrections ciblées (`G1` à `G7`)** ont été réalisées sur la branche `arena/0f0ecf73-prime-care`, avec un commit atomique par correction :

| Réf. | Commit | Description de la correction |
|---|---|---|
| **G1** | `4bd09ac` | **Mode hors ligne complet & `client_recorded_at` conservé (§5.1, §5.3, §8.4)** : branchement de `enqueueOfflineAction` sur les 6 formulaires de saisie médicale (`#form-quick-measure`, `#form-insulin-give`, `#form-intake-skip`, `#form-as-needed`, `#form-quick-meal` sans photo, `#form-quick-note`) et les boutons 1-tap (`[data-quick-give-id]`, `[data-quick-care-id]`), avec repli automatique en cas de `navigator.onLine === false` ou d’erreur réseau (`TypeError`), fermeture de la feuille et toast « Enregistré hors ligne » ; désactivation de l’envoi de repas avec photo hors ligne (« Photo : réseau nécessaire ») ; envoi de `client_recorded_at` en ISO 8601 UTC (`new Date().toISOString()`) et validation dans `ApiMedicalController::resolveClientTimestamp` (conservation comme `measured_at`, `given_at`, `eaten_at`, `created_at` et `checked_at`/`done_at`, plafonnement à l’heure serveur si futur `> 2 min`, refus `422 OFFLINE_TOO_OLD` si passé `> 72 h`). |
| **G2** | `57c7d0a` | **Mention d’information RGPD imposée côté serveur (§3.4, §8.3)** : vérification de `users.attendance_gdpr_accepted_at !== null` dans `AttendanceService::recordPunch`, `AttendanceService::declareForgotPunch` et `AttendanceController::punch` / `declareForgot` (bouton glissant, QR code et déclaration manuelle), retournant `HTTP 403` avec le code `GDPR_NOTICE_REQUIRED` si `NULL` ; côté interface (`phase3.js`), ouverture automatique de la feuille `#gdpr-notice-sheet` et enchaînement automatique du pointage en attente dès le clic sur « J’ai compris » (`POST /api/attendance/gdpr-accept`). |
| **G3** | `6bcd510` | **Service Worker : périmètre de cache restreint à la liste blanche (§5.2, §8.4)** : restriction stricte de la mise en cache des navigations HTML dans `public/sw.js` à la liste blanche exacte `const OFFLINE_NAV_WHITELIST = ['/', '/today', '/journal']` ; exclusion totale des réponses ayant un en-tête `Content-Disposition` ou `Cache-Control: no-store` ainsi que des URLs contenant `/api/`, `/export`, `/csv`, `/pdf`, `/media/` ; ajout de `Cache-Control: no-store` côté PHP sur les exports CSV (`AttendanceController::exportCsv`) et sur `/media/*` (`Response::download`). |
| **G4** | `08acad5` | **Récapitulatif des heures en cartes sous `600 px` (§3.6, §6)** : dans `app/Views/attendance/hours.php` et `public/assets/css/app.css`, ajout d’une carte récapitulative du total du mois en haut (`.pc-hours-total-card`), affichage d’une carte par aide-soignante (`.pc-hours-card` avec libellés à gauche et valeurs à droite : planifiées, pointées, nuit, dimanche, fériés, écart) en dessous de `600 px`, et affichage du tableau à partir de `600 px` sans aucun débordement horizontal de `320 px` à `1280 px`. |
| **G5** | `0072d69` | **Bouton glissant de pointage conforme à l’Addendum B (§6, §8.2)** : rail `.pc-slide-punch` / `.pc-slider-punch` en dégradé signature `linear-gradient(45deg, #5E5CE6, #BF5AF2)` avec texte blanc centré (« Glisser pour arriver » / « Glisser pour partir »), poignée ronde blanche avec icône indigo `#5E5CE6`, coche animée et passage en état vert « Arrivée pointée à 07h58 » après validation. |
| **G6** | `9e50edf` | **Tests unitaires complets sur §7 (`tests/Unit/Phase3Test.php`)** : ajout des tests pour le vecteur officiel **RFC 8291 Appendix A** octet par octet (via paramètres optionnels de test dans `WebPushService::encryptPayload`), la suppression automatique d’un abonnement expiré (`410 Gone`), les délais croissants (`next_try_at`) et l’abandon après 5 échecs avec `last_error` dans `notification_queue`, la liste complète des 11 jours fériés 2027 (`29/03/2027`, `06/05/2027`, `17/05/2027`), les garde-fous hors ligne `client_recorded_at` (`-20 min` conservé, futur `> 2 min` ramené à l’heure serveur, `> 72 h` refusé `422 OFFLINE_TOO_OLD`, conflit `409` non bloquant), ainsi que les tests automatisés pour **G2** (`403 GDPR_NOTICE_REQUIRED`) et **G3** (vérification statique de `public/sw.js`). |
| **G7** | *(commit final)* | **Captures `390×844` complémentaires et mise à jour de `RETOUR_PHASE_3.md`** : reprise des captures du bouton glissant après G5 (`01`, `02`) et du récapitulatif des heures après G4 (`14`, `15`), ajout des 7 nouvelles captures `29` à `35` (état pointé « Arrivée pointée à 07h58 », feuille scan QR, notice RGPD pointage, notification push système reçue dans Chromium, toast « Saisies synchronisées », bandeau hors ligne sombre, rendu visuel du courriel HTML d’alerte) et mise à jour complète du présent rapport. |
