# Design System — Prime Care (« Proposition B — Bleu nuit »)

> **Produit** : Prime Care — *Suivi de soins à domicile*  
> **Éditeur** : [Prime Vision Solutions](https://primevisionsolutions.fr)  
> **Direction artistique** : **Proposition B — Bleu nuit** (`docs/ADDENDUM_DESIGN_B.md`)  
> **Architecture CSS** : `@layer reset, tokens, base, components, utilities, pages;`  
> **Vitrine interactive** : `/design` (accessible au rôle `admin`)

---

## 1. Principes directeurs (Proposition B — Bleu nuit)

1. **Identité Bleu nuit & Dégradé signature à 45°** :
   - En-têtes et écrans d’immersion (accueil pleine largeur en haut d’écran, connexion, loader) en **Bleu nuit profond (`#0A1633`)** dans les deux modes, animés de **deux halos lumineux** indigo (`#5E5CE6`) et violet (`#BF5AF2`).
   - Dégradé officiel Prime Care (dans le même sens que le logo) : **`linear-gradient(45deg, #5E5CE6, #BF5AF2)`** (`#5E5CE6 → #BF5AF2`).
2. **En-tête d’accueil pleine largeur (§4 de l’additif)** :
   - Bloc pleine largeur en haut de l’écran passant sous la barre d’état (`safe-area-inset-top`), coins **bas** arrondis à **32 px** (`border-radius: 0 0 32px 32px`), deux halos, monogramme blanc de 30 px avec la date, boutons ronds translucides (urgences + profil), salutation *« Bonjour Fatou »* et carte patient vitrée.
   - `theme-color: #0A1633` et `apple-mobile-web-app-status-bar-style: black-translucent`.
3. **Cartes à 24 px sans ombre portée (§3 de l’additif)** :
   - Toutes les cartes (`.pc-card`, `.pc-list-group`, `.pc-empty-state`, `.pc-hypo-card`) adoptent un rayon de **`24px`** (`var(--r-card)`), **`box-shadow: none`** et une bordure fine `1px solid var(--border)`.
4. **Barre d’onglets pilule Bleu nuit (§5 de l’additif)** :
   - Pilule flottante Bleu nuit dans les deux modes : fond `rgba(10, 22, 51, 0.94)` + `backdrop-filter: blur(20px)`, repli opaque `#0A1633`, ombre `0 12px 30px rgba(10, 22, 51, 0.35)`.
   - Hauteur **66 px**, rayon **33 px**, marges latérales de **16 px**.
   - **Onglets inactifs** : icône seule en blanc à 60 % avec `aria-label`.
   - **Onglet actif** : pastille `rgba(255, 255, 255, 0.14)` contenant l’icône et le libellé en blanc, avec `aria-current="page"`.
   - **Bouton central « + »** : 50 px en dégradé `linear-gradient(45deg, #5E5CE6, #BF5AF2)` avec ombre violette.
5. **Zéro CDN & Zéro style en ligne (CSP stricte)** :
   - Police `Inter Variable` et icônes SVG `Lucide` 100 % auto-hébergées dans `public/assets/vendor/`.
   - Aucune directive `unsafe-inline` dans la CSP : les couleurs d’avatars utilisent une palette fixe de 8 classes (`.pc-avatar--c1` à `.pc-avatar--c8`) calculées côté serveur via `avatar_color_class(int $userId)`.

---

## 2. Tokens de couleurs (`public/assets/css/tokens.css` — §2 de l’additif)

### Surfaces & Neutres

| Token | Mode Clair | Mode Sombre | Usage |
|---|---|---|---|
| `--bg` | `#F4F7FC` | `#060B1A` | Fond général de l’application |
| `--surface` / `--surface-1` | `#FFFFFF` | `#111A33` | Cartes à 24 px sans ombre |
| `--surface-2` | `#EEF2F9` | `#172342` | Champs de saisie, pastilles |
| `--surface-3` | `#E2E8F4` | `#1F2E54` | États actifs, squelettes |
| `--header` | `#0A1633` | `#0A1633` | En-tête Bleu nuit profond (identique dans les deux modes) |
| `--border` | `rgba(10,22,51,0.08)` | `rgba(255,255,255,0.09)` | Bordure fine des cartes 24 px |
| `--text-1` | `#0A1633` | `#F8FAFC` | Texte principal |
| `--text-2` | `#475569` | `#94A3B8` | Texte secondaire |

### Marque & Modules métiers

| Token | Valeur | Usage |
|---|---|---|
| `--brand` | `#5E5CE6` (Clair) / `#7D7AFF` (Sombre) | Indigo Prime Care |
| `--brand-purple` | `#BF5AF2` | Violet du dégradé signature |
| `--brand-gradient` | `linear-gradient(45deg, #5E5CE6, #BF5AF2)` | Dégradé officiel 45° `#5E5CE6 → #BF5AF2` |
| `--c-vitals` | `#FF2D55` | Constantes vitales & Glycémie |
| `--c-meds` | `#00C7BE` | Médicaments & Insuline |
| `--c-meals` | `#FF9500` | Repas & Hydratation |
| `--c-notes` | `#AF52DE` | Journal & Transmissions |
| `--c-team` | `#5E5CE6` | Planning & Équipe |

---

## 3. Composants clés de la Proposition B

- **Loader Bleu nuit (`.pc-loader`)** : fond `#0A1633` avec halos, monogramme `PC` animé, logotype `PRIME CARE`, barre de progression horizontale (`.pc-loader__progress-bar`) en dégradé `45deg` `#5E5CE6 → #BF5AF2` et signature **« by Prime Vision Solutions »**.
- **Écran de connexion en deux parties (`/login`, `/login/pin`)** :
  1. **« Qui êtes-vous ? »** (`.pc-who-grid`) : sélection visuelle des profils disposant d’un jeton d’appareil de confiance valide (`trusted_devices`).
  2. **Clavier PIN (`.pc-pin-pad`)** : saisie 4–6 chiffres soumettant uniquement le `selector` du profil.
- **En-tête d’accueil Bleu nuit pleine largeur (`.pc-home-hero`)** : bloc pleine largeur en haut d’écran (`border-radius: 0 0 32px 32px`), monogramme blanc 30 px + date, boutons ronds translucides, salutation et carte patient vitrée (`.pc-home-patient`).
- **Barre d’onglets pilule Bleu nuit (`.pc-tabbar`)** : pilule `66px` / `33px` (`left/right: 16px`, `rgba(10,22,51,.94)`), onglets inactifs icône seule à 60 %, onglet actif en pastille `rgba(255,255,255,.14)` avec icône + libellé blanc (`aria-current="page"`), et bouton central `+` de `50px` en dégradé avec ombre violette.
