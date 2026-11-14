# Compte rendu d’exécution — Phase 1 : Fondations, sécurité & design system

> **Projet** : Prime Care (« Suivi de soins à domicile »)  
> **Éditeur** : [Prime Vision Solutions](https://primevisionsolutions.fr)  
> **Branche** : `arena/e3966115-prime-care`  
> **Statut** : Phase 1 terminée, testée et vérifiée (50/50 assertions unitaires/intégration, 0 erreur de syntaxe `php -l`, 0 erreur console navigateur, 19 captures mobiles `390×844` clair/sombre).

---

## 1. Réalisations de la Phase 1

### Étape 0 — Mise en place, architecture & identité visuelle
- Création de l’arborescence complète conforme au §4 du brief (`app/Core`, `app/Controllers`, `app/Models`, `app/Services`, `app/Middleware`, `app/Views`, `app/Helpers`, `bin`, `config`, `database`, `docs`, `lang`, `public`, `routes`, `storage`, `tests`).
- Configuration serveur et déploiement : `.gitignore`, `config/config.example.php`, `README.md`, `public/.htaccess` (Apache 2.4+), `docs/nginx.example.conf`, `docs/CAHIER_DES_CHARGES.md`.
- Auto-hébergement intégral sans CDN (`public/assets/vendor/`) : police variable `inter-var.woff2`, 67 icônes SVG `lucide-static` v1.73.0 et fichier `LICENSES.md`.
- Conservation intacte des 6 fichiers originaux de la marque dans `public/assets/brand/` (`prime-care-icone-degrade.svg/.png`, `prime-care-icone-transparent.svg/.png`, `prime-care-logo-complet.svg/.png`) et génération automatique des déclinaisons (`prime-care-logo-complet-blanc.svg`, `favicon.svg`, `prime-care-badge.svg`, `favicon-32.png`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `badge-96.png`, `og-image.png`) via `php bin/make-icons.php`.

### Étape 1 — Socle PHP (`app/Core`), Middlewares, Policy & Helpers
- **16 classes Core (`app/Core/`)** avec `declare(strict_types=1);` et conformité PSR-12 :
  - `Config`, `Logger` (masquage automatique des mots de passe/PIN/jetons), `Lang` (`lang/fr.php`), `ErrorHandler` (vues stylées 403, 404, 419, 429, 500),
  - `Database` (PDO singleton `utf8mb4`, `ATTR_EMULATE_PREPARES = false`, `time_zone = '+00:00'`, `transaction()`),
  - `Model` (`find`, `findOrFail`, `where`, `create`, `update`, `softDelete`, `paginate`, protection `$fillable`, `created_by`, horodatage UTC),
  - `Session` (`__Host-` en production HTTPS, `HttpOnly`, `SameSite=Lax`, expiration d’inactivité 30 min, messages flash),
  - `Csrf` (jeton 32 octets aléatoires, vérification `hash_equals`, rotation),
  - `Request`, `Response` (HTML, JSON unifié `{"ok":true,"data":{...}}` / `{"ok":false,"error":{...}}`, redirection, téléchargement privé),
  - `Router` (`GET`/`POST`/`PUT`/`PATCH`/`DELETE`, paramètres typés `{id:\d+}`, groupes, middlewares, routes nommées, 404/405),
  - `View` (layouts, sections nommées, partials, nonce CSP), `Validator`, `RateLimiter` (5 échecs / 15 min par identifiant et IP), `Auth` (mot de passe Argon2id/bcrypt, PIN 4–6 chiffres, remember-me `selector:validator` SHA-256 avec rotation 30 jours), `Uploader` (validation MIME réelle `finfo`, ré-encodage GD avec correction d’orientation EXIF et suppression des métadonnées GPS/EXIF).
- **5 Middlewares (`app/Middleware/`)** : `SecurityHeadersMiddleware`, `CsrfMiddleware`, `AuthMiddleware`, `GuestMiddleware`, `RoleMiddleware`.
- **Matrice RBAC centralisée (`app/Services/Policy.php`)** et journalisation systématique (`app/Services/AuditService.php`).

### Étape 2 — Base de données, Migrations & Seeders
- Script CLI `bin/migrate.php` supportant `--fresh`, `--seed` et `--status` + `bin/seed.php`.
- Migration `database/migrations/001_phase1_foundation.sql` créant les 9 tables de fondation : `migrations`, `users`, `remember_tokens`, `password_resets`, `login_attempts`, `patients`, `patient_contacts`, `settings`, `audit_logs`.
- Seeder `database/seeds/Phase1Seeder.php` injectant les 5 comptes de démonstration (Karim Benali `admin`, Sofia Marchetti `coordinator`, Fatou Diallo `caregiver`, Marie Lefèvre `caregiver`, Dr Laurent Petit `viewer`) et le patient **Henri Benali** (84 ans, diabète de type 2, 4 contacts d’urgence, consignes permanentes et protocole d’hypoglycémie).

### Étape 3 — Authentification, Coquille applicative, Profil, Équipe & Patient
- **Authentification (`/login`, `/login/pin`, `/forgot-password`, `/reset-password/{token}`, `/logout`)** :
  - Fond dégradé mesh animé, carte blanche/sombre avec rebond spring, logo complet Prime Care en SVG en ligne (220 px `currentColor`), slogan « Suivi de soins à domicile », bouton œil sur le mot de passe, interrupteur « Rester connecté », crédit Prime Vision Solutions en pied d’écran.
  - Clavier numérique iOS de code PIN (« Bonjour Fatou », avatar, 4 à 6 chiffres, pastilles animées, secousse `shake` + vibration haptique en cas d’erreur, lien « Changer d’utilisateur »).
  - Réinitialisation de mot de passe journalisée dans `storage/logs/mail.log`.
- **Coquille applicative (`layouts/app.php`)** :
  - Loader de lancement plein écran (§6.8) joué une seule fois par session (`sessionStorage`), mini-loader réutilisable (`partials/loader-mini.php`), barre de navigation haute avec grand titre iOS rétractable et icône PC 28 px sur l’accueil, barre d’onglets basse adaptée au rôle avec bouton central `+` ouvrant la feuille d’action rapide, accueil avec en-tête dégradé et squelettes « Bientôt disponible », onglet « Plus » et fiche « À propos ».
- **Profil utilisateur (`/profile`)** :
  - Upload de photo de profil (aperçu carré côté client + traitement GD côté serveur), modification nom/prénom/téléphone, sélecteur de thème en direct (Auto / Clair / Sombre), configuration du code PIN en double saisie, changement de mot de passe avec jauge de robustesse, révocation des autres appareils connectés.
- **Gestion de l’équipe (`/users`, réservé à `admin`)** :
  - Cartes groupées par rôle avec recherche instantanée, pastille de statut actif/inactif, création avec génération d’un mot de passe initial affiché une seule fois dans une feuille modale avec bouton « Copier » (et envoi dans `storage/logs/mail.log`), affichage du taux horaire réservé à l’administrateur, activation/désactivation avec feuille de confirmation iOS et traçabilité dans `audit_logs`.
- **Fiche patient (`/patient`)** :
  - Carte rouge prioritaire **« Conduite à tenir en cas d’hypoglycémie (< 0,70 g/L) »** en haut d’écran avec boutons d’appel direct **SAMU (15)** et **Urgences (112)**, liste des contacts d’urgence avec boutons `tel:`, `sms:` et `mailto:`, consignes permanentes, paramètres du diabète, identité et digicode d’accès secours.
  - Édition de la fiche patient et CRUD des contacts d’urgence en feuilles modales pour l’administrateur.
- **PWA & Design System (`/design`)** :
  - `public/manifest.webmanifest`, `public/sw.js`, `public/offline.html`, feuille d’installation iOS/Android.
  - Page vitrine `/design` (avec bouton « Rejouer le loader ») et documentation `docs/DESIGN_SYSTEM.md`.

---

## 2. Choix techniques & Résolution des points d’attention

1. **Conformité stricte CSP (`nonce`) et attributs de style dynamiques** :
   - Les scripts ES2020+ sont chargés exclusivement via `<script type="module" nonce="...">` avec `script-src 'self' 'nonce-...'` (aucun `unsafe-inline`). Pour permettre les pastilles de couleur dynamiques des membres de l’équipe (`color_hex`), la directive CSP Level 3 `style-src-attr 'unsafe-inline'` est utilisée tout en maintenant `style-src 'self' 'nonce-...'` sur les feuilles de style.
2. **Service Worker & Redirections HTTP** :
   - Les requêtes de navigation (`request.mode === 'navigate'`) utilisent une stratégie *Network-First* directe avec repli vers `/offline.html` uniquement en cas d’erreur réseau, évitant ainsi la mise en cache de réponses `opaqueredirect` lors des redirections d’authentification (`/login`, `/logout`, `/login/switch`).

---

## 3. Comment tester la Phase 1

### Commandes CLI
```bash
# Vérifier la syntaxe de tous les fichiers PHP
find app bin config database lang public routes tests -name "*.php" -exec php -l {} +

# Réinitialiser la base de données avec les comptes et le patient de démonstration
php bin/migrate.php --fresh --seed

# Lancer les 50 assertions de la suite de tests automatisés
php tests/run.php
```

### Parcours de test recommandés
1. **Connexion & Code PIN** :
   - Ouvrir `/login`, observer le loader de lancement Prime Care (ou cliquer sur « Rejouer le loader » dans `/design`).
   - Cliquer sur « Code PIN rapide » (`/login/pin`) et saisir `3333` pour ouvrir la session de **Fatou Diallo** (aide-soignante) en 1 main.
2. **Accueil & Fiche Patient** :
   - Depuis l’accueil, toucher la carte **Henri Benali · 84 ans** pour ouvrir `/patient` : vérifier la carte rouge d’hypoglycémie, les boutons SAMU 15 / 112 et les boutons d’appel des contacts.
   - Toucher le bouton central `+` de la barre d’onglets pour ouvrir la feuille modale d’ajout rapide.
3. **Administration & Équipe** :
   - Se déconnecter via l’onglet « Plus » (`/more`), puis se connecter avec `admin@primecare.test` / `Admin!2026` (ou PIN `1111`).
   - Ouvrir l’onglet **Équipe** (`/users`), tester la recherche instantanée, ouvrir la fiche de Fatou Diallo (`/users/3`) pour vérifier l’affichage du taux horaire confidentiel et la feuille de désactivation iOS, ou créer un nouveau membre pour tester la feuille de mot de passe initial à usage unique.
   - Ouvrir `/design` depuis l’onglet « Plus » pour inspecter le Design System et tester le thème Clair / Sombre.

---

## 4. Captures d’écran mobiles (`390×844` — Clair & Sombre)

Toutes les captures ont été générées via Chromium headless dans `docs/retours/captures/phase-1/` :
- `01-loader-launch.png` — Loader de lancement Prime Care (monogramme PC + logotype)
- `02-login-light.png` & `03-login-dark.png` — Écran de connexion (Clair & Sombre)
- `04-pin-pad-light.png` & `05-pin-pad-dark.png` — Clavier de code PIN iOS « Bonjour Fatou » (Clair & Sombre)
- `06-home-caregiver-light.png` & `07-home-caregiver-dark.png` — Accueil aide-soignante (Clair & Sombre)
- `08-quick-add-sheet.png` — Feuille modale d’action rapide (`+`)
- `09-patient-light.png` & `10-patient-dark.png` — Fiche patient & Carte rouge d’hypoglycémie (Clair & Sombre)
- `11-profile-light.png` & `12-profile-dark.png` — Mon profil & Sélecteur de thème (Clair & Sombre)
- `13-team-admin-light.png` & `14-team-admin-dark.png` — Gestion de l’équipe avec recherche instantanée (Clair & Sombre)
- `15-team-member-detail.png` — Fiche détail d’un membre de l’équipe (vue administrateur)
- `16-more-menu-light.png` & `17-about-page.png` — Menu « Plus » & Fiche « À propos »
- `18-design-system-light.png` & `19-design-system-dark.png` — Vitrine `/design` (Clair & Sombre)

---

## 5. Points d’attention pour la Phase 2 (Suivi médical quotidien & cœur de métier)

- Les routes de préfiguration (`/coming-soon/glycemia`, `/coming-soon/medication`, `/coming-soon/meal`, `/coming-soon/journal`, `/today`, `/health`, `/journal`) sont déjà câblées dans la barre d’onglets et la feuille d’action rapide `+` : en Phase 2, elles seront remplacées par leurs contrôleurs métier dédiés (`VitalController`, `MedicationController`, `MealController`, `CareLogController`).
- Les seuils personnalisés du patient (`target_glycemia_fasting_min`, `target_glycemia_fasting_max`, `target_glycemia_postmeal_max`, `hypo_threshold_g_l`, `hyper_threshold_g_l`) sont déjà stockés dans la table `patients` et dans `settings`, prêts à alimenter le moteur de coloration sémantique (`ok`/`warn`/`danger`) et d’alerte immédiate en cas de glycémie hors cible.
