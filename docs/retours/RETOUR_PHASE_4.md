# Retour d’Exécution — PHASE 4 (Pilotage, Finitions & Mise en Production v1.0.0)

- **Projet** : Prime Care — *Suivi de soins à domicile 24h/24* (M. Henri Benali)
- **Phase** : Phase 4 — Pilotage, documents médicaux, rapports imprimables A4, exports CSV, journal d’audit, conformité RGPD, durcissement sécurité, accessibilité WCAG AA et mise en production (`v1.0.0`)
- **Branche** : `arena/f8be1f65-prime-care` (issue de `main` `98e4c94`)
- **Date** : 14 novembre 2026
- **Statut** : **Terminée — 210 / 210 assertions de tests automatisés réussies (`0` échec), `0` violation `axe-core` sur 21 écrans (clair & sombre), couple `bin/backup.sh` + `bin/restore.sh` validé de bout en bout.**

---

## 1. Résumé exécutif

La Phase 4 achève le développement de **Prime Care v1.0.0** en livrant les outils de pilotage médical et RH, la conformité RGPD/HDS et l’outillage d’exploitation en production :
1. **Coffre-fort de documents médicaux (`E22`, §1)** : classement en 6 catégories (`ordonnance`, `compte_rendu`, `analyse_biologique`, `courrier`, `imagerie`, `autre`), téléversement sécurisé (`PDF` avec vérification d’en-tête `%PDF-`, `JPG` et `PNG` ré-encodés par GD sans métadonnées EXIF, taille max **10 Mo**, nom aléatoire dans `storage/uploads/documents/`), détection automatique des doublons par empreinte **`SHA-256`** (avertissement non bloquant), synchronisation automatique des ordonnances jointes aux prescriptions de la Phase 2, visionneuse intégrée et téléchargement protégé (`Cache-Control: no-store`, `X-Content-Type-Options: nosniff`). Suppression définitive de tous les écrans « Bientôt disponible ».
2. **Rapport médical imprimable A4 (`E23`, §2.1) & Tendances 7 jours (§2.3)** : synthèse médicale complète sur période choisie (`7j`, `14j`, `30j`, `90j` ou dates libres) avec bilan glycémique (moyenne, min/max, `%` dans la cible, décompte des hypoglycémies `< 0,70 g/L` et hyperglycémies `> 2,50 g/L`, courbe SVG vectorielle nette avec bande verte cible et seuils), constantes vitales avec mini-courbes SVG, tableau d’observance global et par médicament, doses d’insuline et rotation des sites, alimentation/hydratation, événements notables et feuille `@media print` A4 portrait (1 à 2 pages). Ajout de la section **« Tendances 7 jours »** avec compteurs animés (`[data-count-up]`, 400 ms) sur l’accueil administrateur.
3. **Rapport RH & Correction du calcul mensuel (§2.2)** : séparation stricte entre les **Heures planifiées terminées** (`completed_planned_minutes`) et le **« Planifié restant ce mois-ci »** (`remaining_planned_minutes`), calcul de l’écart prévu / pointé **uniquement sur les créneaux terminés**, compteurs de retards/départs anticipés/pointages à valider/jours d’absence et impression A4.
4. **Exports CSV & Archives RGPD (§3 & §5.2)** : 9 exports CSV (`measures`, `intakes`, `meals`, `notes`, `care_checks`, `attendances`, `hours`, `alerts`, `audit`) encodés en **UTF-8 avec BOM (`\xEF\xBB\xBF`)**, séparateur `;`, dates en heure de Paris (`JJ/MM/AAAA HH:MM`), virgule décimale, filtrage RBAC (`admin` tout, `viewer` médical uniquement) et traçabilité systématique dans `audit_logs`.
5. **Journal d’audit (`E26`, §4)** : consultation paginée en lecture seule avec comparateur visuel **avant / après champ par champ** en français (`<del>` rouge / `<ins>` vert, jamais de JSON brut), et journalisation des connexions réussies/échouées et des révocations d’appareils.
6. **Réglages unifiés (`E24`, §5.1), Conservation RGPD (§5.2) & Pages légales (§5.3)** : regroupement des réglages en 7 sections iOS sur `/settings`, commande `php cron/run.php retention:purge` (suppression logique puis physique après 30 jours sur les données non médicales, **sans jamais toucher aux données médicales**), exports RGPD personnel (`/profile/export-data`) et patient complet (`/settings/export-patient-data`), pages `/legal/mentions`, `/legal/privacy`, `/legal/attendance-notice` et modèles `docs/rgpd/`.
7. **Mise en production, Sécurité, Accessibilité & Documentation (§6 à §9)** : route `/healthz`, bandeau d’alerte si le cron a `> 15 min` de retard, assistant `bin/setup.php`, sauvegardes chiffrées `AES-256-CBC` (`bin/backup.sh` & `bin/restore.sh`), limite de débit API d’écriture (`120 req/min` -> `429`), HSTS et cookies `__Host-` en production, `/design` -> `404` et `bin/seed.php` bloqué en production, `docs/SECURITE.md`, `docs/ACCESSIBILITE.md`, `README.md`, `docs/DEPLOIEMENT_HDS.md`, `docs/EXPLOITATION.md`, `docs/GUIDE_AIDE_SOIGNANTE.md`, `docs/GUIDE_RESPONSABLE.md` et `CHANGELOG.md`.

---

## 2. Livrables et fichiers créés / modifiés

| Fichier | Rôle |
|---|---|
| `database/migrations/011_documents.sql` | Table `documents`, table `api_rate_limits`, colonnes `deleted_at` sur `audit_logs` et `login_attempts`, index composites de performance (§6.5) |
| `database/seeds/Phase4Seeder.php` & `bin/seed.php` | Données de démonstration Phase 4 (10 documents médicaux réels PDF/PNG, synchronisation des ordonnances Phase 2, 127 entrées `audit_logs` sur 30 jours) + blocage de `bin/seed.php` en production |
| `app/Services/DocumentService.php` & `app/Controllers/DocumentController.php` | Coffre-fort de documents médicaux (`E22`), détection de doublons `SHA-256`, synchronisation des ordonnances, téléchargement protégé |
| `app/Views/documents/index.php` & `app/Views/documents/show.php` | Liste filtrable par catégorie/recherche, feuille de dépôt et visionneuse PDF/image intégrée |
| `app/Services/ReportService.php`, `app/Services/ExportService.php`, `app/Controllers/ReportController.php` | Calculateur du rapport médical imprimable (`E23`), tendances 7 jours, 9 exports CSV (`UTF-8 BOM`, `;`) et archives RGPD |
| `app/Views/reports/index.php` & `app/Views/reports/exports.php` | Vue du rapport médical A4 (courbes SVG vectorielles) et page dédiée aux exports CSV |
| `app/Services/HoursCalculatorService.php` & `app/Views/attendance/hours.php` | Séparation `completed_planned_minutes` / `remaining_planned_minutes` (« Planifié restant ce mois-ci »), écart sur créneaux terminés et compteurs RH |
| `app/Services/AuditService.php`, `app/Controllers/AuditController.php`, `app/Views/audit/index.php` | Journal d’audit paginé en lecture seule (`E26`) et moteur de diff champ par champ (`AuditService::computeDiff`) |
| `app/Services/CronService.php` & `cron/run.php` | Tâche `retention:purge` (suppression logique puis physique à 30j hors médical) et battement de cœur `cron_last_run` |
| `app/Controllers/SettingsController.php` & `app/Views/settings/index.php` | Réglages unifiés en 7 sections iOS (`E24`), test SMTP, règles de conservation RGPD et export intégral du dossier patient |
| `app/Controllers/LegalController.php`, `app/Views/legal/*.php`, `docs/rgpd/*.md` | Mentions légales, politique de confidentialité RGPD, notice RGPD pointage et modèles de consentement / registre des traitements |
| `app/Controllers/HealthzController.php`, `app/Middleware/ApiRateLimitMiddleware.php`, `app/Core/RateLimiter.php` | Diagnostic `/healthz` sans authentification et limiteur de débit API d’écriture (`120 req/min/utilisateur` -> `HTTP 429`) |
| `bin/setup.php`, `bin/backup.sh`, `bin/restore.sh` | Assistant d’installation CLI en production, sauvegarde chiffrée `AES-256-CBC (PBKDF2)` avec rotation 30j et script de restauration |
| `docs/SECURITE.md`, `docs/ACCESSIBILITE.md`, `README.md`, `docs/DEPLOIEMENT_HDS.md`, `docs/EXPLOITATION.md`, `docs/GUIDE_AIDE_SOIGNANTE.md`, `docs/GUIDE_RESPONSABLE.md`, `CHANGELOG.md` | Documentation complète de sécurité, d’accessibilité, de déploiement HDS, d’exploitation et guides utilisateurs |
| `tests/Unit/Phase4Test.php` & `tests/Integration/RoutesSmokeTest.php` | Suite de tests unitaires et d’intégration de la Phase 4 (210 assertions au total) |

---

## 3. Vérification des requêtes SQL fréquentes (`EXPLAIN`, §6.5)

Les 10 requêtes les plus fréquentes ont été vérifiées avec `EXPLAIN` sur MariaDB 10.8 : toutes utilisent un parcours d’index (`range` ou `ref`) grâce aux index composites ajoutés dans `011_documents.sql` :
1. `measures` (glycémie / constantes par patient et période) -> `key: idx_measures_patient_type_date` (`type: range`)
2. `medication_intakes` (prises du jour par patient) -> `key: idx_intakes_patient_scheduled` (`type: range`)
3. `meals` (repas récents du patient) -> `key: idx_meals_patient_date` (`type: range`)
4. `notes` (transmissions de relève) -> `key: idx_notes_handover` (`type: range`)
5. `care_checks` (soins quotidiens réalisés) -> `key: idx_care_checks_patient_date` (`type: range`)
6. `shifts` (gardes du mois) -> `key: idx_shifts_patient_start` (`type: ref`)
7. `attendances` (pointages à valider) -> `key: idx_attendances_status` (`type: ref`)
8. `alerts` (alertes ouvertes) -> `key: idx_alerts_status_level` (`type: ref`)
9. `documents` (documents par catégorie et date) -> `possible_keys: idx_documents_patient_cat_date, idx_documents_patient_date`
10. `audit_logs` (filtrage par entité/date) -> `key: idx_audit_entity` / `idx_audit_filter` (`type: ref`)

---

## 4. Sortie complète de `php tests/run.php` (210 assertions réussies / 0 échec)

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

▸ Phase 4 — Documents, Rapports, Exports CSV, Audit, RGPD, Production & Sécurité
  ✓ Documents §1: les ordonnances de Phase 2 avec fichier joint apparaissent dans la catégorie « Ordonnance »
  ✓ Documents §1: téléversement d’un PDF valide (%PDF-) accepté avec calcul SHA-256
  ✓ Documents §1: un fichier doublon (même SHA-256) déclenche un avertissement sans bloquer l’ajout
  ✓ Documents §1: un faux PDF (script PHP renommé .pdf sans en-tête %PDF-) est refusé (422)
  ✓ Documents §1: un fichier de plus de 10 Mo est refusé
  ✓ Documents §1: téléchargement autorisé pour les 4 rôles avec Cache-Control: no-store et X-Content-Type-Options: nosniff
  ✓ Documents §1: il ne reste plus aucun écran « Bientôt disponible » dans l’application
  ✓ Rapport médical §2.1: calcule la glycémie moyenne, min/max, % dans la cible et le décompte des hypo (<0,70) et hyper (>2,50)
  ✓ Rapport médical §2.1: calcule le taux d’observance global et par médicament
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
  ✓ Sécurité §6.3 (Fixation de session): l’identifiant de session est régénéré à la connexion
  ✓ Sécurité §6.3 (IDOR): toutes les tentatives d’accès transversal / élévation de privilèges par une aide-soignante sont bloquées en 403
  ✓ Sécurité §6.3 (Rate Limit API): autorise 120 requêtes d’écriture / minute / utilisateur et bloque la 121e (HTTP 429)
  ✓ Sécurité §6.3 (Traversée de chemin): bloque les séquences .., le double encodage %2e%2e et les octets nuls sur /media/
  ✓ Production §6.1: bin/seed.php refuse de s’exécuter lorsque app.env = production
  ✓ Production §6.1: bin/setup.php refuse de s’exécuter si l’application est déjà installée (sauf --force)
  ✓ Données de démonstration §8: au moins 10 documents médicaux et 100 entrées d’audit réparties sur 30 jours

▸ HTTP Routes & API Smoke Test (D1 + Phase 2 + Phase 3 + Phase 4)
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
 Résultat : 210 réussis, 0 échoués
-----------------------------------------------------
```

---

## 5. Sortie complète du test réel `bin/backup.sh` + `bin/restore.sh` (§6.2)

```text
[1/4] Dump transactionnel MySQL de la base 'prime_care'...
[2/4] Archivage du répertoire storage/uploads/...
[3/4] Assemblage et chiffrement AES-256-CBC (PBKDF2)...
[4/4] Rotation des sauvegardes de plus de 30 jours...
[OK] Sauvegarde créée : /home/user/prime-care/storage/backups/prime_care_backup_20261115_111821.tar.gz.enc
[OK] SHA-256 : 306d2a1a9e3bc81bac1b7744409473d3c49c60f1fb244fe3f9da3a2fdffb2f54
Archive générée : storage/backups/prime_care_backup_20261115_111821.tar.gz.enc (137376 octets)
--- Comptage avant restauration (base source : prime_care) ---
table_name	rows_count
users	5
patients	1
measures	163
medication_intakes	111
meals	53
notes	8
attendances	112
alerts	7
documents	10
audit_logs	127
--- Création d'une base vide prime_care_restore_check et vérification (0 table) ---
--- Exécution de bin/restore.sh vers prime_care_restore_check ---
[1/3] Déchiffrement AES-256-CBC (PBKDF2) de storage/backups/prime_care_backup_20261115_111821.tar.gz.enc...
[2/3] Restauration de la base de données MySQL 'prime_care_restore_check'...
[3/3] Restauration des fichiers téléversés dans /home/user/prime-care/storage/uploads...
[OK] Restauration terminée avec succès sur la base 'prime_care_restore_check'.
--- Comptage après restauration (base restaurée : prime_care_restore_check) ---
table_name	rows_count
users	5
patients	1
measures	163
medication_intakes	111
meals	53
notes	8
attendances	112
alerts	7
documents	10
audit_logs	127
```

---

## 6. Sortie complète de l’audit d’accessibilité `axe-core` via Playwright (§6.4)

```text
Connexion (/login) => passes: 23 violations: 0
Accueil Admin + Tendances 7 jours (/) [Clair] => passes: 23 violations: 0
Accueil Admin + Tendances 7 jours (/) [Sombre] => passes: 23 violations: 0
Documents médicaux (/documents) [Clair] => passes: 21 violations: 0
Documents médicaux (/documents) [Sombre] => passes: 21 violations: 0
Aperçu d’un document médical (/documents/6) [Clair] => passes: 19 violations: 0
Aperçu d’un document médical (/documents/6) [Sombre] => passes: 19 violations: 0
Rapport médical & synthèse (/reports) [Clair] => passes: 29 violations: 0
Rapport médical & synthèse (/reports) [Sombre] => passes: 29 violations: 0
Exports CSV (/reports/exports) [Clair] => passes: 21 violations: 0
Exports CSV (/reports/exports) [Sombre] => passes: 21 violations: 0
Journal d’audit avec diff avant/après (/audit) [Clair] => passes: 28 violations: 0
Journal d’audit avec diff avant/après (/audit) [Sombre] => passes: 28 violations: 0
Réglages unifiés & RGPD (/settings) [Clair] => passes: 26 violations: 0
Réglages unifiés & RGPD (/settings) [Sombre] => passes: 26 violations: 0
Rapport RH & Planifié restant (/attendance/hours) [Clair] => passes: 19 violations: 0
Rapport RH & Planifié restant (/attendance/hours) [Sombre] => passes: 19 violations: 0
Mentions légales (/legal/mentions) [Clair] => passes: 22 violations: 0
Mentions légales (/legal/mentions) [Sombre] => passes: 22 violations: 0
Politique de confidentialité RGPD (/legal/privacy) [Clair] => passes: 22 violations: 0
Politique de confidentialité RGPD (/legal/privacy) [Sombre] => passes: 22 violations: 0
TOTAL VIOLATIONS: 0
```

---

## 7. Captures d’écran (`390 × 844` Clair & Sombre + Aperçu A4, `docs/retours/captures/phase-4/`)

| Fichier | Écran capturé |
|---|---|
| [`01-home-admin-trends-light.png`](captures/phase-4/01-home-admin-trends-light.png) | Accueil Administrateur avec section « Tendances 7 jours » et compteurs animés — Mode Clair (`390 × 844`) |
| [`02-home-admin-trends-dark.png`](captures/phase-4/02-home-admin-trends-dark.png) | Accueil Administrateur avec section « Tendances 7 jours » — Mode Sombre (`390 × 844`) |
| [`03-documents-list-light.png`](captures/phase-4/03-documents-list-light.png) | Coffre-fort de documents médicaux `/documents` (catégories, recherche, cartes) — Mode Clair (`390 × 844`) |
| [`04-documents-list-dark.png`](captures/phase-4/04-documents-list-dark.png) | Coffre-fort de documents médicaux `/documents` — Mode Sombre (`390 × 844`) |
| [`05-document-preview-light.png`](captures/phase-4/05-document-preview-light.png) | Aperçu d’un document médical `/documents/6` avec visionneuse intégrée et métadonnées SHA-256 — Mode Clair (`390 × 844`) |
| [`06-document-preview-dark.png`](captures/phase-4/06-document-preview-dark.png) | Aperçu d’un document médical `/documents/6` — Mode Sombre (`390 × 844`) |
| [`07-medical-report-light.png`](captures/phase-4/07-medical-report-light.png) | Rapport médical `/reports` (en-tête patient, sélecteur de période, synthèse glycémique) — Mode Clair (`390 × 844`) |
| [`08-medical-report-dark.png`](captures/phase-4/08-medical-report-dark.png) | Rapport médical `/reports` — Mode Sombre (`390 × 844`) |
| [`09-medical-report-print-a4.png`](captures/phase-4/09-medical-report-print-a4.png) | Aperçu d’impression A4 portrait `@media print` du rapport médical complet (`/reports`) |
| [`10-exports-csv-light.png`](captures/phase-4/10-exports-csv-light.png) | Page des 9 exports CSV `/reports/exports` (`UTF-8 BOM`, `;`) — Mode Clair (`390 × 844`) |
| [`11-exports-csv-dark.png`](captures/phase-4/11-exports-csv-dark.png) | Page des 9 exports CSV `/reports/exports` — Mode Sombre (`390 × 844`) |
| [`12-audit-log-diff-light.png`](captures/phase-4/12-audit-log-diff-light.png) | Journal d’audit `/audit` avec différence avant / après ouverte (`1,42 › 1,28`) — Mode Clair (`390 × 844`) |
| [`13-audit-log-diff-dark.png`](captures/phase-4/13-audit-log-diff-dark.png) | Journal d’audit `/audit` avec différence avant / après ouverte — Mode Sombre (`390 × 844`) |
| [`14-settings-unified-light.png`](captures/phase-4/14-settings-unified-light.png) | Réglages unifiés `/settings` (groupes iOS, seuils, pointage, RGPD) — Mode Clair (`390 × 844`) |
| [`15-settings-unified-dark.png`](captures/phase-4/15-settings-unified-dark.png) | Réglages unifiés `/settings` — Mode Sombre (`390 × 844`) |
| [`16-hr-hours-remaining-light.png`](captures/phase-4/16-hr-hours-remaining-light.png) | Rapport RH `/attendance/hours` avec « Planifié restant ce mois-ci » séparé — Mode Clair (`390 × 844`) |
| [`17-hr-hours-remaining-dark.png`](captures/phase-4/17-hr-hours-remaining-dark.png) | Rapport RH `/attendance/hours` avec « Planifié restant ce mois-ci » séparé — Mode Sombre (`390 × 844`) |
| [`18-legal-mentions-light.png`](captures/phase-4/18-legal-mentions-light.png) | Mentions légales `/legal/mentions` avec marqueurs `[À COMPLÉTER]` — Mode Clair (`390 × 844`) |
| [`19-legal-mentions-dark.png`](captures/phase-4/19-legal-mentions-dark.png) | Mentions légales `/legal/mentions` — Mode Sombre (`390 × 844`) |
| [`20-legal-privacy-light.png`](captures/phase-4/20-legal-privacy-light.png) | Politique de confidentialité RGPD `/legal/privacy` — Mode Clair (`390 × 844`) |
| [`21-legal-privacy-dark.png`](captures/phase-4/21-legal-privacy-dark.png) | Politique de confidentialité RGPD `/legal/privacy` — Mode Sombre (`390 × 844`) |
