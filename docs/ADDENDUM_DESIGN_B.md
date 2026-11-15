# Additif de Direction Artistique — Proposition B « Bleu nuit »

> **Document de référence prioritaire sur le §6 du brief pour tous les points qu’il traite.**  
> **Emplacement** : `docs/ADDENDUM_DESIGN_B.md`

---

## 1. Positionnement visuel : « Proposition B — Bleu nuit »

L’interface de **Prime Care** adopte la direction artistique **Proposition B — Bleu nuit** :
- Une identité médicale rassurante et haut de gamme articulée autour du **Bleu nuit Prime Care (`#0A1633`)**, du **dégradé signature Indigo → Violet à 45° (`linear-gradient(45deg, #5E5CE6, #BF5AF2)`)** et de surfaces épurées à **rayon 24 px sans ombre portée**.
- Contrastes francs, hiérarchie immédiate à une main, lisibilité maximale pour l’équipe d’aides-soignantes de jour comme de garde de nuit.

---

## 2. Palette & Tokens CSS (`public/assets/css/tokens.css`)

| Token | Mode Clair | Mode Sombre | Rôle |
|---|---|---|---|
| `--bg` | `#F4F7FC` | `#060B1A` | Fond général de l’application |
| `--surface` / `--surface-1` | `#FFFFFF` | `#111A33` | Surface primaire des cartes (24 px, sans ombre) |
| `--surface-2` | `#EEF2F9` | `#172342` | Surface secondaire (champs, pastilles, groupes) |
| `--surface-3` | `#E2E8F4` | `#1F2E54` | Surface tertiaire / états actifs |
| `--header` | `#0A1633` | `#0A1633` | En-tête Bleu nuit profond Prime Care (identique dans les deux modes) |
| `--header-2` | `#11224D` | `#11224D` | Nuance secondaire de l’en-tête Bleu nuit |
| `--brand` | `#5E5CE6` | `#7D7AFF` | Indigo Prime Care |
| `--brand-purple` | `#BF5AF2` | `#BF5AF2` | Violet signature du dégradé |
| `--brand-gradient` | `linear-gradient(45deg, #5E5CE6, #BF5AF2)` | `linear-gradient(45deg, #5E5CE6, #BF5AF2)` | Dégradé officiel 45° (`#5E5CE6 → #BF5AF2`) |
| `--r-card` | `24px` | `24px` | Rayon unique des cartes |
| `--shadow-card` | `none` | `none` | Aucune ombre portée sur les cartes (bordure fine `--border`) |

### Palette fixe d’avatars sans style en ligne (`.pc-avatar--c1` à `.pc-avatar--c8`)
Pour respecter une politique CSP stricte sans `unsafe-inline`, la couleur d’avatar est déterminée côté serveur à partir de l’identifiant (`avatar_color_class(int $userId)`) parmi 8 classes CSS :
- `.pc-avatar--c1` : `#5E5CE6` (Indigo)
- `.pc-avatar--c2` : `#00C7BE` (Turquoise)
- `.pc-avatar--c3` : `#AF52DE` (Pourpre)
- `.pc-avatar--c4` : `#FF9F0A` (Ambre)
- `.pc-avatar--c5` : `#34C759` (Émeraude)
- `.pc-avatar--c6` : `#0A84FF` (Azur)
- `.pc-avatar--c7` : `#FF2D55` (Corail)
- `.pc-avatar--c8` : `#5856D6` (Outremer)

---

## 3. Cartes à 24 px sans ombre

- Toutes les cartes (`.pc-card`, `.pc-list-group`, `.pc-health-card`) utilisent un rayon de **`24px`** (`var(--r-card)`), **`box-shadow: none`** et une bordure subtile `1px solid var(--border)`.

---

## 4. En-tête d’accueil Bleu nuit pleine largeur

- L’écran d’accueil s’ouvre sur un **bloc pleine largeur en haut de l’écran** (`.pc-home-hero`), qui passe sous la barre d’état (`safe-area-inset-top`).
- Coins **bas** arrondis à **32 px** (`border-radius: 0 0 32px 32px`), deux halos lumineux (`#5E5CE6` et `#BF5AF2`).
- Contenu en blanc : monogramme blanc de 30 px avec la date, boutons ronds translucides (urgences et profil), salutation *« Bonjour Fatou »* (sans grand titre « Accueil » au-dessus), puis la carte patient vitrée (**Henri Benali · 84 ans**).
- `theme-color` à `#0A1633` sur cette page, avec `apple-mobile-web-app-status-bar-style` à `black-translucent`.
- Les cartes du contenu commencent directement sous l’en-tête.

---

## 5. Barre d’onglets pilule Bleu nuit

- Une **pilule bleu nuit** dans les deux modes : fond `rgba(10, 22, 51, 0.94)` + `backdrop-filter: blur(20px)`, repli opaque `#0A1633`, ombre `0 12px 30px rgba(10, 22, 51, 0.35)`.
- Hauteur **66 px**, rayon **33 px**, marges latérales de **16 px**.
- **Onglets inactifs** : icône seule, en blanc à 60 %, avec `aria-label`.
- **Onglet actif** : pastille `rgba(255, 255, 255, 0.14)` contenant l’icône et le libellé en blanc, avec `aria-current="page"`.
- **Bouton « + »** de **50 px** en dégradé `linear-gradient(45deg, #5E5CE6, #BF5AF2)`, avec l’ombre violette.

---

## 6. Loader Bleu nuit avec barre de progression

- L’écran de lancement (`.pc-loader`) s’affiche sur fond **Bleu nuit (`#0A1633`)** avec halos lumineux, monogramme **PC**, logotype blanc, slogan « Suivi de soins à domicile », **barre de progression horizontale** en dégradé `#5E5CE6 → #BF5AF2` et signature **« by Prime Vision Solutions »** clairement lisible en fin d’animation.

---

## 7. Connexion sécurisée en deux parties : « Qui êtes-vous ? » & Clavier PIN sur appareil de confiance

1. **Table `trusted_devices` (`002_trusted_devices.sql`)** :
   - Stocke les jetons d’appareils de confiance (`selector` unique + `validator_hash` SHA-256, `device_label`, expiration à 90 jours, `revoked_at`).
2. **Activation explicite depuis `/profile`** :
   - Uniquement après une authentification par mot de passe réussie (« Activer la connexion par PIN sur cet appareil »).
   - Pose un cookie `HttpOnly`, `Secure` (en prod), `SameSite=Strict` valable 90 jours pouvant contenir les jetons de plusieurs membres de l’équipe sur un téléphone partagé au domicile.
3. **Écran `/login` et `/login/pin` en deux parties** :
   - **Partie 1 — « Qui êtes-vous ? »** : affiche uniquement les profils dont un jeton valide est présent sur ce navigateur (aucun profil par défaut, aucun paramètre `?user=`, aucun `user_id` en clair).
   - **Partie 2 — Clavier PIN** : envoie uniquement le `selector` du profil sélectionné (`POST /login/pin`), vérifie le validateur avec `hash_equals` puis le code PIN, et bloque le PIN du profil pendant 15 minutes après 5 erreurs en proposant le mot de passe.
   - Si aucun jeton valide n’est présent sur le navigateur, seul le formulaire e-mail + mot de passe est affiché.

---

## 8. Phase 2 — Composants médicaux « Bleu nuit »

1. **Chronologie du poste sur l’accueil aide-soignante** :
   - Fil vertical structuré sous l’en-tête Bleu nuit :
     - **Fait** : ligne apaisée avec coche verte (`--ok`) et horodatage d’exécution ;
     - **En retard** : carte à bordure/accent rouge (`--danger`) avec badge icône + texte « En retard » et bouton d’action directe (« Saisir » / « Valider ») ;
     - **Tâche en cours mise en avant** : carte **Bleu nuit (`#0A1633`)** à rayon 24 px avec halos subtils, texte blanc et boutons d’action immédiate (« Donné » / « Non donné » ou « Saisir la glycémie ») ;
     - **À venir** : cartes groupées sobres avec l’heure prévue en chiffres tabulaires.
2. **Bloc héros Bleu nuit (détail d’une mesure `/health/{code}`)** :
   - Carte supérieure sur fond `#0A1633` avec halos, affichant la dernière valeur mesurée en **graisse `800`** (`tabular-nums`), l’unité, le badge de statut (icône + libellé explicite), le contexte et l’auteur.
   - Pour la glycémie : **jauge horizontale à 3 zones** (`Hypo < 0,70`, `Cible 0,80–1,30 / < 1,80`, `Hyper > 2,50`) avec curseur positionné par classe CSS (aucun `style=` en ligne).
3. **Graphiques ApexCharts (7, 30 et 90 jours)** :
   - Couleurs des modules (`--c-vitals: #FF2D55`, `--c-meds: #00C7BE`, `--c-meals: #FF9500`, `--brand: #5E5CE6`), bandes horizontales de seuils (cible verte translucide, seuil critique rouge en pointillés), chiffres tabulaires, lisibilité parfaite en mode clair et sombre, animations désactivées si `prefers-reduced-motion: reduce`, et **résumé textuel accessible** sous chaque graphique.
4. **Barre de portion de repas en 4 segments & Photos avant/après côte à côte** :
   - Sélecteur tactile de portion mangée (`Rien 0 %`, `1/4 · 25 %`, `Moitié · 50 %`, `3/4 · 75 %`, `Tout · 100 %`) avec jauge visuelle à 4 segments.
   - Grille de deux emplacements photo côte à côte (**Avant le repas** / **Après le repas**) avec aperçu miniature et barre de progression de compression.
5. **Vitrine `/design` et documentation `docs/DESIGN_SYSTEM.md`** :
   - Présentent l’ensemble des tokens et composants des Phases 1, 2 et 3.

---

## 8 bis. Phase 3 — Composants d’équipe, pointage, alertes et mode hors ligne « Bleu nuit »

1. **Bouton glissant de pointage (`.pc-slide-punch`) & Alternative accessible** :
   - Rail horizontal haut contraste (hauteur `56px`, rayon `28px`) en haut de l’accueil aide-soignante :
     - **Arrivée** : teinte émeraude (`--ok`) avec curseur tactile draggable (`pointerdown` / `pointermove` / `pointerup`) et libellé *« Glisser pour arriver »* ;
     - **Départ** : teinte ambre (`--warn`) avec libellé *« Glisser pour partir »*.
   - **Alternative accessible obligatoire** (clavier `Entrée` / `Espace` et lecteur d’écran) : bouton explicite *« Pointer mon arrivée / départ »* ouvrant une bottom sheet de confirmation (*« Confirmer mon arrivée à 07h58 »*).
2. **Grille de planning d’équipe & créneau non couvert** :
   - Cartes et cellules à rayon `24px` sans ombre, pastilles d’initiales `.pc-avatar--c1` à `.pc-avatar--c8` sans style en ligne, et motif rouge hachuré (`.pc-shift--uncovered`) avec icône + mention *« Non couvert »* ou badge ambre *« À remplacer »* en cas d’absence.
3. **Cloche du centre d’alertes & Cartes d’alertes par gravité** :
   - Bouton rond translucide dans l’en-tête Bleu nuit avec pastille rouge (`.pc-alert-badge`) indiquant le nombre d’alertes ouvertes (`new` + `seen`).
   - Cartes d’alertes différenciées par gravité (icône + bordure + badge texte `Critique` / `Attention` / `Information`, jamais par la couleur seule).
4. **Bandeau hors ligne & Badge de synchronisation IndexedDB** :
   - Bandeau non intrusif (`.pc-offline-banner`) indiquant *« Hors ligne — données du … »* et badge d’attente *« N saisie(s) en attente »* avec bouton *« Synchroniser maintenant »*.

---

## 9. Checklist de contrôle de l’Additif B

- [x] B.1 — Tokens CSS « Proposition B — Bleu nuit » appliqués (`--bg: #F4F7FC` / `#060B1A`, `--surface` sombre `#111A33`, `--header: #0A1633` dans les deux modes, `--brand-gradient: linear-gradient(45deg, #5E5CE6, #BF5AF2)`).
- [x] B.2 — Cartes à rayon `24px` sans ombre portée (`box-shadow: none`, bordure fine).
- [x] B.3 — En-tête Bleu nuit (`--header`) pleine largeur en haut de l’accueil (`border-radius: 0 0 32px 32px`, deux halos, monogramme blanc 30 px + date, boutons ronds translucides, « Bonjour Fatou », carte patient vitrée, sans titre « Accueil » au-dessus, `theme-color: #0A1633`).
- [x] B.4 — Barre d’onglets pilule Bleu nuit (`height: 66px`, `border-radius: 33px`, `left/right: 16px`, `rgba(10,22,51,.94)`, onglets inactifs icône seule à 60 % avec `aria-label`, onglet actif en pastille `rgba(255,255,255,.14)` avec icône + libellé blanc et `aria-current="page"`, bouton « + » de 50 px en dégradé avec ombre violette).
- [x] B.5 — Loader de lancement Bleu nuit avec barre de progression et signature « by Prime Vision Solutions ».
- [x] B.6 — Écran de connexion en deux parties (« Qui êtes-vous ? » profils de confiance + clavier PIN `selector:validator` conforme à C1).
- [x] B.7 — Page `/design` et `docs/DESIGN_SYSTEM.md` mis à jour selon la Proposition B — Bleu nuit (libellé dégradé `#5E5CE6 → #BF5AF2`).
