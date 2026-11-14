# Rapport de Livraison & Corrections — Phase 1 : Socle, Authentification, Équipe, Patient & Design System (« Proposition B — Bleu nuit »)

> **Projet** : **Prime Care** — *Suivi de soins à domicile*  
> **Éditeur** : [Prime Vision Solutions](https://primevisionsolutions.fr)  
> **Phase** : Phase 1 / 4 (Socle MVC, Authentification Mot de passe + PIN par Appareils de confiance, RBAC 4 rôles, Fiche Patient & Urgences, Gestion de l’équipe, Design System « Proposition B — Bleu nuit », PWA & Loader animé)  
> **Branche Git** : `arena/e3966115-prime-care`  
> **Date** : 14 novembre 2026

---

## 1. Résumé

La **Phase 1** de **Prime Care** ainsi que l’intégralité des **7 corrections de revue (C1 à C7)** sont livrées, testées et vérifiées sur la branche `arena/e3966115-prime-care` :

- **C1 (Critique — Appareils de confiance pour le code PIN)** : migration `002_trusted_devices.sql` créant la table `trusted_devices` (`selector` unique + `validator_hash` SHA-256). Activation explicite depuis `/profile` après connexion par mot de passe (cookie `HttpOnly`, `SameSite=Strict`, 90 jours, support multi-profils sur téléphone partagé). `/login` et `/login/pin` n’affichent que les profils disposant d’un jeton valide sur le navigateur ; `POST /login/pin` et `POST /api/auth/pin` reçoivent uniquement le `selector` (jamais `user_id`), vérifient le validateur par `hash_equals()` puis le PIN avec blocage 15 min après 5 erreurs et révocation complète.
- **C2 (Rate limit IP sur Wi-Fi partagé)** : maintien de la limite de **5 échecs / 15 min par compte** et relèvement du seuil **par adresse IP à 30 échecs / 15 min** (configurable via `security.ip_max_attempts`).
- **C3 (Additif Design « Proposition B — Bleu nuit »)** : intégration de `docs/ADDENDUM_DESIGN_B.md`, refonte des tokens (`--header: #0A1633`, dégradé `#5E5CE6 → #BF5AF2`), cartes à rayon `24px` sans ombre portée (`box-shadow: none`), en-tête Bleu nuit avec halos lumineux sur l’accueil, barre d’onglets flottante avec bouton central `+` en dégradé, loader Bleu nuit avec barre de progression et signature *« by Prime Vision Solutions »*, écran de connexion en deux parties (*« Qui êtes-vous ? »* + clavier PIN) et mise à jour de `/design` + `docs/DESIGN_SYSTEM.md`.
- **C4 (Suite de tests isolée & code de sortie CLI)** : `tests/run.php` force `app.env = testing`, crée/vide la base dédiée `prime_care_test` et exécute les migrations (`--fresh`) + seed avant de lancer les 7 suites (**69 assertions réussies, 0 échec**). En CLI (`PHP_SAPI === 'cli'`), `ErrorHandler` écrit en texte brut sur `STDERR` (jamais d’HTML) et termine avec `exit(1)`.
- **C5 (CSP sans `unsafe-inline` & protection d’encadrement)** : suppression de `style-src-attr 'unsafe-inline'` dans `SecurityHeadersMiddleware`, remplacement de tous les styles en ligne par des classes CSS et une palette fixe de 8 classes d’avatars (`.pc-avatar--c1` à `.pc-avatar--c8`) calculée côté serveur (`avatar_color_class()`), et envoi systématique de `X-Frame-Options: DENY` et `frame-ancestors 'none'` dans tous les environnements.
- **C6 (Corrections d’affichage 320, 375 et 390 px)** : badge de rôle sous le nom et e-mail tronqué avec `text-overflow: ellipsis` sur `/users` ; nom complet non tronqué et badge « Prioritaire » sous le nom sans chevauchement sur `/patient` ; logo complet unique sur `/about` ; libellé `#5E5CE6 → #BF5AF2` sur `/design` ; 0 débordement horizontal vérifié à 320, 375 et 390 px.
- **C7 (Gabarit strict du rapport)** : présent document structuré selon les 12 sections exigées.

---

## 2. Branche et liste des commits avec leur hash

- **Branche** : `arena/e3966115-prime-care` (créée depuis `main` `e7e5b999ece96bf256c4024c8ee6fd61291cf1aa`)

| Ordre | Hash court | Message de commit | Périmètre |
|---|---|---|---|
| 1 | `6661f81` | `chore(setup): initialize project structure, brand assets, and self-hosted vendors` | Socle Phase 1 |
| 2 | `897eb03` | `feat(core): implement MVC micro-framework, security middlewares, and RBAC policy` | Socle Phase 1 |
| 3 | `0f4df47` | `feat(db): add Phase 1 schema migration, demo seeder, and active-record models` | Socle Phase 1 |
| 4 | `53c5f60` | `feat(design): implement Apple Health iOS design system, PC launch loader, and PWA` | Socle Phase 1 |
| 5 | `0de7094` | `feat(phase1): add auth, PIN pad, app shell, profile, team CRUD, and patient emergency view` | Socle Phase 1 |
| 6 | `d45f9de` | `test(phase1): add unit/integration test suite, mobile screenshots, and Phase 1 report` | Socle Phase 1 |
| 7 | `7941cc4` | `docs(phase1): record exact branch name and completion footer in RETOUR_PHASE_1.md` | Socle Phase 1 |
| 8 | `35ea73e` | `fix(auth): C1 trusted devices for PIN login` | **Correction C1** |
| 9 | `a98991f` | `fix(security): C2 IP rate limit 30 attempts per 15 min on shared Wi-Fi` | **Correction C2** |
| 10 | `42b5cd6` | `feat(design): C3 apply Proposition B Bleu nuit direction` | **Correction C3** |
| 11 | `b781b02` | `fix(tests): C4 isolate test database and CLI plain-text error exit code` | **Correction C4** |
| 12 | `4cf6499` | `fix(security): C5 CSP without unsafe-inline, 8 avatar classes and frame-ancestors none` | **Correction C5** |
| 13 | `1077820` | `fix(ui): C6 responsive layout fixes on users, patient, about and design screens` | **Correction C6** |
| 14 | *(commit courant)* | `docs(retour): C7 update RETOUR_PHASE_1.md with strict 12-section template and C1-C7 table` | **Correction C7** |

---

## 3. Definition of Done de `01_PHASE_1.md` (recopiée en entier et cochée) + §9 de l’Additif B

### 3.1 Definition of Done complète de `01_PHASE_1.md`

- [x] **Architecture & Socle technique (`app/Core/`, `public/index.php`)** :
  - [x] Front controller unique `public/index.php` avec chargement de `app/Helpers/bootstrap.php` et `app/Helpers/functions.php`.
  - [x] 16 classes `App\Core\*` opérationnelles avec `declare(strict_types=1);` : `Config`, `Logger`, `Lang`, `Database`, `Model`, `Session`, `Csrf`, `Request`, `Response`, `Router`, `View`, `Validator`, `RateLimiter`, `Auth`, `Uploader`, `ErrorHandler`.
  - [x] `php -l` passe avec **0 erreur** sur 100 % des fichiers PHP du dépôt.
  - [x] **Zéro CDN externe** : polices `Inter Variable` (`woff2`) et icônes SVG `Lucide` 100 % auto-hébergées dans `public/assets/vendor/`.
- [x] **Base de données, Migrations & Seed (`database/`, `bin/migrate.php`)** :
  - [x] `php bin/migrate.php --fresh --seed` s’exécute sans erreur et applique `001_phase1_foundation.sql` + `002_trusted_devices.sql`.
  - [x] Encodage `utf8mb4` / `utf8mb4_unicode_ci`, moteur `InnoDB`, clés étrangères explicites, horodatages en UTC.
  - [x] 5 comptes de démonstration créés (Karim Benali `admin`, Sofia Marchetti `coordinator`, Fatou Diallo `caregiver`, Marie Lefèvre `caregiver`, Dr Laurent Petit `viewer`).
  - [x] Fiche patient unique de M. Henri Benali (84 ans, diabète de type 2 insulinotraité) et ses 4 contacts d’urgence pré-remplis.
- [x] **Authentification, Appareils de confiance PIN & Sécurité (`Auth`, `CsrfMiddleware`, `RateLimiter`, `SecurityHeadersMiddleware`)** :
  - [x] Connexion par e-mail + mot de passe haché avec `PASSWORD_ARGON2ID` (fallback `PASSWORD_BCRYPT`) et régénération d’ID de session (`session_regenerate_id(true)`).
  - [x] Connexion rapide par code PIN (4 à 6 chiffres) **uniquement sur appareil de confiance** (`trusted_devices` : `selector` + `validator_hash` SHA-256, cookie `HttpOnly`, `SameSite=Strict`, 90 jours, activé depuis `/profile` après connexion par mot de passe).
  - [x] Protection anti-brute-force : blocage 15 min après **5 échecs par compte** (ou par profil PIN) et **30 échecs par IP** (Wi-Fi partagé).
  - [x] Jeton CSRF obligatoire sur 100 % des requêtes `POST`/`PUT`/`PATCH`/`DELETE` (erreur HTTP `419` en cas d’absence ou d’invalidité).
  - [x] En-têtes de sécurité complets : CSP avec nonce par requête **sans `unsafe-inline`**, `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`.
- [x] **Contrôle d’accès RBAC (`Policy`, `RoleMiddleware`)** :
  - [x] Matrice RBAC stricte à 4 rôles (`admin`, `coordinator`, `caregiver`, `viewer`).
  - [x] Un non-admin recevant une requête sur `/users`, `/design` ou `POST /patient` reçoit une réponse HTTP `403`.
  - [x] Le taux horaire (`hourly_rate`) n’est visible et modifiable que par le rôle `admin`.
- [x] **Écrans fonctionnels de la Phase 1** :
  - [x] Écran d’accueil (`/`) avec bandeau Bleu nuit, carte patient M. Henri Benali, raccourcis vers la fiche patient/urgences et aperçu des modules Phase 2/3.
  - [x] Fiche patient & Urgences (`/patient`) avec carte rouge prioritaire du protocole d’hypoglycémie (`< 0,70 g/L`), boutons d’appel direct SAMU (`15`), Urgences (`112`), contacts d’urgence (`tel:`, `sms:`, `mailto:`) et édition modale réservée à l’admin.
  - [x] Gestion de l’équipe (`/users`, `/users/create`, `/users/{id}`, `/users/{id}/edit`) avec recherche instantanée, création/édition de compte, attribution de PIN initial et activation/désactivation (avec révocation immédiate des jetons).
  - [x] Profil utilisateur (`/profile`) avec modification des coordonnées, upload d’avatar sécurisé (validation MIME `finfo`), changement de mot de passe, changement de PIN, activation/révocation du PIN sur l’appareil courant, sélecteur de thème (`Auto` / `Clair` / `Sombre`) en temps réel et révocation des autres appareils.
  - [x] Menu Plus (`/more`), page À propos (`/about`), écrans « Bientôt disponible » (`/today`, `/health`, `/journal`, `/settings`, `/coming-soon/{module}`) — zéro lien mort.
  - [x] Pages d’erreur personnalisées en français (`403`, `404`, `419`, `429`, `500`).
- [x] **Identité de marque, Loader, PWA & Crédit éditeur** :
  - [x] Les 7 fichiers SVG officiels dans `public/assets/brand/` sont strictement intacts.
  - [x] Loader animé de lancement avec monogramme `PC`, barre de progression et mention *« by Prime Vision Solutions »*.
  - [x] Crédit discret *« Application créée par Prime Vision Solutions »* présent dans le pied de page, l’écran de connexion et la page `/about`.
  - [x] Manifeste PWA (`manifest.webmanifest`) et Service Worker (`sw.js`) fonctionnels.

### 3.2 Checklist point par point du §9 de `docs/ADDENDUM_DESIGN_B.md` (« Proposition B — Bleu nuit »)

- [x] **§9.1 — Tokens de couleurs « Bleu nuit » (`public/assets/css/tokens.css`)** : `--bg` (`#F4F6FB` clair / `#070D1F` sombre), `--surface-1` (`#FFFFFF` / `#0F172A`), `--header` (`#0A1633` / `#060C1D`), `--brand` (`#5E5CE6` / `#7D7AFF`), `--brand-purple` (`#BF5AF2`) et `--brand-gradient` (`linear-gradient(135deg, #5E5CE6 0%, #BF5AF2 100%)`).
- [x] **§9.2 — Cartes à rayon 24 px sans ombre portée (`public/assets/css/components.css`)** : `--r-card: 24px`, `border-radius: 24px`, `box-shadow: none` et bordure `1px solid var(--border)` sur `.pc-card`, `.pc-list-group`, `.pc-empty-state` et `.pc-hypo-card`.
- [x] **§9.3 — En-tête Bleu nuit avec halos lumineux sur l’accueil (`app/Views/home/index.php`, `public/assets/css/app.css`)** : bloc `.pc-home-hero` en fond Bleu nuit (`var(--header)`) avec halos radial-gradient indigo/violet et bandeau patient vitré `.pc-home-patient`.
- [x] **§9.4 — Barre d’onglets flottante avec bouton central `+` en dégradé (`app/Views/partials/tabbar.php`, `public/assets/css/components.css`)** : `.pc-tabbar` flottante (`border-radius: 24px`, `left/right: 12px`, `bottom: calc(10px + var(--safe-bottom))`) et bouton `.pc-tabbar__plus-btn` en dégradé `#5E5CE6 → #BF5AF2`.
- [x] **§9.5 — Loader de lancement Bleu nuit avec barre de progression (`app/Views/partials/loader.php`, `public/assets/css/components.css`)** : fond `#0A1633` avec halos, monogramme `PC` animé, logotype `PRIME CARE`, barre de progression horizontale `.pc-loader__progress-bar` en dégradé `#5E5CE6 → #BF5AF2` et signature *« by Prime Vision Solutions »*.
- [x] **§9.6 — Écran de connexion en deux parties « Qui êtes-vous ? » + clavier PIN (`app/Views/auth/login.php`, `app/Views/auth/pin.php`, `public/assets/js/modules/pin-pad.js`)** : fond Bleu nuit `#0A1633` avec halos, grille de sélection de profils `.pc-who-grid` alimentée exclusivement par les jetons `trusted_devices` valides (C1) et clavier PIN soumettant le `selector`.
- [x] **§9.7 — Page `/design` et `docs/DESIGN_SYSTEM.md` mis à jour** : vitrine `/design` et documentation alignées sur la Proposition B — Bleu nuit, le dégradé `#5E5CE6 → #BF5AF2` et les 8 classes d’avatars `.pc-avatar--c1` à `--c8`.

---

## 4. Fichiers créés ou modifiés

### Fichiers créés ou modifiés lors des corrections C1 à C7

- **Migrations & Configuration** :
  - `database/migrations/002_trusted_devices.sql` *(créé — C1)* : table `trusted_devices` avec clé étrangère `user_id` et index unique `selector`.
  - `config/config.example.php` *(modifié — C1, C2)* : ajout de `security.ip_max_attempts = 30` et `security.trusted_device_days = 90`.
  - `lang/fr.php` *(modifié — C1, C3)* : ajout de `app.tagline` et des chaînes de gestion des appareils de confiance PIN.
  - `routes/web.php` *(modifié — C1)* : ajout des routes `POST /profile/trust-device` et `POST /profile/trust-device/revoke`.
- **Noyau & Middlewares (`app/Core/`, `app/Middleware/`, `app/Helpers/`)** :
  - `app/Core/Auth.php` *(modifié — C1)* : implémentation complète des appareils de confiance (`issueTrustedDevice`, `trustedDeviceProfilesFromCookie`, `attemptTrustedPin`, `revokeTrustedDeviceSelector`, `revokeAllDevicesForUser`, `authenticatedViaPassword`) et suivi de l’origine d’authentification (`auth_via`).
  - `app/Core/RateLimiter.php` *(modifié — C2)* : séparation des seuils par compte (`max_attempts = 5`) et par IP (`ip_max_attempts = 30`).
  - `app/Core/ErrorHandler.php` *(modifié — C4)* : sortie texte brut sur `STDERR` et `exit(1)` en mode CLI (`PHP_SAPI === 'cli'`).
  - `app/Core/Session.php` & `app/Core/View.php` *(modifiés — C5)* : ajout de `Session::getFlash()`, `View::hasSection()` et normalisation de `View::partial()`.
  - `app/Helpers/functions.php` *(modifié — C5)* : ajout de `avatar_color_class(int $userId)`, `has_error()`, `field_error()`, `can()`, et support polymorphe de `icon()` et `brand_svg()`.
  - `app/Middleware/SecurityHeadersMiddleware.php` *(modifié — C5)* : retrait de `style-src-attr 'unsafe-inline'` et envoi systématique de `X-Frame-Options: DENY` et `frame-ancestors 'none'` dans tous les environnements.
- **Contrôleurs (`app/Controllers/`)** :
  - `app/Controllers/AuthController.php` *(modifié — C1)* : affichage conditionnel des profils de confiance sur `/login` et `/login/pin`, validation du `selector` sur `POST /login/pin` et `POST /api/auth/pin`, blocage 15 min après 5 erreurs.
  - `app/Controllers/ProfileController.php` *(modifié — C1)* : actions `trustDevice()`, `revokeTrustedDevice()` et révocation globale dans `revokeDevices()`.
  - `app/Controllers/UserController.php` *(modifié — C1)* : révocation de tous les jetons `trusted_devices` et `remember_tokens` lors de la désactivation d’un compte par l’admin.
- **Vues & Assets CSS/JS (`app/Views/`, `public/assets/`)** :
  - `public/assets/css/tokens.css`, `components.css`, `utilities.css`, `app.css` *(modifiés — C3, C5, C6)* : tokens Bleu nuit, cartes 24 px sans ombre, barre d’onglets flottante, loader avec barre de progression, palette `.pc-avatar--c1` à `--c8`, zéro style en ligne et correctifs responsives 320–390 px.
  - `public/assets/js/modules/pin-pad.js` & `loader.js` *(modifiés — C1, C3)* : sélection de profil « Qui êtes-vous ? » et soumission du `selector`.
  - `app/Views/auth/login.php`, `auth/pin.php`, `auth/reset.php`, `partials/loader.php`, `partials/navbar.php`, `partials/quick-add-sheet.php`, `partials/install-sheet.php`, `layouts/error.php`, `layouts/print.php`, `errors/403.php`..`500.php`, `home/index.php`, `home/more.php`, `home/about.php`, `home/coming-soon.php`, `profile/index.php`, `users/index.php`, `users/show.php`, `users/form.php`, `patient/show.php`, `design/index.php` *(modifiés — C1, C3, C5, C6)*.
- **Tests & Documentation (`tests/`, `docs/`)** :
  - `tests/run.php`, `tests/Unit/AuthTest.php`, `tests/Unit/RateLimiterTest.php`, `tests/Unit/HelperEscapeTest.php` *(modifiés — C1, C2, C4, C5)*.
  - `docs/ADDENDUM_DESIGN_B.md` *(créé — C3)*, `docs/DESIGN_SYSTEM.md` *(modifié — C3)*, `docs/retours/RETOUR_PHASE_1.md` *(modifié — C7)* et `docs/retours/captures/phase-1/*.png` *(19 captures régénérées — C3, C6, C7)*.

---

## 5. Base de données

Le schéma Phase 1 comporte **8 tables** créées par `001_phase1_foundation.sql` et `002_trusted_devices.sql` :

| Table | Migration | Rôle & Colonnes principales |
|---|---|---|
| `migrations` | `001_phase1_foundation.sql` | Suivi des migrations exécutées (`id`, `migration`, `executed_at`). |
| `users` | `001_phase1_foundation.sql` | Comptes utilisateurs (`id`, `first_name`, `last_name`, `email` unique, `phone`, `role` ENUM 4 rôles, `password_hash` Argon2id, `pin_hash`, `avatar_path`, `color_hex`, `theme`, `contract_type`, `weekly_hours`, `hourly_rate`, `hired_at`, `emergency_contact_*`, `is_active`, timestamps UTC). |
| `trusted_devices` | `002_trusted_devices.sql` | **Appareils de confiance pour connexion PIN (C1)** (`id`, `user_id` FK `ON DELETE CASCADE`, `selector` unique, `validator_hash` SHA-256, `device_label`, `created_at`, `last_used_at`, `expires_at`, `revoked_at`). |
| `remember_tokens` | `001_phase1_foundation.sql` | Jetons « Rester connecté » (`id`, `user_id` FK, `selector` unique, `validator_hash`, `user_agent`, `ip_address`, `expires_at`, `created_at`). |
| `password_resets` | `001_phase1_foundation.sql` | Jetons de réinitialisation de mot de passe (`id`, `email`, `token_hash`, `expires_at`, `used_at`, `created_at`). |
| `login_attempts` | `001_phase1_foundation.sql` | Journal anti-brute-force (`id`, `identifier`, `ip_address`, `attempted_at`, `succeeded`). |
| `patients` | `001_phase1_foundation.sql` | Fiche patient unique de M. Henri Benali (`id`, `first_name`, `last_name`, `birth_date`, `ssn_masked`, `address`, `access_code_notes`, `blood_group`, `diabetes_type`, `target_glycemia_*`, `hypo_threshold_g_l`, `hyper_threshold_g_l`, `allergies`, `medical_history`, `permanent_instructions`, `hypo_protocol`). |
| `patient_contacts` | `001_phase1_foundation.sql` | Contacts d’urgence et intervenants (`id`, `patient_id` FK, `full_name`, `relation_role`, `phone`, `email`, `is_emergency_priority`, `sort_order`, `notes`). |
| `settings` | `001_phase1_foundation.sql` | Paramètres applicatifs clé/valeur typés (`setting_key`, `setting_value`, `value_type`). |
| `audit_logs` | `001_phase1_foundation.sql` | Journal d’audit immuable (`id`, `user_id` FK, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`). |

---

## 6. Comment tester avec comptes de test

### 6.1 Commandes d’installation et de test

```bash
# 1. Appliquer les migrations (001 + 002) et injecter le jeu de données de démonstration
php bin/migrate.php --fresh --seed

# 2. Exécuter la suite de tests automatisés (isolée sur prime_care_test)
php tests/run.php

# 3. Lancer le serveur de développement local
php -S 0.0.0.0:8080 -t public public/index.php
```

### 6.2 Comptes de test pré-configurés (Seed)

| Prénom & Nom | Rôle (`role`) | E-mail | Mot de passe | Code PIN (après activation sur appareil) |
|---|---|---|---|---|
| **Karim Benali** | `admin` (Responsable) | `admin@primecare.test` | `Admin!2026` | `1111` |
| **Sofia Marchetti** | `coordinator` (Référente) | `coord@primecare.test` | `Coord!2026` | `2222` |
| **Fatou Diallo** | `caregiver` (Aide-soignante) | `fatou@primecare.test` | `Soin!2026` | `3333` |
| **Marie Lefèvre** | `caregiver` (Aide-soignante) | `marie@primecare.test` | `Soin!2026` | `4444` |
| **Dr Laurent Petit** | `viewer` (Consultation) | `medecin@primecare.test` | `Medecin!2026` | `5555` |

### 6.3 Parcours de vérification du PIN par Appareil de confiance (C1)

1. Ouvrir `/login` dans un navigateur neuf : seul le formulaire **e-mail + mot de passe** s’affiche (aucun profil par défaut, aucun paramètre `?user=`).
2. Se connecter avec `fatou@primecare.test` / `Soin!2026`.
3. Aller dans **Plus → Mon profil** (`/profile`), section *« Code PIN & Appareil de confiance »*, puis cliquer sur **« Activer la connexion par PIN sur cet appareil »**.
4. Se déconnecter : `/login` affiche désormais la grille **« Qui êtes-vous ? »** avec le profil de Fatou et le clavier numérique PIN. Saisir `3333` connecte immédiatement Fatou.

---

## 7. Tableau de la règle des 3 clics

Toutes les actions critiques de la Phase 1 respectent strictement le plafond de **3 taps maximum** depuis l’écran d’accueil (`/`) :

| Action métier | Parcours utilisateur depuis l’accueil (`/`) | Nombre de taps | Cible max | Statut |
|---|---|---|---|---|
| **Consulter le protocole d’hypoglycémie (< 0,70 g/L)** | Tap sur la carte rouge *« Urgences & Protocole Hypo »* (ou bandeau patient) | **1 tap** | ≤ 3 taps | Conforme |
| **Appeler le SAMU (15) ou le 112** | 1. Tap *« Urgences & Protocole Hypo »* → 2. Tap bouton rouge *« Appeler le SAMU (15) »* | **2 taps** | ≤ 2 taps | Conforme |
| **Appeler un contact d’urgence (Karim Benali / Médecin)** | 1. Tap *« Urgences & Protocole Hypo »* → 2. Tap icône téléphone verte du contact | **2 taps** | ≤ 3 taps | Conforme |
| **Ouvrir la feuille d’ajout rapide (+)** | Tap sur le bouton central `+` en dégradé de la barre d’onglets flottante | **1 tap** | ≤ 3 taps | Conforme |
| **Changer le thème (Auto / Clair / Sombre)** | 1. Tap onglet *« Plus »* → 2. Tap segment *« Sombre »* ou *« Clair »* | **2 taps** | ≤ 3 taps | Conforme |
| **Activer le PIN sur cet appareil** | 1. Tap avatar en haut à droite (`/profile`) → 2. Tap *« Activer la connexion par PIN sur cet appareil »* | **2 taps** | ≤ 3 taps | Conforme |
| **Créer un membre de l’équipe (Admin)** | 1. Tap onglet *« Équipe »* → 2. Tap bouton *« + Ajouter »* | **2 taps** | ≤ 3 taps | Conforme |

---

## 8. Résultats qualité avec sortie de `php tests/run.php` collée

- **Validation syntaxique PHP (`php -l`)** : `0 erreur` sur l’ensemble des fichiers PHP (`app/`, `bin/`, `config/`, `database/`, `lang/`, `public/`, `routes/`, `tests/`).
- **Typage strict** : `declare(strict_types=1);` présent en tête de chaque fichier PHP.
- **Zéro style en ligne (`grep -rn 'style=' app/Views/ public/assets/js/`)** : `0 occurrence`.
- **Erreurs console / CSP en navigateur headless Chromium** : `0 erreur`.

### Sortie complète de `php tests/run.php`

```text
=====================================================
 Prime Care — Suite de tests automatisés (Phase 1)
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

-----------------------------------------------------
 Résultat : 69 réussis, 0 échoués
-----------------------------------------------------
```

---

## 9. Captures refaites en clair et sombre (`docs/retours/captures/phase-1/`)

Les 19 captures mobiles (`390×844` `@2x`, et vérifiées sans débordement à `320px`, `375px` et `390px`) ont été régénérées avec la direction artistique **Proposition B — Bleu nuit** :

| N° | Fichier | Description |
|---|---|---|
| 01 | `docs/retours/captures/phase-1/01-loader-launch.png` | **Loader Bleu nuit une fois l’animation terminée** : monogramme `PC`, `PRIME CARE`, barre de progression en dégradé `#5E5CE6 → #BF5AF2` à 100 % et mention **« by Prime Vision Solutions »** visible. |
| 02 | `docs/retours/captures/phase-1/02-login-light.png` | Connexion e-mail + mot de passe (navigateur sans jeton d’appareil, thème Clair). |
| 03 | `docs/retours/captures/phase-1/03-login-dark.png` | Connexion e-mail + mot de passe (thème Sombre). |
| 04 | `docs/retours/captures/phase-1/04-pin-pad-light.png` | **Connexion en deux parties « Qui êtes-vous ? » + Clavier PIN** (profils de confiance Sofia & Fatou activés via C1, thème Clair). |
| 05 | `docs/retours/captures/phase-1/05-pin-pad-dark.png` | Connexion en deux parties « Qui êtes-vous ? » + Clavier PIN (thème Sombre). |
| 06 | `docs/retours/captures/phase-1/06-home-caregiver-light.png` | Accueil Aide-soignante (`Fatou`) : en-tête Bleu nuit avec halos, cartes 24 px sans ombre, tabbar flottante avec `+` en dégradé (Clair). |
| 07 | `docs/retours/captures/phase-1/07-home-caregiver-dark.png` | Accueil Aide-soignante (`Fatou`, Sombre). |
| 08 | `docs/retours/captures/phase-1/08-quick-add-sheet.png` | Feuille modale d’action rapide (`+`). |
| 09 | `docs/retours/captures/phase-1/09-patient-light.png` | **Fiche patient & Urgences (`/patient`, Clair)** : protocole d’hypoglycémie + contacts d’urgence avec nom complet `Karim Benali` non tronqué et badge `Prioritaire` sans collision (C6.2). |
| 10 | `docs/retours/captures/phase-1/10-patient-dark.png` | Fiche patient & Urgences (`/patient`, Sombre). |
| 11 | `docs/retours/captures/phase-1/11-profile-light.png` | Mon profil (`/profile`, Clair) avec section *« Code PIN & Appareil de confiance »* active. |
| 12 | `docs/retours/captures/phase-1/12-profile-dark.png` | Mon profil (`/profile`, Sombre). |
| 13 | `docs/retours/captures/phase-1/13-team-admin-light.png` | **Liste de l’équipe (`/users`, Clair)** : e-mail tronqué proprement et badge de rôle sous le nom sans chevauchement (C6.1). |
| 14 | `docs/retours/captures/phase-1/14-team-admin-dark.png` | Liste de l’équipe (`/users`, Sombre). |
| 15 | `docs/retours/captures/phase-1/15-team-member-detail.png` | Détail d’un membre de l’équipe (`/users/3` — Fatou Diallo). |
| 16 | `docs/retours/captures/phase-1/16-more-menu-light.png` | Menu Plus (`/more`, Clair). |
| 17 | `docs/retours/captures/phase-1/17-about-page.png` | **Page À propos (`/about`)** : uniquement le logo complet, sans doublon monogramme (C6.3). |
| 18 | `docs/retours/captures/phase-1/18-design-system-light.png` | **Design System (`/design`, Clair)** : Proposition B — Bleu nuit, dégradé libellé `#5E5CE6 → #BF5AF2` (C6.4) et avatars `.pc-avatar--c1` à `--c8` (C5). |
| 19 | `docs/retours/captures/phase-1/19-design-system-dark.png` | Design System (`/design`, Sombre). |

---

## 10. Décisions et écarts

1. **Multi-profils sur téléphone du domicile (C1)** : le cookie `prime_care_trusted_devices` (`HttpOnly`, `SameSite=Strict`, 90 jours) stocke jusqu’à 8 paires `selector:validator` séparées par `,`. Ainsi, plusieurs aides-soignantes utilisant le téléphone partagé du domicile peuvent chacune activer leur PIN après une première connexion par mot de passe et basculer d’un tap dans la grille *« Qui êtes-vous ? »* sans jamais exposer d’ID utilisateur ni permettre l’usurpation d’un compte non autorisé sur l’appareil.
2. **Séparation stricte de l’origine d’authentification (`auth_via`)** : une session ouverte par code PIN (`auth_via = 'pin'`) ne permet pas d’émettre un nouveau jeton d’appareil de confiance sans repasser par une authentification par mot de passe (`Auth::authenticatedViaPassword()`).
3. **Palette fixe d’avatars côté serveur (C5)** : afin d’éliminer 100 % des attributs `style="..."` et de supprimer `style-src-attr 'unsafe-inline'` de la CSP, la fonction `avatar_color_class(int $userId)` associe de façon déterministe chaque utilisateur à l’une des 8 classes CSS `.pc-avatar--c1` à `.pc-avatar--c8`.

---

## 11. Limites connues

- Les modules métier des **Phases 2, 3 et 4** (saisie et courbes des constantes vitales, plan de traitement et prises de médicaments, repas avec photos, journal de bord et transmissions, pointage QR/GPS et planning, coffre-fort documentaire, alertes Web Push/SMTP et exports PDF/CSV) sont volontairement représentés par des écrans *« Bientôt disponible »* conformément au découpage du cahier des charges.
- En environnement de développement local sans serveur SMTP externe, les e-mails transactionnels (`MailService`) sont consignés dans `storage/logs/mail.log`.

---

## 12. Section « Corrections » (C1 à C7)

| Code | Intitulé de la correction | Commit | Statut |
|---|---|---|---|
| **C1** | **Critique — Connexion par code PIN via appareils de confiance (`trusted_devices`)** : migration `002_trusted_devices.sql`, activation depuis `/profile` après connexion mot de passe, cookie `HttpOnly` / `SameSite=Strict` 90 jours, soumission par `selector` uniquement, vérification `hash_equals`, blocage 15 min après 5 erreurs, révocation complète et 11 tests unitaires dédiés. | `35ea73e` | **Corrigé & Testé** |
| **C2** | **Rate limit IP sur Wi-Fi partagé** : maintien de 5 échecs / 15 min par compte et passage à 30 échecs / 15 min par IP (`security.ip_max_attempts = 30`) + tests unitaires dédiés. | `a98991f` | **Corrigé & Testé** |
| **C3** | **Additif Design « Proposition B — Bleu nuit »** : ajout de `docs/ADDENDUM_DESIGN_B.md`, application intégrale du §9 (tokens, cartes 24 px sans ombre, en-tête Bleu nuit avec halos, tabbar flottante avec `+` en dégradé, loader Bleu nuit avec barre de progression, login en 2 parties « Qui êtes-vous ? » + PIN pad) et mise à jour de `/design` + `docs/DESIGN_SYSTEM.md`. | `42b5cd6` | **Corrigé & Vérifié** |
| **C4** | **Suite de tests isolée & code de sortie CLI** : `tests/run.php` force `app.env = testing`, recrée/vide `prime_care_test` avec `--fresh` + seed avant d’exécuter les suites ; `ErrorHandler` affiche du texte brut en CLI (`PHP_SAPI === 'cli'`) et quitte avec le code `1`. | `b781b02` | **Corrigé & Testé** |
| **C5** | **CSP sans `unsafe-inline` & protection d’encadrement** : suppression de `style-src-attr 'unsafe-inline'`, remplacement des couleurs d’avatars en ligne par `.pc-avatar--c1` à `.pc-avatar--c8`, 0 attribut `style=`, et envoi de `X-Frame-Options: DENY` + `frame-ancestors 'none'` dans tous les environnements. | `4cf6499` | **Corrigé & Testé** |
| **C6** | **Corrections d’affichage à 320, 375 et 390 px** : liste `/users` sans chevauchement e-mail/badge, contacts `/patient` sans troncature du nom ni collision avec « Prioritaire », logo unique sur `/about`, libellé `#5E5CE6 → #BF5AF2` sur `/design` et vérification 320/375/390 px. | `1077820` | **Corrigé & Capturé** |
| **C7** | **Mise à jour de `docs/retours/RETOUR_PHASE_1.md` selon le gabarit strict en 12 sections** : DoD complète cochée + §9 de l’additif B, sortie de `php tests/run.php` collée, captures refaites en clair/sombre et tableau récapitulatif C1 à C7. | *(commit courant)* | **Corrigé & Livré** |
