# Guide de Déploiement en Production & Hébergement HDS — Prime Care v1.0.0

Prime Care traite des données de santé à caractère personnel (glycémies, prescriptions d'insuline, constantes vitales, comptes rendus médicaux). Conformément à l'article **L.1111-8 du Code de la santé publique** et au RGPD (Art. 9 et 32), tout hébergement externalisé en production doit reposer sur une infrastructure certifiée **HDS (Hébergeur de Données de Santé)** située en France ou dans l'Union européenne.

---

## 1. Choix d'un hébergeur certifié HDS en France

Quatre fournisseurs français disposent de la certification HDS (colonnes 1 à 6) adaptée à une instance dédiée ou machine virtuelle isolée :
1. **OVHcloud Healthcare** (Roubaix / Gravelines / Strasbourg) — Gamme *Hosted Private Cloud Healthcare* ou instances HDS isolées.
2. **Scaleway Dedibox / Elements HDS** (Paris / Saint-Ouen-l'Aumône) — Serveurs dédiés et instances Cloud qualifiées HDS.
3. **3DS Outscale** (Cloud souverain certifié HDS & SecNumCloud, région `eu-west-2` Paris).
4. **Claranet Santé** / **GPLExpert Santé** (infogérance HDS clé en main en France).

### Dimensionnement recommandé (1 patient, 5 à 15 utilisateurs)
- **Système** : Debian 12 LTS ou Ubuntu 24.04 LTS.
- **CPU / RAM** : 2 vCPU, 4 Go RAM.
- **Stockage** : 40 Go SSD NVMe chiffré au repos (`LUKS` / chiffrement disque HDS) + volume externe de sauvegarde chiffrée.

---

## 2. Pare-feu système (UFW / nftables)

N'autoriser que les ports strictement nécessaires :
```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH (authentification par clé Ed25519 uniquement, PasswordAuthentication no)
ufw allow 80/tcp    # HTTP (redirection 301 vers HTTPS + challenge ACME Let's Encrypt)
ufw allow 443/tcp   # HTTPS (TLS 1.2 / 1.3)
ufw enable
```
MySQL/MariaDB doit écouter **exclusivement** sur `127.0.0.1:3306` (`bind-address = 127.0.0.1`).

---

## 3. Configuration de PHP-FPM (`php.ini` de production)

Dans `/etc/php/8.4/fpm/php.ini` :
```ini
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/www/prime-care/storage/logs/php-error.log

upload_max_filesize = 12M
post_max_size = 14M
memory_limit = 256M
max_execution_time = 30
date.timezone = UTC

session.use_strict_mode = 1
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.use_only_cookies = 1

opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.validate_timestamps = 0
```

---

## 4. Configuration de MySQL / MariaDB (`/etc/mysql/mariadb.conf.d/50-server.cnf`)

```ini
[mysqld]
bind-address = 127.0.0.1
port = 3306
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
default-time-zone = '+00:00'
innodb_buffer_pool_size = 1G
innodb_flush_log_at_trx_commit = 1
slow_query_log = 1
long_query_time = 1.0
```

Création de la base et de l'utilisateur applicatif à privilèges restreints :
```sql
CREATE DATABASE prime_care CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'primecare_app'@'127.0.0.1' IDENTIFIED BY 'MotDePasseFortAleatoire32Caracteres!';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON prime_care.* TO 'primecare_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

---

## 5. Configuration du serveur Web (Nginx + TLS 1.3)

Seul le dossier `/var/www/prime-care/public` doit être exposé comme `root` du serveur Web. Les dossiers `app/`, `config/`, `database/`, `storage/` et `bin/` sont situés hors de la racine publique.

```nginx
server {
    listen 80;
    server_name primecare.exemple.fr;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name primecare.exemple.fr;

    root /var/www/prime-care/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/primecare.exemple.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/primecare.exemple.fr/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;

    client_max_body_size 12M;
    server_tokens off;

    # Compression Gzip / Brotli pour les ressources statiques
    gzip on;
    gzip_types text/css application/javascript image/svg+xml application/json;

    # Interdire tout accès direct aux fichiers cachés (.git, .env, etc.)
    location ~ /\. {
        deny all;
    }

    # Cache long sur les polices et ressources statiques versionnées
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Point d'entrée unique : front-controller public/index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```
> **Apache 2.4** : Si vous utilisez Apache, le fichier `public/.htaccess` fourni redirige déjà toutes les requêtes vers `public/index.php` et bloque l'exécution de scripts dans les dossiers de médias.

---

## 6. Installation de l'application avec `bin/setup.php`

```bash
cd /var/www/prime-care
sudo -u www-data php bin/setup.php
```
L'assistant :
1. Vérifie les extensions PHP et les permissions d'écriture sur `storage/uploads/`, `storage/logs/`, `storage/cache/`, `storage/backups/`.
2. Génère `config/config.php` avec `app.env = 'production'`, `app.debug = false`, `security.force_https = true`, une clé HMAC de 64 caractères hexadécimaux et les clés VAPID Web Push P-256.
3. Restreint les permissions de `config/config.php` à `0600`.
4. Exécute les 11 migrations SQL (`001` à `011`).
5. Crée le premier compte administrateur et la fiche patient.

---

## 7. Tâches planifiées (`crontab`), Chiffrement (`age` / OpenSSL) & Copie distante (`BACKUP_REMOTE`)

Créez d'abord le fichier d'environnement hors du dépôt `/etc/prime-care/backup.env` (`chmod 600`, propriétaire `www-data:www-data`) :
```bash
# /etc/prime-care/backup.env
# Si le binaire `age` est présent sur le serveur et AGE_RECIPIENT renseigné, bin/backup.sh chiffre avec `age` ;
# sinon il utilise automatiquement `openssl enc -aes-256-cbc -pbkdf2`.
AGE_RECIPIENT="age1..."
AGE_IDENTITY="/etc/prime-care/age.key"
PRIME_CARE_BACKUP_KEY="VotreCleDeChiffrementHDSLongueEtSecrete"

# Cible rsync ou sftp:// pour la copie automatique de l'archive chiffrée vers un second emplacement HDS
BACKUP_REMOTE="backup-hds@second-site-hds.exemple.fr:/var/backups/prime-care"
```

Éditez ensuite la crontab de l'utilisateur `www-data` (`crontab -u www-data -e`) :
```cron
# Exécution toutes les minutes du moteur de planification, d'alertes, d'envoi Web Push/SMTP et de purge RGPD
* * * * * /usr/bin/php /var/www/prime-care/cron/run.php >> /var/www/prime-care/storage/logs/cron.log 2>&1

# Sauvegarde quotidienne chiffrée à 03h15 avec copie distante BACKUP_REMOTE et rotation locale à 30 jours
15 3 * * * /var/www/prime-care/bin/backup.sh >> /var/www/prime-care/storage/logs/backup.log 2>&1
```

---

## 8. Procédure de mise à jour sans interruption

```bash
cd /var/www/prime-care
# 1. Sauvegarde de sécurité avant mise à jour
PRIME_CARE_BACKUP_KEY="..." ./bin/backup.sh

# 2. Récupération de la nouvelle version
git fetch origin --tags
git checkout v1.0.1

# 3. Application des nouvelles migrations SQL éventuelles
sudo -u www-data php bin/migrate.php

# 4. Rechargement du cache OPcache de PHP-FPM
sudo systemctl reload php8.4-fpm
```
