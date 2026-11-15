# Plan d’Exécution — Phase 4 : Pilotage, finitions et mise en production (version 1.0)

> **Branche** : `arena/f8be1f65-prime-care` (créée depuis `main` `98e4c942d293fe15df48be94ea3a6e414cc854b2`)
> **Objectif** : Livrer la **version 1.0.0** de Prime Care, prête pour un hébergement certifié HDS, sans aucun écran « Bientôt disponible », avec les 181 tests existants intacts plus la suite de tests Phase 4.

---

## 1. Ordre d’exécution et découpage en étapes

1. **Étape 1 — Migration `011_documents.sql`, index SQL & Seeder Phase 4 (`Phase4Seeder.php`)**
   - Création de la table `documents` (`id`, `patient_id`, `prescription_id`, `category`, `title`, `doc_date`, `description`, `file_path`, `original_name`, `mime`, `size`, `sha256`, `uploaded_by`, `created_at`, `updated_at`, `created_by`, `deleted_at`) + index composites de performance pour les 10 requêtes les plus fréquentes.
   - Synchronisation automatique des ordonnances jointes aux prescriptions (`prescriptions.document_path`) dans la table `documents` (catégorie `ordonnance`, reliées par `prescription_id`).
   - Enrichissement de `Phase4Seeder` avec des documents médicaux réalistes (ordonnance, analyse biologique HbA1c, compte rendu diabétologue, courrier médecin traitant) et entrées d’audit avec différences avant/après.

2. **Étape 2 — Module Documents médicaux (E22, §1)**
   - `app/Services/DocumentService.php` & `app/Controllers/DocumentController.php` :
     - Dépôt sécurisé (`admin` uniquement) : PDF, JPG, PNG, **10 Mo maximum** (`10 * 1024 * 1024`), vérification `finfo`, en-tête `%PDF-` obligatoire pour les PDF, ré-encodage GD sans EXIF pour les images, nom aléatoire dans `storage/uploads/documents/`, calcul `sha256` et avertissement non bloquant en cas de doublon.
     - Consultation (`admin`, `coordinator`, `caregiver`, `viewer`) : filtrage par catégorie (`ordonnance`, `compte_rendu`, `analyse_biologique`, `courrier`, `imagerie`, `autre`), tri par `doc_date`, recherche dans le titre, visionneuse intégrée (PDF `inline` / image plein écran) et téléchargement (`attachment`) servis par contrôleur authentifié avec `Cache-Control: no-store` et `X-Content-Type-Options: nosniff`.
     - Suppression logique (`admin` uniquement) tracée dans `audit_logs`.
   - Suppression définitive de toute route/écran « Bientôt disponible » (`app/Views/home/more.php`, `app/Controllers/HomeController.php`).

3. **Étape 3 — Rapports médicaux imprimables A4, Rapport RH corrigé & Tendances 7j (E23, §2)**
   - `app/Services/ReportService.php` & `app/Controllers/ReportController.php` :
     - **Rapport médical imprimable** (`/reports`, rôles `admin`, `coordinator`, `viewer`) : périodes `7j`, `14j`, `30j`, `90j` ou dates libres (`from` / `to`).
       1. En-tête : logo complet, patient (nom, âge, type de diabète, cibles), période, date d’édition, « Édité par ».
       2. Synthèse glycémique : moyenne, min, max, **temps dans la cible (%)**, nombre d’hypoglycémies (`< 0,70 g/L`) et d’hyperglycémies (`> 2,50 g/L`), moyenne par moment de la journée (à jeun, avant repas, après repas, coucher), courbe SVG nette sans animation avec bandes de seuils.
       3. Autres constantes (tension, température, poids, SpO2) : tableau moyenne / min / max / dernière valeur + mini-courbe SVG.
       4. Observance du traitement : taux global et taux par médicament, liste des « Non donné » avec motif, insuline (doses données et répartition des sites d’injection).
       5. Alimentation : nombre de repas, portion moyenne mangée (%), glucides moyens (g).
       6. Événements notables : valeurs critiques, chutes, incidents, transmissions urgentes, alertes critiques avec leur résolution.
       7. Pied de rapport : **« Document de suivi, ne remplace pas un avis médical »** et pied de page `@media print` A4 portrait : **« Généré avec Prime Care — Prime Vision Solutions »** + numérotation de page, `break-inside: avoid`, aucun élément d’interface.
     - **Rapport RH & Correction de l’écart mensuel (§2.2)** :
       - Mise à jour de `HoursCalculatorService::calculateMonthlySummary` : séparation stricte entre `completed_planned_minutes` (créneaux dont `end_at <= nowUtc`) et `remaining_planned_minutes` (créneaux à venir du mois en cours).
       - Calcul de `diff_minutes = punched_minutes - completed_planned_minutes` (**uniquement sur les créneaux déjà terminés**) et affichage distinct de **« Planifié restant ce mois-ci »** dans `/attendance/hours` et `/reports/hr` (avec compteurs de retards, départs anticipés, pointages à valider, absences et version imprimable).
     - **Tableau de bord Admin — « Tendances 7 jours » (§2.3)** :
       - Sous les points d’attention sur `/` (`admin`, `coordinator`, `viewer`) : 4 cartes indicateurs avec **compteur animé** (`data-count-up`, désactivé si `prefers-reduced-motion: reduce`), flèche d’évolution vs les 7 jours précédents (`J-14..J-7`) pour : glycémie moyenne, temps dans la cible (%), observance (%), hypoglycémies, + lien vers `/reports?period=30d`.

4. **Étape 4 — Exports CSV & Journal d’audit avec diff avant/après (§3 & §4)**
   - `app/Services/ExportService.php` :
     - Écran `/reports/exports` (`admin`, `coordinator`, `viewer` — le lecteur n’a accès qu’aux exports médicaux : mesures, prises, repas, notes/transmissions, soins ; l’admin a en plus pointages, heures, alertes et journal d’audit).
     - Téléchargement `GET /reports/exports/{type}` avec filtre de période (`from`, `to`), séparateur `;`, UTF-8 avec BOM (`\xEF\xBB\xBF`), dates en heure de Paris `JJ/MM/AAAA HH:MM`, nombres avec virgule décimale, en-têtes en français, `Cache-Control: no-store`, `Content-Disposition: attachment`, et **traçabilité systématique dans `audit_logs`** (`action = 'export'`, ressource, période).
   - `app/Controllers/AuditController.php` & `app/Views/audit/index.php` (E26, `admin` uniquement) :
     - Liste paginée filtrable par utilisateur (`user_id`), module/table (`entity_type`), action (`create`, `update`, `delete`, `login`, `login_failed`, `export`, `validate`, `revoke_device`…) et période (`from`, `to`).
     - Détail d’une ligne : **différences avant / après** affichées champ par champ avec libellés lisibles, ancienne valeur barrée (`<del>`) puis nouvelle valeur (`<ins>`), sans jamais afficher de JSON brut tel quel.
     - Traçabilité dans `audit_logs` des connexions réussies/échouées (`AuthController`) et des révocations d’appareils de confiance (`ProfileController`).

5. **Étape 5 — Réglages regroupés iOS (E24), Conservation RGPD (`retention:purge`), Export RGPD & Pages légales (§5)**
   - **Réglages regroupés (`/settings`)** : liste groupée style iOS en haut de `/settings` regroupant : Patient, Mesures et seuils, Traitements, Soins rapides, Pointage, Alertes, E-mail, Application (unité de glycémie, fenêtre de prise, durée du loader), **Conservation des données**.
   - **Conservation des données & tâche cron `retention:purge` (§5.2)** :
     - Durées configurables (par défaut : données médicales = conservées sans purge automatique ; pointages = 5 ans ; logs techniques = 12 mois ; alertes traitées = 24 mois ; journal d’audit = 6 ans).
     - `CronService::runTask('retention:purge')` : applique la suppression logique (`deleted_at`) puis physique après 30 jours sur les pointages/alertes/logs éligibles, journalise l’opération dans `audit_logs` et **ne touche jamais aux données médicales** (`measures`, `medication_intakes`, `prescriptions`, `meals`, `notes`, `care_checks`, `documents`, `patients`).
   - **Droit d’accès RGPD (§5.2)** :
     - Bouton « Exporter mes données » dans `/profile` (`POST /profile/export-data`) : génère une archive ZIP (`ZipArchive`) ou un fichier CSV multi-sections contenant le compte, les saisies réalisées par l’utilisateur connecté et ses pointages (strictement limité à `Auth::id()`).
     - Bouton « Exporter toutes les données du patient » pour `admin` (`POST /settings/export-patient-data`).
   - **Pages légales (§5.3) & Modèles `docs/rgpd/`** :
     - Routes publiques/authentifiées `/legal/mentions`, `/legal/privacy`, `/legal/attendance-notice` accessibles depuis `/about` et en bas de `/login`.
     - `docs/rgpd/MODELE_CONSENTEMENT_PATIENT.md` et `docs/rgpd/REGISTRE_DES_TRAITEMENTS.md` avec marqueurs `[À COMPLÉTER]` explicites et aucune donnée juridique inventée.

6. **Étape 6 — Mode Production, `bin/setup.php`, `/healthz`, Bandeau Cron, `bin/backup.sh` & `bin/restore.sh` (§6.1 & §6.2)**
   - Verrouillage `app.env = production` : `app.debug` forcé à `false` dans `Config`, `/design` renvoie `HTTP 404`, `bin/seed.php` et `bin/migrate.php --seed` refusés en production, HSTS, cookies `__Host-`.
   - `GET /healthz` : renvoie uniquement `{"ok":true,"db":true,"cron_last_run":"..."}` avec `Cache-Control: no-store` et zéro donnée personnelle.
   - Enregistrement horodaté du dernier passage de `cron/run.php` (`settings.cron_last_run_at` + `storage/cache/cron_last_run.txt`) et affichage pour l’`admin` du bandeau **« Les alertes automatiques sont à l’arrêt »** dans le layout principal si le dernier passage remonte à plus de 15 minutes.
   - `bin/setup.php` : assistant CLI interactif ou piloté par `--config=setup.ini` (contrôle PHP 8.2+, extensions, droits d’écriture, HTTPS, fuseau, exécution des migrations sans seed de démo, création du 1er admin, du patient et de ses contacts, génération des clés VAPID et du secret HMAC QR).
   - `bin/backup.sh` & `bin/restore.sh` : sauvegarde `mysqldump --single-transaction` + archive `storage/uploads`, chiffrement `openssl enc -aes-256-cbc -pbkdf2`, rotation 30 jours, copie secondaire optionnelle (`rsync`/`sftp`), et test réel complet (sauvegarde → vidage → restauration → comptage des lignes).

7. **Étape 7 — Sécurité (ASVS L1, IDOR, 429 API, Traversée), Accessibilité (`axe-core`) & Performance (`EXPLAIN` + Lighthouse) (§6.3 à §6.5)**
   - Limitation de débit des API d’écriture (`RateLimiter::checkApiWriteLimit` : 120 requêtes / minute / utilisateur → `HTTP 429`).
   - Protection stricte contre la traversée de chemin (`../`, octet nul `\0`, double encodage `%2e%2e`, `%252e`) sur `/media` et `/documents`.
   - Chargement conditionnel d’`apexcharts.min.js` et `jsQR.min.js` uniquement sur les écrans qui en ont besoin + `<link rel="preload">` de la police Inter + `loading="lazy"` sur les images.
   - Audit `axe-core` automatisé sous Chromium sur les 13 écrans principaux (objectif : **0 violation `serious` ou `critical`**) et rédaction de `docs/ACCESSIBILITE.md` et `docs/SECURITE.md`.
   - Analyse `EXPLAIN` des 10 requêtes SQL principales et mesure de performance mobile.

8. **Étape 8 — Documentation complète 1.0, Suite de tests Phase 4, Captures `390×844` & `RETOUR_PHASE_4.md` (§6.6 à §9)**
   - Rédaction de `README.md`, `docs/DEPLOIEMENT_HDS.md`, `docs/EXPLOITATION.md`, `docs/GUIDE_AIDE_SOIGNANTE.md` (1 tâche par section + captures dédiées), `docs/GUIDE_RESPONSABLE.md`, `CHANGELOG.md`, mise à jour de `docs/DESIGN_SYSTEM.md` et version `1.0.0` dans `/about`.
   - Ajout de `tests/Unit/Phase4Test.php` couvrant tous les points du §7 (documents, rapports, rapport RH, exports CSV + audit, diff d’audit + connexions, `retention:purge`, export RGPD isolé, mode production, IDOR complet, fixation de session, 429 API, chemins de fichiers, smoke test 4 rôles).
   - Prise et vérification visuelle de toutes les captures `390×844` (clair & sombre + aperçu d’impression A4 page 1 et page 2) dans `docs/retours/captures/phase-4/`.
   - Rédaction de `docs/retours/RETOUR_PHASE_4.md`, commits propres et `git push origin arena/f8be1f65-prime-care`.
