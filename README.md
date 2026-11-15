# Prime Care — Suivi Médical, Traitements & Coordination 24h/24 à Domicile

**Version : 1.0.0** — Application Web Progressive (PWA) mobile-first conçue pour la prise en charge 24h/24 à domicile d'un patient âgé diabétique (**M. Henri Benali**), coordonnant en temps réel les aides-soignantes, l'aide-soignante référente, le responsable familial et le médecin traitant.

---

## Fonctionnalités principales (Phases 1 à 4)

1. **Socle, Identité & Sécurité (Phase 1)**
   - Authentification complète par e-mail/mot de passe (`Argon2id`/`bcrypt`) et connexion rapide par **code PIN à 4–6 chiffres** réservée aux appareils de confiance (`selector:validator` SHA-256).
   - 4 rôles stricts (RBAC) : **Administrateur / Responsable** (`admin`), **Coordinatrice** (`coordinator`), **Aide-soignante** (`caregiver`), **Lecteur médical / famille** (`viewer`).
   - Fiche patient complète, protocole d'urgence hypoglycémie/hyperglycémie accessible en 1 geste depuis tout écran, annuaire d'équipe.
2. **Suivi Médical Quotidien (Phase 2)**
   - **Constantes médicales** : glycémie capillaire (`g/L` ou `mg/dL`, seuils selon le contexte *à jeun* / *post-prandial*, ouverture automatique du protocole d'urgence si `< 0,70 g/L`), tension artérielle, température, SpO₂, fréquence cardiaque, poids, douleur EVA, diurèse.
   - **Traitements & Insuline** : génération idempotente des prises sur 48h glissantes (`Europe/Paris` -> `UTC`), anti-double-prise (`HTTP 409`), grille de lecture d'insuline du responsable et rotation visuelle des 6 sites d'injection.
   - **Repas & Hydratation** : détection automatique du type de repas selon l'heure, favoris alimentaires en 1 touche, photo de repas ré-encodée sans EXIF.
   - **Journal de bord, Soins rapides & Relève** : frise chronologique unifiée, soins quotidiens en 1 touche, transmission de relève avec récapitulatif automatique du poste.
3. **Planning, Pointage, Alertes & Mode Hors-Ligne (Phase 3)**
   - **Planning des gardes** : vues Semaine et Mois, détection des chevauchements, duplication sur `N` semaines, gestion des congés/absences avec bascule automatique « À remplacer ».
   - **Pointage d'arrivée / départ** : bouton glissant anti-fausse manipulation, vérification GPS Haversine (jamais bloquante) ou scan du QR code signé HMAC du domicile, déclaration d'oubli avec motif.
   - **Calcul des heures & Paie** : découpage automatique la minute près des heures de nuit (`21h–06h`), de dimanche, des 11 jours fériés français et des changements d'heure été/hiver (`11h` / `13h`), écart prévu/pointé sur les créneaux terminés et « Planifié restant ce mois-ci ».
   - **Moteur d'alertes & Web Push VAPID** : alertes automatiques dédoublonnées (`dedupe_key`), fermeture automatique dès réalisation du soin, escalade après 15 min, Web Push RFC 8291 (`ES256` + `aes128gcm`) natif et e-mail SMTP.
   - **Mode hors-ligne (PWA)** : file d'attente locale IndexedDB avec rejeu idempotent par `client_uuid`.
4. **Pilotage, Documents, Rapports, Audit & Production (Phase 4)**
   - **Documents médicaux** : coffre-fort de documents (`PDF`, `JPG`, `PNG` jusqu'à 10 Mo), détection de doublon `SHA-256`, synchronisation des ordonnances jointes aux prescriptions, visionneuse intégrée.
   - **Rapports médicaux imprimables A4 & Exports CSV** : synthèse médicale 1–2 pages A4 pour la consultation diabétologique, tendances 7 jours sur l'accueil responsable, 9 types d'exports CSV (`UTF-8 BOM`, séparateur `;`) et archives RGPD.
   - **Journal d'audit** : traçabilité complète avec comparateur visuel avant / après champ par champ (`<del>` / `<ins>`).
   - **Outils d'exploitation HDS** : `/healthz`, assistant CLI `bin/setup.php`, sauvegardes chiffrées `AES-256` (`bin/backup.sh` / `bin/restore.sh`), purge RGPD (`retention:purge`).

---

## Prérequis techniques

- **PHP 8.2, 8.3 ou 8.4** avec les extensions : `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`, `openssl`, `zip`.
- **MySQL 8.0+** ou **MariaDB 10.6+** (`InnoDB`, `utf8mb4_unicode_ci`).
- Serveur Web **Nginx** ou **Apache 2.4** avec HTTPS (TLS 1.2 / 1.3).
- Aucune dépendance externe Composer ni framework tiers (architecture MVC PHP natif 100 % autonome).

---

## Installation rapide

### 1. En développement local

```bash
# 1. Créer la configuration locale
cp config/config.example.php config/config.php

# 2. Appliquer les 11 migrations SQL et insérer les 30 jours de données de démonstration
php bin/migrate.php --fresh --seed

# 3. Lancer la suite de tests automatisés (210 assertions)
php tests/run.php

# 4. Démarrer le serveur de développement
php -S 0.0.0.0:8080 -t public
```

### 2. En production (assistant interactif)

```bash
php bin/setup.php
```
L'assistant vérifie la version de PHP et les extensions requises, teste la connexion MySQL, génère la clé secrète HMAC et la paire de clés VAPID P-256 (`ES256`), écrit `config/config.php` (`chmod 600`), exécute toutes les migrations SQL, crée le premier compte administrateur et la fiche patient, puis affiche la ligne `crontab` à activer.

---

## Commandes utiles

| Commande | Description |
|---|---|
| `php bin/migrate.php` | Applique les migrations SQL en attente (`database/migrations/001..011.sql`) |
| `php bin/migrate.php --fresh --seed` | Réinitialise la base et charge les données de démonstration (bloqué en production) |
| `php bin/seed.php` | Recharge les données de démonstration (bloqué si `app.env = production`) |
| `php bin/setup.php` | Assistant d'installation initiale en production |
| `php cron/run.php` | Exécute toutes les tâches planifiées (`schedule:generate`, `alerts:scan`, `notifications:send`, `cleanup`, `retention:purge`) |
| `php cron/run.php retention:purge` | Exécute uniquement la purge RGPD des anciennes données hors médical |
| `PRIME_CARE_BACKUP_KEY="..." ./bin/backup.sh` | Crée une sauvegarde chiffrée `AES-256-CBC` (base MySQL + `storage/uploads/`) dans `storage/backups/` |
| `PRIME_CARE_BACKUP_KEY="..." ./bin/restore.sh <archive>` | Déchiffre et restaure une sauvegarde complète |
| `php tests/run.php` | Exécute la suite complète de tests unitaires et d'intégration sur la base isolée `prime_care_test` |

---

## Comptes de démonstration

| Prénom & Nom | Rôle | E-mail | Mot de passe | Code PIN (appareil de confiance) |
|---|---|---|---|---|
| **Karim Benali** | Administrateur / Responsable (`admin`) | `admin@primecare.test` | `Admin!2026` | `1111` |
| **Sofia Marchetti** | Aide-soignante référente (`coordinator`) | `coord@primecare.test` | `Coord!2026` | `2222` |
| **Fatou Diallo** | Aide-soignante jour (`caregiver`) | `fatou@primecare.test` | `Soin!2026` | `3333` |
| **Marie Lefèvre** | Aide-soignante soir / week-end (`caregiver`) | `marie@primecare.test` | `Soin!2026` | `4444` |
| **Dr Laurent Petit** | Médecin traitant / Lecteur (`viewer`) | `medecin@primecare.test` | `Lecture!2026` | `5555` |

---

## Documentation complète (`docs/`)

- [`docs/DEPLOIEMENT_HDS.md`](docs/DEPLOIEMENT_HDS.md) — Guide de déploiement sur hébergeur certifié HDS en France
- [`docs/EXPLOITATION.md`](docs/EXPLOITATION.md) — Guide d'exploitation, supervision `/healthz`, sauvegardes et procédures d'urgence
- [`docs/GUIDE_AIDE_SOIGNANTE.md`](docs/GUIDE_AIDE_SOIGNANTE.md) — Guide terrain 2 pages pour les aides-soignantes
- [`docs/GUIDE_RESPONSABLE.md`](docs/GUIDE_RESPONSABLE.md) — Guide complet pour le responsable familial et la coordinatrice
- [`docs/SECURITE.md`](docs/SECURITE.md) — Rapport d'audit de sécurité (Phase 4 §6.3)
- [`docs/ACCESSIBILITE.md`](docs/ACCESSIBILITE.md) — Rapport d'audit d'accessibilité WCAG AA & `axe-core` (Phase 4 §6.4)
- [`docs/DESIGN_SYSTEM.md`](docs/DESIGN_SYSTEM.md) — Charte graphique *Sérénité Nocturne & Clarté Clinique*
- [`docs/rgpd/REGISTRE_DES_TRAITEMENTS.md`](docs/rgpd/REGISTRE_DES_TRAITEMENTS.md) & [`docs/rgpd/MODELE_CONSENTEMENT_PATIENT.md`](docs/rgpd/MODELE_CONSENTEMENT_PATIENT.md) — Modèles RGPD prêts à compléter
- [`CHANGELOG.md`](CHANGELOG.md) — Historique des versions jusqu'à la `1.0.0`
