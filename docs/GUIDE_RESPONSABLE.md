# Guide du Responsable & de la Coordinatrice — Prime Care v1.0.0

Ce guide s'adresse au **responsable familial** (`admin`, M. Karim Benali) et à l'**aide-soignante référente / coordinatrice** (`coordinator`, Mme Sofia Marchetti) pour le pilotage quotidien de la prise en charge de M. Henri Benali.

---

## 1. Gérer l'équipe et les accès (`/users`)

Depuis **Plus > Équipe & Utilisateurs** :
- **Créer un compte** (`/users/create`) : renseignez le prénom, le nom, l'e-mail, le téléphone, le rôle, le type de contrat (`CDI`, `CESU`, `Libéral`, `Famille`, `Médecin`), le volume horaire hebdomadaire, le taux horaire brut (visible uniquement par l'administrateur), le mot de passe initial et le code PIN à 4 chiffres.
- **Rôles disponibles** :
  - `admin` : accès complet (équipe, réglages médicaux, paie, audit, suppression de documents).
  - `coordinator` : gestion du planning, validation des pointages, traitement des alertes, rapports et exports médicaux/RH.
  - `caregiver` : saisie quotidienne (constantes, prises, repas, soins, notes), consultation de son propre planning et pointage.
  - `viewer` : consultation médicale en lecture seule, impression du rapport médical A4 et exports CSV médicaux (idéal pour le médecin traitant).
- **Sécurité des appareils** : depuis la fiche d'un membre (`/users/{id}`), vous visualisez ses appareils de confiance actifs et pouvez les révoquer en 1 clic.

---

## 2. Configurer les traitements, la grille d'insuline et les documents (`/treatments` & `/documents`)

### Catalogue de médicaments et prescriptions (`/admin/treatments`)
- Créez chaque prescription avec sa posologie, ses créneaux horaires (`08:00`, `12:30`, `20:00`) ou son mode **« Si besoin »** (avec délai minimum obligatoire entre 2 prises et maximum par 24h), et joignez le scan PDF ou photo de l'ordonnance.
- **Grille d'insuline du responsable** : configurez les tranches de glycémie (ex. `1,30 à 1,80 g/L -> 4 UI`, `1,81 à 2,50 g/L -> 6 UI`) pour que la dose exacte prescrite s'affiche en lecture directe aux aides-soignantes lors de la saisie.
- À chaque modification d'horaire, les prises futures en attente (`pending`) sur les 48 prochaines heures sont régénérées automatiquement sans toucher aux prises déjà validées.

### Coffre-fort de documents médicaux (`/documents`)
- Les ordonnances jointes aux prescriptions apparaissent automatiquement dans la catégorie **« Ordonnance »**.
- Déposez les **Comptes rendus**, **Analyses biologiques (HbA1c)**, **Courriers médicaux** et **Imageries** (`PDF`, `JPG`, `PNG` jusqu'à 10 Mo). L'application vous avertit automatiquement si un fichier identique (`SHA-256`) a déjà été déposé.

---

## 3. Planifier les gardes et gérer les remplacements (`/planning`)

Depuis **Plus > Planning des gardes** :
- Basculez entre la vue **Semaine** et la vue **Mois**.
- **Créer un créneau** : choisissez l'aide-soignante, le jour, l'heure de début et de fin (les gardes de nuit traversant minuit, ex. `20:00 -> 08:00`, sont gérées nativement). Tout chevauchement pour la même intervenante est bloqué automatiquement.
- **Dupliquer une semaine type** : copiez en 1 clic le planning de la semaine affichée sur `1` à `8` semaines suivantes.
- **Déclarer un congé ou une absence maladie** : dès la validation de l'absence, tous les créneaux de l'intervenante sur la période passent automatiquement au statut orange **« À remplacer »** pour que vous puissiez les réaffecter.

---

## 4. Valider les pointages et exporter les heures pour la paie (`/attendance` & `/attendance/hours`)

### Validation des pointages (`/attendance`)
- Les pointages effectués au domicile (GPS `< 150 m` ou QR code valide) sont validés automatiquement (**Vert**).
- Les pointages hors rayon, sans GPS ou déclarés après oubli apparaissent en **« À valider »** : vérifiez le motif et cliquez sur **Valider** ou **Refuser**.

### Récapitulatif mensuel & Paie (`/attendance/hours`)
- Le tableau mensuel distingue clairement :
  - les **Heures planifiées terminées** et les **Heures pointées**, sur lesquelles est calculé l'**Écart prévu / pointé** ;
  - le **« Planifié restant ce mois-ci »** (créneaux futurs non encore échus), afin de ne jamais afficher un écart négatif trompeur en début de mois.
- Les heures sont ventilées automatiquement entre **Jour**, **Nuit (21h–06h)**, **Dimanche** et **Jours fériés français** (11 fériés calculés automatiquement, changements d'heure été/hiver inclus).
- Cliquez sur **« Exporter CSV Paie »** pour obtenir le fichier directement ouvrable dans Excel / CESU (`UTF-8 BOM`, séparateur `;`).

---

## 5. Traiter les alertes et superviser les tendances (`/alerts` & Accueil)

- Sur votre **Accueil**, la carte **« Tendances 7 jours »** compare automatiquement les 7 derniers jours aux 7 jours précédents (glycémie moyenne, `%` dans la cible, observance, hypoglycémies).
- Dans **Alertes** (`/alerts`) :
  - Les alertes d'oubli (mesure ou prise en retard) se ferment **automatiquement** dès que l'aide-soignante réalise l'action.
  - Les alertes critiques non prises en charge après 15 minutes vous sont automatiquement **escaladées** par notification Web Push et e-mail (même pendant les heures calmes `22h00–07h00`).

---

## 6. Imprimer le rapport médical pour le médecin (`/reports`)

Avant chaque consultation diabétologique ou visite du Dr Petit :
1. Ouvrez **Plus > Rapports & Synthèse** (`/reports`).
2. Sélectionnez la période (**7 jours**, **14 jours**, **30 jours**, **90 jours** ou dates personnalisées).
3. Cliquez sur **« Imprimer / Enregistrer en PDF »** : la mise en page **A4 portrait** masque automatiquement la navigation et imprime sur 1 à 2 pages l'en-tête patient, le bilan glycémique (moyenne, min/max, `%` dans la cible, courbe SVG avec seuils), les constantes vitales, le tableau d'observance par médicament, le bilan nutritionnel et les événements marquants.
