# Plan d’exécution — Phase 3 : Équipe, pointage, alertes et mode hors ligne

> **Branche de travail** : `arena/0f0ecf73-prime-care` (créée depuis `main` `b252a7b03cfb2e407bdd5c355500e8b080442fb1`)  
> **Périmètre** : Phase 3 / 4 — Planning d’équipe (modèles de créneaux, vue semaine/mois, duplication, remplacements, absences, contrôle de couverture), Pointage des aides-soignantes (bouton glissant + alternative accessible clavier/lecteur d’écran, GPS Haversine, QR code HMAC du domicile + scan caméra `BarcodeDetector`/`jsQR`, oubli de pointage déclaré, contrôle hebdomadaire, récapitulatif mensuel des heures de nuit/dimanche/jours fériés français et export CSV de paie), Alertes & Notifications temps réel (7 règles de déclenchement, déduplication, escalade à 15 min, clôture automatique, centre d’alertes, Web Push VAPID natif RFC 8291 / RFC 8292 sans bibliothèque tierce, e-mail SMTP PHPMailer avec logo PNG et `mail.log` en dev, file `notification_queue` avec 5 tentatives, canal SMS désactivé) et Mode hors ligne complet (file IndexedDB `offline-queue.js`, `client_uuid` idempotent, synchronisation automatique et lecture hors ligne sécurisée).

---

## 1. Objectif de la Phase 3

Doter **Prime Care** de la gestion complète de l’équipe à domicile autour de M. Henri Benali : planification continue des gardes de jour et de nuit sans trou de couverture, pointage d’arrivée et de départ infalsifiable côté serveur (avec preuve de présence souple GPS et/ou QR code du domicile sans jamais bloquer la prise de poste), calcul exact des heures travaillées (normales, nuit 21h–6h, dimanche, jours fériés français y compris Pâques/Ascension/Pentecôte) exportables en CSV pour la paie, détection et notification immédiate des événements critiques ou retards via Web Push VAPID natif et e-mail HTML, et résilience totale aux coupures réseau au domicile grâce à la file d’attente hors ligne IndexedDB à synchronisation automatique et idempotente. Toutes les fondations et fonctionnalités des Phases 1 et 2 (142 tests automatisés, Design B « Bleu nuit », CSP stricte à nonces sans `unsafe-inline`, zéro attribut `style=`) demeurent strictement préservées.

---

## 2. Ordre d’exécution et découpage technique

### Étape 0 — Préparation & Bibliothèques tierces (`docs/retours/PLAN_PHASE_3.md`, `public/assets/vendor/`)
1. Rédaction du présent plan `docs/retours/PLAN_PHASE_3.md` et mise à jour du §8 de `docs/ADDENDUM_DESIGN_B.md` avec les composants visuels de la Phase 3 (bouton glissant de pointage, grille de planning, badge d’alerte, bandeau hors ligne et badge de synchronisation).
2. Intégration locale sans Composer ni CDN :
   - **PHPMailer** (3 fichiers : `PHPMailer.php`, `SMTP.php`, `Exception.php` sous `public/assets/vendor/phpmailer/`, chargés par l’autoloader PSR-4 `PHPMailer\PHPMailer\`).
   - **jsQR** (`public/assets/vendor/jsQR.min.js`, licence MIT) en repli de `BarcodeDetector` pour le scan du QR code de pointage par la caméra.
   - Mise à jour de `public/assets/vendor/LICENSES.md` (LGPL-2.1 pour PHPMailer, MIT pour jsQR).

### Étape 1 — Base de données : Migrations `007` à `010` & `Phase3Seeder`
1. **`database/migrations/007_planning.sql`** :
   - `shift_templates` : modèles de créneaux réutilisables (ex. *Garde de jour 08h00–20h00*, *Garde de nuit 20h00–08h00*, *Demi-journée matin 08h00–14h00*).
   - `shifts` : créneaux planifiés (`patient_id`, `template_id`, `user_id` nullable si non couvert, `start_at` et `end_at` en UTC, `status` `planned` / `confirmed` / `cancelled`, `replaced_user_id`, `notes`, index `(start_at, end_at)` et `(user_id, start_at)`).
   - `absences` : absences déclarées (`patient_id`, `user_id`, `absence_type` `conge` / `maladie` / `autre`, `start_date`, `end_date`, `comment`).
   - Enrichissement de la fiche patient (`patients`) avec les coordonnées GPS fictives du domicile (`home_latitude`, `home_longitude`, `home_radius_m`) utilisées pour le contrôle de présence par géolocalisation.
2. **`database/migrations/008_attendance.sql`** :
   - `attendances` : pointages d’arrivée et de départ (`patient_id`, `user_id`, `shift_id`, `type` `in` / `out`, `recorded_at` heure serveur UTC, `declared_at` heure déclarée si oubli, `method` `slide` / `qr` / `declared`, `latitude`, `longitude`, `accuracy_m`, `distance_m`, `ip`, `status` `valid` / `pending` / `rejected`, `validated_by`, `validated_at`, `reason`, `rejection_reason`).
   - `attendance_qr_tokens` : jetons de QR code du domicile (`patient_id`, `token_id`, `secret_hash`, `label`, `revoked_at`, `created_by`).
3. **`database/migrations/009_alerts.sql`** :
   - `alerts` : alertes persistées (`patient_id`, `type`, `level` `info` / `warning` / `critical`, `source_type`, `source_id`, `dedupe_key`, `open_dedupe_key` généré ou indexé pour garantir l’unicité tant que l’alerte est ouverte, `title`, `message`, `target_url`, `status` `new` / `seen` / `handled`, `escalated_at`, `handled_by`, `handled_at`, `resolution`).
   - `notification_settings` : préférences par utilisateur (`user_id`, `push_enabled`, `email_enabled`, `sms_enabled`, `quiet_hours_enabled`, `quiet_start`, `quiet_end`, `types_json`).
   - `push_subscriptions` : abonnements Web Push (`user_id`, `endpoint`, `endpoint_hash` UNIQUE, `p256dh`, `auth`, `user_agent`, `last_seen_at`).
   - `notification_queue` : file d’envoi asynchrone (`alert_id`, `user_id`, `channel` `push` / `email` / `sms`, `recipient`, `subject`, `payload_json`, `attempts`, `max_attempts`, `next_try_at`, `sent_at`, `last_error`, `status` `pending` / `sent` / `failed`).
4. **`database/migrations/010_offline.sql`** :
   - Ajout de la colonne `client_uuid CHAR(36) NULL UNIQUE` sur `measures`, `medication_intakes`, `meals`, `notes` et `care_checks` pour garantir l’idempotence stricte de la synchronisation hors ligne.
5. **`database/seeds/Phase3Seeder.php`** :
   - Planning complet sur les **4 semaines passées** et les **2 semaines à venir** avec gardes de jour (`08h00–20h00`) et gardes de nuit (`20h00–08h00`) réparties entre Fatou, Leïla et Aïcha.
   - **1 créneau non couvert** dans les prochaines 36 heures, **1 absence** déclarée, **1 remplacement** tracé.
   - Pointages réalistes sur les 4 semaines écoulées (à l’heure, quelques retards de 15 à 25 min, **1 oubli de pointage déclaré** en statut `pending` « À valider »).
   - Jeton QR code actif du domicile et coordonnées GPS fictives du domicile de M. Henri Benali (Créteil / Île-de-France).
   - Alertes variées de chaque type et de chaque statut (`new`, `seen`, `handled`).

### Étape 2 — Planning d’équipe (`/planning`, `PlanningController`, `PlanningService`, `HoursCalculatorService`)
1. **Fonctionnalités du planning (E16)** :
   - Modèles de créneaux éditables (`shift_templates`).
   - Vue **Semaine** (par défaut, 7 jours × créneaux) et vue **Mois**, colorées par aide-soignante avec pastille d’initiales.
   - Création / modification d’un créneau en bottom sheet (date, modèle ou horaires personnalisés, aide-soignante ou « Non couvert », notes).
   - **Duplication d’une semaine type** sur `N` semaines en 1 action.
   - **Remplacement** d’une aide-soignante sur un créneau (`replaced_user_id` conservé et affiché *« Fatou (remplace Leïla) »*).
   - **Contrôle de couverture** : créneau sans aide-soignante mis en évidence en rouge hachuré (*« Non couvert »*) ; refus serveur (`HTTP 422`) de deux créneaux qui se chevauchent pour la même personne.
   - **Absences** : déclaration d’absence marquée automatiquement sur les créneaux correspondants (*« À remplacer »*).
   - **Côté aide-soignante** : carte *« Mon prochain créneau »* sur l’accueil et onglet Planning en lecture seule filtré sur ses propres créneaux.
   - **RBAC** : `admin` (tout), `coordinator` (CRUD planning), `caregiver` (lecture de ses créneaux), `viewer` (lecture).

### Étape 3 — Pointage, contrôle de présence & récapitulatif mensuel des heures (`/attendance`, `/attendance/hours`, `AttendanceController`, `AttendanceService`, `HoursCalculatorService`)
1. **Pointage côté aide-soignante (E05)** :
   - **Bouton glissant** en haut de l’accueil (`caregiver`, `coordinator`) : *« Glisser pour arriver »* (vert émeraude) / *« Glisser pour partir »* (ambre), doublé d’une **alternative accessible au clavier et au lecteur d’écran** (bouton activable par `Entrée` / `Espace` / tap ouvrant une feuille de confirmation *« Confirmer mon arrivée à HHhMM »*).
   - Heure enregistrée **exclusivement côté serveur** (`UTC_TIMESTAMP()`).
   - **Vérification de présence configurable** dans Réglages → Pointage (`gps`, `qr`, `both`, `none` ; rayon GPS par défaut `150 m`) :
     - **GPS** : demandé uniquement au moment du pointage, calcul de distance Haversine côté serveur ; si hors rayon ou refusé → pointage enregistré en statut `pending` (*« Hors du domicile (320 m) »* ou *« Position refusée »*), **jamais bloquant**.
     - **QR code du domicile** : jeton signé HMAC, imprimable en A4 depuis Réglages → Pointage, révocable/régénérable en 1 clic ; scanné via `BarcodeDetector` ou `jsQR`.
   - **Oubli de pointage** : lien *« J’ai oublié de pointer »* avec saisie de l’heure réelle (`declared_at`) et motif obligatoire → statut `pending` + alerte au responsable.
   - **Départ** : proposition immédiate *« Rédiger la transmission de fin de poste »*.
   - **Option « Saisie autorisée uniquement si pointée »** (`require_clocked_in`, désactivée par défaut) : lorsqu’elle est activée, toute tentative de saisie médicale par une aide-soignante non en poste retourne `HTTP 403` avec le message explicite *« Pointez votre arrivée pour saisir »*.
   - **Notice RGPD** : affichée à la première utilisation du pointage et enregistrée dans `users` + `audit_logs`.
2. **Contrôle des pointages (E17 — `admin`, `coordinator`)** :
   - Grille hebdomadaire (jours × aides-soignantes) avec statuts visuels + texte : *À l’heure* (≤ 10 min), *Retard de N min* (> 10 min), *Départ anticipé*, *À valider*, *Non pointé*, *Prévu*, et total d’heures du mois en en-tête.
   - Liste **« À valider »** : validation ou refus motivé tracé dans `audit_logs`.
3. **Récapitulatif mensuel des heures & Export CSV de paie (E18)** :
   - Par aide-soignante sur le mois choisi : heures planifiées, heures pointées (calculées sur les pointages `valid`), **heures de nuit (21h00–06h00)**, **heures du dimanche**, **heures des jours fériés français** (fixes + mobiles calculés via la date de Pâques : lundi de Pâques, Ascension, lundi de Pentecôte), écart.
   - Découpage exact minute par minute des gardes à cheval sur minuit, sur deux jours (ex. samedi 20h → dimanche 08h), sur un jour férié et lors des changements d’heure d’été / d’hiver (`Europe/Paris`).
   - Export CSV (`;`, UTF-8 avec BOM `\xEF\xBB\xBF`, une ligne par personne et par jour + ligne de total) prêt pour le logiciel de paie ou l’URSSAF / CESU.

### Étape 4 — Alertes temps réel, Web Push VAPID natif, SMTP PHPMailer & Cron (`AlertService`, `WebPushService`, `NotificationService`, `cron/run.php`)
1. **Moteur d’alertes (`AlertService`) & 7 règles de déclenchement** :
   - Valeur critique (`crit_low` / `crit_high`) → `critical` immédiate (`admin` + `coordinator`).
   - Hors cible non critique (`low` / `high`) → `warning` immédiate (`admin` + `coordinator`).
   - Prise en retard > 30 min → `warning` (aide-soignante en poste + `admin`), escalade à `admin` après 15 min supplémentaires.
   - Mesure attendue en retard > 30 min → `warning` (aide-soignante en poste).
   - Pointage d’arrivée absent > 15 min après le début du créneau → `critical` (`admin` + `coordinator` + aide-soignante prévue).
   - Créneau non couvert à moins de 48 h → `warning` (`admin` + `coordinator`).
   - Oubli de pointage ou pointage hors zone à valider → `info` (`admin` + `coordinator`).
   - Déduplication par `dedupe_key` tant que l’alerte est ouverte, escalade à 15 min (`escalated_at`), **clôture automatique** (*« résolue automatiquement »*) dès que l’événement attendu survient, et unification de `AttentionService` sur les alertes ouvertes.
2. **Centre d’alertes (`/alerts`, `AlertController`)** :
   - Icône cloche dans l’en-tête avec pastille rouge du nombre d’alertes ouvertes (`new` + `seen`).
   - Tri par gravité (`critical` > `warning` > `info`) puis date décroissante, filtre ouvertes / traitées, passage automatique `new` → `seen` à l’ouverture, action « Traiter » (choix rapides + texte libre), lien direct vers la source.
3. **Préférences & Heures calmes (`/profile`)** :
   - Activation Push et E-mail par type d’alerte, plage d’heures calmes (ex. `22h00–07h00`) systématiquement contournée par les alertes `critical`.
4. **Web Push VAPID natif (`bin/make-vapid.php`, `app/Services/WebPushService.php`, `public/sw.js`)** :
   - Génération de la paire de clés ECDSA `prime256v1` dans `storage/keys/vapid.json` (hors Git).
   - Jeton JWT VAPID signé `ES256` (RFC 8292, conversion signature OpenSSL ASN.1 DER → format brut `r || s` de 64 octets).
   - Chiffrement de la charge utile selon **RFC 8291 (`aes128gcm`)** en PHP natif (`openssl_pkey_derive` ECDH + HKDF-SHA-256 + `aes-128-gcm` avec tag de 16 octets).
   - Envoi HTTPS cURL avec en-têtes `TTL` et `Urgency`, gestion des codes `201`, `404`/`410` (suppression de l’abonnement expiré), `429`/`5xx` (reprogrammation dans `notification_queue`).
   - Réception `push` et `notificationclick` dans `public/sw.js` avec `badge-96.png` et `tag = dedupe_key`.
   - Bouton **« Envoyer une notification de test »** dans Mon profil.
5. **E-mail HTML PHPMailer & File `notification_queue` & `cron/run.php`** :
   - Gabarit HTML sobre avec en-tête `#0A1633` et logo complet PNG (`prime-care-logo-complet.png`), doublé dans `storage/logs/mail.log` en développement.
   - File `notification_queue` avec backoff exponentiel (jusqu’à 5 tentatives).
   - `SmsChannel` (`app/Services/Channels/SmsChannel.php`) préparé et désactivé par défaut.
   - `cron/run.php` exécutant les 5 tâches (`schedule:generate`, `alerts:scan`, `notifications:send`, `alerts:daily`, `cleanup`) sous verrou `flock()`, plus déclenchement de secours debouncé à 5 min.

### Étape 5 — Mode hors ligne complet (`public/assets/js/modules/offline-queue.js`, `public/sw.js`, `ApiMedicalController`)
1. **File d’attente IndexedDB (`offline-queue.js`)** :
   - Enregistrement hors ligne des saisies : mesure, prise « Donné » / « Non donné », prise « si besoin », repas sans photo, note et soin rapide.
   - Chaque saisie embarque un `client_uuid` (`crypto.randomUUID()`) et son horodatage réel de saisie.
   - **Idempotence serveur** : si `client_uuid` existe déjà en base, le serveur retourne `HTTP 200` avec l’enregistrement existant sans créer de doublon.
   - **Synchronisation automatique** au retour du réseau (`online`), à l’ouverture de l’application et via Background Sync (`sw.js`), avec badge visible *« N saisie(s) en attente »*, bouton « Synchroniser maintenant », toast récapitulatif et gestion propre des conflits `409`.
2. **Cache de consultation hors ligne (`sw.js`)** :
   - Ressources statiques en *cache-first* ; pages HTML déjà visitées en *network-first* avec repli cache et bandeau *« Hors ligne — données du … »*.
   - **Aucune réponse JSON d’API médicale n’est mise en cache par le Service Worker**, et le cache de pages est intégralement purgé à la déconnexion (`POST /logout`).

### Étape 6 — Tests automatisés, Documentation, Captures 390×844 & Rapport `RETOUR_PHASE_3.md`
1. Extension de `php tests/run.php` avec les tests unitaires et d’intégration de la Phase 3 (planning, pointage GPS Haversine / QR HMAC / `require_clocked_in`, calculateur d’heures nuit/dimanche/fériés/changement d’heure + CSV BOM, alertes 7 règles / déduplication / escalade / clôture auto / heures calmes, Web Push VAPID JWT ES256 + RFC 8291 `aes128gcm`, idempotence `client_uuid`, smoke test de toutes les routes GET sur les 4 rôles avec `0 réponse 500`), tout en conservant les **142 tests des Phases 1 et 2 au vert**.
2. Mise à jour de `README.md`, `docs/DESIGN_SYSTEM.md` et `/design`, génération de toutes les captures `390×844` Clair & Sombre dans `docs/retours/captures/phase-3/`, rédaction de `docs/retours/RETOUR_PHASE_3.md` (12 sections), commit et `git push origin arena/0f0ecf73-prime-care`.
