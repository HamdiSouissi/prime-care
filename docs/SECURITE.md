# Rapport de Vérification de Sécurité — Prime Care v1.0.0

Ce document consigne la revue complète de sécurité menée lors de la Phase 4 (§6.3) sur l'ensemble du code source, des routes Web et API JSON, de la base de données MySQL/MariaDB et du Service Worker PWA.

---

## 1. Synthèse des contrôles (12 domaines vérifiés)

| # | Domaine de sécurité | Statut | Mécanisme en place & Fichiers audités |
|---|---|---|---|
| 1 | **Injections SQL (SQLi)** | **CONFORME** | `PDO::ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`, requêtes préparées paramétrées partout (`app/Core/Database.php`, `app/Services/*.php`, `app/Models/*.php`). Aucune concaténation de variable utilisateur dans une requête SQL. |
| 2 | **Cross-Site Scripting (XSS)** | **CONFORME** | Échappement systématique via `e()` (`htmlspecialchars(..., ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')`) dans `100 %` des vues (`app/Views/**/*.php`). Politique `Content-Security-Policy` stricte sans `unsafe-inline` (`style-src 'self'`, `script-src 'self'`). `0` attribut `style=` en ligne. |
| 3 | **Cross-Site Request Forgery (CSRF)** | **CONFORME** | Jeton aléatoire 256 bits (`bin2hex(random_bytes(32))`) vérifié en temps constant via `hash_equals()` par `CsrfMiddleware` sur toutes les requêtes `POST`, `PUT`, `PATCH`, `DELETE` (formulaires Web `_csrf` et API JSON `X-CSRF-Token`). Retourne HTTP `419` en cas d'absence ou d'invalidité. |
| 4 | **Accès direct aux objets (IDOR)** | **CONFORME** | Contrôle systématique de `patient_id` et du propriétaire (`recorded_by` / `user_id`) combiné à `EditWindowService` (fenêtre de 30 minutes pour l'auteure aide-soignante, blocage d'accès aux saisies d'une collègue) et `Policy::allows($role, $resource, $action)`. |
| 5 | **Élévation de privilèges (RBAC)** | **CONFORME** | Matrice de permissions centralisée dans `app/Services/Policy.php` + `RoleMiddleware`. Rôle `viewer` strictement en lecture seule (`403` sur toute route d'écriture, sur les pointages/heures RH et sur l'audit). Rôle `caregiver` bloqué (`403`) sur `/users`, `/settings`, `/audit`, `/reports`, `/admin/*`. |
| 6 | **Fixation de session & Cookies** | **CONFORME** | `session_regenerate_id(true)` (et régénération d'identifiant en mode CLI/test) à chaque connexion par mot de passe ou par code PIN (`Session::regenerate()`). En production (`app.env = production`), préfixe `__Host-` sur les cookies (`Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`). |
| 7 | **Force brute & Limite de débit API** | **CONFORME** | Double compteur dans `RateLimiter` : 5 échecs / 15 min par compte ou profil PIN + 30 échecs / 15 min par adresse IP partagée (C2). Limiteur de débit sur toutes les routes API d'écriture (`ApiRateLimitMiddleware` + table `api_rate_limits`) : **120 requêtes / minute / utilisateur** (`HTTP 429 Too Many Requests` au-delà). |
| 8 | **Téléversement de fichiers** | **CONFORME** | `Uploader::uploadImage` (5 Mo max) et `Uploader::uploadDocument` (10 Mo max) : validation MIME réelle par `finfo(FILEINFO_MIME_TYPE)`, vérification de la signature magique `%PDF-` pour les PDF, ré-encodage GD supprimant toutes les métadonnées EXIF/GPS pour les images JPG/PNG, renommage aléatoire (`random_bytes(16)`), stockage hors de `public/` (`storage/uploads/`), calcul d'empreinte `SHA-256`. |
| 9 | **Traversée de chemin (Path Traversal)** | **CONFORME** | `MediaController::show` et `DocumentController::download` rejettent tout octet nul (`\0`), toute séquence `..` ou encodée (`%2e%2e`, `%2f`, `\`), appliquent `basename()` sur la catégorie et le fichier, et vérifient par `realpath()` que la cible résolue commence strictement par le répertoire `storage/uploads/`. |
| 10 | **Exposition d'informations** | **CONFORME** | En production (`app.env = production`, `app.debug = false`) : aucune trace de pile ni requête SQL affichée au client (`ErrorHandler` journalise dans `storage/logs/app.log` et renvoie une page générique en français), route `/design` désactivée (`404`), `bin/seed.php` bloqué (`exit(1)`), en-têtes `Server` et `X-Powered-By` masqués (`expose_php = Off`). |
| 11 | **Service Worker & Cache partagé (G3)** | **CONFORME** | `public/sw.js` restreint la mise en cache des navigations HTML à la liste blanche exacte `['/', '/today', '/journal']`, exclut toute réponse avec `Content-Disposition`, `/export`, `/media/` ou `/documents/`, et purge intégralement les caches dynamiques + la file IndexedDB à la déconnexion (`POST /logout`). |
| 12 | **Pointage & QR Code domicile** | **CONFORME** | Horodatage serveur faisant foi (`attendances.recorded_at`), jeton QR signé par `HMAC-SHA256` avec numéro de version révocable immédiatement (`AttendanceService::regenerateQrToken`), consentement RGPD préalable obligatoire (`attendance_gdpr_accepted_at`, `403 GDPR_NOTICE_REQUIRED`). |

---

## 2. En-têtes HTTP de sécurité vérifiés (`SecurityHeadersMiddleware`)

- `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'`
- `X-Content-Type-Options: nosniff` (également forcé sur tous les téléchargements de documents et d'exports CSV/RGPD)
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(self), camera=(self), microphone=()`
- `Strict-Transport-Security: max-age=63072000; includeSubDomains` (activé automatiquement lorsque `app.env = production` ou sous HTTPS)
- `Cache-Control: no-store` sur tous les fichiers médicaux servis (`/documents/{id}/download`, `/media/*`, `/reports/export/*`).

---

## 3. Résultats des tests automatisés de sécurité (`tests/Unit/Phase4Test.php`)

L'ensemble des contrôles ci-dessus est vérifié automatiquement à chaque exécution de `php tests/run.php` :
- **C1 (Appareils de confiance & PIN)** : rejet de `user_id` forgé, rejet de `selector:validator` altéré, verrouillage après 5 essais PIN erronés, révocation immédiate des jetons.
- **C2 (Force brute compte vs IP foyer)** : verrouillage du compte A après 5 échecs sans bloquer le compte B sur la même IP domestique (seuil IP = 30).
- **C5 (CSP stricte)** : `0` attribut `style=` en ligne sur les 40+ routes HTML testées.
- **Série IDOR complète** : blocage `403` d'une aide-soignante sur `/audit`, `/settings`, `/documents/{id}/delete`, `/reports`, `/reports/export/audit`, `/users/1/edit`, et sur la modification des saisies d'une collègue.
- **Fixation de session** : changement vérifié de `Session::id()` lors de `Auth::attempt()`.
- **Rate Limit API 120 req/min** : les 120 premières requêtes d'écriture sur la même minute sont acceptées, la 121e est rejetée (`HTTP 429`).
- **Path Traversal** : rejet en `403`/`404` de `..`, `..%2f..%2fconfig.php` et `test\0.pdf`.
