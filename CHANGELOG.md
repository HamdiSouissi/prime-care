# Changelog — Prime Care

Toutes les évolutions notables de **Prime Care** sont documentées dans ce fichier.

---

## [1.0.0] — 2026-11-14 (Mise en production — Phase 4)

### Ajouté (Phase 4 — Pilotage, finitions et mise en production)
- **Gestion des documents médicaux (`E22`, `/documents`, `/documents/{id}`)** :
  - Coffre-fort de documents classés en 6 catégories (*Ordonnance*, *Compte rendu*, *Analyse biologique*, *Courrier médical*, *Imagerie*, *Autre*).
  - Téléversement sécurisé `PDF` (vérification d'en-tête `%PDF-`), `JPG` et `PNG` (ré-encodage GD sans EXIF) jusqu'à **10 Mo** avec nom aléatoire dans `storage/uploads/documents/`.
  - Détection automatique des doublons par empreinte **`SHA-256`** avec avertissement non bloquant.
  - Synchronisation automatique des ordonnances jointes aux prescriptions de la Phase 2 dans la catégorie « Ordonnance ».
  - Visionneuse PDF/image intégrée et téléchargement protégé (`Cache-Control: no-store`, `X-Content-Type-Options: nosniff`).
  - Suppression définitive de tous les écrans temporaires « Bientôt disponible ».
- **Rapports médicaux imprimables A4 (`E23`, `/reports`) & Tendances 7 jours** :
  - Synthèse médicale complète sur période choisie (`7j`, `14j`, `30j`, `90j` ou dates personnalisées) : bilan glycémique (moyenne, min/max, `%` dans la cible, décompte des hypoglycémies `< 0,70 g/L` et hyperglycémies `> 2,50 g/L`, courbe SVG vectorielle avec seuils), constantes vitales avec mini-courbes SVG, tableau d'observance global et par médicament, bilan nutritionnel et événements marquants.
  - Feuille de style `@media print` dédiée au format **A4 portrait** (1 à 2 pages, masquage de la navigation, couleurs forcées pour l'impression).
  - Section **« Tendances 7 jours »** sur l'accueil administrateur avec flèches d'évolution (`↑`, `↓`, `→`) et compteurs animés (`[data-count-up]`, 400 ms).
- **Rapport RH & Correction du calcul mensuel (`/attendance/hours`)** :
  - Séparation stricte entre les **Heures planifiées terminées** et le **« Planifié restant ce mois-ci »** : l'écart prévu / pointé est désormais calculé uniquement sur les créneaux terminés.
  - Ajout des compteurs RH (retards d'arrivée, départs anticipés, pointages à valider, jours d'absence) et bouton d'impression A4.
- **Exports CSV & Archives RGPD (`/reports/exports`, `/reports/export/{type}`)** :
  - 9 exports CSV (`measures`, `intakes`, `meals`, `notes`, `care_checks`, `attendances`, `hours`, `alerts`, `audit`) avec BOM UTF-8 (`\xEF\xBB\xBF`), séparateur `;`, dates en heure de Paris (`JJ/MM/AAAA HH:MM`) et virgule décimale.
  - Contrôle RBAC : `admin` exporte tout, `viewer` exporte uniquement les 5 catégories médicales (`403` sur RH/audit).
  - Traçabilité systématique de chaque export dans `audit_logs`.
- **Journal d'audit (`E26`, `/audit`)** :
  - Interface de consultation paginée en lecture seule avec filtres par utilisateur, type d'action, entité et plage de dates.
  - Comparateur visuel **avant / après champ par champ** en français (`<del>` rouge / `<ins>` vert), sans jamais afficher de JSON brut.
  - Journalisation complète des connexions réussies/échouées et des révocations d'appareils de confiance.
- **Réglages unifiés (`E24`, `/settings`), Conservation RGPD & Pages légales** :
  - Regroupement de tous les réglages en 7 sections iOS (*Patient & Médical*, *Seuils d'alerte*, *Soins rapides*, *Pointage & Heures*, *Alertes & Notifications*, *Application*, *Conservation des données & RGPD*).
  - Commande `php cron/run.php retention:purge` (suppression logique puis physique après 30 jours sur les données non médicales ; **les données médicales ne sont jamais purgées automatiquement**).
  - Bouton **« Exporter mes données »** (`/profile/export-data`) et **« Exporter toutes les données du patient »** (`/settings/export-patient-data`).
  - Pages légales `/legal/mentions`, `/legal/privacy`, `/legal/attendance-notice` et modèles `docs/rgpd/MODELE_CONSENTEMENT_PATIENT.md` & `docs/rgpd/REGISTRE_DES_TRAITEMENTS.md`.
- **Mise en production, Santé & Exploitation HDS** :
  - Route `/healthz` sans authentification et bandeau d'alerte sur l'accueil admin si le cron a plus de 15 minutes de retard.
  - Assistant CLI `bin/setup.php`, scripts de sauvegarde chiffrée `AES-256-CBC` `bin/backup.sh` et `bin/restore.sh`.
  - Limiteur de débit API d'écriture (**120 requêtes / minute / utilisateur**, `HTTP 429`), HSTS en production (`max-age=63072000; includeSubDomains`), cookies `__Host-`, blocage de `/design` (`404`) et de `bin/seed.php` en production.

---

## [0.3.0] — 2026-11-14 (Phase 3 — Planning, Pointage, Heures, Alertes & Mode Hors-Ligne)
- Planning des gardes (Semaine/Mois, détection de chevauchement, duplication sur `N` semaines, absences « À remplacer »).
- Pointage d'arrivée/départ (bouton glissant, GPS Haversine 150 m, QR code signé HMAC révocable, déclaration d'oubli, notice RGPD `G2`).
- Calculateur d'heures et paie (nuit `21h–06h`, dimanche, 11 jours fériés français, changements d'heure été/hiver `11h`/`13h`).
- Moteur d'alertes dédoublonnées (`dedupe_key`), escalade après 15 min, Web Push VAPID RFC 8291 (`ES256` + `aes128gcm`) et file `notification_queue`.
- Mode hors-ligne PWA (file IndexedDB + synchronisation `client_uuid` + horodatage `G1` + liste blanche `sw.js` `G3`).

---

## [0.2.0] — 2026-11-14 (Phase 2 — Suivi Médical Quotidien)
- Constantes médicales (glycémie `g/L` & `mg/dL`, seuils contextuels, ouverture automatique du protocole d'hypoglycémie `< 0,70 g/L`, courbes ApexCharts).
- Traitements, génération idempotente sur 48h (`Europe/Paris` -> `UTC`), anti-double-prise `HTTP 409`, grille d'insuline du responsable et rotation des 6 sites d'injection.
- Repas, hydratation, favoris alimentaires et photos ré-encodées sans EXIF.
- Journal de bord unifié, soins rapides en 1 touche, transmission de relève et fenêtre de modification de 30 minutes (`EditWindowService`).

---

## [0.1.0] — 2026-11-14 (Phase 1 — Socle, Identité, Authentification & Équipe)
- Architecture MVC PHP 8.2+ native sans dépendance externe, routeur HTTP, CSRF temps constant, CSP stricte sans `unsafe-inline`.
- Authentification mot de passe + code PIN sur appareils de confiance (`C1`), anti-force-brute compte vs IP foyer (`C2`).
- Design System *Proposition B — Bleu nuit* (`#0A1633`, dégradé `45deg` `#5E5CE6 -> #BF5AF2`), mode clair/sombre, fiche patient et gestion d'équipe.
