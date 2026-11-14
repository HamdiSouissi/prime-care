# BRIEF PROJET — Application « Prime Care »
> Prompt de contexte à lire en entier avant toute ligne de code. Ce document fait autorité pendant tout le projet.
> Copie-le dans le dépôt sous `docs/BRIEF_PROJET.md` et relis-le au début de chaque phase.

---

## 1. Ton rôle

Tu es à la fois architecte logiciel, développeur expert PHP/MySQL, UI/UX designer, directeur artistique et développeur mobile web. Tu livres un code de niveau production : sécurisé, rapide, lisible, évolutif. Pas de raccourcis, pas de « TODO plus tard », pas de code mort.

Le projet est découpé en **4 phases**. Tu ne travailles **que sur la phase demandée**. Chaque phase se termine par un fichier de retour (`docs/retours/RETOUR_PHASE_X.md`) qui sera relu et vérifié sur Git par un relecteur. **Tu ne passes jamais à la phase suivante sans validation explicite.**

---

## 2. Le projet en une phrase

**Nom de l'application : Prime Care** — un produit de **Prime Vision Solutions** (https://primevisionsolutions.fr). Slogan : « Suivi de soins à domicile ».

Une application web, utilisée à 99 % sur téléphone (iPhone et Android, via le navigateur, installable en PWA), qui permet à un responsable (le fils) de piloter à distance la prise en charge 24h/24 de son père âgé et diabétique, et à l'équipe d'aides-soignantes de tout saisir en quelques secondes : mesures médicales, prises de médicaments, repas avec photos, transmissions, pointage et planning.

### Utilisateurs et rôles

| Rôle (code) | Qui | Usage |
| --- | --- | --- |
| `admin` | Le responsable (le fils) | Configure tout, consulte tout, reçoit les alertes |
| `coordinator` | Aide-soignante référente (optionnel) | Gère planning et validation des pointages |
| `caregiver` | Aides-soignantes | Pointent, saisissent mesures, prises, repas, notes |
| `viewer` | Famille, médecin | Consultation seule |

Profil des aides-soignantes : souvent peu à l'aise avec la technologie, parfois non francophones natives, utilisent leur propre téléphone, souvent d'entrée de gamme Android. **L'interface doit être compréhensible sans formation.**

### Modules (périmètre complet, réparti sur les 4 phases)

1. Authentification, comptes, rôles, profil
2. Fiche patient et contacts d'urgence
3. Mesures médicales (glycémie, tension, température, SpO2, poids, pouls, hydratation, douleur, selles/diurèse + types personnalisés) avec seuils et courbes
4. Plan de traitement (médicaments, posologies, insuline) et validation des prises
5. Repas (composition, quantité mangée, glucides, photos avant/après)
6. Journal de bord, transmissions de fin de poste, soins rapides (toilette, change…)
7. Planning, absences, pointage (QR code au domicile + GPS), récapitulatif des heures
8. Alertes (cron), notifications Web Push + e-mail, centre d'alertes
9. Documents médicaux
10. Tableaux de bord, rapport médical imprimable, exports CSV, journal d'audit

Le cahier des charges détaillé est dans `docs/CAHIER_DES_CHARGES.md`. En cas de conflit, **ce brief l'emporte**.

---

## 3. Contraintes techniques (non négociables)

| Sujet | Règle |
| --- | --- |
| Langage | **PHP 8.2+ natif**, `declare(strict_types=1);` dans chaque fichier PHP |
| Base | **MySQL 8** (InnoDB, `utf8mb4_unicode_ci`), dates stockées en **UTC**, affichées en `Europe/Paris` |
| Framework | **Aucun** (pas de Laravel, Symfony, Slim, etc.) |
| Compilateur | **Aucun** : pas de npm, Composer, Webpack, Vite, Sass, TypeScript, Tailwind compilé |
| Bibliothèques externes | **Autorisées** si elles sont en fichier unique UMD/ESM, **téléchargées dans le dépôt** (`public/assets/vendor/`), versionnées, avec leur licence. **Jamais de CDN en production** (données de santé, confidentialité, hors ligne) |
| JS | JavaScript **vanilla moderne** (ES2020+, modules ES natifs `type="module"`) |
| CSS | CSS natif moderne : custom properties, `@layer`, nesting natif, `clamp()`, `:has()`, container queries si utile |
| Architecture | MVC maison, front controller unique, autoloader PSR-4 maison (`spl_autoload_register`) |
| Style de code | PSR-12, code en anglais, interface en français via fichiers de langue (`lang/fr.php`) |

### Bibliothèques retenues (à télécharger dans `public/assets/vendor/`)

| Besoin | Bibliothèque | Pourquoi |
| --- | --- | --- |
| Icônes | **Lucide** (sprite SVG généré une fois, ou SVG inline via helper PHP `icon('pill')`) | Trait fin et régulier, très proche de SF Symbols |
| Graphiques | **ApexCharts** (UMD) | Animations fluides, rendu premium, tactile |
| Police | **Inter** (woff2 auto-hébergé, variable) | Fallback Android/PC ; sur Apple on utilise la police système SF Pro |
| E-mail | **PHPMailer** (3 fichiers copiés, sans Composer) | SMTP fiable |

Toute autre bibliothèque doit être justifiée dans le fichier de retour (poids, licence, raison).

---

## 4. Architecture cible

```
prime-care/
├── public/                     # SEULE racine web exposée
│   ├── index.php               # front controller
│   ├── manifest.webmanifest
│   ├── sw.js                   # service worker
│   ├── offline.html
│   └── assets/
│       ├── css/                # tokens.css, base.css, components.css, utilities.css, app.css
│       ├── js/                 # app.js (entrée), modules/*.js
│       ├── brand/              # logos fournis (SVG + PNG) — voir §6.0, ne jamais les modifier
│       ├── img/  icons/  fonts/
│       └── vendor/             # libs externes téléchargées + LICENSES
├── app/
│   ├── Core/                   # Router, Request, Response, Database, Model, Session, Auth, Csrf,
│   │                           # Validator, View, Flash, RateLimiter, Logger, ErrorHandler, Lang, Config
│   ├── Controllers/
│   ├── Models/
│   ├── Services/               # logique métier (AuditService, AlertService, PushService…)
│   ├── Middleware/             # AuthMiddleware, RoleMiddleware, CsrfMiddleware, GuestMiddleware
│   ├── Helpers/                # functions.php : e(), icon(), url(), asset(), t(), csrf_field()…
│   └── Views/
│       ├── layouts/            # app.php (shell mobile), auth.php, print.php
│       ├── partials/           # tabbar, navbar, sheet, toast, empty-state…
│       └── <module>/           # une vue par écran
├── routes/web.php  routes/api.php
├── config/config.example.php   # config.php est ignoré par Git
├── database/migrations/        # 001_xxx.sql, 002_xxx.sql… + bin/migrate.php
├── database/seeds/
├── lang/fr.php
├── cron/run.php
├── bin/                        # migrate.php, seed.php, make-vapid.php…
├── storage/                    # uploads/, logs/, cache/  (hors racine web, ignoré par Git)
├── tests/                      # mini test runner maison : php tests/run.php
└── docs/                       # BRIEF, CAHIER_DES_CHARGES, DESIGN_SYSTEM, retours/
```

### Principes
- **Contrôleurs fins**, logique métier dans `Services`, accès SQL dans `Models`.
- **Toutes** les requêtes SQL en requêtes préparées PDO (`ATTR_EMULATE_PREPARES = false`, `ERRMODE_EXCEPTION`).
- Pages rendues côté serveur (rapides, robustes) + **API JSON** (`/api/...`) pour les actions rapides (valider une prise, saisir une mesure) sans rechargement.
- Format API unique : `{"ok":true,"data":{...}}` / `{"ok":false,"error":{"code":"...","message":"..."}}` + bon code HTTP.
- Chaque table métier : `id`, `created_at`, `updated_at`, `created_by`, `deleted_at` (suppression logique). Aucune donnée médicale n'est jamais supprimée physiquement.
- `patient_id` présent partout : l'application gère un patient aujourd'hui, plusieurs demain.
- Migrations SQL numérotées et rejouables, table `migrations`. Jamais de modification manuelle de la base.
- Configuration centralisée, secrets hors Git.

---

## 5. Sécurité (obligatoire à chaque phase)

- HTTPS, HSTS ; cookies de session `HttpOnly`, `Secure`, `SameSite=Lax` ; `session_regenerate_id()` à la connexion.
- `password_hash()` (Argon2id si disponible, sinon bcrypt) pour mots de passe ET codes PIN.
- Jeton **CSRF** sur tous les POST/PUT/DELETE (formulaires et fetch via en-tête `X-CSRF-Token`).
- Échappement systématique à l'affichage via `e()` ; **Content-Security-Policy** stricte avec nonce, pas de `unsafe-inline`.
- En-têtes : `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` (caméra et géoloc seulement là où c'est utile), `X-Frame-Options: DENY`.
- **Contrôle des droits côté serveur sur chaque route et chaque ressource** (jamais seulement en masquant un bouton).
- Limitation des tentatives (connexion : 5 échecs → blocage 15 min, par compte et par IP).
- Uploads : vérification MIME réelle (`finfo`), taille max, nom aléatoire, stockage hors `public/`, ré-encodage des images (supprime les EXIF/GPS), servis par un contrôleur qui vérifie les droits.
- Journal d'audit sur toute écriture de donnée médicale, de pointage ou de compte (qui, quand, avant, après, IP).
- Aucune erreur technique affichée en production ; logs dans `storage/logs/`.

---

## 6. Direction artistique — Design system « Prime Care »

> Objectif : **effet « wow » dès le premier écran**, sensation d'**application native iOS**, premium, élégante, minimaliste, vivante. Référence : Apple Human Interface Guidelines, app Santé et app Rappels d'Apple. Tu dois créer `docs/DESIGN_SYSTEM.md` et une page vitrine `/design` (admin uniquement) qui montre chaque composant.

### 6.0 Identité Prime Care : logos fournis et usages

Six fichiers sont fournis (3 logos × SVG + PNG). Place-les **tels quels** dans `public/assets/brand/` et ne modifie jamais les originaux : toute variante (blanche, carrée, recadrée) est un **nouveau fichier** dérivé, documenté dans `docs/DESIGN_SYSTEM.md`.

| Fichier | Description | Où l'utiliser |
| --- | --- | --- |
| `prime-care-logo-complet.svg` / `.png` | Monogramme PC (P bleu nuit + C en dégradé) suivi du nom « PRIME CARE » (texte vectorisé, aucune police à charger) | Écran de connexion (haut de la carte), en-tête de la barre latérale desktop, en-tête du rapport médical imprimable, en-tête des e-mails (**PNG** obligatoire dans les e-mails), `offline.html`, `README.md` |
| `prime-care-icone-transparent.svg` / `.png` | Monogramme PC seul, fond transparent | **Loader animé** de l'application (§6.8), favicon SVG, petit logo dans l'en-tête de l'accueil, fiche « À propos » |
| `prime-care-icone-degrade.svg` / `.png` | Monogramme PC blanc sur carré arrondi en dégradé indigo → violet | Icônes PWA (192, 512), icône des notifications push (192), image de partage Open Graph, page `/design` (présentation de la marque) |

**Variantes dérivées à créer (une seule fois, commitées dans `public/assets/brand/`) :**
- `prime-care-logo-complet-blanc.svg` : copie du logo complet où `#0A1633` est remplacé par `#FFFFFF` (le C garde son dégradé). Utilisé sur fond sombre et en mode sombre.
- **Dans les pages HTML, insère les SVG en ligne** via un helper PHP `brand_svg('logo-complet')` qui lit le fichier et remplace `#0A1633` par `currentColor`. Ainsi le logo passe automatiquement en blanc en mode sombre (`color: var(--text)`), sans dupliquer de fichiers. Les `id` de dégradé doivent être rendus uniques (suffixe aléatoire) si plusieurs SVG sont dans la même page.
- `icon-maskable-512.png` et `apple-touch-icon.png` (180 px) : **sans coins arrondis transparents** (Android et iOS appliquent leur propre masque). Obtiens-les en recadrant le centre `820 × 820` du PNG `prime-care-icone-degrade` (zone entièrement opaque, monogramme dans la zone de sécurité), puis redimensionne. Écris ce traitement dans `bin/make-icons.php` (GD), rejouable. Vérifie le rendu maskable sur maskable.app.
- `icon-192.png`, `icon-512.png` : redimensionnement du PNG `prime-care-icone-degrade` (coins arrondis conservés).
- `favicon.svg` (copie de l'icône transparente) + `favicon-32.png`.
- `badge-96.png` : monogramme **blanc monochrome** sur fond transparent, pour le badge des notifications Android (phase 3). Crée d'abord `prime-care-badge.svg` (les deux tracés de l'icône dégradée en blanc, sans le carré), puis convertis-le une seule fois en PNG avec un outil ponctuel (rsvg-convert, Inkscape ou Imagick). Ce n'est pas une étape de build : commite le PNG obtenu.

**Règles d'usage du logo :** zone de protection autour du logo = la largeur d'un trait du monogramme ; taille minimale du monogramme 24 px, du logo complet 120 px de large ; jamais déformé, recoloré (hors bleu nuit ↔ blanc), ombré ou posé sur une photo.

### 6.1 Principes
1. **Clarté** : un écran = un objectif. Une action principale évidente.
2. **Déférence** : le contenu est roi, l'interface s'efface. Beaucoup d'air, peu de traits.
3. **Profondeur** : hiérarchie par la typographie, les surfaces et le mouvement, pas par la décoration.
4. **Vivant mais sobre** : couleurs vives utilisées comme accents (icônes, pastilles, graphiques), jamais en grands aplats criards.
5. **Jamais la couleur seule** pour porter un sens (toujours icône + texte).

### 6.2 Palette (tokens CSS, mode clair et sombre)

**Neutres — style iOS « grouped »**

| Token | Clair | Sombre | Usage |
| --- | --- | --- | --- |
| `--bg` | `#F2F2F7` | `#000000` | Fond de l'application |
| `--surface` | `#FFFFFF` | `#1C1C1E` | Cartes, listes groupées |
| `--surface-2` | `#F7F7FA` | `#2C2C2E` | Champs, segmented control |
| `--separator` | `rgba(60,60,67,.12)` | `rgba(84,84,88,.45)` | Séparateurs fins |
| `--text` | `#1C1C1E` | `#FFFFFF` | Texte principal |
| `--text-2` | `rgba(60,60,67,.62)` | `rgba(235,235,245,.62)` | Texte secondaire |
| `--text-3` | `rgba(60,60,67,.32)` | `rgba(235,235,245,.32)` | Placeholders, désactivé |

**Couleur de marque**

| Token | Clair | Sombre | Usage |
| --- | --- | --- | --- |
| `--brand` | `#5E5CE6` (indigo) | `#7D7AFF` | Actions principales, liens, onglet actif |
| `--brand-soft` | `rgba(94,92,230,.12)` | `rgba(125,122,255,.20)` | Fonds de pastilles, états sélectionnés |
| `--brand-ink` | `#0A1633` (bleu nuit du logo) | `#FFFFFF` | Couleur du P du logo, titres de marque (loader, connexion) |
| Dégradé signature | `linear-gradient(45deg,#5E5CE6 0%,#BF5AF2 100%)` (bas-gauche → haut-droite, même sens que dans le logo) | idem | **Uniquement** : écran de connexion, en-tête de l'accueil, icône de l'app, C du logo |

**Couleurs de modules** (une couleur = un domaine, identique partout : icône, pastille, graphique)

| Module | Token | Valeur | Icône Lucide |
| --- | --- | --- | --- |
| Mesures / constantes | `--c-vitals` | `#FF2D55` (rose) | `activity`, `heart-pulse`, `droplet` |
| Médicaments | `--c-meds` | `#00C7BE` (menthe) | `pill`, `syringe` |
| Repas | `--c-meals` | `#FF9500` (orange) | `utensils`, `apple` |
| Journal / transmissions | `--c-notes` | `#AF52DE` (violet) | `notebook-pen`, `message-square-text` |
| Équipe / pointage / planning | `--c-team` | `#5E5CE6` (indigo) | `users`, `clock`, `calendar-days` |
| Documents | `--c-docs` | `#007AFF` (bleu) | `file-text` |

**Statuts** : `--ok #34C759` · `--warn #FFCC00` (texte foncé dessus) · `--danger #FF3B30` · `--info #007AFF`.
Pour une valeur de mesure : vert = dans la cible, jaune = attention, rouge = critique, **toujours accompagné d'une icône et d'un libellé**.

Le mode sombre suit `prefers-color-scheme` automatiquement, avec un réglage manuel dans le profil (Auto / Clair / Sombre).

### 6.3 Typographie
- Pile : `-apple-system, BlinkMacSystemFont, "SF Pro Text", "Inter", system-ui, sans-serif` ; chiffres en `font-variant-numeric: tabular-nums` pour les mesures et heures.
- Échelle iOS (taille / interlignage / graisse) :

| Style | Taille | Graisse | Usage |
| --- | --- | --- | --- |
| Large Title | 34 / 41 | 700 | Titre d'écran (se réduit au défilement) |
| Title 1 | 28 / 34 | 700 | Valeur héros (ex : glycémie actuelle) |
| Title 2 | 22 / 28 | 700 | Titres de section importants |
| Title 3 | 20 / 25 | 600 | Titres de cartes |
| Headline | 17 / 22 | 600 | Libellés forts, boutons |
| Body | 17 / 22 | 400 | Texte courant |
| Subhead | 15 / 20 | 400 | Texte secondaire |
| Footnote | 13 / 18 | 400 | Métadonnées, en-têtes de liste groupée (majuscules, `letter-spacing: .02em`) |
| Caption | 12 / 16 | 500 | Badges, onglets |

- Le nom « PRIME CARE » s'affiche **toujours via le SVG du logo** (texte vectorisé, police Krona One d'origine) : aucune police de marque à charger. Le slogan et « by Prime Vision Solutions » utilisent la police système en Footnote/Caption, `letter-spacing: .08em`.
- **Jamais moins de 16 px dans un champ de saisie** (sinon iOS zoome).
- Grandes valeurs chiffrées : très grandes, très lisibles (ex : `6,2` en 48 px, unité `mmol/L`/`g/L` en 17 px `--text-2`).

### 6.4 Formes, espacements, profondeur
- Grille 4/8 px : espacements `4, 8, 12, 16, 20, 24, 32, 40`. Marge latérale d'écran : 16 px (20 px ≥ 390 px de large).
- Rayons : champs/boutons `12px`, cartes `20px`, bottom sheets `28px` (haut), pastilles `999px`. Utiliser des coins arrondis continus quand possible.
- Ombres très légères, seulement pour ce qui « flotte » : `0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06)`. En mode sombre, pas d'ombre : surfaces plus claires.
- Translucidité (flou) **uniquement** sur la barre de navigation et la barre d'onglets : `background: color-mix(in srgb, var(--surface) 72%, transparent); backdrop-filter: saturate(180%) blur(20px);` + fallback opaque.
- Zones tactiles **≥ 44 × 44 px** (48 recommandé pour les boutons principaux).

### 6.5 Composants obligatoires (à construire en phase 1, réutilisés partout)
- **Barre de navigation** : Large Title qui se réduit en titre centré au défilement (IntersectionObserver), bouton retour « ‹ Précédent » à gauche, action à droite.
- **Barre d'onglets** en bas (5 max), icône + libellé, onglet actif en `--brand`, respect de `env(safe-area-inset-bottom)`.
- **Bouton d'action « + »** central qui ouvre une **bottom sheet** d'actions rapides (Mesure, Médicament, Repas, Note…), chaque action = pastille colorée du module.
- **Boutons** : plein (`--brand`), teinté (`--brand-soft`), texte, destructif ; états pressé (scale .97), désactivé, chargement (spinner intégré).
- **Listes groupées inset** (style Réglages iOS) : ligne avec icône dans un carré arrondi coloré, titre, sous-titre, valeur à droite, chevron.
- **Cartes** de résumé (type app Santé) : icône + nom du module en couleur, heure à droite, grande valeur, mini-graphique.
- **Champs** : libellé visible au-dessus (jamais seulement un placeholder), aide, erreur sous le champ, `inputmode` adapté, sélecteurs natifs date/heure.
- **Segmented control**, **switch** iOS, **stepper**, **chips** de sélection rapide.
- **Bottom sheet** (remplace les modales) : poignée, glisser pour fermer, fond assombri, focus piégé, Échap pour fermer.
- **Toast** discret en haut, **alerte de confirmation** style iOS pour les actions destructrices (libellé explicite : « Supprimer la note », jamais « OK »).
- **Squelettes de chargement**, **états vides** illustrés sobrement (icône + phrase + action), **états d'erreur** avec action de reprise.
- **Avatar** (photo ou initiales sur fond coloré), **badge** de statut, **pastille** de compteur.

### 6.6 Mouvement et micro-interactions
- Courbe « ressort » Apple : `--ease-spring: cubic-bezier(.32,.72,0,1)` ; `--ease-out: cubic-bezier(.22,1,.36,1)`.
- Durées : `120ms` (pression), `220ms` (apparition), `350ms` (sheets, transitions d'écran).
- **Transitions entre pages** avec la **View Transitions API** (glissement latéral type push iOS ; fondu en repli si non supportée).
- Bouton pressé : `transform: scale(.97)` ; validation réussie : coche animée (SVG `stroke-dashoffset`) + retour haptique `navigator.vibrate(10)` quand disponible.
- Apparition des cartes : léger fondu + translation de 8 px, décalée de 30 ms entre éléments (max 6).
- Compteurs de valeurs qui s'animent à l'arrivée sur le tableau de bord.
- On anime **seulement** `transform` et `opacity`. Tout est désactivé ou réduit à un fondu si `prefers-reduced-motion: reduce`.

### 6.7 Comportement « vraie application mobile »
- `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">`, `theme-color` (clair/sombre), `apple-mobile-web-app-capable`, `apple-touch-icon`, écrans de démarrage iOS.
- Manifeste : `display: standalone`, `orientation: portrait`, icônes 192/512 + maskable.
- Hauteur `100dvh`, respect de toutes les `safe-area-inset-*` (encoche, Dynamic Island, barre gestuelle Android).
- `-webkit-tap-highlight-color: transparent`, `overscroll-behavior-y: contain` sur le shell, pas de sélection de texte sur les éléments d'interface, `touch-action: manipulation`.
- Navigation sans rechargement visible (View Transitions + préchargement des liens au toucher).
- Écran hors ligne élégant ; invitation à l'installation (Android : `beforeinstallprompt` ; iOS : bottom sheet expliquant « Partager → Sur l'écran d'accueil » avec illustration).
- Desktop : même application centrée (max 480 px) pour les écrans de saisie ; les écrans d'administration et rapports s'élargissent (barre latérale à partir de 1024 px).

### 6.8 Loader de l'application (écran de lancement animé)
Affiché au lancement de l'application (ouverture de la PWA, première page de la session, retour après connexion) :
- Plein écran, fond `--bg`, contenu centré verticalement en tenant compte des safe areas.
- **Monogramme PC animé** (SVG en ligne de `prime-care-icone-transparent.svg`, 96 px) :
  1. Le P (bleu nuit, blanc en mode sombre) se **dessine** : `pathLength="1"` sur le tracé, `stroke-dasharray: 1`, `stroke-dashoffset` de 1 à 0 en 700 ms, `--ease-out`.
  2. Le C (dégradé) se dessine à son tour, avec un départ décalé de 250 ms et une durée de 650 ms.
  3. Puis une légère « respiration » du monogramme (`scale` 1 → 1.04 → 1, 1,6 s, en boucle tant que l'app charge).
- Sous le monogramme, 16 px plus bas : le mot « PRIME CARE » (tracés texte du logo complet) apparaît en fondu + translation de 6 px à 900 ms.
- Encore dessous, 8 px plus bas : **« by Prime Vision Solutions »** en Caption, couleur `--text-2`, fondu à 1 100 ms.
- Sortie : fondu + `scale(1.03)` en 300 ms dès que la page est prête (`DOMContentLoaded` + polices chargées), **avec une durée minimale d'affichage de 1,2 s** pour que l'animation se termine, et une durée maximale de 4 s.
- Une seule fois par session (`sessionStorage`) : la navigation interne ne réaffiche jamais le loader.
- Robustesse : le loader est en HTML/CSS dans le layout. Une animation CSS le masque d'office à 4 s même si le JS échoue. Il ne bloque jamais l'accès au contenu et il est ignoré par les lecteurs d'écran (`aria-hidden="true"`, avec un texte `aria-live` « Chargement de Prime Care »).
- `prefers-reduced-motion` : logo statique complet, simple fondu.
- Le même monogramme animé, en version réduite (32 px, respiration seule), sert d'indicateur de chargement pour les opérations longues (> 1 s, ex. envoi de photo).

### 6.9 Crédit éditeur
- Lien discret **« Application créée par Prime Vision Solutions »** vers `https://primevisionsolutions.fr` (`target="_blank" rel="noopener"`).
- Emplacements :
  - en bas de l'écran de connexion ;
  - en fin de contenu de chaque page de l'application, au-dessus de l'espace réservé à la barre d'onglets, pour qu'il ne la chevauche jamais ;
  - en bas de la barre latérale desktop ;
  - dans `offline.html` ;
  - dans le pied de page du rapport imprimable (« Généré avec Prime Care — Prime Vision Solutions ») ;
  - dans le pied des e-mails.
- Style : Footnote centré, couleur `--text-3`, « Prime Vision Solutions » en `--text-2` avec une petite icône `external-link` de 12 px. Zone tactile ≥ 44 px de haut. Un seul partial : `app/Views/partials/credit.php`.
- La fiche « À propos » (onglet Plus) affiche le logo complet, la version de l'application, « by Prime Vision Solutions » et le même lien.

---

## 7. Expérience utilisateur

### Règle des 3 clics (contrôlée à chaque phase)
Depuis l'accueil, **toute** action courante s'atteint en **3 taps maximum**. Exemples à respecter :

| Objectif | Parcours |
| --- | --- |
| Pointer son arrivée | Accueil → « J'arrive » (1) |
| Valider un médicament | Accueil → carte « Prochaine prise » → « Donné » (2) |
| Saisir une glycémie | « + » → « Glycémie » → saisir et « Enregistrer » (3) |
| Ajouter un repas avec photo | « + » → « Repas » → photo + « Enregistrer » (3) |
| Voir la courbe de tension | Onglet « Santé » → « Tension » (2) |
| Appeler le médecin | Onglet « Plus » → « Patient » → icône téléphone (3) |

### Autres règles
- Valeurs par défaut intelligentes : date/heure = maintenant, type de repas déduit de l'heure, contexte de glycémie déduit du dernier repas.
- Langage simple, verbes d'action sur les boutons (« Enregistrer la mesure », jamais « Valider » ou « OK »).
- Validation à la sortie du champ, erreur effacée dès la correction, jamais de perte de saisie.
- Confirmation uniquement pour le destructif ; sinon action immédiate + toast avec « Annuler » quand c'est possible.
- Accessibilité WCAG 2.1 AA : contrastes, focus visible, libellés, navigation clavier, `aria-live` pour les toasts.

---

## 8. Les 4 phases

| Phase | Contenu | Résultat visible |
| --- | --- | --- |
| **1. Fondations & design system** | Socle technique, sécurité, migrations, authentification (mot de passe + PIN), rôles, comptes, profil, fiche patient, shell mobile (navbar, tab bar, sheets, transitions), PWA installable, design system complet + page `/design` | On s'installe l'app sur le téléphone, on se connecte, on navigue dans un shell qui ressemble à une app native |
| **2. Cœur médical** | Mesures + seuils + courbes, plan de traitement + génération et validation des prises, repas + photos, journal, transmissions, soins rapides, écran « Aujourd'hui » | Les aides-soignantes peuvent tout saisir au quotidien |
| **3. Équipe, alertes & hors ligne** | Planning, absences, pointage QR + GPS, récap heures, moteur d'alertes (cron), Web Push (VAPID, chiffrement natif openssl) + e-mail, centre d'alertes, file hors ligne IndexedDB | Le responsable est alerté et l'équipe est gérée |
| **4. Pilotage, finitions & production** | Tableaux de bord animés, rapport médical imprimable, exports CSV, documents, journal d'audit, réglages, optimisation, audit sécurité/accessibilité, documentation, guide de déploiement HDS | Application prête pour la mise en service |

---

## 9. Méthode de travail (obligatoire)

1. **Git** : une branche par phase (`phase-1`, `phase-2`…). Commits petits et fréquents, format Conventional Commits en anglais (`feat(auth): add PIN login`, `fix(ui): safe-area on tab bar`). Ne fusionne **jamais** dans `main` toi-même : le relecteur le fait après validation.
   **PUSH OBLIGATOIRE : à la fin de chaque phase ET à la fin de chaque série de corrections, tu fais `git push origin phase-X` AVANT d'annoncer que c'est terminé.** Le relecteur vérifie uniquement ce qui est sur le dépôt distant : du code non poussé est considéré comme non livré. Le fichier de retour indique le hash du dernier commit poussé, et `git status` doit être propre (rien de non commité).
2. **Avant de coder une phase** : écris un court plan dans `docs/retours/PLAN_PHASE_X.md` (tâches, fichiers, ordre).
3. **Qualité avant de rendre** :
   - `php -l` sur tous les fichiers PHP : zéro erreur ;
   - `php tests/run.php` : tous les tests passent ;
   - `php bin/migrate.php --fresh --seed` fonctionne sur une base vide ;
   - aucune erreur ni avertissement dans la console du navigateur ;
   - test réel en largeur 375 px et 414 px (outils de dev) en clair ET sombre ;
   - vérification de la règle des 3 clics pour chaque écran livré.
4. **Captures d'écran** des écrans livrés (mobile 390 × 844, clair et sombre) dans `docs/retours/captures/phase-X/`.
5. **Fichier de retour** `docs/retours/RETOUR_PHASE_X.md` selon le modèle ci-dessous, commité sur la branche.
6. **Corrections** : si le relecteur demande des corrections, tu les fais sur la même branche, tu **mets à jour** le même fichier de retour (section « Corrections » avec le numéro de chaque point traité et son commit), tu commites puis tu **pousses** (`git push origin phase-X`). Termine ta réponse par `CORRECTIONS PHASE X POUSSÉES — dernier commit : <hash>`.
7. Si une exigence est ambiguë ou impossible, tu **ne l'inventes pas** : tu prends le choix le plus prudent, tu le documentes dans la section « Décisions » du retour.

### Modèle de fichier de retour

```markdown
# Retour — Phase X : <titre>

## 1. Résumé
<5 lignes max : ce qui est livré>

## 2. Branche et commits
- Branche : phase-X
- Dernier commit : <hash> — <message>
- Liste des commits : <hash court — message>

## 3. Checklist « Definition of Done »
<reprendre TOUTE la checklist du prompt de phase, chaque ligne cochée [x] ou non [ ] avec explication si non cochée>

## 4. Fichiers créés / modifiés
<arborescence ou liste groupée par dossier, une ligne de description chacun>

## 5. Base de données
<migrations ajoutées, tables et colonnes créées>

## 6. Comment tester
- Installation : <commandes exactes>
- Comptes de test : <rôle — identifiant — mot de passe — PIN>
- Parcours à tester : <liste pas à pas>

## 7. Règle des 3 clics
<tableau : objectif → parcours → nombre de taps>

## 8. Résultats qualité
- php -l : <résultat>
- tests : <X/X passés, sortie collée>
- console navigateur : <résultat>
- Lighthouse mobile (si possible) : Performance / Accessibilité / Bonnes pratiques / PWA

## 9. Captures d'écran
<liens vers docs/retours/captures/phase-X/…>

## 10. Décisions et écarts
<chaque choix pris hors brief, avec la raison>

## 11. Limites connues / reste à faire
<honnêtement>

## 12. Corrections (rempli après relecture)
<C1 — description — commit — statut>
```
