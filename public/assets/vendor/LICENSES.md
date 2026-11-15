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
```

---

## 4. PHPMailer (`public/assets/vendor/phpmailer/`)
- **Version** : 6.9.2 (3 fichiers autonomes sans Composer : `PHPMailer.php`, `SMTP.php`, `Exception.php`)
- **Licence** : GNU Lesser General Public License v2.1 (LGPL-2.1)
- **Auteurs** : Marcus Bointon, Jim Jagielski, Brent R. Matzelle & The PHPMailer Contributors
- **Usage** : Envoi d'e-mails HTML transactionnels et d'alertes médicales via SMTP (avec doublage automatique dans `storage/logs/mail.log` en environnement de développement).

```text
PHPMailer is distributed under the GNU Lesser General Public License v2.1 (LGPL-2.1).
Copyright (c) 2012-2024, Marcus Bointon, Jim Jagielski, Andy Prevost.
Copyright (c) 2001-2003, Brent R. Matzelle.
```

---

## 5. jsQR (`public/assets/vendor/jsQR.min.js`)
- **Version** : 1.4.0
- **Licence** : Apache-2.0 / MIT-compatible Open Source License
- **Auteur** : Cosmo Wolfe
- **Usage** : Décodage local en JavaScript du QR code de pointage du domicile (en repli de l'API native `BarcodeDetector` lorsque celle-ci n'est pas disponible sur le navigateur).
