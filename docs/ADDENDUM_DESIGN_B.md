# Additif de Direction Artistique — Proposition B « Bleu nuit »

> **Document de référence prioritaire sur le §6 du brief pour tous les points qu’il traite.**  
> **Emplacement** : `docs/ADDENDUM_DESIGN_B.md`

---

## 1. Positionnement visuel : « Proposition B — Bleu nuit »

L’interface de **Prime Care** adopte la direction artistique **Proposition B — Bleu nuit** :
- Une identité médicale rassurante et haut de gamme articulée autour du **Bleu nuit Prime Care (`#0A1633`)**, du **dégradé signature Indigo → Violet (`#5E5CE6 → #BF5AF2`)** et de surfaces épurées à **rayon 24 px sans ombre portée**.
- Contrastes francs, hiérarchie immédiate à une main, lisibilité maximale pour l’équipe d’aides-soignantes de jour comme de garde de nuit.

---

## 2. Palette & Tokens CSS (`public/assets/css/tokens.css`)

| Token | Mode Clair | Mode Sombre | Rôle |
|---|---|---|---|
| `--bg` | `#F4F6FB` | `#070D1F` | Fond général de l’application (gris-bleu très doux en clair, bleu abysse en sombre) |
| `--surface-1` | `#FFFFFF` | `#0F172A` | Surface primaire des cartes (24 px, sans ombre) |
| `--surface-2` | `#EEF2F9` | `#162038` | Surface secondaire (champs, pastilles, groupes) |
| `--surface-3` | `#E2E8F4` | `#1E293B` | Surface tertiaire / états actifs |
| `--header` | `#0A1633` | `#060C1D` | En-tête Bleu nuit profond Prime Care |
| `--header-2` | `#11224D` | `#0D1936` | Nuance secondaire de l’en-tête Bleu nuit |
| `--brand` | `#5E5CE6` | `#6E6CF0` | Indigo Prime Care |
| `--brand-purple` | `#BF5AF2` | `#C969F7` | Violet signature du dégradé |
| `--brand-gradient` | `linear-gradient(135deg, #5E5CE6 0%, #BF5AF2 100%)` | `linear-gradient(135deg, #5E5CE6 0%, #BF5AF2 100%)` | Dégradé officiel (`#5E5CE6 → #BF5AF2`) |
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

## 4. En-tête Bleu nuit avec halos sur l’accueil

- L’écran d’accueil s’ouvre sur un **en-tête Bleu nuit (`var(--header)`)** enrichi de **deux halos lumineux floutés** (`#5E5CE6` en haut à droite, `#BF5AF2` en bas à gauche).
- Il regroupe le bonjour personnalisé, la date complète en français, le badge de garde et le bandeau d’accès direct à la fiche patient (**Henri Benali · 84 ans**).

---

## 5. Barre d’onglets flottante avec « + » central en dégradé

- La barre de navigation basse (`.pc-tabbar`) est une **capsule flottante** détachée des bords (`left: 12px`, `right: 12px`, `bottom: calc(10px + var(--safe-bottom))`, `border-radius: 24px`).
- Le bouton d’action rapide central **« + »** (`.pc-tabbar__plus-btn`) utilise le dégradé officiel **`#5E5CE6 → #BF5AF2`** (`var(--brand-gradient)`).

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

## 8. Vitrine `/design` et documentation `docs/DESIGN_SYSTEM.md`

- La page `/design` et le document `docs/DESIGN_SYSTEM.md` présentent la direction artistique **Proposition B — Bleu nuit**, ses tokens, le dégradé `#5E5CE6 → #BF5AF2`, les cartes 24 px sans ombre, l’en-tête Bleu nuit à halos, la barre d’onglets flottante et le loader Bleu nuit avec barre de progression.

---

## 9. Checklist de contrôle de l’Additif B

- [x] B.1 — Tokens CSS « Proposition B — Bleu nuit » appliqués (`--bg: #F4F6FB` / `#070D1F`, `--header: #0A1633`, `--brand-gradient: #5E5CE6 → #BF5AF2`).
- [x] B.2 — Cartes à rayon `24px` sans ombre portée (`box-shadow: none`, bordure fine).
- [x] B.3 — En-tête Bleu nuit (`--header`) avec halos lumineux sur l’écran d’accueil.
- [x] B.4 — Barre d’onglets basse flottante avec bouton central « + » en dégradé `#5E5CE6 → #BF5AF2`.
- [x] B.5 — Loader de lancement Bleu nuit avec barre de progression et signature « by Prime Vision Solutions ».
- [x] B.6 — Écran de connexion en deux parties (« Qui êtes-vous ? » profils de confiance + clavier PIN `selector:validator` conforme à C1).
- [x] B.7 — Page `/design` et `docs/DESIGN_SYSTEM.md` mis à jour selon la Proposition B — Bleu nuit (libellé dégradé `#5E5CE6 → #BF5AF2`).
