# Licences des bibliothèques tierces embarquées (`public/assets/vendor/`)

Conformément aux exigences de confidentialité et de fonctionnement hors ligne de **Prime Care**, aucune ressource externe n'est chargée depuis un CDN. Toutes les ressources tierces sont hébergées localement dans ce répertoire.

---

## 1. Lucide Icons (`public/assets/vendor/lucide/`)
- **Version** : 1.73.0
- **Licence** : ISC License
- **Auteurs** : Lucide Contributors (et Cole Bemis pour Feather Icons)
- **Usage** : Icônes vectorielles SVG rendues en ligne via le helper PHP `icon()`.

```text
ISC License

Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2023 as part of Feather (MIT).
All other copyright (c) for Lucide are held by Lucide Contributors 2026.

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.
```

---

## 2. Police Inter Variable (`public/assets/vendor/inter-var.woff2` & `public/assets/fonts/inter-var.woff2`)
- **Version** : 4.1 (distribuée via `@fontsource-variable/inter` 5.3.0)
- **Licence** : SIL Open Font License 1.1 (OFL-1.1)
- **Auteur** : Rasmus Andersson (The Inter Project Authors)
- **Usage** : Police variable de repli sur Android / Windows / Linux (sur Apple iOS/macOS, la police système SF Pro est prioritaire).

---

## 3. ApexCharts (`public/assets/vendor/apexcharts.min.js`)
- **Version** : 3.54.1 (build UMD autonome conforme CSP sans `unsafe-inline`)
- **Licence** : MIT License
- **Auteur** : ApexCharts / Juned Chhipa
- **Usage** : Graphiques médicaux (courbes 7 / 30 / 90 jours avec bandes de seuils, mini-courbe 7 jours sur l'accueil responsable, chiffres tabulaires et support `prefers-reduced-motion`).

```text
The MIT License (MIT)

Copyright (c) 2018-2024 ApexCharts

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
```

---

## 4. Bibliothèques prévues pour les phases ultérieures
- **PHPMailer** (Phase 3 — LGPL-2.1) : envoi SMTP d'alertes et notifications.
