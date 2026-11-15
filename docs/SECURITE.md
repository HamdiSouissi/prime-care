# Rapport d'Audit de Sécurité — Grille OWASP ASVS 4.0 Niveau 1 (Prime Care v1.0.0)

Ce document consigne la grille complète de conformité **OWASP Application Security Verification Standard (ASVS) v4.0.3 — Niveau 1 (L1)** menée sur **Prime Care v1.0.0** lors de la Phase 4 (§6.3 & H7). Chaque exigence applicable des chapitres **V2, V3, V4, V5, V7, V8, V9, V12, V13 et V14** est associée à son état (`Conforme` / `Non applicable` / `Écart`), à sa **preuve vérifiable** (nom du test dans `php tests/run.php` ou commande CLI) et aux **fichiers sources concernés**.

---

## V2 — Authentification (`Authentication`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V2.1.1** | Les mots de passe utilisateurs ont une longueur minimale d'au moins 8 caractères (10+ recommandés pour l'administration). | **Conforme** | `ValidatorTest` (`Validator fails invalid payload`) ; validation `min:8` à la création/modification et `min:10` dans `bin/setup.php` (`php tests/run.php`) | `app/Core/Validator.php`, `app/Controllers/ProfileController.php`, `bin/setup.php` |
| **V2.1.2** | Les mots de passe de 64 caractères ou plus sont acceptés sans troncature silencieuse (Argon2id / Bcrypt). | **Conforme** | `AuthTest` (`Auth::attempt accepts valid caregiver password`) | `app/Core/Auth.php`, `app/Models/User.php` |
| **V2.1.5** | Les utilisateurs peuvent changer leur mot de passe ; le changement exige la saisie du mot de passe actuel. | **Conforme** | Vérification `password_verify` de `current_password` avant mise à jour dans `ProfileController::updatePassword` | `app/Controllers/ProfileController.php` |
| **V2.1.9** | Aucune règle arbitraire de composition ne force des motifs prévisibles ; les mots de passe ne sont jamais stockés ni affichés en clair. | **Conforme** | `Phase4Test` (`Audit §4: computeDiff ... masque les champs sensibles`) | `app/Core/Auth.php`, `app/Services/AuditService.php` |
| **V2.2.1** | Protection anti-automatisation / anti-force brute sur l'authentification par mot de passe et par code PIN (verrouillage 15 min après 5 échecs par compte, plafond 30 échecs / 15 min par IP partagée du domicile). | **Conforme** | `RateLimiterTest` (`C2: Account A is locked after 5 failed attempts in 15 min`, `C2: Account B on the same household IP is NOT blocked`, `C2: IP address is blocked once 30 failed attempts are reached`) & `AuthTest` (`C1: Profile PIN is locked for 15 min after 5 failed PIN attempts`) | `app/Core/RateLimiter.php`, `app/Core/Auth.php`, `app/Controllers/AuthController.php` |
| **V2.3.1** | Les mots de passe et codes PIN initiaux ou temporaires sont générés de manière cryptographiquement sûre et hachés avant stockage. | **Conforme** | `bin/setup.php` (`password_hash(..., PASSWORD_ARGON2ID ?? PASSWORD_BCRYPT)`) | `bin/setup.php`, `app/Controllers/UserController.php` |
| **V2.4.1** | Les mots de passe et codes PIN sont stockés sous forme hachée et salée avec un algorithme résistant (`PASSWORD_ARGON2ID` avec repli `PASSWORD_BCRYPT`). | **Conforme** | `AuthTest` (`Auth::attempt accepts valid caregiver password`, `C1: Valid trusted device token + valid PIN 3333 logs Fatou in`) | `app/Core/Auth.php`, `database/migrations/001_users_and_auth.sql` |
| **V2.5.1** | Les jetons de réinitialisation de mot de passe et d'appareil de confiance utilisent un sélecteur + validateur aléatoire de 256 bits (`random_bytes(32)`) dont seul le hachage `SHA-256` est stocké en base. | **Conforme** | `AuthTest` (`C1: Forged selector:validator cookie yields 0 trusted profiles`, `Auth::issueRememberToken returns selector:validator pair`) | `app/Core/Auth.php`, `app/Controllers/AuthController.php` |
| **V2.5.2** | Aucun indice de mot de passe (« password hint ») ni question secrète faible n'est utilisé. | **Conforme** | Vérification du schéma SQL : aucune colonne `password_hint` ou `security_question` (`grep -rn "password_hint" app/ database/`) | `database/migrations/001_users_and_auth.sql` |
| **V2.5.4** | Lors d'une demande de réinitialisation de mot de passe (`POST /forgot-password`), le message retourné est identique que l'adresse existe ou non (anti-énumération). | **Conforme** | Inspection de `AuthController::sendForgot` (message générique unique) | `app/Controllers/AuthController.php` |
| **V2.7.1 / C1** | La connexion rapide par code PIN à 4 chiffres sur appareil partagé est strictement liée à un jeton matériel `selector:validator` stocké dans `trusted_devices` ; toute falsification de `user_id` est rejetée. | **Conforme** | `AuthTest` (`C1: Forged user_id=1&pin=1111 in POST /login/pin is rejected (no admin takeover)`, `C1: Admin PIN submitted against Fatou trusted selector is rejected`) | `app/Core/Auth.php`, `app/Controllers/AuthController.php` |

---

## V3 — Gestion des sessions (`Session Management`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V3.1.1** | Les identifiants de session ne sont jamais exposés dans les URL ni dans les journaux (`session.use_only_cookies = 1`, `session.use_trans_sid = 0`). | **Conforme** | Inspection de `Session::start()` (`ini_set('session.use_only_cookies', '1')`) | `app/Core/Session.php` |
| **V3.2.1** | L'application génère un nouvel identifiant de session à chaque authentification réussie par mot de passe ou par code PIN (protection contre la fixation de session). | **Conforme** | `Phase4Test` (`Sécurité §6.3 (Fixation de session): l’identifiant de session est régénéré à la connexion`) | `app/Core/Session.php`, `app/Core/Auth.php` |
| **V3.2.2** | Les jetons de session et de reconnexion possèdent au moins 128 bits d'entropie issus d'un CSPRNG (`random_bytes`). | **Conforme** | `CsrfTest` (`CSRF token is 64 hex characters (32 random bytes)`), `AuthTest` (`Auth::issueRememberToken`) | `app/Core/Session.php`, `app/Core/Csrf.php`, `app/Core/Auth.php` |
| **V3.2.3** | Les jetons « Se souvenir de moi » (`remember_tokens`) sont rotatifs à usage unique (`consumeRememberToken`). | **Conforme** | `AuthTest` (`Auth::consumeRememberToken rotates token on valid use`, `Auth::consumeRememberToken rejects already-used token`) | `app/Core/Auth.php` |
| **V3.3.1** | La déconnexion (`POST /logout`) invalide la session serveur, détruit le cookie de session et révoque le jeton `remember_token` actif. | **Conforme** | `AuthTest` (`Auth::check() is false when logged out`) | `app/Core/Auth.php`, `app/Core/Session.php`, `app/Controllers/AuthController.php` |
| **V3.3.2** | L'utilisateur (et l'administrateur en cas d'urgence) peut révoquer immédiatement tous les appareils de confiance et jetons actifs d'un compte. | **Conforme** | `AuthTest` (`C1: revokeAllDevicesForUser revokes all trusted device tokens for user`), `Phase4Test` (`Audit §4: ... les révocations d’appareils de confiance sont tracées`) | `app/Core/Auth.php`, `app/Controllers/ProfileController.php` |
| **V3.4.1** | Les cookies de session et d'authentification utilisent l'attribut `Secure` en HTTPS / production et le préfixe `__Host-` en mode `production`. | **Conforme** | `Phase4Test` (`Production §6.1: en mode production, ... les cookies ont le préfixe __Host-`) | `app/Core/Session.php`, `app/Core/Auth.php` |
| **V3.4.2** | Les cookies sensibles ont l'attribut `HttpOnly = true` (inaccessibles à `document.cookie`). | **Conforme** | Inspection de `Session::start()` et `Auth::setCookie()` (`httponly => true`) | `app/Core/Session.php`, `app/Core/Auth.php` |
| **V3.4.3** | Les cookies utilisent l'attribut `SameSite=Lax` (ou `Strict`). | **Conforme** | Inspection de `Session::start()` et `Auth::setCookie()` (`samesite => 'Lax'`) | `app/Core/Session.php`, `app/Core/Auth.php` |

---

## V4 — Contrôle d'accès (`Access Control`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V4.1.1** | Les règles de contrôle d'accès sont appliquées côté serveur (dans `AuthMiddleware`, `RoleMiddleware`, `Policy` et les services métiers), jamais uniquement côté client. | **Conforme** | `RoutesSmokeTest` (`D1 Smoke (sans session): toutes les routes protégées redirigent en 302 vers /login`, `D1 Smoke (caregiver): 403 sur /users...`, `D1 Smoke (viewer): 403 sur les routes d’administration et de pointage`) | `app/Services/Policy.php`, `app/Middleware/AuthMiddleware.php`, `app/Middleware/RoleMiddleware.php` |
| **V4.1.2** | Aucun attribut sensible (rôle, `user_id` auteur, statut de validation) ne peut être injecté ou forcé par un utilisateur non autorisé (mass-assignment / escalade). | **Conforme** | `Phase4Test` (`Sécurité §6.3 (H3 IDOR Utilisateur)`, `Sécurité §6.3 (H3 IDOR Export RGPD)`) | `app/Controllers/UserController.php`, `app/Controllers/ProfileController.php` |
| **V4.1.3** | Principe du moindre privilège entre les 4 rôles (`admin`, `coordinator`, `caregiver`, `viewer`) : le rôle `viewer` est strictement en lecture seule ; seule l'administration accède à `/users`, `/settings`, `/audit` et au taux horaire. | **Conforme** | `PolicyTest` (12 assertions RBAC) + `RoutesSmokeTest` (`H1 Exports CSV HTTP E2E: les 9 types × 4 profils`) | `app/Services/Policy.php`, `app/Services/ExportService.php` |
| **V4.1.5** | Les contrôles d'accès échouent de manière fermée (`fail securely` -> `HTTP 403 Forbidden`) lorsque l'action ou la ressource n'est pas explicitement autorisée. | **Conforme** | `Policy::allows()` retourne `false` par défaut (`default => false`) | `app/Services/Policy.php` |
| **V4.2.1** | Protection contre les références directes non sécurisées aux objets (**IDOR / BOLA**) sur chacune des 12 ressources adressées par identifiant (mesure, prise, repas, note, document, créneau, pointage, alerte, utilisateur, média, export CSV, export RGPD). | **Conforme** | `Phase4Test` (12 tests HTTP E2E `Sécurité §6.3 (H3 IDOR Mesure / Prise / Repas / Note / Document / Créneau / Pointage / Alerte / Utilisateur / Média / Export / Export RGPD)` vérifiant le code `403`/`302` **et** l'invariance des données en base) | `app/Services/EditWindowService.php`, `app/Controllers/*.php`, `tests/Unit/Phase4Test.php` |
| **V4.2.2** | Protection contre la falsification de requête intersites (**CSRF**) sur toutes les requêtes `POST` Web et API JSON via jeton synchronisé vérifié par `hash_equals()`. | **Conforme** | `CsrfTest` (6 assertions) + `RoutesSmokeTest` (`E2 API JSON: POST /api/measures sans en-tête CSRF retourne 419 au format JSON`) | `app/Core/Csrf.php`, `app/Middleware/CsrfMiddleware.php` |
| **V4.3.1** | Les interfaces d'administration (`/users`, `/admin/treatments`, `/admin/measures`, `/settings`, `/audit`) exigent le rôle `admin` via `RoleMiddleware` et `Policy`. | **Conforme** | `RoutesSmokeTest` (`D1 Smoke (caregiver): 403 sur /users, /admin/treatments, /admin/measures, /settings...`) | `routes/web.php`, `app/Middleware/RoleMiddleware.php` |
| **V4.3.2** | La navigation dans les répertoires (`directory browsing`) est désactivée et seule la racine `public/` est exposée. | **Conforme** | `public/.htaccess` (`Options -Indexes`) et configuration Nginx dans `docs/DEPLOIEMENT_HDS.md` | `public/.htaccess`, `docs/DEPLOIEMENT_HDS.md` |

---

## V5 — Validation, assainissement et encodage (`Validation, Sanitization and Encoding`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V5.1.1 / V5.1.3** | Toutes les entrées HTTP sont validées côté serveur selon des règles positives (types numériques, bornes plausibles, dates ISO, énumérations strictes, messages d'erreur en français). | **Conforme** | `ValidatorTest` (6 assertions) + `ThresholdServiceTest` (`ThresholdService: 9,50 g/L (> max 6,00) exige une confirmation`) | `app/Core/Validator.php`, `app/Services/ThresholdService.php` |
| **V5.2.1** | Toutes les données dynamiques affichées dans les vues HTML sont échappées avec `e()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`). | **Conforme** | `HelperEscapeTest` (`Helper e() escapes HTML entities including single and double quotes`) | `app/Helpers/functions.php`, `app/Views/**/*.php` |
| **V5.3.3** | Protection contre les injections XSS basées sur le DOM et les scripts en ligne via une `Content-Security-Policy` stricte à nonce aléatoire (`script-src 'self' 'nonce-...'`, `style-src 'self'`, `0` directive `unsafe-inline`, `0` attribut `style=` en ligne). | **Conforme** | `HelperEscapeTest` (`C5: Content-Security-Policy contains zero unsafe-inline directives`) + `RoutesSmokeTest` (`C5 CSP stricte: 0 attribut style= en ligne dans le HTML généré sur l’ensemble des vues Phases 1, 2, 3 & 4`) | `app/Middleware/SecurityHeadersMiddleware.php`, `app/Views/**/*.php` |
| **V5.3.4 / V5.3.5** | Toutes les requêtes SQL utilisent **exclusivement** des requêtes préparées PDO avec `PDO::ATTR_EMULATE_PREPARES => false` (zéro concaténation de variable utilisateur dans le SQL). | **Conforme** | Inspection de `app/Core/Database.php` (`PDO::ATTR_EMULATE_PREPARES => false`) et audit statique (`grep -rn "->query(" app/`) | `app/Core/Database.php`, `app/Models/*.php`, `app/Services/*.php` |
| **V5.3.8** | Aucune fonction d'exécution de commande shell (`exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `eval`) n'est appelée depuis le code PHP web. | **Conforme** | Vérification statique : `grep -rnE "\b(shell_exec|passthru|proc_open|eval)\s*\(" app/` retourne `0` résultat | `app/` |

---

## V7 — Gestion des erreurs et journalisation (`Error Handling and Logging`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V7.1.1 / V7.1.2** | L'application ne journalise jamais les mots de passe, codes PIN, jetons de session ou clés privées en clair dans `storage/logs/app.log` ni dans `audit_logs`. | **Conforme** | `Phase4Test` (`Audit §4: computeDiff calcule les différences avant / après champ par champ en français et masque les champs sensibles`) | `app/Services/AuditService.php`, `app/Core/Logger.php` |
| **V7.2.1 / V7.2.2** | Toutes les connexions réussies, tentatives de connexion échouées, créations/modifications/suppressions de données médicales ou RH, révocations d'appareils et exports CSV/RGPD sont enregistrés dans `audit_logs` (lecture seule, avec IP et User-Agent). | **Conforme** | `Phase4Test` (`Audit §4: les connexions réussies/échouées et les révocations d’appareils de confiance sont tracées dans audit_logs`, `Exports CSV §3: ... trace l’export dans audit_logs`) | `app/Services/AuditService.php`, `app/Controllers/AuditController.php` |
| **V7.4.1 / H2** | Lorsqu'une erreur inattendue survient en production (`app.env = production`), un message générique est renvoyé et `app.debug` est **forcé à `false`** quelle que soit la configuration : aucune trace de pile ni erreur SQL n'est exposée en HTML ou JSON. | **Conforme** | `Phase4Test` (`Production §6.1 (H2): avec app.env=production et app.debug=true, debug est forcé à false et aucune trace ni message technique ne fuite en HTML ou JSON`) | `app/Core/Config.php`, `app/Core/ErrorHandler.php` |

---

## V8 — Protection des données & RGPD (`Data Protection`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V8.1.1 / G3** | Les données médicales sensibles et les téléchargements (`Content-Disposition`, `/export`, `/media/`, `/documents/{id}/download`) ne sont jamais mis en cache dans les caches partagés ni dans le Service Worker (`Cache-Control: no-store`). | **Conforme** | `Phase3Test` (`Service Worker G3: public/sw.js restreint le cache de navigation à la liste blanche exacte ['/', '/today', '/journal'] et exclut Content-Disposition, /export et /media/`) + `Phase4Test` (`Documents §1: téléchargement autorisé ... avec Cache-Control: no-store`) | `public/sw.js`, `app/Core/Response.php`, `app/Controllers/DocumentController.php` |
| **V8.2.2** | Aucune donnée médicale complète (glycémie, constante, dose, nom du patient) n'est stockée de façon persistante dans `localStorage` ou `sessionStorage` ; seule la file d'attente hors ligne temporaire est stockée dans IndexedDB et purgée dès synchronisation ou déconnexion. | **Conforme** | Inspection de `public/assets/js/modules/offline.js` | `public/assets/js/modules/offline.js` |
| **V8.3.1** | Les données sensibles ne sont jamais transmises dans la chaîne de requête URL (`GET`) : mots de passe, codes PIN, jetons CSRF et saisies médicales passent exclusivement en corps `POST`. | **Conforme** | Inspection de `routes/web.php` et `routes/api.php` | `routes/web.php`, `routes/api.php` |
| **V8.3.4 / §5.2** | Conformité RGPD (minimisation, durées de conservation par catégorie, suppression logique puis purge physique après 30 jours sur les données non médicales sans jamais toucher aux données médicales, droit d'accès/portabilité Art. 15 & 20, notice RGPD pointage). | **Conforme** | `Phase4Test` (`Conservation RGPD §5.2: retention:purge applique la suppression logique puis physique (>30j) et ne touche JAMAIS aux données médicales`, `RGPD §5.2: le bouton « Exporter mes données »`, `RGPD §5.2: le bouton « Exporter toutes les données du patient »`) + `Phase3Test` (`Attendance G2: refuse tout pointage avec HTTP 403 GDPR_NOTICE_REQUIRED`) | `app/Services/CronService.php`, `app/Services/ExportService.php`, `app/Controllers/LegalController.php` |

---

## V9 — Sécurité des communications (`Communications`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V9.1.1 / V9.1.2** | En production, TLS 1.2 / 1.3 est exigé et la redirection HTTPS ainsi que l'en-tête `Strict-Transport-Security: max-age=63072000; includeSubDomains` sont activés. | **Conforme** | `Phase4Test` (`Production §6.1: en mode production, ... HSTS = max-age=63072000; includeSubDomains`) | `app/Middleware/SecurityHeadersMiddleware.php`, `docs/DEPLOIEMENT_HDS.md` |
| **V9.2.1** | Les charges utiles des notifications Web Push sont chiffrées de bout en bout en `aes128gcm` (RFC 8291, ECDH P-256 + HKDF-SHA-256) et authentifiées par jeton VAPID ES256 (RFC 8292). | **Conforme** | `Phase3Test` (`WebPushService: signe/vérifie le JWT ES256 et chiffre/déchiffre de bout en bout en aes128gcm`, `WebPushService: conformité octet par octet au vecteur officiel RFC 8291 Appendix A`) | `app/Services/WebPushService.php` |

---

## V12 — Fichiers et ressources (`Files and Resources`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V12.1.1** | La taille des fichiers téléversés est strictement limitée côté serveur (**5 Mo** pour les photos de repas/avatars, **10 Mo** pour les documents médicaux). | **Conforme** | `EditWindowHandoverMealTest` (`E2 Uploads: fichier trop lourd (> 5 Mo) est refusé`) + `Phase4Test` (`Documents §1: un fichier de plus de 10 Mo est refusé`) | `app/Core/Uploader.php` |
| **V12.2.1** | Vérification du type réel du contenu par `finfo(FILEINFO_MIME_TYPE)` et vérification des octets magiques `%PDF-` pour les PDF ; ré-encodage systématique des images `JPEG`/`PNG` par `GD` supprimant les métadonnées EXIF/GPS et tout code embarqué. | **Conforme** | `EditWindowHandoverMealTest` (`E2 Uploads: faux JPEG contenant du code PHP est refusé par finfo`, `E2 Uploads: vrai JPEG avec EXIF est ré-encodé sans EXIF`) + `Phase4Test` (`Documents §1: un faux PDF (script PHP renommé .pdf sans en-tête %PDF-) est refusé (422)`, `Documents §1 (H3): les métadonnées EXIF sont supprimées sur un JPEG déposé dans Documents`) | `app/Core/Uploader.php`, `app/Services/DocumentService.php` |
| **V12.3.1 / V12.3.2** | Les fichiers téléversés sont renommés avec un identifiant aléatoire (`random_bytes(16)`), stockés **hors de la racine web** dans `storage/uploads/` et servis via un contrôleur vérifiant `basename()`, l'absence de `..`, de double encodage `%2e%2e`, d'octets nuls `\0` et le préfixe canonique `realpath()`. | **Conforme** | `Phase4Test` (`Sécurité §6.3 (Traversée de chemin): bloque les séquences .., le double encodage %2e%2e et les octets nuls sur /media/`) | `app/Core/Uploader.php`, `app/Controllers/MediaController.php`, `app/Controllers/DocumentController.php` |
| **V12.4.1 / V12.5.1** | Les fichiers servis par l'application ne sont jamais interprétés comme du code par le serveur ni par le navigateur (`X-Content-Type-Options: nosniff`, `Content-Disposition`). | **Conforme** | `Phase4Test` (`Documents §1: téléchargement autorisé pour les 4 rôles avec Cache-Control: no-store et X-Content-Type-Options: nosniff`) | `app/Core/Response.php`, `app/Controllers/MediaController.php` |

---

## V13 — Sécurité des API (`API and Web Service`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V13.1.1 / V13.1.3** | Toutes les routes API (`/api/*`) partagent les mêmes contrôles d'authentification (`AuthMiddleware`), d'autorisation (`Policy`) et de protection CSRF (`X-CSRF-Token`) que l'application Web, sans clé API ni jeton dans l'URL. | **Conforme** | `RoutesSmokeTest` (`E2 API JSON: POST /api/measures sans en-tête CSRF retourne 419`, `API JSON (viewer): POST /api/measures est bloqué en 403`) | `routes/api.php`, `app/Middleware/CsrfMiddleware.php` |
| **V13.1.4 / §6.3** | Limite de débit anti-abus sur les routes API d'écriture : maximum **120 requêtes / minute / utilisateur** avec réponse `HTTP 429 Too Many Requests` (`Retry-After: 60`) au-delà. | **Conforme** | `Phase4Test` (`Sécurité §6.3 (Rate Limit API): autorise 120 requêtes d’écriture / minute / utilisateur et bloque la 121e (HTTP 429)`) | `app/Middleware/ApiRateLimitMiddleware.php`, `app/Core/RateLimiter.php` |
| **V13.2.1 / G1** | Idempotence des écritures API hors ligne via `client_uuid` (anti-doublon `200 OK`), conflit de double prise (`HTTP 409 Conflict`), et validation stricte de `client_recorded_at` (rejet `> 72 h` avec `422 OFFLINE_TOO_OLD`, plafonnement à l'heure serveur si `> +2 min` dans le futur). | **Conforme** | `RoutesSmokeTest` (`Phase 3 Mode hors ligne: rejouer le même client_uuid sur POST /api/measures retourne 200 et ne crée qu’un seul enregistrement`) + `Phase3Test` (`Offline G1: client_recorded_at ...`) | `app/Controllers/ApiMedicalController.php`, `app/Services/IntakeService.php` |

---

## V14 — Configuration et en-têtes HTTP (`Configuration`)

| N° ASVS | Exigence ASVS 4.0 Niveau 1 | État | Preuve (Test automatisé / Commande) | Fichier(s) concerné(s) |
|---|---|---|---|---|
| **V14.1.1 / V14.2.1** | Zéro dépendance externe CDN au chargement (polices, icônes Lucide, ApexCharts et jsQR auto-hébergés localement dans `public/assets/`) ; aucun outil de débogage ni données de test en production (`/design` -> `404` et `bin/seed.php` bloqué en production). | **Conforme** | `Phase4Test` (`Production §6.1: en mode production, /design retourne 404`, `Production §6.1: bin/seed.php refuse de s’exécuter lorsque app.env = production`, `Production §6.1: bin/setup.php refuse de s’exécuter si l’application est déjà installée`) | `app/Controllers/DesignController.php`, `bin/seed.php`, `bin/setup.php` |
| **V14.3.2** | Le mode `debug` est forcé à `false` en environnement `production` dès le chargement de `Config`. | **Conforme** | `Phase4Test` (`Production §6.1 (H2): avec app.env=production et app.debug=true, debug est forcé à false...`) | `app/Core/Config.php`, `app/Core/ErrorHandler.php` |
| **V14.4.1** | Toutes les réponses HTTP incluent un `Content-Type` explicite avec jeu de caractères `UTF-8` (`text/html; charset=UTF-8`, `application/json; charset=UTF-8`, `text/csv; charset=UTF-8`). | **Conforme** | Inspection de `app/Core/Response.php` | `app/Core/Response.php` |
| **V14.4.3** | En-tête `Content-Security-Policy` strict sur toutes les réponses (`default-src 'self'; script-src 'self' 'nonce-...'; style-src 'self'; object-src 'none'; frame-ancestors 'none'`). | **Conforme** | `HelperEscapeTest` (`C5: Content-Security-Policy contains zero unsafe-inline directives`) | `app/Middleware/SecurityHeadersMiddleware.php` |
| **V14.4.4** | En-tête `X-Content-Type-Options: nosniff` présent sur toutes les réponses HTML, JSON, CSV et médias. | **Conforme** | `SecurityHeadersMiddleware` + `Phase4Test` (`Documents §1: téléchargement autorisé ... avec X-Content-Type-Options: nosniff`) | `app/Middleware/SecurityHeadersMiddleware.php`, `app/Core/Response.php` |
| **V14.4.5** | En-tête `Strict-Transport-Security: max-age=63072000; includeSubDomains` envoyé en production / HTTPS. | **Conforme** | `Phase4Test` (`Production §6.1: ... HSTS = max-age=63072000; includeSubDomains`) | `app/Middleware/SecurityHeadersMiddleware.php` |
| **V14.4.6** | En-tête `Referrer-Policy: strict-origin-when-cross-origin` présent sur toutes les réponses. | **Conforme** | Inspection de `SecurityHeadersMiddleware::handle` | `app/Middleware/SecurityHeadersMiddleware.php` |
| **V14.4.7** | Protection anti-clickjacking sur toutes les réponses via `X-Frame-Options: DENY` et `CSP frame-ancestors 'none'`, et restriction des capteurs matériels via `Permissions-Policy: geolocation=(self), camera=(self), microphone=()`. | **Conforme** | `HelperEscapeTest` (`C5: frame-ancestors 'none' and X-Frame-Options: DENY are sent in all environments`) | `app/Middleware/SecurityHeadersMiddleware.php` |

---

## Liste des écarts restants

- **Écarts restants : aucun.**
  - **100 %** des exigences OWASP ASVS 4.0 Niveau 1 applicables (V2, V3, V4, V5, V7, V8, V9, V12, V13, V14) sont **Conformes** et couvertes par la suite de tests automatisés (`php tests/run.php`).
