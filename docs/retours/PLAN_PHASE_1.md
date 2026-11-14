# Plan d'exécution — Phase 1 : Fondations, sécurité & design system

## 1. Objectif de la Phase 1
Livrer le socle technique complet en PHP 8.2+ natif et MySQL (sans framework ni compilateur), le système d'authentification (mot de passe, code PIN à 4-6 chiffres, jeton « rester connecté » rotatif, réinitialisation de mot de passe, limitation des tentatives), la matrice d'autorisations par rôle (`Policy`), la gestion des comptes et de l'équipe (`admin`), le profil utilisateur, la fiche patient avec protocole d'hypoglycémie et contacts d'urgence, l'identité visuelle Prime Care (logos officiels et dérivés), le shell mobile PWA iOS natif (loader animé PC, barre de navigation Large Title, tab bar par rôle, bottom sheet « + », View Transitions, crédit Prime Vision Solutions), la vitrine `/design`, la documentation du Design System et la suite de tests automatisés.

---

## 2. Ordre d'exécution et tâches

### Étape 1 — Préparation, arborescence, configuration et assets de marque (§0 & §6.0)
- Création de l'arborescence complète (`public/`, `app/`, `routes/`, `config/`, `database/`, `lang/`, `cron/`, `bin/`, `storage/`, `tests/`, `docs/`).
- `.gitignore` excluant `config/config.php`, `storage/*` (hors `.gitkeep`), `.env`, fichiers temporaires.
- `config/config.example.php` et `config/config.php` local (app, db + test_name, session, mail, security, paths).
- `public/.htaccess` (réécriture front controller, cache assets, en-têtes et blocage fichiers sensibles) + `docs/nginx.example.conf` + `docs/CAHIER_DES_CHARGES.md`.
- Copie intégrale et non modifiée des 6 fichiers de marque dans `public/assets/brand/` :
  - `prime-care-logo-complet.svg` / `.png`
  - `prime-care-icone-transparent.svg` / `.png`
  - `prime-care-icone-degrade.svg` / `.png`
- Création des variantes dérivées officielles :
  - `prime-care-logo-complet-blanc.svg`
  - `prime-care-badge.svg` et `badge-96.png`
  - Script `bin/make-icons.php` (GD, rejouable) générant `icon-maskable-512.png`, `apple-touch-icon.png` (180 px, recadrage central 820×820 opaque), `icon-192.png`, `icon-512.png`, `favicon.svg`, `favicon-32.png` et `og-image.png` (1200×630 sur `#F4F7FC`).
- Téléchargement local dans `public/assets/vendor/` : police variable `Inter` (`woff2`), icônes `Lucide` (catalogue SVG inline via `icon()`), et `public/assets/vendor/LICENSES.md`.

### Étape 2 — Noyau MVC & Sécurité (`app/Core/`, `app/Middleware/`, `app/Services/`, `app/Helpers/`)
- Autoloader PSR-4 maison dans `public/index.php` (et bootstrap partagé avec CLI/tests).
- Classes `app/Core/` :
  - `Config` : lecture par notation pointée `Config::get('db.host', $default)`.
  - `Logger` : logs journaliers JSON dans `storage/logs/app-YYYY-MM-DD.log` avec filtrage des secrets (`password`, `pin`, `token`).
  - `ErrorHandler` : conversion erreurs → exceptions, pages stylées 403, 404, 419, 429, 500 (mode debug vs production), réponses JSON si API.
  - `Request` & `Response` : méthodes HTTP + override `_method`, nettoyage entrées, JSON, `wantsJson()`, fichiers, réponses HTML/JSON/redirect/download.
  - `Router` : routes GET/POST/PUT/PATCH/DELETE, paramètres typés `{id:\d+}`, groupes (préfixe + middlewares), routes nommées, 404/405.
  - `Database` : singleton PDO MySQL (`utf8mb4`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`, `FETCH_ASSOC`, session `time_zone='+00:00'`), `transaction(callable)`.
  - `Model` : classe abstraite (`find`, `findOrFail`, `where`, `all`, `create`, `update`, `softDelete`, `paginate`, protection `$fillable`, exclusion `deleted_at`, horodatages UTC et `created_by`).
  - `Session` : cookies sécurisés (`HttpOnly`, `Secure` en HTTPS, `SameSite=Lax`, préfixe `__Host-` en prod), régénération d'ID, timeout d'inactivité 30 min, messages flash et `old()` / `errors()`.
  - `Csrf` : jeton par session, `hash_equals`, vérification sur POST/PUT/PATCH/DELETE (champ `_csrf` ou en-tête `X-CSRF-Token`).
  - `RateLimiter` : table `login_attempts`, blocage 15 min après 5 échecs par identifiant ET par IP.
  - `Auth` : authentification par mot de passe et par code PIN (`password_hash` Argon2id/bcrypt), jeton « Rester connecté » (sélecteur + validateur SHA-256 en base, rotation à chaque reconnexion, 30 jours), cookie d'appareil pour proposer l'écran PIN (« Bonjour Prénom »), déconnexion simple et révocation des autres appareils.
  - `Validator` : règles `required`, `email`, `min`, `max`, `between`, `numeric`, `integer`, `date`, `time`, `in`, `regex`, `unique`, `confirmed`, `file`, `image`, `max_size`, messages FR (`lang/fr.php`).
  - `View` : moteur de gabarits avec layouts, sections, partials, nonce CSP, échappement systématique.
  - `Lang` : traductions `t()` avec interpolation et pluriels depuis `lang/fr.php`.
  - `Uploader` : vérification MIME via `finfo`, taille max, nom aléatoire `bin2hex(random_bytes(16))`, ré-encodage GD (orientation EXIF appliquée puis EXIF supprimés), miniature carrée, stockage dans `storage/uploads/`.
- Middlewares : `SecurityHeadersMiddleware`, `CsrfMiddleware`, `AuthMiddleware`, `GuestMiddleware`, `RoleMiddleware`.
- Services : `Policy` (matrice RBAC complète testée unitairement), `AuditService` (journalisation systématique avec avant/après, utilisateur, IP, user-agent), `MailService` (écriture dans `storage/logs/mail.log` en dev).
- Helpers (`app/Helpers/functions.php`) : `e()`, `t()`, `url()`, `route()`, `asset()`, `icon()`, `brand_svg()`, `csrf_field()`, `csrf_token()`, `old()`, `errors()`, `auth()`, `format_date()`, `format_time()`, `format_datetime()`, `initials()`, `csp_nonce()`.

### Étape 3 — Base de données, migrations & seed (`database/`, `bin/`)
- `database/migrations/001_phase1_foundation.sql` :
  - `migrations`, `users`, `remember_tokens`, `password_resets`, `login_attempts`, `patients`, `patient_contacts`, `settings`, `audit_logs`.
- `bin/migrate.php` supportant `--fresh`, `--seed`, `--status`.
- `database/seeds/Phase1Seeder.php` (et `bin/seed.php`) créant :
  - Les 5 comptes de test (`admin`, `coordinator`, 2 `caregiver`, `viewer`) avec leurs mots de passe et codes PIN respectifs.
  - Le patient « Henri Benali » (84 ans, diabète de type 2, objectifs glycémiques, allergies, antécédents, consignes permanentes, protocole d'hypoglycémie complet) et ses 4 contacts (médecin traitant, diabétologue, pharmacie, fils responsable).
  - Les réglages initiaux dans `settings`.

### Étape 4 — Design System CSS/JS & Shell Mobile (`public/assets/`, `app/Views/`)
- CSS natif structuré avec `@layer reset, tokens, base, components, utilities, pages;` :
  - `tokens.css`, `base.css`, `components.css`, `utilities.css`, `app.css`.
  - Mode clair / sombre automatique (`prefers-color-scheme`) et forcé (`data-theme="light|dark"`).
- Modules JS ES2020+ (`public/assets/js/`) :
  - `app.js`, `modules/loader.js`, `modules/navbar.js`, `modules/tabs.js`, `modules/sheet.js`, `modules/toast.js`, `modules/segmented.js`, `modules/pin-pad.js`, `modules/transitions.js`, `modules/haptics.js`, `modules/pwa.js`, `modules/avatar-crop.js`.
- Layouts & Partials :
  - `layouts/auth.php`, `layouts/app.php`, `layouts/print.php`, `layouts/error.php`.
  - `partials/loader.php`, `partials/loader-mini.php`, `partials/navbar.php`, `partials/tabbar.php`, `partials/sidebar.php`, `partials/quick-add-sheet.php`, `partials/toast.php`, `partials/credit.php`, `partials/install-sheet.php`.
- PWA : `public/manifest.webmanifest`, `public/sw.js` (précache shell, network-first HTML avec fallback `offline.html`, cache-first assets, exclusion stricte de `/api/`), `public/offline.html`.

### Étape 5 — Écrans et Contrôleurs de la Phase 1 (`app/Controllers/`, `app/Models/`, `app/Views/`)
- `AuthController` : connexion email/mot de passe (`/login`), connexion PIN (`/login/pin`), changement d'utilisateur, mot de passe oublié (`/forgot-password`) et réinitialisation (`/reset-password/{token}`), déconnexion (`POST /logout`).
- `HomeController` : tableau de bord d'accueil personnalisé selon le rôle (`/`), page « Plus » (`/more`), page « À propos » (`/about`), écrans « Disponible bientôt » (`/coming-soon/{module}`) pour chaque module des phases 2-4 (aucun lien mort).
- `ProfileController` : mon profil (`/profile`), upload photo avec recadrage/compression et ré-encodage GD sans EXIF, modification coordonnées, mot de passe, code PIN (double saisie), thème (Auto/Clair/Sombre en temps réel + API JSON), révocation des autres sessions.
- `UserController` (`admin`) : liste de l'équipe avec filtre/recherche instantanée (`/users`), création (`/users/create`), fiche détail et édition (`/users/{id}`, `/users/{id}/edit`), affichage unique du mot de passe initial généré avec copie, activation/désactivation avec alerte de confirmation iOS, journal d'audit.
- `PatientController` : fiche patient complète (`/patient`) avec âge calculé, carte rouge douce de protocole hypoglycémie, contacts d'urgence (15, 112 + contacts avec boutons `tel:`, `sms:`, `mailto:`), édition par section et gestion CRUD des contacts pour `admin` avec journal d'audit.
- `DesignController` (`admin`) : page vitrine `/design` présentant l'identité de marque, les couleurs, la typographie et tous les composants dans tous leurs états avec bascule Clair/Sombre instantanée.
- `MediaController` : service sécurisé des avatars/photos depuis `storage/uploads/` après contrôle d'authentification.

### Étape 6 — Documentation, Tests automatisés, Vérifications & Captures
- Rédaction de `README.md` et `docs/DESIGN_SYSTEM.md`.
- Runner `tests/run.php` testant : `Router`, `Validator`, `Csrf`, `Auth` (hash, PIN, remember token + rotation), `RateLimiter`, `Policy` (matrice complète), `e()` (XSS).
- Vérification `php -l` sur 100 % des fichiers PHP, `php bin/migrate.php --fresh --seed`, `php tests/run.php`.
- Vérification navigateur mobile (320 px, 375 px, 390×844, 414 px, 1280 px) en mode clair et sombre, absence d'erreur console, capture des écrans dans `docs/retours/captures/phase-1/`.
- Rédaction de `docs/retours/RETOUR_PHASE_1.md`, commit et push.
