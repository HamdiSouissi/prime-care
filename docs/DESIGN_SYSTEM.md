# Design System — Prime Care (« Proposition B — Bleu nuit »)

> **Produit** : Prime Care — *Suivi de soins à domicile*  
> **Éditeur** : [Prime Vision Solutions](https://primevisionsolutions.fr)  
> **Direction artistique** : **Proposition B — Bleu nuit** (`docs/ADDENDUM_DESIGN_B.md`)  
> **Architecture CSS** : `@layer reset, tokens, base, components, utilities, pages;`  
> **Vitrine interactive** : `/design` (accessible au rôle `admin`)

---

## 1. Principes directeurs (Proposition B — Bleu nuit)

1. **Identité Bleu nuit & Dégradé signature** :
   - En-têtes et écrans d’immersion (accueil, connexion, loader) en **Bleu nuit profond (`#0A1633`)** animés de **halos lumineux** indigo (`#5E5CE6`) et violet (`#BF5AF2`).
   - Dégradé officiel Prime Care : **`#5E5CE6 → #BF5AF2`** (`linear-gradient(135deg, #5E5CE6 0%, #BF5AF2 100%)`).
2. **Cartes à 24 px sans ombre portée** :
   - Toutes les cartes (`.pc-card`, `.pc-list-group`, `.pc-empty-state`, `.pc-hypo-card`) adoptent un rayon de **`24px`** (`var(--r-card)`), **`box-shadow: none`** et une bordure fine `1px solid var(--border)`.
3. **Barre d’onglets flottante** :
   - La barre d’onglets basse (`.pc-tabbar`) flotte au-dessus du bord inférieur (`border-radius: 24px`, `left/right: 12px`) avec un bouton central **« + »** en dégradé `#5E5CE6 → #BF5AF2`.
4. **Usage à une main & Règle des 3 clics** :
   - Cibles tactiles d’au moins **44 × 44 px**, police des champs `>= 16px` (aucun zoom intempestif sur iOS Safari), et accès en **≤ 3 taps** à toutes les actions vitales.
5. **Zéro CDN & Zéro style en ligne (CSP stricte)** :
   - Police `Inter Variable` et icônes SVG `Lucide` 100 % auto-hébergées dans `public/assets/vendor/`.
   - Aucune directive `unsafe-inline` dans la CSP : les couleurs d’avatars utilisent une palette fixe de 8 classes (`.pc-avatar--c1` à `.pc-avatar--c8`) calculées côté serveur via `avatar_color_class(int $userId)`.

---

## 2. Tokens de couleurs (`public/assets/css/tokens.css`)

### Surfaces & Neutres

| Token | Mode Clair | Mode Sombre | Usage |
|---|---|---|---|
| `--bg` | `#F4F6FB` | `#070D1F` | Fond général de l’application |
| `--surface-1` | `#FFFFFF` | `#0F172A` | Cartes à 24 px sans ombre |
| `--surface-2` | `#EEF2F9` | `#162038` | Champs de saisie, pastilles |
| `--surface-3` | `#E2E8F4` | `#1E293B` | États actifs, squelettes |
| `--header` | `#0A1633` | `#060C1D` | En-tête Bleu nuit profond |
| `--border` | `rgba(10,22,51,0.08)` | `rgba(255,255,255,0.09)` | Bordure fine des cartes 24 px |
| `--text-1` | `#0A1633` | `#F8FAFC` | Texte principal |
| `--text-2` | `#475569` | `#94A3B8` | Texte secondaire |

### Marque & Modules métiers

| Token | Valeur | Usage |
|---|---|---|
| `--brand` | `#5E5CE6` (Clair) / `#7D7AFF` (Sombre) | Indigo Prime Care |
| `--brand-purple` | `#BF5AF2` | Violet du dégradé signature |
| `--brand-gradient` | `linear-gradient(135deg, #5E5CE6 0%, #BF5AF2 100%)` | Dégradé officiel `#5E5CE6 → #BF5AF2` |
| `--c-vitals` | `#FF2D55` | Constantes vitales & Glycémie |
| `--c-meds` | `#00C7BE` | Médicaments & Insuline |
| `--c-meals` | `#FF9500` | Repas & Hydratation |
| `--c-notes` | `#AF52DE` | Journal & Transmissions |
| `--c-team` | `#5E5CE6` | Planning & Équipe |

---

## 3. Composants clés de la Proposition B

- **Loader Bleu nuit (`.pc-loader`)** : fond `#0A1633` avec halos, monogramme `PC` animé, logotype blanc, barre de progression horizontale (`.pc-loader__progress-bar`) en dégradé `#5E5CE6 → #BF5AF2` et signature **« by Prime Vision Solutions »**.
- **Écran de connexion en deux parties (`/login`, `/login/pin`)** :
  1. **« Qui êtes-vous ? »** (`.pc-who-grid`) : sélection visuelle des profils disposant d’un jeton d’appareil de confiance valide (`trusted_devices`).
  2. **Clavier PIN (`.pc-pin-pad`)** : saisie 4–6 chiffres soumettant uniquement le `selector` du profil.
- **En-tête d’accueil Bleu nuit (`.pc-home-hero`)** : fond `var(--header)` avec halos radial-gradient et bandeau patient vitré (`.pc-home-patient`).
- **Barre d’onglets flottante (`.pc-tabbar`)** : capsule flottante `24px` avec bouton central `+` en dégradé `#5E5CE6 → #BF5AF2`.
