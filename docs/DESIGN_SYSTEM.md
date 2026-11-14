# Design System Prime Care — Direction artistique Apple Santé (iOS 17/18)

> **Prime Care** · « Suivi de soins à domicile » · Application créée par **[Prime Vision Solutions](https://primevisionsolutions.fr)**

---

## 1. Principes directeurs

1. **Ergonomie à une main (Zone du pouce)** :
   - Navigation principale fixée en bas de l’écran (`pc-tabbar`) avec 5 entrées et un bouton d’action rapide central (`+`) de 52 × 52 px.
   - Cibles tactiles minimales de **44 × 44 px** (48 × 48 px pour les actions principales et 56 × 56 px pour les boutons critiques).
   - **Règle des 3 clics** : toute action courante (appel d’urgence, consultation du protocole d’hypoglycémie, changement de thème, ouverture de la saisie rapide) est accessible en 3 taps maximum depuis l’accueil.
2. **Clarté médicale & Garde de nuit** :
   - Deux thèmes complets (**Clair** par défaut et **Sombre** automatique ou forcé) pilotés par les variables CSS de `public/assets/css/tokens.css` et l’attribut `data-theme="auto|light|dark"` sur `<html>`.
   - Chiffres tabulaires (`font-variant-numeric: tabular-nums`) obligatoires sur toutes les valeurs médicales, heures et numéros.
3. **Architecture CSS par couches (`@layer`)** :
   - `@layer reset, tokens, base, components, utilities, pages;` sans aucun préprocesseur ni framework CSS externe.

---

## 2. Identité visuelle & Déclinaisons du logo (`public/assets/brand/`)

Les 6 fichiers originaux de la marque sont conservés intacts dans `public/assets/brand/` :
- `prime-care-icone-degrade.svg` & `.png` : carré arrondi dégradé indigo-violet (`#5E5CE6` → `#BF5AF2`) avec monogramme **PC** blanc.
- `prime-care-icone-transparent.svg` & `.png` : monogramme **PC** entrelacé (P en `currentColor`, C en dégradé `#5E5CE6` → `#BF5AF2`).
- `prime-care-logo-complet.svg` & `.png` : monogramme **PC** + logotype **PRIME CARE** vectorisé (`viewBox="0 0 790 280"`).

Les déclinaisons générées par `php bin/make-icons.php` couvrent :
- `prime-care-logo-complet-blanc.svg` (en-têtes sombres et exports),
- `favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` (180×180), `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `badge-96.png`, `og-image.png` (1200×630).

---

## 3. Palette de couleurs (`tokens.css`)

| Token CSS | Mode Clair | Mode Sombre | Usage |
|---|---|---|---|
| `--bg` | `#F2F2F7` | `#000000` | Fond général iOS Grouped Background |
| `--surface` | `#FFFFFF` | `#1C1C1E` | Cartes et listes groupées |
| `--surface-2` | `#F9F9FB` | `#2C2C2E` | Champs de saisie et sous-cartes |
| `--brand` | `#5E5CE6` | `#7D7AFF` | Indigo Santé principal |
| `--brand-ink` | `#0A1633` | `#FFFFFF` | Couleur du P et du texte « PRIME CARE » |
| `--accent-cyan` | `#00C7BE` | `#5AC8FA` | Turquoise secondaire |
| `--c-vitals` | `#FF2D55` | `#FF375F` | Module Constantes & Glycémie |
| `--c-meds` | `#007AFF` | `#0A84FF` | Module Médicaments & Insuline |
| `--c-meals` | `#FF9500` | `#FF9F0A` | Module Repas & Hydratation |
| `--c-notes` | `#AF52DE` | `#BF5AF2` | Module Journal & Transmissions |
| `--c-team` | `#34C759` | `#30D158` | Module Planning, Pointage & Équipe |
| `--ok` / `--warn` / `--danger` | `#34C759` / `#FF9500` / `#FF3B30` | `#30D158` / `#FF9F0A` / `#FF453A` | États sémantiques (normal, surveillance, alerte critique) |

---

## 4. Échelle typographique (`Inter Variable` auto-hébergée)

- `.text-display` : `clamp(2.25rem, 6vw, 2.75rem)` · Bold 700 · `tabular-nums` (valeurs phares de glycémie)
- `.text-large-title` : `clamp(1.75rem, 5vw, 2.125rem)` · Bold 700 (titres d’écran façon iOS)
- `.text-title-1` : `1.5rem` (24 px) · Bold 700
- `.text-title-2` : `1.25rem` (20 px) · Semibold 600
- `.text-title-3` : `1.0625rem` (17 px) · Semibold 600
- `.text-headline` : `1rem` (16 px) · Semibold 600
- `.text-body` : `1rem` (16 px) · Regular 400 (minimum 16 px sur tous les champs pour éviter le zoom automatique d’iOS Safari)
- `.text-subhead` : `0.875rem` (14 px)
- `.text-footnote` : `0.8125rem` (13 px)
- `.text-caption` : `0.75rem` (12 px) · Medium 500

---

## 5. Composants UI (`components.css` & modules JS)

1. **Barre de navigation (`partials/navbar.php` + `js/modules/navbar.js`)** :
   - Fond translucide `backdrop-filter: blur(20px) saturate(180%)`.
   - Grand titre (`Large Title`) qui se replie automatiquement au défilement via `IntersectionObserver`.
2. **Barre d’onglets (`partials/tabbar.php` + `js/modules/tabs.js`)** :
   - 5 entrées adaptées au rôle (`caregiver`/`coordinator` : Accueil, Aujourd’hui, `+`, Journal, Plus ; `admin` : Accueil, Santé, `+`, Équipe, Plus).
3. **Feuille modale iOS (`js/modules/sheet.js`)** :
   - Glissement depuis le bas (`var(--ease-spring)`), fermeture au glissement vertical (`touchstart`/`touchmove`/`touchend`), piège de focus clavier et touche `Escape`.
4. **Contrôle segmenté (`js/modules/segmented.js`)** :
   - Indicateur actif animé et commutation en direct du thème Auto / Clair / Sombre.
5. **Clavier PIN iOS (`js/modules/pin-pad.js`)** :
   - 10 touches circulaires de 72 px, pastilles de progression, soumission automatique au 4e chiffre, animation `shake` + vibration haptique en cas d’erreur.
6. **Loader de lancement (`partials/loader.php`, `partials/loader-mini.php`, `js/modules/loader.js`)** :
   - Séquence en 3 temps (tracé `stroke-dashoffset` du P puis du C, apparition de « PRIME CARE » + « by Prime Vision Solutions », pulsation respirante et fondu de sortie).
   - Joué une seule fois par session (`sessionStorage['pc_loader_shown']`) avec filet de sécurité CSS pur si JS est désactivé et respect strict de `prefers-reduced-motion: reduce`.
