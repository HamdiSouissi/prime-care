# Prime Care — Suivi de soins à domicile

> **Prime Care** est une application web progressive (PWA) mobile-first de suivi de soins à domicile 24h/24 pour patient âgé diabétique, conçue avec une direction artistique inspirée d’**Apple Santé (iOS 17/18)**.  
> Éditeur : **[Prime Vision Solutions](https://primevisionsolutions.fr)**

---

## 1. Caractéristiques techniques (Zéro framework · Zéro outil de build)

- **Backend** : PHP 8.2+ natif (`declare(strict_types=1);` dans 100 % des fichiers, PSR-12, MVC sur mesure dans `app/`).
- **Base de données** : MySQL 8.0+ / MariaDB (`utf8mb4_unicode_ci`, InnoDB, clés étrangères `ON DELETE RESTRICT`, stockage des dates en UTC et affichage en `Europe/Paris`, suppression logique `deleted_at` et journal d’audit `audit_logs` systématique).
- **Frontend** : HTML5 sémantique, CSS3 moderne structuré par `@layer reset, tokens, base, components, utilities, pages;`, JavaScript ES2020+ modulaire (`type="module"`) sans compilateur ni bundler.
- **Zéro CDN (Confidentialité des données de santé)** : Polices (`Inter Variable` WOFF2) et icônes (`Lucide Static` v1.73.0) intégralement auto-hébergées dans `public/assets/`.
- **Sécurité renforcée** :
  - Requêtes préparées PDO exclusives (`PDO::ATTR_EMULATE_PREPARES = false`).
  - Hachage `Argon2id` (fallback `bcrypt`) pour les mots de passe et codes PIN.
  - Protection CSRF (`hash_equals`) sur toutes les requêtes mutantes (`POST`, `PUT`, `PATCH`, `DELETE`).
  - En-têtes HTTP de sécurité stricts (`Content-Security-Policy` avec `nonce` aléatoire par requête sans `unsafe-inline` pour les scripts, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options: DENY` en production).
  - Limitation de débit (`RateLimiter`) : blocage 15 minutes après 5 tentatives échouées par identifiant ou adresse IP.

---

## 2. Prérequis

- **PHP 8.2 ou supérieur** avec les extensions : `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `openssl`, `intl`, `exif`, `sodium`.
- **MySQL 8.0+** (ou MariaDB 10.6+ / MySQL 5.7+ compatible JSON & `utf8mb4`).
- Serveur web **Apache 2.4+** (`mod_rewrite` activé, `DocumentRoot` pointant sur `public/`) ou **Nginx** (voir `docs/nginx.example.conf`).

---

## 3. Installation rapide

```bash
# 1. Copier le fichier de configuration et ajuster les accès MySQL
cp config/config.example.php config/config.php

# 2. (Optionnel) Régénérer les icônes PWA PNG et splash screens iOS à partir des PNG de marque
php bin/make-icons.php

# 3. Exécuter les migrations (001 à 006) et injecter les 14 jours de données de démonstration (Phases 1 & 2)
php bin/migrate.php --fresh --seed

# 4. Générer les occurrences de prises et mesures attendues sur 48h (idempotent)
php cron/run.php schedule:generate

# 5. Vérifier l'état des migrations
php bin/migrate.php --status

# 6. Lancer la suite de tests automatisés
php tests/run.php

# 7. Démarrer le serveur de développement local
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public public/index.php
```

### Tâche planifiée (Crontab de production)

Pour générer automatiquement les prises de médicaments (`medication_intakes`) et mesures attendues (`measure_expectations`) sur les prochaines 48 heures (avec verrou fichier anti-concurrence dans `storage/cache/cron.lock`) :

```cron
*/5 * * * * php /chemin/vers/prime-care/cron/run.php schedule:generate >> /chemin/vers/prime-care/storage/logs/cron.log 2>&1
```

> En complément, l'ouverture de l'écran **Accueil (`/`)** ou **Aujourd'hui (`/today`)** déclenche automatiquement un filet de sécurité idempotent si le cron n'a pas tourné récemment.

### Tester l’installation mobile PWA en HTTPS (iOS & Android)

Les navigateurs mobiles (Chrome Android pour `beforeinstallprompt` / WebAPK et Safari iOS pour le Service Worker) exigent une origine sécurisée (`https://`) lorsqu’on accède au serveur depuis un téléphone physique :

1. **Option 1 — Cloudflare Quick Tunnel (sans compte, en 5 secondes)** :
   ```bash
   cloudflared tunnel --url http://localhost:8080
   ```
   Ouvrez l'URL `https://xxxx.trycloudflare.com/install` sur le téléphone ou scannez le QR code affiché sur `/install`.
2. **Option 2 — Tailscale Funnel / Serve** :
   ```bash
   tailscale serve --bg 8080
   ```
3. **Option 3 — Certificat local `mkcert` sur réseau Wi-Fi local** :
   ```bash
   mkcert -install && mkcert 192.168.1.XX localhost
   ```

---

## 4. Comptes de démonstration (Phase 1)

| Rôle | Nom complet | Adresse e-mail | Mot de passe | Code PIN | Permissions principales |
|---|---|---|---|---|---|
| **Administrateur (`admin`)** | Karim Benali | `admin@primecare.test` | `Admin!2026` | `1111` | Accès intégral : équipe, taux horaires, édition fiche patient, `/design` |
| **Coordinatrice (`coordinator`)** | Sofia Marchetti | `coord@primecare.test` | `Coord!2026` | `2222` | Vue « Aujourd’hui », coordination planning & traitements |
| **Aide-soignante (`caregiver`)** | Fatou Diallo | `fatou@primecare.test` | `Soin!2026` | `3333` | Saisie rapide (`+`), vue « Aujourd’hui », journal, fiche patient |
| **Aide-soignante (`caregiver`)** | Marie Lefèvre | `marie@primecare.test` | `Soin!2026` | `4444` | Garde de nuit & week-end, saisie rapide (`+`), fiche patient |
| **Lecture seule (`viewer`)** | Dr Laurent Petit | `medecin@primecare.test` | `Lecture!2026` | `5555` | Consultation du dossier médical et de la fiche patient |

**Patient de démonstration** : **Henri Benali** (84 ans, né le 18/03/1942, diabète de type 2 insulinotraité, 4 contacts d’urgence, consignes permanentes et protocole d’hypoglycémie).

---

## 5. Architecture du dépôt

```text
prime-care/
├── app/
│   ├── Core/           # 16 briques du mini-framework (Router, Database, Auth, Csrf, View, Policy...)
│   ├── Controllers/    # Contrôleurs HTTP (Auth, Home, Profile, User, Patient, Design, Media)
│   ├── Models/         # Modèles Active-Record légers (User, Patient, PatientContact, Setting)
│   ├── Services/       # Services métier (Policy RBAC, AuditService, MailService)
│   ├── Middleware/     # Middlewares HTTP (Auth, Guest, Role, Csrf, SecurityHeaders)
│   ├── Views/          # Layouts, partials et vues PHP (strictement échappées via e())
│   └── Helpers/        # Autoloader PSR-4 natif (bootstrap.php) et fonctions globales (functions.php)
├── bin/                # Scripts CLI (migrate.php, seed.php, make-icons.php)
├── config/             # Configuration applicative (config.example.php)
├── database/           # Migrations SQL numérotées et seeders PHP
├── docs/               # Cahier des charges, Design System, Nginx et retours de phases
├── lang/               # Dictionnaire français (lang/fr.php)
├── public/             # Unique point d'entrée web (index.php, .htaccess, sw.js, manifest, assets)
├── routes/             # Définition des routes web.php et api.php
├── storage/            # Logs quotidiens, e-mails simulés (mail.log) et uploads privés
└── tests/              # Suite de tests unitaires et d'intégration (tests/run.php)
```

---

## 6. Licence & Crédit

Application créée par **[Prime Vision Solutions](https://primevisionsolutions.fr)**. Tous droits réservés.
