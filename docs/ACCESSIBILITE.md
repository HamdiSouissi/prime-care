# Rapport d'Audit d'Accessibilité WCAG 2.1 AA — Prime Care v1.0.0

Ce document consigne la vérification d'accessibilité menée lors de la Phase 4 (§6.4) sur les écrans principaux de l'application en **mode clair** (`[data-theme="light"]`) et en **mode sombre** (`[data-theme="dark"]`), ainsi que les résultats de l'audit automatisé **`axe-core` v4.14.0** exécuté via **Playwright Chromium**.

---

## 1. Synthèse des critères WCAG 2.1 AA vérifiés

| Critère | Statut | Implémentation dans Prime Care |
|---|---|---|
| **Contrastes WCAG AA** (`>= 4,5:1` texte normal, `>= 3:1` grands textes) | **CONFORME (0 violation)** | Palette contrastée en mode clair (`#0A1633` sur `#FFFFFF`/`#F4F7FC`, ratio `> 16:1`) et en mode sombre (`#F8FAFC` sur `#060B1A`/`#111A33`, ratio `> 14:1`). Badges et boutons teintés renforcés (`.pc-badge--*`, `.pc-btn--tinted`, `.pc-btn--danger-soft`, `.text-ok`, `.text-danger`, `.text-vitals`, `.pc-diff-old`, `.pc-diff-new`). |
| **Cibles tactiles** (`>= 44 × 44 px`, `56 × 56 px` actions principales) | **CONFORME** | Tous les boutons (`.pc-btn`), lignes de réglages (`.pc-group__row`), boutons ronds d'en-tête (`44 × 44 px`), touches du clavier PIN (`64 × 64 px`), bouton central `+` (`50 × 50 px`) et bouton glissant de pointage (`56 px` de haut) respectent ou dépassent la taille minimale de `44 × 44 px`. |
| **Navigation au clavier** | **CONFORME** | Ordre de tabulation logique, anneau `:focus-visible` indigo `2px solid var(--brand)`, fermeture des feuilles modales (`Bottom Sheets`) par la touche `Échap`, régions de tableaux déroulants dotées de `tabindex="0"` et `role="region"`. |
| **Lecteur d'écran (ARIA & HTML sémantique)** | **CONFORME** | Tous les champs de formulaire sont reliés à un `<label for="...">` explicite ou un `aria-label` en français ; les icônes décoratives portent `aria-hidden="true"` ; les bandeaux d'alerte portent `role="alert"` ou `role="status" aria-live="polite"` ; l'onglet actif porte `aria-current="page"`. |
| **Respect de `prefers-reduced-motion: reduce`** | **CONFORME** | Règle globale `@media (prefers-reduced-motion: reduce)` dans `base.css` et vérification JS `window.matchMedia('(prefers-reduced-motion: reduce)').matches` dans `initPhase4()` désactivant l'animation des compteurs `[data-count-up]` et les transitions. |

---

## 2. Résultat de l'audit automatisé `axe-core` via Playwright (21 écrans audités)

Outil utilisé : **`axe-core` 4.14.0** + **`playwright-core` 1.65.1** (Chromium Headless 153, viewport `390 × 844`, tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`).

### Tableau récapitulatif (`0` violation sur les 21 écrans)

| # | Écran audité | Thème | Règles `axe-core` validées (`passes`) | Violations (`violations`) |
|---|---|---|---|---|
| 1 | Connexion (`/login`) | Clair / Bleu nuit | 23 | **0** |
| 2 | Accueil Admin + Tendances 7 jours (`/`) | Clair | 23 | **0** |
| 3 | Accueil Admin + Tendances 7 jours (`/`) | Sombre | 23 | **0** |
| 4 | Documents médicaux (`/documents`) | Clair | 21 | **0** |
| 5 | Documents médicaux (`/documents`) | Sombre | 21 | **0** |
| 6 | Aperçu d'un document médical (`/documents/6`) | Clair | 19 | **0** |
| 7 | Aperçu d'un document médical (`/documents/6`) | Sombre | 19 | **0** |
| 8 | Rapport médical & synthèse (`/reports`) | Clair | 29 | **0** |
| 9 | Rapport médical & synthèse (`/reports`) | Sombre | 29 | **0** |
| 10 | Exports CSV (`/reports/exports`) | Clair | 21 | **0** |
| 11 | Exports CSV (`/reports/exports`) | Sombre | 21 | **0** |
| 12 | Journal d'audit avec diff avant/après (`/audit`) | Clair | 28 | **0** |
| 13 | Journal d'audit avec diff avant/après (`/audit`) | Sombre | 28 | **0** |
| 14 | Réglages unifiés & RGPD (`/settings`) | Clair | 26 | **0** |
| 15 | Réglages unifiés & RGPD (`/settings`) | Sombre | 26 | **0** |
| 16 | Rapport RH & Planifié restant (`/attendance/hours`) | Clair | 19 | **0** |
| 17 | Rapport RH & Planifié restant (`/attendance/hours`) | Sombre | 19 | **0** |
| 18 | Mentions légales (`/legal/mentions`) | Clair | 22 | **0** |
| 19 | Mentions légales (`/legal/mentions`) | Sombre | 22 | **0** |
| 20 | Politique de confidentialité RGPD (`/legal/privacy`) | Clair | 22 | **0** |
| 21 | Politique de confidentialité RGPD (`/legal/privacy`) | Sombre | 22 | **0** |

### Sortie brute de l'exécution `axe-core`
```text
Connexion (/login) => passes: 23 violations: 0
Accueil Admin + Tendances 7 jours (/) [Clair] => passes: 23 violations: 0
Accueil Admin + Tendances 7 jours (/) [Sombre] => passes: 23 violations: 0
Documents médicaux (/documents) [Clair] => passes: 21 violations: 0
Documents médicaux (/documents) [Sombre] => passes: 21 violations: 0
Aperçu d’un document médical (/documents/6) [Clair] => passes: 19 violations: 0
Aperçu d’un document médical (/documents/6) [Sombre] => passes: 19 violations: 0
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
TOTAL VIOLATIONS: 0
```
