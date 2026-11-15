# Rapport d'Audit d'Accessibilité WCAG 2.1 AA — Prime Care v1.0.0

Ce document consigne la vérification complète d'accessibilité menée lors de la Phase 4 (§6.4 & H5) sur **l'ensemble des écrans principaux des Phases 1 à 4** en **mode clair** (`[data-theme="light"]`) et en **mode sombre** (`[data-theme="dark"]`), les résultats de l'audit automatisé **`axe-core` v4.14.0** exécuté via **Playwright Chromium**, ainsi que les **contrôles manuels** (navigation clavier, touche `Échap` sur les feuilles modales, zoom 200 % et `prefers-reduced-motion`).

---

## 1. Synthèse des critères WCAG 2.1 AA vérifiés

| Critère | Statut | Implémentation dans Prime Care |
|---|---|---|
| **Contrastes WCAG AA** (`>= 4,5:1` texte normal, `>= 3:1` grands textes) | **CONFORME (0 violation)** | Palette contrastée en mode clair (`#0A1633` sur `#FFFFFF`/`#F4F7FC`, ratio `> 16:1`) et en mode sombre (`#F8FAFC` sur `#060B1A`/`#111A33`, ratio `> 14:1`). Badges, pilules actives (`.pc-filter-pill.is-active`, `.pc-chip-radio input:checked + span` en `#4F46E5` sur sombre, ratio `5,7:1`) et boutons fantômes (`.pc-btn--ghost` en `var(--brand-strong)` / `#C7D2FE`) vérifiés à `0` violation sur les 39 états. |
| **Cibles tactiles** (`>= 44 × 44 px`, `56 × 56 px` actions principales) | **CONFORME** | Tous les boutons (`.pc-btn`), lignes de réglages (`.pc-group__row`), boutons ronds d'en-tête (`44 × 44 px`), touches du clavier PIN (`64 × 64 px`), bouton central `+` (`50 × 50 px`) et bouton glissant de pointage (`56 px` de haut) respectent ou dépassent la taille minimale de `44 × 44 px`. |
| **Navigation au clavier** | **CONFORME** | Ordre de tabulation logique, anneau `:focus-visible` indigo `2px solid var(--brand)`, fermeture des feuilles modales (`Bottom Sheets`) par la touche `Échap`, régions de tableaux déroulants dotées de `tabindex="0"` et `role="region"`. |
| **Lecteur d'écran (ARIA & HTML sémantique)** | **CONFORME** | Tous les champs de formulaire sont reliés à un `<label for="...">` explicite ou un `aria-label` en français ; les icônes décoratives portent `aria-hidden="true"` ; les groupes de filtres portent `role="group"` ; les bandeaux d'alerte portent `role="alert"` ou `role="status" aria-live="polite"` ; l'onglet actif porte `aria-current="page"`. |
| **Respect de `prefers-reduced-motion: reduce`** | **CONFORME** | Règle globale `@media (prefers-reduced-motion: reduce)` dans `base.css` et vérification JS `window.matchMedia('(prefers-reduced-motion: reduce)').matches` dans `initPhase4()` désactivant l'animation des compteurs `[data-count-up]` et les transitions. |

---

## 2. Contrôles manuels & fonctionnels (§6.4)

| Contrôle manuel | Procédure exécutée | Résultat |
|---|---|---|
| **Navigation au clavier (`Tab` / `Shift+Tab` / `Entrée` / `Espace`)** | Parcours complet au clavier sans souris sur `/login`, `/` (aide-soignante), `/today`, `/health`, `/meals`, `/journal`, `/planning`, `/attendance`, `/alerts`, `/documents`, `/reports`, `/audit`, `/settings`. | **CONFORME** — Aucun piège de focus ; l'anneau `:focus-visible` (`2px solid var(--brand)`, `outline-offset: 2px`) est net sur chaque lien, bouton, puce radio et champ ; les boutons d'action rapide (« Donné », « Mesurer », « Saisir ») s'activent via `Entrée` et `Espace`. |
| **Fermeture par la touche `Échap` sur les feuilles modales (`Bottom Sheets`)** | Ouverture de la feuille d'ajout rapide (`#pc-quick-add-sheet`), de la feuille de saisie de mesure (`#sheet-measure` sur `/health` et `/today`), de la feuille de repas (`#sheet-meal`) et de la feuille de dépôt de document (`#sheet-upload-doc` sur `/documents`), puis appui sur `Escape`. | **CONFORME** — Le gestionnaire `document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllSheets(); })` dans `public/assets/js/modules/sheets.js` referme immédiatement la feuille ouverte, retire `.is-open`, repasse `aria-hidden="true"` et restaure le défilement de la page. |
| **Zoom navigateur à `200 %` (`390 px` équivalent `195 px` CSS / reflow)** | Test de zoom texte et page à `200 %` sur `/today`, `/health`, `/reports`, `/documents` et `/settings`. | **CONFORME** — Les grilles (`.pc-grid-2`, `.pc-grid-3`, `.pc-module-grid--2col`) se replient sur 1 colonne (`flex-wrap: wrap` et `min-width: 0`) ; aucun texte n'est tronqué ni masqué ; les tableaux larges (`/reports`, `/attendance/hours`) disposent d'un conteneur `.pc-table-wrap` déroulant horizontalement (`overflow-x: auto`, `tabindex="0"`). |
| **Mode `prefers-reduced-motion: reduce`** | Émulation `page.emulateMedia({ reducedMotion: 'reduce' })` sur l'accueil admin (`/`) et ouverture de feuilles modales. | **CONFORME** — Les compteurs `[data-count-up]` affichent immédiatement leur valeur finale sans interpolation (`0 ms`), et toutes les animations/transitions CSS (`animation-duration: 0.01ms !important`, `transition-duration: 0.01ms !important`) sont neutralisées. |

---

## 3. Résultat complet de l'audit automatisé `axe-core` via Playwright (39 écrans / états audités —Phases 1 à 4)

Outil utilisé : **`axe-core` 4.14.0** + **`playwright-core` 1.65.1** (Chromium Headless 153, viewport `390 × 844`, tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`).

### Tableau récapitulatif (`0` violation sur les 39 écrans audités en Clair et Sombre)

| # | Phase | Écran / État audité | Thème | Règles `axe-core` validées (`passes`) | Violations (`violations`) |
|---|---|---|---|---|---|
| 1 | Phase 1 | Connexion (`/login`) | Clair / Bleu nuit | 23 | **0** |
| 2 | Phase 2/3 | Accueil de l’aide-soignante (`/`) | Clair | 19 | **0** |
| 3 | Phase 2/3 | Accueil de l’aide-soignante (`/`) | Sombre | 19 | **0** |
| 4 | Phase 2 | Aujourd’hui (`/today`) | Clair | 19 | **0** |
| 5 | Phase 2 | Aujourd’hui (`/today`) | Sombre | 19 | **0** |
| 6 | Phase 2 | Feuille de saisie de mesure ouverte (`/health`) | Clair | 26 | **0** |
| 7 | Phase 2 | Feuille de saisie de mesure ouverte (`/health`) | Sombre | 26 | **0** |
| 8 | Phase 2 | Santé & Constantes (`/health`) | Clair | 18 | **0** |
| 9 | Phase 2 | Santé & Constantes (`/health`) | Sombre | 18 | **0** |
| 10 | Phase 2 | Repas & Hydratation (`/meals`) | Clair | 23 | **0** |
| 11 | Phase 2 | Repas & Hydratation (`/meals`) | Sombre | 23 | **0** |
| 12 | Phase 2 | Journal & Transmissions (`/journal`) | Clair | 23 | **0** |
| 13 | Phase 2 | Journal & Transmissions (`/journal`) | Sombre | 23 | **0** |
| 14 | Phase 3 | Planning (`/planning`) | Clair | 23 | **0** |
| 15 | Phase 3 | Planning (`/planning`) | Sombre | 23 | **0** |
| 16 | Phase 3 | Pointage (`/attendance`) | Clair | 19 | **0** |
| 17 | Phase 3 | Pointage (`/attendance`) | Sombre | 19 | **0** |
| 18 | Phase 3 | Centre d’alertes (`/alerts`) | Clair | 22 | **0** |
| 19 | Phase 3 | Centre d’alertes (`/alerts`) | Sombre | 22 | **0** |
| 20 | Phase 4 | Accueil Admin + Tendances 7 jours (`/`) | Clair | 23 | **0** |
| 21 | Phase 4 | Accueil Admin + Tendances 7 jours (`/`) | Sombre | 23 | **0** |
| 22 | Phase 4 | Documents médicaux (`/documents`) | Clair | 21 | **0** |
| 23 | Phase 4 | Documents médicaux (`/documents`) | Sombre | 21 | **0** |
| 24 | Phase 4 | Aperçu d’un document médical (`/documents/6`) | Clair | 9 | **0** |
| 25 | Phase 4 | Aperçu d’un document médical (`/documents/6`) | Sombre | 9 | **0** |
| 26 | Phase 4 | Rapport médical & synthèse (`/reports`) | Clair | 29 | **0** |
| 27 | Phase 4 | Rapport médical & synthèse (`/reports`) | Sombre | 29 | **0** |
| 28 | Phase 4 | Exports CSV (`/reports/exports`) | Clair | 21 | **0** |
| 29 | Phase 4 | Exports CSV (`/reports/exports`) | Sombre | 21 | **0** |
| 30 | Phase 4 | Journal d’audit avec diff avant/après (`/audit`) | Clair | 28 | **0** |
| 31 | Phase 4 | Journal d’audit avec diff avant/après (`/audit`) | Sombre | 28 | **0** |
| 32 | Phase 4 | Réglages unifiés & RGPD (`/settings`) | Clair | 26 | **0** |
| 33 | Phase 4 | Réglages unifiés & RGPD (`/settings`) | Sombre | 26 | **0** |
| 34 | Phase 4 | Rapport RH & Planifié restant (`/attendance/hours`) | Clair | 19 | **0** |
| 35 | Phase 4 | Rapport RH & Planifié restant (`/attendance/hours`) | Sombre | 19 | **0** |
| 36 | Phase 4 | Mentions légales (`/legal/mentions`) | Clair | 22 | **0** |
| 37 | Phase 4 | Mentions légales (`/legal/mentions`) | Sombre | 22 | **0** |
| 38 | Phase 4 | Politique de confidentialité RGPD (`/legal/privacy`) | Clair | 22 | **0** |
| 39 | Phase 4 | Politique de confidentialité RGPD (`/legal/privacy`) | Sombre | 22 | **0** |

### Sortie brute de l'exécution `axe-core`
```text
Connexion (/login) [Clair] => passes: 23 violations: 0
Accueil de l’aide-soignante (/) [Clair] => passes: 19 violations: 0
Accueil de l’aide-soignante (/) [Sombre] => passes: 19 violations: 0
Aujourd’hui (/today) [Clair] => passes: 19 violations: 0
Aujourd’hui (/today) [Sombre] => passes: 19 violations: 0
Feuille de saisie de mesure ouverte (/health) [Clair] => passes: 26 violations: 0
Feuille de saisie de mesure ouverte (/health) [Sombre] => passes: 26 violations: 0
Santé & Constantes (/health) [Clair] => passes: 18 violations: 0
Santé & Constantes (/health) [Sombre] => passes: 18 violations: 0
Repas & Hydratation (/meals) [Clair] => passes: 23 violations: 0
Repas & Hydratation (/meals) [Sombre] => passes: 23 violations: 0
Journal & Transmissions (/journal) [Clair] => passes: 23 violations: 0
Journal & Transmissions (/journal) [Sombre] => passes: 23 violations: 0
Planning (/planning) [Clair] => passes: 23 violations: 0
Planning (/planning) [Sombre] => passes: 23 violations: 0
Pointage (/attendance) [Clair] => passes: 19 violations: 0
Pointage (/attendance) [Sombre] => passes: 19 violations: 0
Centre d’alertes (/alerts) [Clair] => passes: 22 violations: 0
Centre d’alertes (/alerts) [Sombre] => passes: 22 violations: 0
Accueil Admin + Tendances 7 jours (/) [Clair] => passes: 23 violations: 0
Accueil Admin + Tendances 7 jours (/) [Sombre] => passes: 23 violations: 0
Documents médicaux (/documents) [Clair] => passes: 21 violations: 0
Documents médicaux (/documents) [Sombre] => passes: 21 violations: 0
Aperçu d’un document médical (/documents/6) [Clair] => passes: 9 violations: 0
Aperçu d’un document médical (/documents/6) [Sombre] => passes: 9 violations: 0
Rapport médical & synthèse (/reports) [Clair] => passes: 29 violations: 0
Rapport médical & synthèse (/reports) [Sombre] => passes: 29 violations: 0
Exports CSV (/reports/exports) [Clair] => passes: 21 violations: 0
Exports CSV (/reports/exports) [Sombre] => passes: 21 violations: 0
Journal d’audit avec diff avant/après (/audit) [Clair] => passes: 28 violations: 0
Journal d’audit avec diff avant/après (/audit) [Sombre] => passes: 28 violations: 0
Réglages unifiés & RGPD (/settings) [Clair] => passes: 26 violations: 0
Réglages unifiés & RGPD (/settings) [Sombre] => passes: 26 violations: 0
Rapport RH & Planifié restant (/attendance/hours) [Clair] => passes: 19 violations: 0
Rapport RH & Planifié restant (/attendance/hours) [Sombre] => passes: 19 violations: 0
Mentions légales (/legal/mentions) [Clair] => passes: 22 violations: 0
Mentions légales (/legal/mentions) [Sombre] => passes: 22 violations: 0
Politique de confidentialité RGPD (/legal/privacy) [Clair] => passes: 22 violations: 0
Politique de confidentialité RGPD (/legal/privacy) [Sombre] => passes: 22 violations: 0
TOTAL AUDITED: 39 TOTAL VIOLATIONS: 0
```
