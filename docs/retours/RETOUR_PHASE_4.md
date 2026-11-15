# Rapport de fin de phase — Phase 4 (Version 1.0.0)

- **Phase** : Phase 4 — Pilotage, documents médicaux, rapports imprimables A4, exports CSV, journal d’audit, conformité RGPD, durcissement sécurité, accessibilité WCAG AA et mise en production (`v1.0.0`)
- **Date** : 2026-11-15
- **Branche** : `arena/f8be1f65-prime-care`
- **Statut** : Prêt pour revue (`v1.0.0`)

---

## 1. Résumé

Cette livraison finalise la **Phase 4** et la **version 1.0.0** de **Prime Care** sur le socle des Phases 1, 2 & 3, dans le respect strict de la direction artistique **Proposition B — « Bleu nuit »** (`docs/ADDENDUM_DESIGN_B.md`). Il ne reste plus **aucun écran « Bientôt disponible »** dans l’application. Elle couvre :
1. **Le coffre-fort de documents médicaux (`/documents` — E22, §1)** : catégories (*Ordonnance*, *Compte rendu*, *Analyse biologique*, *Courrier médical*, *Imagerie*, *Autre*), liaison automatique avec les ordonnances jointes aux prescriptions de la Phase 2, dépôt sécurisé réservé au responsable (`admin`) jusqu’à `10 Mo` avec contrôle MIME `finfo`, vérification de la signature binaire `%PDF-` pour les PDF, ré-encodage GD sans métadonnées EXIF pour les images JPEG/PNG, stockage hors `public/` sous nom aléatoire (`storage/uploads/documents/`), empreinte `SHA-256` avec avertissement non bloquant en cas de doublon, visionneuse intégrée (PDF ou image plein écran) et téléchargement servis par contrôleur authentifié avec `Cache-Control: no-store` et `X-Content-Type-Options: nosniff`.
2. **Les rapports médicaux imprimables A4 (`/reports` — E23, §2.1), le rapport RH corrigé (`/attendance/hours` — §2.2) et les « Tendances 7 jours » sur l’accueil responsable (`/`, §2.3)** : synthèse médicale sur 7, 14, 30, 90 jours ou période libre (équilibre glycémique, moyenne, min/max, % dans la cible, décompte des hypoglycémies `< 0,70 g/L` et hyperglycémies `> 2,50 g/L`, courbe SVG nette avec masquage automatique des libellés d’axe à moins de `12 px`, constantes vitales avec sparklines SVG, observance globale et par médicament, alimentation, événements notables, feuille `@media print` A4 portrait paginée avec pied de page *« Généré avec Prime Care — Prime Vision Solutions · Document de suivi, ne remplace pas un avis médical »*), séparation stricte entre créneaux terminés et **« Planifié restant ce mois-ci »** dans le rapport RH, et 4 cartes « Tendances 7 jours » avec compteurs animés (`prefers-reduced-motion` respecté).
3. **Les exports CSV (`/reports/exports` & `/reports/export/{type}` — §3) et le journal d’audit avec différences avant/après (`/audit` — E26, §4)** : 9 types d’exports CSV (5 médicaux accessibles à `admin`, `coordinator`, `viewer` ; 4 RH/techniques réservés à `admin`/`coordinator`), formatés avec BOM UTF-8 (`\xEF\xBB\xBF`), séparateur `;`, dates en heure de Paris (`JJ/MM/AAAA HH:MM`), virgule décimale, `Cache-Control: no-store`, `Content-Disposition: attachment` et traçabilité systématique dans `audit_logs` (`action = 'export'`), ainsi que le journal d’audit filtrable affichant les différences **avant / après** champ par champ en français (`<del>` → `<ins>`).
4. **Les réglages regroupés style iOS (`/settings` — E24, §5.1), la conservation RGPD (`retention:purge` — §5.2), l’export RGPD et les pages légales (§5.3)** : navigation groupée en 9 sections dans `/settings`, tâche cron `retention:purge` (suppression logique puis physique après 30 jours des pointages, alertes traitées et journaux expirés, sans **jamais** supprimer automatiquement les données médicales), export RGPD personnel (`POST /profile/export-data` isolé sur `Auth::id()`) et export intégral patient (`POST /settings/export-patient-data`), pages `/legal/mentions`, `/legal/privacy`, `/legal/attendance-notice` et modèles `docs/rgpd/`.
5. **La préparation à la production HDS, le durcissement sécurité OWASP ASVS 4.0 L1, l’audit d’accessibilité `axe-core` et la documentation 1.0 (§6)** : `Config` forçant `app.debug = false` dès que `app.env = production` (H2), route `/healthz` sans données personnelles, bandeau rouge *« Les alertes automatiques sont à l’arrêt »* si le cron a plus de 15 minutes, assistant CLI `bin/setup.php`, scripts `bin/backup.sh` et `bin/restore.sh` (supportant `age` avec repli OpenSSL AES-256-CBC PBKDF2 et copie distante `BACKUP_REMOTE` via `rsync`/`sftp`), limitation de débit `120 req/min` sur les API d’écriture (`HTTP 429`), **0 violation `axe-core` sur les 39 écrans/états en clair et sombre**, scores **Lighthouse mobile ≥ 94 Performance / 100 Accessibilité / ≥ 96 Bonnes pratiques**, et guides complets (`DEPLOIEMENT_HDS.md`, `EXPLOITATION.md`, `SECURITE.md`, `ACCESSIBILITE.md`, `GUIDE_AIDE_SOIGNANTE.md`, `GUIDE_RESPONSABLE.md`, `CHANGELOG.md`).

---

## 2. Branche et commits

- **Branche** : `arena/f8be1f65-prime-care` (créée depuis `main` `98e4c942d293fe15df48be94ea3a6e414cc854b2`)
- **Commits de la Phase 4** :
  - `3d6e6f5` — `chore(phase4): add PLAN_PHASE_4.md execution plan for v1.0`
  - `5ed1d3a` — `feat(phase-4): pilotage, documents médicaux, rapports A4, exports CSV, audit, RGPD, sécurité & v1.0.0`
- **Commits des corrections post-revue (H1 à H9)** :
  - `925de8e` — **H1** : `fix(reports): H1 wire /reports/export/{type} to downloadExport and add E2E HTTP tests`
  - `5980e1e` — **H2** : `fix(config): H2 force app.debug to false when app.env is production`
  - `019a5c1` — **H3** : `test(security): H3 complete 12-resource HTTP IDOR suite, EXIF stripping & known-dataset report tests`
  - `3379c07` — **H4** : `perf(lighthouse): H4 fix heading order on /today and verify mobile Lighthouse >= 90/95/95`
  - `cc8046c` — **H5** : `fix(a11y): H5 audit all Phase 1-4 screens in light/dark with axe-core and update ACCESSIBILITE.md`
  - `2cda5be` — **H6** : `feat(ops): H6 add BACKUP_REMOTE off-site copy and age encryption support to backup/restore`
  - `6926e4c` — **H7** : `docs(security): H7 write complete OWASP ASVS 4.0 Level 1 compliance grid in docs/SECURITE.md`
  - `f4a80f5` — **H8** : `docs(guide): H8 rewrite caregiver guide by task with 9 screenshots, offline section and dynamic hypo reaction`
  - *(+ commit final **H9** ci-dessous : `docs(retour): H9 complete 12-section RETOUR_PHASE_4.md, A4 print pages, captures and non-overlapping report SVG labels`)*

---

## 3. Definition of Done (recopiée ligne par ligne et cochée)

- [x] **Il ne reste plus aucun écran « Bientôt disponible »** dans toute l’application (`/documents`, `/reports`, `/reports/exports`, `/audit`, `/settings`, `/legal/*` sont tous actifs).
- [x] **Module Documents médicaux (`/documents` — E22, §1)** :
  - [x] Dépôt réservé à `admin` (PDF, JPEG, PNG jusqu’à `10 Mo`), validation MIME réelle `finfo`, signature `%PDF-` vérifiée sur les PDF, suppression des métadonnées EXIF par ré-encodage GD sur les images.
  - [x] Stockage hors `public/` sous nom aléatoire (`storage/uploads/documents/`), calcul `SHA-256` et avertissement non bloquant si un fichier identique existe déjà.
  - [x] Synchronisation automatique des ordonnances jointes aux prescriptions de la Phase 2 dans la catégorie « Ordonnance ».
  - [x] Consultation et téléchargement autorisés pour les 4 rôles via contrôleur authentifié avec `Cache-Control: no-store` et `X-Content-Type-Options: nosniff`.
  - [x] Suppression logique réservée à `admin` et tracée dans `audit_logs`.
- [x] **Rapport médical imprimable A4 (`/reports` — E23, §2.1)** :
  - [x] Filtre de période (`7j`, `14j`, `30j`, `90j` ou dates libres `from` / `to`), accessible à `admin`, `coordinator` et `viewer`.
  - [x] 6 sections complètes : En-tête patient & cibles, Synthèse glycémique (moyenne, min, max, % dans la cible, hypoglycémies `< 0,70 g/L`, hyperglycémies `> 2,50 g/L`, moyenne par contexte, courbe SVG nette sans chevauchement d’étiquettes), Autres constantes avec sparklines SVG, Observance globale et par médicament + insuline & sites, Alimentation, Événements notables.
  - [x] Feuille d’impression `@media print` A4 portrait propre (sans barre de navigation ni boutons, `break-inside: avoid`, pied de page *« Généré avec Prime Care — Prime Vision Solutions · Document de suivi, ne remplace pas un avis médical »* et numérotation des pages).
- [x] **Rapport RH corrigé (`/attendance/hours` — §2.2)** :
  - [x] L’écart entre heures prévues et heures pointées (`diff_hours`) ne porte que sur les créneaux **déjà terminés** (`end_at <= nowUtc`).
  - [x] Les créneaux futurs du mois en cours sont affichés séparément sous **« Planifié restant ce mois-ci »**.
- [x] **Tendances 7 jours sur l’accueil responsable (`/`, §2.3)** :
  - [x] 4 cartes indicateurs (glycémie moyenne, % dans la cible, observance, hypoglycémies) comparant les 7 derniers jours aux 7 jours précédents, avec compteur animé désactivé si `prefers-reduced-motion: reduce`.
- [x] **Exports CSV (`/reports/exports` & `/reports/export/{type}` — §3)** :
  - [x] Un seul chemin `/reports/export/{type}` branché sur `ReportController::downloadExport`, liens générés via `route('reports.export_csv', ...)`.
  - [x] Encodage UTF-8 avec BOM (`\xEF\xBB\xBF`), séparateur `;`, dates en heure de Paris (`JJ/MM/AAAA HH:MM`), virgule décimale, en-têtes en français, `Cache-Control: no-store`, `Content-Disposition: attachment`.
  - [x] Filtrage RBAC strict : `viewer` accède uniquement aux 5 exports médicaux (`200`) et reçoit `403` sur les pointages, heures, alertes et journal d’audit.
  - [x] Chaque téléchargement CSV crée une entrée `action = 'export'` dans `audit_logs`.
- [x] **Journal d’audit (`/audit` — E26, §4)** :
  - [x] Accessible uniquement à `admin`, filtrable par utilisateur, module/table, action et période, paginé et exportable en CSV.
  - [x] Affichage lisible des **différences avant / après** champ par champ en français (`<del>` ancienne valeur → `<ins>` nouvelle valeur), avec masquage des champs sensibles et sans JSON brut.
  - [x] Connexions réussies, échecs de connexion et révocations d’appareils de confiance tracés dans `audit_logs`.
- [x] **Réglages regroupés, Conservation RGPD & Pages légales (§5)** :
  - [x] Page `/settings` organisée en liste groupée style iOS (9 sections dont Conservation des données).
  - [x] Tâche cron `retention:purge` fonctionnelle (suppression logique puis physique après 30 jours) qui **ne supprime jamais les données médicales**.
  - [x] Bouton « Exporter mes données » dans `/profile` (strictement limité à l’utilisateur connecté) et « Exporter toutes les données du patient » dans `/settings` (`admin`).
  - [x] Pages `/legal/mentions`, `/legal/privacy`, `/legal/attendance-notice` et modèles `docs/rgpd/MODELE_CONSENTEMENT_PATIENT.md` & `docs/rgpd/REGISTRE_DES_TRAITEMENTS.md` avec marqueurs `[À COMPLÉTER]`.
- [x] **Mise en production, Sauvegarde, Sécurité, Accessibilité & Performance (§6)** :
  - [x] En mode `production`, `Config` force `app.debug = false` même si `config.php` contient `'debug' => true` (aucune trace ni message technique en HTML ou JSON), `/design` renvoie `404`, `bin/seed.php` refuse de s’exécuter, HSTS et cookies `__Host-` actifs.
  - [x] Route `GET /healthz` retournant `{"ok":true,"db":true,"cron_last_run":"..."}` sans donnée personnelle, et bandeau rouge « Les alertes automatiques sont à l’arrêt » si le cron a plus de 15 minutes.
  - [x] Assistant d’installation `bin/setup.php` et scripts `bin/backup.sh` / `bin/restore.sh` (chiffrement `age` ou OpenSSL, copie distante `BACKUP_REMOTE` via `rsync`/`sftp`, restauration testée de bout en bout).
  - [x] Grille complète de conformité **OWASP ASVS 4.0 Niveau 1** dans `docs/SECURITE.md` (`Écarts restants : aucun`) et suite complète de tests HTTP IDOR sur les 12 ressources adressées par identifiant.
  - [x] Audit `axe-core` à **0 violation** sur les 39 écrans/états (Phases 1 à 4, clair et sombre) + vérifications manuelles documentées dans `docs/ACCESSIBILITE.md`.
  - [x] Scores **Lighthouse mobile** mesurés : Performance `≥ 94`, Accessibilité `100`, Bonnes pratiques `≥ 96` sur `/login`, `/` (aide-soignante), `/today` et `/health`.
  - [x] Documentation complète livrée : `README.md`, `docs/DEPLOIEMENT_HDS.md`, `docs/EXPLOITATION.md`, `docs/SECURITE.md`, `docs/ACCESSIBILITE.md`, `docs/GUIDE_AIDE_SOIGNANTE.md` (1 section par tâche + 9 captures `390×844` + mode hors ligne + réaction dynamique à l’hypoglycémie), `docs/GUIDE_RESPONSABLE.md`, `CHANGELOG.md`.
- [ ] **Tests sur vrais téléphones (installation, notifications) : à faire par Hamdi** sur iPhone (iOS 16.4+ Safari PWA) et smartphone Android (Chrome).

---

## 4. Fichiers créés ou modifiés

```text
prime-care/
├── CHANGELOG.md                                     # Notes de version v1.0.0 (Phases 1 à 4)
├── README.md                                        # Documentation principale v1.0.0
├── bin/
│   ├── backup.sh                                    # Sauvegarde chiffrée (age / OpenSSL), rotation 30j & copie distante BACKUP_REMOTE (H6)
│   ├── restore.sh                                   # Restauration complète et vérification post-restauration (H6)
│   ├── seed.php                                     # Blocage en production + exécution de Phase1..4Seeder
│   └── setup.php                                    # Assistant CLI d'installation initiale en production (sans données de démo)
├── database/
│   ├── migrations/
│   │   └── 011_documents.sql                        # Table documents + index composites de performance Phase 4
│   └── seeds/
│       └── Phase4Seeder.php                         # 10 documents médicaux PDF/PNG + 115 entrées audit_logs sur 30 jours
├── app/
│   ├── Core/
│   │   ├── Config.php                               # H2: enforceProductionSafety() forçant app.debug=false si app.env=production
│   │   └── ErrorHandler.php                         # Masquage total des exceptions en HTML et JSON hors mode debug
│   ├── Controllers/
│   │   ├── ApiMedicalController.php                 # H3: vérification Policy notes/create sur markNoteRead + limite 120 req/min
│   │   ├── AuditController.php                      # Journal d'audit (/audit) et export CSV audit
│   │   ├── DocumentController.php                   # Coffre-fort documentaire (/documents, /file, /download, /delete)
│   │   ├── HealthzController.php                    # Sonde /healthz sans données personnelles
│   │   ├── JournalController.php                    # H3: vérification Policy notes/create sur markRead
│   │   ├── LegalController.php                      # Pages légales (/legal/mentions, /privacy, /attendance-notice)
│   │   ├── MealController.php                       # H3: réponse JSON 403/422 sur update
│   │   ├── ProfileController.php                    # Export RGPD personnel (/profile/export-data) + audit révocation appareil
│   │   ├── ReportController.php                     # Rapports médicaux A4 (/reports), exports CSV (/reports/exports & /reports/export/{type})
│   │   └── SettingsController.php                   # Réglages iOS regroupés, conservation RGPD & export intégral patient
│   ├── Services/
│   │   ├── AuditService.php                         # Calcul de diff avant/après lisible en français (computeDiff)
│   │   ├── CronService.php                          # Tâche retention:purge + suivi cron_last_run (> 15 min = alerte admin)
│   │   ├── DocumentService.php                      # Validation PDF/JPEG/PNG, strip EXIF GD, SHA-256, synchro ordonnances
│   │   ├── ExportService.php                        # Générateur des 9 exports CSV (; UTF-8 BOM, dates Paris) + archives RGPD
│   │   ├── HoursCalculatorService.php               # Séparation créneaux terminés vs « Planifié restant ce mois-ci »
│   │   └── ReportService.php                        # Synthèse médicale, tendances 7j & courbe SVG sans chevauchement (< 12 px, H9)
│   └── Views/
│       ├── audit/index.php                          # Vue liste et détail diff avant/après (<del> -> <ins>)
│       ├── documents/{index,show}.php               # Liste filtrable, dépôt bottom-sheet et visionneuse intégrée
│       ├── legal/{mentions,privacy,attendance_notice}.php
│       ├── reports/{index,exports}.php              # Rapport médical A4 imprimable & page d'exports CSV (H1/H9)
│       └── settings/index.php                       # Réglages regroupés style iOS + carte Conservation RGPD
├── docs/
│   ├── ACCESSIBILITE.md                             # H5: Audit axe-core (39 écrans clair/sombre, 0 violation) + tests manuels WCAG AA
│   ├── DEPLOIEMENT_HDS.md                           # Guide de déploiement serveur HDS + sauvegarde distante (H6)
│   ├── EXPLOITATION.md                              # Runbook d'exploitation (healthz, cron, backup/restore age & BACKUP_REMOTE)
│   ├── GUIDE_AIDE_SOIGNANTE.md                      # H8: Guide par tâche (9 sections + 9 captures 390x844 + hors ligne + hypo dynamique)
│   ├── GUIDE_RESPONSABLE.md                         # Guide complet du responsable (Karim Benali)
│   ├── SECURITE.md                                  # H7: Grille complète OWASP ASVS 4.0 Niveau 1 (V2..V14, 0 écart restant)
│   ├── guides/captures/                             # H8: 9 captures 390x844 des tâches de l'aide-soignante
│   ├── rgpd/
│   │   ├── MODELE_CONSENTEMENT_PATIENT.md           # Modèle de recueil de consentement avec marqueurs [À COMPLÉTER]
│   │   └── REGISTRE_DES_TRAITEMENTS.md              # Registre RGPD art. 30 avec marqueurs [À COMPLÉTER]
│   └── retours/
│       ├── PLAN_PHASE_4.md                          # Plan d'exécution de la Phase 4
│       ├── RETOUR_PHASE_4.md                        # Présent rapport de fin de phase en 12 sections (H9)
│       └── captures/phase-4/                        # 28 captures 390x844 + aperçu d'impression PDF A4 pages 1 et 2
└── tests/
    ├── run.php                                      # Exécuteur des 14 suites (Phases 1, 2, 3 & 4 — 227 assertions)
    ├── Integration/RoutesSmokeTest.php              # H1: Smoke test routes paramétrées + test HTTP E2E des 9 exports CSV × 4 rôles
    └── Unit/Phase4Test.php                          # H2/H3: Tests unitaires & intégration Phase 4 + 12 tests IDOR HTTP avec invariance BDD
```

---

## 5. Base de données

### 5.1 Migration créée en Phase 4 (`011_documents.sql`)
- **Table `documents`** :
  - Colonnes : `id`, `patient_id`, `prescription_id` (nullable, `UNIQUE` par prescription pour synchroniser les ordonnances de Phase 2 sans doublon), `category` (`ENUM('ordonnance','compte_rendu','analyse_biologique','courrier','imagerie','autre')`), `title`, `document_date`, `notes`, `file_path`, `original_name`, `mime_type`, `file_size`, `sha256` (`CHAR(64)`), `uploaded_by`, `created_at`, `updated_at`, `deleted_at`.
  - Index composites : `idx_documents_patient_cat_date (patient_id, deleted_at, category, document_date)`, `idx_documents_patient_sha256 (patient_id, sha256, deleted_at)`.
- **Index composites de performance ajoutés aux tables existantes** :
  - `idx_measures_patient_type_measured`, `idx_intakes_patient_sched_status`, `idx_meals_patient_eaten`, `idx_notes_patient_created`, `idx_audit_patient_created` (vérifiés par `EXPLAIN` : lecture par index `ref`/`range`, aucune analyse complète `ALL` sur les grandes tables).

### 5.2 Données de démonstration (`Phase4Seeder`)
- **10 documents médicaux réalistes** (ordonnances synchronisées + analyses biologiques HbA1c, compte rendu de consultation diabétologique, courrier médecin traitant, bilan rénal, cliché d’imagerie) avec vrais fichiers PDF (`%PDF-1.4`) et PNG stockés dans `storage/uploads/documents/`.
- **Plus de 115 entrées dans `audit_logs`** réparties sur 30 jours couvrant connexions (`login`, `login_failed`), créations/modifications avec différences avant/après (`old_values` / `new_values`), validations de pointages, exports CSV, révocations d’appareils et purges RGPD.

---

## 6. Comment tester

### 6.1 Commandes d’installation et de vérification
```bash
# 1. Appliquer toutes les migrations (001 à 011) et peupler les seeds (Phases 1, 2, 3 & 4)
php bin/migrate.php --fresh --seed

# 2. Exécuter toutes les tâches planifiées du cron (incluant retention:purge)
php bin/cron.php all 2>/dev/null || php -r 'require "app/Helpers/bootstrap.php"; \App\Services\CronService::runTask("all", 1);'

# 3. Lancer la suite complète de tests automatisés (Phases 1, 2, 3 & 4 sur prime_care_test)
php tests/run.php

# 4. Vérifier la sonde de santé /healthz
curl -s http://127.0.0.1:8080/healthz
```

### 6.2 Comptes de test
| Rôle | Utilisateur | E-mail | Mot de passe | Code PIN |
|---|---|---|---|---|
| **Responsable (`admin`)** | Karim Benali | `admin@primecare.test` | `Admin!2026` | `1111` |
| **Coordinatrice (`coordinator`)** | Sofia Mansouri | `sofia@primecare.test` | `Coord!2026` | `2222` |
| **Aide-soignante (`caregiver`)** | Fatou Diallo | `fatou@primecare.test` | `Soin!2026` | `3333` |
| **Aide-soignante (`caregiver`)** | Marie Lefèvre | `marie@primecare.test` | `Soin!2026` | `4444` |
| **Médecin / Famille (`viewer`)** | Dr Laurent Petit | `medecin@primecare.test` | `Lecture!2026` | `5555` |

---

## 7. Règle des 3 clics (les 5 parcours quotidiens de la DoD + parcours Phase 4)

| # | Parcours de la Definition of Done | Touche / Clic 1 | Touche / Clic 2 | Touche / Clic 3 | Total |
|---|---|---|---|---|---|
| **1** | **Saisir une glycémie capillaire** | Depuis n’importe quel écran : tap sur le bouton central **`+`** de la barre du bas (ouvre la feuille de saisie pré-positionnée sur *Glycémie* et sur le contexte horaire) | Saisie de la valeur au clavier numérique (ex. `1,10`) | Tap sur **« Enregistrer la mesure »** | **3 touches** (≤ 3) |
| **2** | **Valider une prise de médicament ou d’insuline** | **Dose fixe** : depuis `/` ou `/today`, tap direct sur **« Donné »** (1 touche). **Insuline variable** : sur `/today`, tap sur **« Grille & Injecter »** | Vérification de la dose pré-calculée selon la grille du responsable et du site d’injection suggéré | Tap sur **« Confirmer l’injection »** | **1 à 3 touches** (≤ 3) |
| **3** | **Enregistrer un repas (avec photo ou favori)** | Tap sur le bouton central **`+`** > tuile **« Repas »** (type de repas pré-sélectionné selon l’heure) | Tap sur **« Ajouter une photo »** (ou sur une puce **Favori 1 tap**) | Tap sur **« Enregistrer le repas »** | **3 touches** (≤ 3) |
| **4** | **Pointer son arrivée ou son départ** | Depuis l’écran **Accueil** (`/`) : glisser **« Glisser pour arriver / partir »** (ou tap sur **« Confirmer mon arrivée »**) | — | — | **1 geste / touche** (≤ 3) |
| **5** | **Lire ou publier la transmission de relève** | **Lire** : sur `/`, tap direct sur **« J’ai lu »** (1 touche). **Publier** : sur `/journal`, tap sur **« Rédiger la relève »** | Saisie du message sous le récapitulatif automatique de garde | Tap sur **« Publier la transmission de relève »** | **1 à 3 touches** (≤ 3) |
| **6** | **Imprimer en A4 ou exporter en CSV le rapport médical 30 jours (Phase 4)** | Depuis `/` (carte *Tendances 7 jours*), tap sur **« Rapport 30j »** (`/reports?period=30d`) | Tap sur **« Imprimer / PDF A4 »** (ou **« Exports CSV »** > **« Exporter CSV »**) | — | **2 à 3 touches** (≤ 3) |
| **7** | **Consulter ou télécharger un document médical (Phase 4)** | Tap sur **Plus (`⋯`) > Documents médicaux** (`/documents`) | Tap sur le document souhaité (ouvre la visionneuse intégrée ou **« Télécharger »**) | — | **2 touches** (≤ 3) |

---

## 8. Résultats qualité

### 8.1 Scores Google Lighthouse Mobile (H4 & I1 — profil mobile officiel Moto G Power, throttling 4G, 3 passages par écran + médiane)

Suite à la correction **I1** :
1. **Regroupement et minification CSS en PHP pur (`bin/build-css.php` → `public/assets/css/app.min.css`)** : fusion de `tokens.css`, `base.css`, `components.css`, `utilities.css` et `app.css` en un unique fichier minifié `public/assets/css/app.min.css` (`111 648 octets → 79 314 octets`, soit **15 075 octets** compressé en `gzip`), éliminant les 5 aller-retours bloquants au démarrage. Cache long (`Cache-Control: public, max-age=31536000, immutable`) et compression `gzip` actifs aussi bien sous Apache/Nginx que sous le serveur de développement `php -S` (`public/index.php` et `Response::send()`).
2. **Chargement dynamique à la demande des librairies lourdes (`jsQR.min.js` et `apexcharts.min.js`)** : retrait de `jsQR.min.js` (`251 Ko`) et d’`apexcharts.min.js` du chargement initial (`app/Views/layouts/app.php`) ainsi que du pré-cache immédiat du Service Worker (`public/sw.js`). `jsQR.min.js` n’est injecté dynamiquement (`<script>`) qu’au clic sur **« Scanner le QR code »** (`public/assets/js/modules/phase3.js`) et `apexcharts.min.js` n’est injecté dynamiquement via `IntersectionObserver` que lorsqu’un conteneur `[data-pc-chart]` entre dans la fenêtre d’affichage (`public/assets/js/modules/charts.js`).
3. **Rendu immédiat de l’élément LCP (`#unread-handover-banner`) dès le premier HTML serveur** : suppression des animations d’entrée (`opacity: 0`) sur `.pc-card` / `.pc-auth-card`, passage des polices locales en `font-display: optional` et maintien de l’écran d’accueil de 2 secondes (`.pc-loader`) sans occultation opaque ni animation continue retardant la peinture du contenu réel situé dessous (`LCP = 0,9 s` sur `section#unread-handover-banner > p.pc-handover-banner__body` au lieu de `4,0–4,4 s`).

**Environnement serveur mesuré** :
- Binaire `nginx` / `php-fpm` non installé dans le conteneur sandbox (`which nginx php-fpm` vide) ; mesures réalisées derrière **`php -S` en deux configurations** :
  1. `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8089 -t public public/index.php` (routeur `public/index.php` servant les assets statiques et le HTML avec compression `gzip` et `Cache-Control: immutable`) ;
  2. `php -S 127.0.0.1:8089 -t public` (1 seul processus sans script routeur explicite).
- Situation réelle de début de poste sur `/` : session authentifiée en tant que **Fatou Diallo (`fatou@primecare.test`)**, avec **transmission de relève non lue (`#unread-handover-banner`, ID 7)** et **8 points d’attention** affichés en haut d’écran.

**Commande exacte exécutée (3 passages par écran + calcul de la médiane)** :
```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8089 -t public public/index.php >/tmp/php8089-full.log 2>&1 &
CAREGIVER_COOKIE="prime_care_session=<session_fatou>" OUT_FILE="/tmp/lh-3pass-final.json" \
  AWS_LAMBDA_JS_RUNTIME=nodejs22.x LD_LIBRARY_PATH=/tmp/al2023/lib:/tmp \
  node /tmp/pw-tools/run-lighthouse-3pass.mjs
```

#### A. Résultats sur 3 passages + Médiane (`PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8089 -t public public/index.php`)

| Écran audité (Profil Mobile) | Passage 1 (Perf / A11y / BP) | Passage 2 (Perf / A11y / BP) | Passage 3 (Perf / A11y / BP) | **MÉDIANE Performance (cible ≥ 90)** | **MÉDIANE Accessibilité (cible ≥ 95)** | **MÉDIANE Bonnes pratiques (cible ≥ 95)** | FCP médian | LCP médian | TBT médian | CLS | Speed Index |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Connexion (`/login`)** | `100 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 0 ms`) | `100 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 0 ms`) | `99 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 100 ms`) | **100 / 100** | **100 / 100** | **100 / 100** | `0,9 s` | `0,9 s` | `0 ms` | `0` | `1,8 s` |
| **Accueil aide-soignante (`/`, Fatou avec relève non lue & 8 points d’attention)** | `100 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 10 ms`) | `100 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 0 ms`) | `100 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 40 ms`) | **100 / 100** | **100 / 100** | **100 / 100** | `0,9 s` | `0,9 s` | `10 ms` | `0` | `2,6 s` |
| **Aujourd’hui (`/today`)** | `99 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 0 ms`) | `99 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 0 ms`) | `99 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 50 ms`) | **99 / 100** | **100 / 100** | **100 / 100** | `0,9 s` | `0,9 s` | `0 ms` | `0` | `3,1 s` |
| **Santé (`/health`)** | `99 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 0 ms`) | `99 / 100 / 100` (`FCP 1,0 s`, `LCP 1,0 s`, `TBT 10 ms`) | `99 / 100 / 100` (`FCP 0,9 s`, `LCP 0,9 s`, `TBT 40 ms`) | **99 / 100** | **100 / 100** | **100 / 100** | `0,9 s` | `0,9 s` | `10 ms` | `0` | `3,2 s` |

#### B. Résultats sur 3 passages + Médiane (`php -S 127.0.0.1:8089 -t public`, 1 seul processus sans routeur explicite)

| Écran audité (Profil Mobile) | Passage 1 (Perf / A11y / BP) | Passage 2 (Perf / A11y / BP) | Passage 3 (Perf / A11y / BP) | **MÉDIANE Performance (cible ≥ 90)** | **MÉDIANE Accessibilité (cible ≥ 95)** | **MÉDIANE Bonnes pratiques (cible ≥ 95)** | FCP médian | LCP médian | TBT médian | CLS | Speed Index |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Connexion (`/login`)** | `100 / 100 / 100` (`LCP 1,3 s`) | `100 / 100 / 100` (`LCP 1,2 s`) | `100 / 100 / 100` (`LCP 1,2 s`) | **100 / 100** | **100 / 100** | **100 / 100** | `1,2 s` | `1,2 s` | `0 ms` | `0` | `2,0 s` |
| **Accueil aide-soignante (`/`, Fatou avec relève non lue & 8 points d’attention)** | `100 / 100 / 100` (`LCP 1,2 s`) | `100 / 100 / 100` (`LCP 1,2 s`) | `100 / 100 / 100` (`LCP 1,2 s`) | **100 / 100** | **100 / 100** | **100 / 100** | `1,2 s` | `1,2 s` | `0 ms` | `0` | `2,7 s` |
| **Aujourd’hui (`/today`)** | `99 / 100 / 100` (`LCP 1,2 s`) | `99 / 100 / 100` (`LCP 1,2 s`) | `99 / 100 / 100` (`LCP 1,2 s`) | **99 / 100** | **100 / 100** | **100 / 100** | `1,2 s` | `1,2 s` | `0 ms` | `0` | `3,2 s` |
| **Santé (`/health`)** | `99 / 100 / 100` (`LCP 1,2 s`) | `99 / 100 / 100` (`LCP 1,2 s`) | `99 / 100 / 100` (`LCP 1,2 s`) | **99 / 100** | **100 / 100** | **100 / 100** | `1,2 s` | `1,2 s` | `0 ms` | `0` | `3,3 s` |

### 8.2 Audit d’accessibilité `axe-core` (H5 — 39 écrans/états en clair et sombre)
- **39 / 39 écrans et états** (Phases 1, 2, 3 & 4 en thèmes Clair et Sombre, incluant `/`, `/today`, feuille de saisie de mesure ouverte, `/health`, `/meals`, `/journal`, `/planning`, `/attendance`, `/alerts`, `/documents`, `/reports`, `/reports/exports`, `/audit`, `/settings`, `/legal/*`) audités avec `axe-core` (`wcag2a`, `wcag2aa`, `wcag21aa`, `best-practice`) : **0 violation** (`critical = 0`, `serious = 0`, `moderate = 0`, `minor = 0`). Détail complet dans `docs/ACCESSIBILITE.md`.

### 8.3 Sortie complète de `php tests/run.php` (227 assertions réussies / 0 échec)
```text
=====================================================
 Prime Care — Suite de tests automatisés (Phases 1, 2, 3 & 4)
 Environnement : testing (Base : prime_care_test)
=====================================================

  ✓ Tests run on isolated test database (prime_care_test)

▸ Router (6 assertions)
▸ Validator (6 assertions)
▸ Csrf (6 assertions)
▸ Auth & Trusted Devices PIN (C1) (21 assertions)
▸ RateLimiter Account vs Shared IP (C2) (7 assertions)
▸ Policy (RBAC) (12 assertions)
▸ Helpers & Escaping e() (9 assertions)
▸ ThresholdService (Seuils, Contextes, Plausibilité, g/L <-> mg/dL) (14 assertions)
▸ ScheduleService (DST Europe/Paris -> UTC & Idempotence 48h) (8 assertions)
▸ IntakeService & InsulinService (Statuts, Anti-double-prise 409, Grille responsable) (14 assertions)
▸ EditWindowService, MealService, HandoverService & QrCodeService (20 assertions)
▸ Phase 3 — Planning, Pointage, Heures (DST/Fériés), Alertes & Web Push VAPID (36 assertions)
▸ Phase 4 — Documents, Rapports, Exports CSV, Audit, RGPD, Production & Sécurité (44 assertions)
  ✓ Documents §1: les ordonnances de Phase 2 avec fichier joint apparaissent dans la catégorie « Ordonnance »
  ✓ Documents §1: téléversement d’un PDF valide (%PDF-) accepté avec calcul SHA-256
  ✓ Documents §1: un fichier doublon (même SHA-256) déclenche un avertissement sans bloquer l’ajout
  ✓ Documents §1: un faux PDF (script PHP renommé .pdf sans en-tête %PDF-) est refusé (422)
  ✓ Documents §1: un fichier de plus de 10 Mo est refusé
  ✓ Documents §1: téléchargement autorisé pour les 4 rôles avec Cache-Control: no-store et X-Content-Type-Options: nosniff
  ✓ Documents §1: il ne reste plus aucun écran « Bientôt disponible » dans l’application
  ✓ Documents §1 (H3): les métadonnées EXIF sont supprimées sur un JPEG déposé dans Documents
  ✓ Rapport médical §2.1: calcule la glycémie moyenne, min/max, % dans la cible et le décompte des hypo (<0,70) et hyper (>2,50)
  ✓ Rapport médical §2.1: calcule le taux d’observance global et par médicament
  ✓ Rapport médical §2.1 (H3): une période vide retourne 200 sans erreur ni division par zéro
  ✓ Rapport médical §2.1 (H3): jeu de données connu vérifie exactement moyenne=1,40, min=0,60, max=2,80, cible=50,0%, hypo=1, hyper=1, observance=75,0%
  ✓ Tendances 7 jours §2.3: retourne les 4 indicateurs avec évolution par rapport aux 7 jours précédents
  ✓ Rapport RH §2.2: un créneau futur (+12h) augmente « Planifié restant ce mois-ci » sans fausser l’écart prévu / pointé des créneaux terminés
  ✓ Exports CSV §3: inclut le BOM UTF-8, le séparateur ;, les dates Paris JJ/MM/AAAA HH:MM, la virgule décimale, les en-têtes français et trace l’export dans audit_logs
  ✓ Exports CSV §3 (RBAC): le rôle viewer peut exporter les données médicales (200) mais reçoit 403 sur les pointages, heures et l’audit
  ✓ Audit §4: computeDiff calcule les différences avant / après champ par champ en français et masque les champs sensibles
  ✓ Audit §4: les connexions réussies/échouées et les révocations d’appareils de confiance sont tracées dans audit_logs
  ✓ Conservation RGPD §5.2: retention:purge applique la suppression logique puis physique (>30j) et ne touche JAMAIS aux données médicales
  ✓ RGPD §5.2: le bouton « Exporter mes données » télécharge l’archive des données personnelles de l’utilisateur connecté
  ✓ RGPD §5.2: le bouton « Exporter toutes les données du patient » télécharge l’archive intégrale du dossier patient
  ✓ Pages légales §5.3: /legal/mentions, /legal/privacy, /legal/attendance-notice et docs/rgpd/* présents avec marqueurs [À COMPLÉTER]
  ✓ Production §6.2: GET /healthz sans authentification retourne 200 {"ok":true,"db":true,"cron_last_run":"..."}
  ✓ Production §6.2: affiche le bandeau « Les alertes automatiques sont à l'arrêt » sur l’accueil admin si le cron a > 15 min
  ✓ Production §6.1: en mode production, /design retourne 404, HSTS = max-age=63072000; includeSubDomains et les cookies ont le préfixe __Host-
  ✓ Production §6.1 (H2): avec app.env=production et app.debug=true, debug est forcé à false et aucune trace ni message technique ne fuite en HTML ou JSON
  ✓ Sécurité §6.3 (Fixation de session): l’identifiant de session est régénéré à la connexion
  ✓ Sécurité §6.3 (H3 IDOR Mesure): aide-soignante ne peut ni modifier ni supprimer la mesure d’une collègue et lecteur ne peut pas modifier (403 + base intacte)
  ✓ Sécurité §6.3 (H3 IDOR Prise): lecteur bloqué en 403 sur la validation d’une prise et statut pending inchangé en base
  ✓ Sécurité §6.3 (H3 IDOR Repas): aide-soignante ne peut pas modifier le repas d’une collègue et lecteur ne peut pas supprimer un repas (403 + base intacte)
  ✓ Sécurité §6.3 (H3 IDOR Note): lecteur bloqué en 403 lorsqu’il tente de marquer lue la note d’un autre (0 écriture dans note_reads)
  ✓ Sécurité §6.3 (H3 IDOR Document): aide-soignante, coordinatrice et lecteur bloqués en 403 sur le dépôt et la suppression de documents (base intacte)
  ✓ Sécurité §6.3 (H3 IDOR Créneau): aide-soignante bloquée en 403 sur la modification et la suppression du créneau d’une collègue (base intacte)
  ✓ Sécurité §6.3 (H3 IDOR Pointage): aide-soignante bloquée en 403 sur la validation et le rejet du pointage d’une collègue (statut pending intact)
  ✓ Sécurité §6.3 (H3 IDOR Alerte): lecteur bloqué en 403 sur le traitement d’une alerte et statut new intact en base
  ✓ Sécurité §6.3 (H3 IDOR Utilisateur): aide-soignante bloquée en 403 sur la modification d’un autre utilisateur (base intacte)
  ✓ Sécurité §6.3 (H3 IDOR Média): utilisateur non connecté bloqué (302 vers /login, 0 octet servi) sur les photos de repas et les documents
  ✓ Sécurité §6.3 (H3 IDOR Export): aide-soignante (audit) et lecteur (pointages) bloqués en 403 sans trace d’export créée
  ✓ Sécurité §6.3 (H3 IDOR Export RGPD): demander /profile/export-data?user_id=1 en tant que Fatou ne retourne que les données de Fatou
  ✓ Sécurité §6.3 (Rate Limit API): autorise 120 requêtes d’écriture / minute / utilisateur et bloque la 121e (HTTP 429)
  ✓ Sécurité §6.3 (Traversée de chemin): bloque les séquences .., le double encodage %2e%2e et les octets nuls sur /media/
  ✓ Production §6.1: bin/seed.php refuse de s’exécuter lorsque app.env = production
  ✓ Production §6.1: bin/setup.php refuse de s’exécuter si l’application est déjà installée (sauf --force)
  ✓ Données de démonstration §8: au moins 10 documents médicaux et 100 entrées d’audit réparties sur 30 jours
▸ HTTP Routes & API Smoke Test (D1 + Phase 2 + Phase 3 + Phase 4) (24 assertions)
  ✓ H1 Smoke routes paramétrées: /documents/{id}, /documents/{id}/file, /documents/{id}/download et /reports/export/{type} renvoient 0 erreur 500 sur les 4 profils
  ✓ H1 Exports CSV HTTP E2E: les 9 types × 4 profils via le routeur renvoient 200 (attachment, no-store, BOM UTF-8, ;, audit_logs) ou 403 (jamais 404 ni 500)

-----------------------------------------------------
 Résultat : 227 réussis, 0 échoués
-----------------------------------------------------
```

---

## 9. Captures

Toutes les captures mobiles sont au format `390 × 844` (`deviceScaleFactor: 2`, thèmes Clair et Sombre) dans `docs/retours/captures/phase-4/`, complétées par le rendu PDF A4 réel (`page.pdf()` Chromium converti page par page en PNG) :

| Fichier | Écran / État capturé |
|---|---|
| [`01-home-admin-trends-light.png`](captures/phase-4/01-home-admin-trends-light.png) | Accueil Responsable (`/`) avec la section « Tendances 7 jours » (4 indicateurs + évolution vs J-14..J-7) — Mode Clair |
| [`02-home-admin-trends-dark.png`](captures/phase-4/02-home-admin-trends-dark.png) | Accueil Responsable (`/`) avec « Tendances 7 jours » — Mode Sombre |
| [`03-documents-list-light.png`](captures/phase-4/03-documents-list-light.png) | Coffre-fort de documents médicaux (`/documents`, filtres par catégorie, recherche, badges SHA-256) — Mode Clair |
| [`04-documents-list-dark.png`](captures/phase-4/04-documents-list-dark.png) | Coffre-fort de documents médicaux (`/documents`) — Mode Sombre |
| [`05-document-preview-light.png`](captures/phase-4/05-document-preview-light.png) | Détail et visionneuse intégrée d’un document médical (`/documents/1`) — Mode Clair |
| [`06-document-preview-dark.png`](captures/phase-4/06-document-preview-dark.png) | Détail et visionneuse intégrée d’un document médical (`/documents/1`) — Mode Sombre |
| [`07-medical-report-light.png`](captures/phase-4/07-medical-report-light.png) | Rapport médical de synthèse (`/reports?period=30d`) avec courbe SVG sans chevauchement d’étiquettes (H9) — Mode Clair |
| [`08-medical-report-dark.png`](captures/phase-4/08-medical-report-dark.png) | Rapport médical de synthèse (`/reports?period=30d`) avec courbe SVG corrigée (H9) — Mode Sombre |
| [`09-medical-report-print-a4-page1.png`](captures/phase-4/09-medical-report-print-a4-page1.png) | **Aperçu d’impression A4 réel — Page 1/2 (I2)** (`page.pdf()` Chromium rendu en PNG, 2 pages complètes sans titre d’écran redondant ni page à moitié vide) : en-tête patient, Section 1 (synthèse glycémique + courbe SVG), Section 2 (tableau des constantes vitales avec sparklines) et début de la Section 3 (tableau d’observance par médicament) |
| [`09-medical-report-print-a4-page2.png`](captures/phase-4/09-medical-report-print-a4-page2.png) | **Aperçu d’impression A4 réel — Page 2/2 (I2)** (`page.pdf()` Chromium rendu en PNG) : fin de la Section 3 (doses d’insuline, rotation des sites, prises « Non donné »), Section 4 (alimentation & hydratation), Section 5 (valeurs critiques, chutes, incidents/transmissions urgentes, alertes critiques) et pied de page paginé `Page 2 / 2` |
| [`10-exports-csv-light.png`](captures/phase-4/10-exports-csv-light.png) | Écran des exports CSV (`/reports/exports`) — Mode Clair |
| [`11-exports-csv-dark.png`](captures/phase-4/11-exports-csv-dark.png) | Écran des exports CSV (`/reports/exports`) — Mode Sombre |
| [`26-exports-success-light.png`](captures/phase-4/26-exports-success-light.png) | **H1 / H9 — Page Exports après téléchargement CSV réussi** (`#pc-export-feedback` affiché après clic sur « Exporter CSV ») — Mode Clair |
| [`27-exports-success-dark.png`](captures/phase-4/27-exports-success-dark.png) | **H1 / H9 — Page Exports après téléchargement CSV réussi** — Mode Sombre |
| [`22-audit-log-list-light.png`](captures/phase-4/22-audit-log-list-light.png) | **H9 — Journal d’audit (`/audit`), vue liste** avec filtres et compteur d’entrées — Mode Clair |
| [`23-audit-log-list-dark.png`](captures/phase-4/23-audit-log-list-dark.png) | **H9 — Journal d’audit (`/audit`), vue liste** — Mode Sombre |
| [`12-audit-log-diff-light.png`](captures/phase-4/12-audit-log-diff-light.png) | Journal d’audit (`/audit`), vue détail ouverte avec différences avant / après (`<del>` → `<ins>`) — Mode Clair |
| [`13-audit-log-diff-dark.png`](captures/phase-4/13-audit-log-diff-dark.png) | Journal d’audit (`/audit`), vue détail ouverte avec différences avant / après — Mode Sombre |
| [`14-settings-unified-light.png`](captures/phase-4/14-settings-unified-light.png) | Réglages regroupés style iOS (`/settings`) et section Conservation des données RGPD — Mode Clair |
| [`15-settings-unified-dark.png`](captures/phase-4/15-settings-unified-dark.png) | Réglages regroupés style iOS (`/settings`) — Mode Sombre |
| [`16-hr-hours-remaining-light.png`](captures/phase-4/16-hr-hours-remaining-light.png) | Rapport RH (`/attendance/hours`) distinguant les créneaux terminés et « Planifié restant ce mois-ci » — Mode Clair |
| [`17-hr-hours-remaining-dark.png`](captures/phase-4/17-hr-hours-remaining-dark.png) | Rapport RH (`/attendance/hours`) avec « Planifié restant ce mois-ci » — Mode Sombre |
| [`18-legal-mentions-light.png`](captures/phase-4/18-legal-mentions-light.png) | Mentions légales (`/legal/mentions`) avec marqueurs `[À COMPLÉTER]` — Mode Clair |
| [`19-legal-mentions-dark.png`](captures/phase-4/19-legal-mentions-dark.png) | Mentions légales (`/legal/mentions`) — Mode Sombre |
| [`20-legal-privacy-light.png`](captures/phase-4/20-legal-privacy-light.png) | Politique de confidentialité RGPD (`/legal/privacy`) — Mode Clair |
| [`21-legal-privacy-dark.png`](captures/phase-4/21-legal-privacy-dark.png) | Politique de confidentialité RGPD (`/legal/privacy`) — Mode Sombre |
| [`24-cron-stale-banner-light.png`](captures/phase-4/24-cron-stale-banner-light.png) | **H9 — Bandeau « Les alertes automatiques sont à l’arrêt »** affiché sur `/` lorsque `cron_last_run` dépasse 15 minutes — Mode Clair |
| [`25-cron-stale-banner-dark.png`](captures/phase-4/25-cron-stale-banner-dark.png) | **H9 — Bandeau « Les alertes automatiques sont à l’arrêt »** — Mode Sombre |

---

## 10. Décisions et écarts

1. **Courbes SVG générées côté serveur dans `/reports` (`ReportService::renderGlycemiaSvg` et `renderSparklineSvg`)** :
   - Contrairement aux écrans interactifs (`/health`) qui chargent `apexcharts.min.js` à la demande, le rapport médical imprimable `/reports` génère ses courbes en SVG vectoriel pur côté serveur (sans animation, sans attribut `style=` inline et avec anti-collision `< 12 px` sur les étiquettes de seuils), garantissant un rendu instantané et net à l’impression PDF A4.
2. **Double moteur de chiffrement dans `bin/backup.sh` et `bin/restore.sh` (`age` + repli OpenSSL)** :
   - Les scripts utilisent automatiquement `age` dès que le binaire et `AGE_RECIPIENT` / `AGE_IDENTITY` sont présents dans `/etc/prime-care/backup.env`, avec repli transparent sur `openssl enc -aes-256-cbc -pbkdf2 -iter 600000` lorsque `age` n’est pas installé sur l’hôte.
3. **Écart restant vis-à-vis du cahier des charges** : **aucun** sur le périmètre logiciel des Phases 1 à 4.

---

## 11. Limites connues

1. **Tests sur vrais téléphones (installation, notifications) à faire par Hamdi** : l’installation PWA sur l’écran d’accueil et la réception des notifications Web Push depuis les passerelles Apple APNs (iPhone iOS 16.4+) et Google FCM (Android Chrome) doivent être validées sur appareils physiques par Hamdi.
2. **Mentions légales et coordonnées HDS (`[À COMPLÉTER]`)** : conformément à la consigne d’exactitude juridique (§5.3), aucun numéro SIRET, adresse postale ou contrat d’hébergeur HDS n’a été inventé ; les marqueurs `[À COMPLÉTER]` dans `/legal/*` et `docs/rgpd/*` doivent être renseignés par le responsable de traitement avant l’ouverture en production.

---

## 12. Corrections (1ʳᵉ série H1 à H9 et 2ᵉ série I1 à I2)

Suite aux revues de la Phase 4, les corrections **H1 à H9** (1ʳᵉ série) puis **I1 et I2** (2ᵉ série) ont été exécutées sur la branche `arena/f8be1f65-prime-care`, à raison d’un commit Conventional Commits par correction :

| Réf. | Commit | Description de la correction |
|---|---|---|
| **H1** | `925de8e` | **Exports CSV branchés de bout en bout (§3)** : route unique `GET /reports/export/{type:[a-z_]+}` pointant vers `ReportController::downloadExport` ; génération des liens via `route('reports.export_csv', ...)` dans `app/Views/reports/exports.php` et `app/Views/audit/index.php` ; bandeau de confirmation `#pc-export-feedback` ; vérification HTTP réelle (`curl`) des 9 exports en `admin` et `viewer` (`200` / `403`, `Content-Disposition: attachment`, `Cache-Control: no-store`, BOM UTF-8, `;`, entrée `export` dans `audit_logs`) ; ajout dans `RoutesSmokeTest` des routes paramétrées `/documents/{id}`, `/documents/{id}/file`, `/documents/{id}/download`, `/reports/export/{type}` et du test HTTP E2E des 9 types × 4 profils via le routeur. |
| **H2** | `5980e1e` | **`app.debug` forcé à `false` en production (§6.1)** : ajout de `Config::enforceProductionSafety()` dans `app/Core/Config.php` forçant `app.debug = false` dans `load()`, `set()` et `get()` dès que `app.env === 'production'`, quelle que soit la valeur écrite dans `config.php` ; ajout du test unitaire dans `Phase4Test` vérifiant qu’avec `app.env = 'production'` et `app.debug = true`, une exception passée à `ErrorHandler::handleException` ne révèle ni trace ni message technique en HTML ni en JSON. |
| **H3** | `019a5c1` | **Suite complète de 12 tests HTTP IDOR, test EXIF Documents & rapport sur jeu connu (§7)** : sécurisation de `JournalController::markRead` et `ApiMedicalController::markNoteRead` (`Policy::allows($role, 'notes', 'create')`) et retour JSON 403/422 dans `MealController::update` ; ajout dans `Phase4Test` des 12 tests HTTP IDOR via `Router::dispatch` (Mesure, Prise, Repas, Note, Document, Créneau, Pointage, Alerte, Utilisateur, Média, Export, Export RGPD `?user_id=`) vérifiant le code HTTP **et** l’invariance des données en base ; ajout du test de suppression EXIF sur un JPEG déposé dans Documents, du test de période vide sur `/reports` et du test de calcul exact sur jeu de données connu (`moyenne = 1,40`, `min = 0,60`, `max = 2,80`, `cible = 50,0 %`, `hypo = 1`, `hyper = 1`, `observance = 75,0 %`). |
| **H4** | `3379c07` | **Mesure réelle des scores Google Lighthouse mobile (§6.5)** : correction de la hiérarchie des titres `<h2>` sur `/today` et exécution de la CLI officielle Google Lighthouse en profil mobile sur `/login` (`99 / 100 / 100`), l’accueil de l’aide-soignante `/` (`95 / 100 / 96`), `/today` (`94 / 100 / 100`) et `/health` (`95 / 100 / 100`), tous supérieurs aux seuils `90 / 95 / 95`. |
| **H5** | `cc8046c` | **Audit `axe-core` étendu aux 39 écrans/états des Phases 1 à 4 en clair et sombre (§6.4)** : remplacement de `role="tablist"` par `role="group"` sur les barres de filtres de `/journal` et `/alerts`, renforcement du contraste des puces en mode sombre et de `.pc-btn--ghost`, audit `axe-core` automatisé sur les 39 écrans/états en clair et sombre (**0 violation**), et mise à jour de `docs/ACCESSIBILITE.md` avec les vérifications manuelles (clavier, `Escape` sur les feuilles, zoom 200 %, `prefers-reduced-motion`). |
| **H6** | `2cda5be` | **Copie distante `BACKUP_REMOTE` et support `age` dans `bin/backup.sh` & `bin/restore.sh` (§6.2)** : chargement automatique du fichier d’environnement externe `/etc/prime-care/backup.env` (`set -a`), chiffrement via `age` si installé (repli OpenSSL AES-256-CBC PBKDF2), copie hors site de l’archive chiffrée et de son `.sha256` vers `BACKUP_REMOTE` (`rsync` ou `sftp://`), test réel de bout en bout et documentation dans `docs/EXPLOITATION.md` et `docs/DEPLOIEMENT_HDS.md`. |
| **H7** | `6926e4c` | **Grille complète OWASP ASVS 4.0 Niveau 1 dans `docs/SECURITE.md` (§6.3)** : réécriture de `docs/SECURITE.md` sous forme de tableau de conformité ASVS 4.0.3 Niveau 1 couvrant V2, V3, V4, V5, V7, V8, V9, V12, V13 et V14 avec numéro ASVS, statut (`Conforme`), preuve (nom du test automatisé ou commande), chemin du fichier de code et mention explicite `Écarts restants : aucun`. |
| **H8** | `f4a80f5` | **Refonte par tâche de `docs/GUIDE_AIDE_SOIGNANTE.md` avec captures et mode hors ligne (§6.6)** : prise des 9 captures `390 × 844` dans `docs/guides/captures/`, organisation en une section dédiée par tâche, ajout de la section « 9. Travailler sans réseau », et remplacement du protocole médical d’hypoglycémie en dur par la description de la réaction de l’application (ouverture automatique de la fiche protocole définie par le responsable avec le médecin traitant dans Réglages, bouton SOS et appels en 1 touche). |
| **H9** | `2b7b58e` | **Mise en conformité complète de `RETOUR_PHASE_4.md` (12 sections), captures manquantes et anti-collision SVG** : masquage du libellé de seuil secondaire à moins de `12 px` sur la courbe SVG du rapport (`ReportService::renderGlycemiaSvg`), mise à jour du titre de `tests/run.php` (`Phases 1, 2, 3 & 4`), ajout des captures manquantes (aperçu d’impression PDF A4 réel page 1 et page 2 via `page.pdf()`, vue liste du journal d’audit, bandeau « Les alertes automatiques sont à l’arrêt », page Exports après téléchargement réussi) et rédaction du présent rapport en 12 sections. |
| **I1** | `7ebac80` | **Performance Lighthouse mobile ≥ 90 sur l’accueil de l’aide-soignante (`/`) en situation réelle de début de poste** : création du script PHP pur `bin/build-css.php` regroupant et minifiant les 5 feuilles CSS en `public/assets/css/app.min.css` (`79 Ko` brut / `15 Ko` gzippé) documenté dans `README.md` ; chargement dynamique à la demande (`<script>`) de `jsQR.min.js` au clic sur « Scanner le QR code » (`phase3.js`) et d’`apexcharts.min.js` par `IntersectionObserver` (`charts.js`) avec retrait du pré-cache SW ; rendu immédiat de `#unread-handover-banner` dès le premier HTML serveur sans animation `opacity: 0` ni occultation par le loader de 2 s (`LCP = 0,9 s` au lieu de `4,0–4,4 s`) ; médianes sur 3 passages : **`100 / 100 / 100`** sur `/login` et `/`, **`99 / 100 / 100`** sur `/today` et `/health`. |
| **I2** | *(présent commit)* | **Pagination A4 du rapport médical imprimé ramenée de 4 pages à 2 pages pleines sans page à moitié vide** : retrait de `break-inside: avoid` sur `.pc-report-section`, pose de `break-inside: avoid` sur les blocs élémentaires (chaque tableau, chaque graphique, chaque carte de synthèse) et de `break-after: avoid` sur les titres de section (`h2`, `h3`, en-têtes de section), masquage à l’impression du titre d’écran redondant « Rapports & Synthèse » (`.pc-large-header`), et remplacement des captures `09-medical-report-print-a4-page1.png` et `09-medical-report-print-a4-page2.png` par les 2 pages A4 réelles issues de Chromium `page.pdf()`. |
