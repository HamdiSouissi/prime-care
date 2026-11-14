<?php

declare(strict_types=1);

use App\Core\View;

?>
<!-- 1. Identité visuelle & Déclinaisons du logo (§6.0) -->
<section class="pc-card">
  <div class="flex items-center justify-between mb-3">
    <h2 class="text-title-2">1. Identité Prime Care & Loader</h2>
    <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-replay-loader>
      <?= icon('refresh-cw', '', 16) ?>
      <span>Rejouer le loader</span>
    </button>
  </div>
  <p class="text-subhead text-muted mb-4">
    Direction artistique inspirée d’Apple Santé (iOS 17/18). Slogan : « Suivi de soins à domicile » · Éditeur : Prime Vision Solutions.
  </p>

  <div class="pc-swatch-grid mb-4">
    <div class="pc-card flex flex-col items-center justify-center gap-2" style="background: #FFFFFF; color: #0A1633;">
      <?= brand_svg('logo-complet', '', 190) ?>
      <span class="text-caption" style="color: #636366;">Logo complet (Clair · #0A1633)</span>
    </div>

    <div class="pc-card flex flex-col items-center justify-center gap-2" style="background: #0A1633; color: #FFFFFF;">
      <?= brand_svg('logo-complet-blanc', '', 190) ?>
      <span class="text-caption" style="color: rgba(255,255,255,0.75);">Logo complet (Sombre / Dégradé)</span>
    </div>

    <div class="pc-card flex flex-col items-center justify-center gap-2">
      <?= brand_svg('icone-degrade', '', 56) ?>
      <span class="text-caption text-muted">Icône dégradé (PWA / Favicon)</span>
    </div>

    <div class="pc-card flex flex-col items-center justify-center gap-2" style="color: var(--brand-ink);">
      <div class="flex items-center gap-4">
        <?= brand_svg('icone-transparent', '', 48) ?>
        <?= View::partial('loader-mini', ['size' => 36]) ?>
      </div>
      <span class="text-caption text-muted">Monogramme PC & Mini-loader</span>
    </div>
  </div>
</section>

<!-- 2. Palette de couleurs & Tokens CSS (§6.2) -->
<section class="pc-card">
  <h2 class="text-title-2 mb-3">2. Palette de couleurs & Modules</h2>

  <div class="pc-swatch-grid">
    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--brand-gradient);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Dégradé Prime Care</div>
        <div class="text-caption text-muted">#5E5CE6 → #00C7BE</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--brand);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Indigo Santé (--brand)</div>
        <div class="text-caption text-muted">#5E5CE6 / #7D7AFF</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--accent-cyan);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Turquoise (--accent-cyan)</div>
        <div class="text-caption text-muted">#00C7BE / #5AC8FA</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--c-vitals);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Constantes & Glycémie</div>
        <div class="text-caption text-muted">#FF2D55 (Corail)</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--c-meds);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Médicaments & Insuline</div>
        <div class="text-caption text-muted">#007AFF (Bleu)</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--c-meals);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Repas & Hydratation</div>
        <div class="text-caption text-muted">#FF9500 (Ambre)</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--c-notes);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Journal & Soins</div>
        <div class="text-caption text-muted">#AF52DE (Violet)</div>
      </div>
    </div>

    <div class="pc-swatch">
      <span class="pc-swatch__color" style="background: var(--c-team);"></span>
      <div>
        <div class="text-footnote" style="font-weight: 600;">Planning & Équipe</div>
        <div class="text-caption text-muted">#34C759 (Vert)</div>
      </div>
    </div>
  </div>
</section>

<!-- 3. Échelle typographique (§6.3) -->
<section class="pc-card flex flex-col gap-2">
  <h2 class="text-title-2 mb-2">3. Échelle typographique (Inter Variable)</h2>
  <div class="text-display text-tabular">1,12 g/L · Display 40px</div>
  <div class="text-large-title">Large Title · 32px Bold</div>
  <div class="text-title-1">Title 1 · 24px Bold</div>
  <div class="text-title-2">Title 2 · 20px Semibold</div>
  <div class="text-title-3">Title 3 · 17px Semibold</div>
  <div class="text-headline">Headline · 16px Semibold</div>
  <div class="text-body">Body · 16px Regular — Conçu pour la lecture à une main de nuit comme de jour.</div>
  <div class="text-subhead text-muted">Subhead · 14px Regular (Texte secondaire)</div>
  <div class="text-footnote text-muted">Footnote · 13px Regular</div>
  <div class="text-caption text-muted">Caption · 12px Medium</div>
</section>

<!-- 4. Boutons, Badges & Contrôles (§6.5) -->
<section class="pc-card flex flex-col gap-4">
  <h2 class="text-title-2">4. Boutons, Badges & Contrôles</h2>

  <div class="flex flex-col gap-2">
    <button type="button" class="pc-btn pc-btn--primary pc-btn--block">
      <?= icon('check', '', 18) ?>
      <span>Bouton Primaire (Dégradé Prime Care)</span>
    </button>
    <button type="button" class="pc-btn pc-btn--tinted pc-btn--block">
      <?= icon('plus', '', 18) ?>
      <span>Bouton Tinted (Indigo doux)</span>
    </button>
    <button type="button" class="pc-btn pc-btn--secondary pc-btn--block">
      <span>Bouton Secondaire</span>
    </button>
    <button type="button" class="pc-btn pc-btn--danger pc-btn--block">
      <?= icon('alert-triangle', '', 18) ?>
      <span>Action Critique / Urgence</span>
    </button>
  </div>

  <div class="flex items-center gap-2" style="flex-wrap: wrap;">
    <span class="pc-badge pc-badge--brand">Indigo Santé</span>
    <span class="pc-badge pc-badge--ok">Dans la cible</span>
    <span class="pc-badge pc-badge--warn">À surveiller</span>
    <span class="pc-badge pc-badge--danger">Alerte critique</span>
    <span class="pc-badge pc-badge--info">Information</span>
  </div>

  <div>
    <div class="pc-label mb-2">Contrôle segmenté (glissement spring)</div>
    <div class="pc-segmented" data-segmented role="group" aria-label="Démo contrôle segmenté">
      <button type="button" class="pc-segmented__btn is-active" data-value="jour" aria-pressed="true">Jour</button>
      <button type="button" class="pc-segmented__btn" data-value="semaine" aria-pressed="false">7 jours</button>
      <button type="button" class="pc-segmented__btn" data-value="mois" aria-pressed="false">30 jours</button>
    </div>
  </div>

  <div class="flex items-center justify-between">
    <span class="text-body">Interrupteur style iOS (Switch)</span>
    <label class="pc-switch" aria-label="Démo interrupteur">
      <input type="checkbox" checked>
      <span class="pc-switch__track"><span class="pc-switch__thumb"></span></span>
    </label>
  </div>

  <div class="flex items-center gap-2">
    <button type="button" class="pc-btn pc-btn--tinted pc-btn--sm" data-sheet-open="demo-design-sheet">
      Ouvrir une feuille modale
    </button>
  </div>
</section>

<!-- Feuille modale de démonstration -->
<div id="demo-design-sheet" class="pc-sheet" role="dialog" aria-modal="true" aria-labelledby="demo-sheet-title" aria-hidden="true">
  <div class="pc-sheet__backdrop" data-sheet-close></div>
  <div class="pc-sheet__panel">
    <div class="pc-sheet__handle"></div>
    <div class="pc-sheet__header">
      <h2 id="demo-sheet-title" class="pc-sheet__title">Feuille modale iOS (Bottom Sheet)</h2>
      <button type="button" class="pc-navbar__btn" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 20) ?>
      </button>
    </div>
    <p class="text-subhead text-muted mb-4">
      Supporte le glissement vertical au doigt (swipe-to-close), le piège de focus clavier et le fond flouté (glassmorphism).
    </p>
    <button type="button" class="pc-btn pc-btn--primary pc-btn--block" data-sheet-close>
      Fermer la feuille
    </button>
  </div>
</div>
