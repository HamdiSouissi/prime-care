<?php

declare(strict_types=1);
?>
<?php View::section('navbar_action'); ?>
  <button type="button" class="pc-btn pc-btn--primary pc-btn--sm" data-replay-loader>
    <?= icon('sparkles', 15) ?>
    <span>Loader</span>
  </button>
<?php View::endSection(); ?>

<!-- 1.Identité visuelle & Direction artistique Proposition B — Bleu nuit -->
<section aria-labelledby="ds-brand-heading">
  <div class="pc-section-header" id="ds-brand-heading">1. Direction artistique « Proposition B — Bleu nuit »</div>
  <div class="pc-design-grid pc-design-grid--2col">
    <div class="pc-card pc-logo-showcase-light flex flex-col items-center justify-center gap-2">
      <?= brand_svg('prime-care-logo-complet.svg') ?>
      <span class="text-caption pc-logo-caption-light">Logo complet (Clair · #0A1633)</span>
    </div>

    <div class="pc-card pc-logo-showcase-dark flex flex-col items-center justify-center gap-2">
      <?= brand_svg('prime-care-logo-complet-blanc.svg') ?>
      <span class="text-caption pc-logo-caption-dark">Logo complet (Bleu nuit · #0A1633)</span>
    </div>
  </div>

  <div class="pc-design-grid pc-design-grid--2col mt-3">
    <div class="pc-card flex flex-col items-center justify-center gap-2">
      <img src="/assets/brand/prime-care-icone-degrade.svg" alt="Icône dégradé Prime Care" width="72" height="72">
      <span class="text-caption text-muted">Icône dégradé PWA (#5E5CE6 → #BF5AF2)</span>
    </div>

    <div class="pc-card flex flex-col items-center justify-center gap-2 text-brand-ink">
      <?= brand_svg('prime-care-icone-transparent.svg', '', 72) ?>
      <span class="text-caption text-muted">Monogramme PC transparent & Mini-loader</span>
      <?= View::partial('partials/loader-mini') ?>
    </div>
  </div>
</section>

<!-- 2. Palette & Tokens CSS (Proposition B — Bleu nuit) -->
<section aria-labelledby="ds-colors-heading">
  <div class="pc-section-header" id="ds-colors-heading">2. Palette & Tokens CSS (Proposition B — Bleu nuit)</div>
  <div class="pc-card">
    <div class="pc-swatch-grid">
      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--gradient"></span>
        <div class="text-footnote font-semibold">Dégradé Prime Care (45°)</div>
        <div class="text-caption text-muted">#5E5CE6 → #BF5AF2</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--header"></span>
        <div class="text-footnote font-semibold">Bleu nuit (--header)</div>
        <div class="text-caption text-muted">#0A1633 (Clair & Sombre)</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--brand"></span>
        <div class="text-footnote font-semibold">Indigo (--brand)</div>
        <div class="text-caption text-muted">#5E5CE6 / #7D7AFF</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--purple"></span>
        <div class="text-footnote font-semibold">Violet (--brand-purple)</div>
        <div class="text-caption text-muted">#BF5AF2 · Surface #111A33</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--vitals"></span>
        <div class="text-footnote font-semibold">Constantes & Glycémie</div>
        <div class="text-caption text-muted">--c-vitals · #FF2D55</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--meds"></span>
        <div class="text-footnote font-semibold">Médicaments & Insuline</div>
        <div class="text-caption text-muted">--c-meds · #00C7BE</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--meals"></span>
        <div class="text-footnote font-semibold">Repas & Hydratation</div>
        <div class="text-caption text-muted">--c-meals · #FF9500</div>
      </div>

      <div class="pc-swatch">
        <span class="pc-swatch__color pc-swatch__color--notes"></span>
        <div class="text-footnote font-semibold">Journal & Soins</div>
        <div class="text-caption text-muted">--c-notes · #AF52DE</div>
      </div>
    </div>

    <div class="mt-4">
      <div class="text-footnote font-semibold mb-2">Palette fixe d’avatars sans style en ligne (.pc-avatar--c1 à --c8)</div>
      <div class="flex items-center gap-2 flex-wrap">
        <?php for ($i = 1; $i <= 8; $i++): ?>
          <span class="pc-avatar pc-avatar--sm pc-avatar--c<?= $i ?>">C<?= $i ?></span>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<!-- 3. Échelle typographique -->
<section aria-labelledby="ds-typo-heading">
  <div class="pc-section-header" id="ds-typo-heading">3. Échelle typographique (SF Pro / Inter)</div>
  <div class="pc-card flex flex-col gap-3">
    <div>
      <span class="text-caption text-muted">Large Title · 32px / 700</span>
      <div class="text-large-title">Bonjour Fatou</div>
    </div>
    <div>
      <span class="text-caption text-muted">Title 1 · 28px / 700</span>
      <div class="text-title-1">Suivi de soins à domicile</div>
    </div>
    <div>
      <span class="text-caption text-muted">Title 2 · 22px / 600</span>
      <div class="text-title-2">M. Henri Benali · 84 ans</div>
    </div>
    <div>
      <span class="text-caption text-muted">Headline · 17px / 600</span>
      <div class="text-headline">Protocole d’hypoglycémie (&lt; 0,70 g/L)</div>
    </div>
    <div>
      <span class="text-caption text-muted">Body · 17px / 400</span>
      <div class="text-body">Donner immédiatement 15 g de glucides rapides (3 morceaux de sucre ou 15 cL de jus d’orange) et recontrôler à 15 minutes.</div>
    </div>
    <div>
      <span class="text-caption text-muted">Metric Tabular · 32px / 700 (tabular-nums)</span>
      <div class="pc-health-card__value">1,12<span class="pc-health-card__unit">g/L</span></div>
    </div>
  </div>
</section>

<!-- 4. Boutons, Badges & États -->
<section aria-labelledby="ds-buttons-heading">
  <div class="pc-section-header" id="ds-buttons-heading">4. Boutons & Badges (Zone tactile ≥ 44×44 px)</div>
  <div class="pc-card flex flex-col gap-3">
    <div class="pc-module-grid pc-module-grid--2col">
      <button type="button" class="pc-btn pc-btn--primary">Action primaire</button>
      <button type="button" class="pc-btn pc-btn--tinted">Action teintée</button>
      <button type="button" class="pc-btn pc-btn--secondary">Action secondaire</button>
      <button type="button" class="pc-btn pc-btn--danger">Urgence / Danger</button>
    </div>

    <div class="flex items-center gap-2 flex-wrap pt-2">
      <span class="pc-badge pc-badge--brand">Marque Prime Care</span>
      <span class="pc-badge pc-badge--ok">Normal (Cible)</span>
      <span class="pc-badge pc-badge--warn">Vigilance</span>
      <span class="pc-badge pc-badge--danger">Alerte critique</span>
      <span class="pc-badge pc-badge--info">Information</span>
    </div>
  </div>
</section>

<!-- 5. Contrôles interactifs -->
<section aria-labelledby="ds-controls-heading">
  <div class="pc-section-header" id="ds-controls-heading">5. Contrôles tactiles & Feuilles modales</div>
  <div class="pc-card flex flex-col gap-4">
    <div>
      <div class="pc-label mb-2">Segmented Control (Thème en direct)</div>
      <div class="pc-segmented" data-theme-segmented>
        <button type="button" class="pc-segmented__btn is-active" data-theme-value="auto">Auto</button>
        <button type="button" class="pc-segmented__btn" data-theme-value="light">Clair</button>
        <button type="button" class="pc-segmented__btn" data-theme-value="dark">Sombre</button>
      </div>
    </div>

    <div class="flex items-center justify-between">
      <div>
        <div class="text-headline">Stepper numérique (+ / −)</div>
        <div class="text-footnote text-muted">Unités d’insuline rapide</div>
      </div>
      <div class="pc-stepper" data-stepper data-min="0" data-max="40" data-step="1">
        <button type="button" class="pc-stepper__btn" data-stepper-dec aria-label="Diminuer">−</button>
        <span class="pc-stepper__val" data-stepper-val>6</span>
        <button type="button" class="pc-stepper__btn" data-stepper-inc aria-label="Augmenter">+</button>
      </div>
    </div>

    <div>
      <div class="pc-label mb-2">Chips de sélection</div>
      <div class="pc-chips">
        <button type="button" class="pc-chip is-selected" data-chip-toggle>À jeun</button>
        <button type="button" class="pc-chip" data-chip-toggle>Avant repas</button>
        <button type="button" class="pc-chip" data-chip-toggle>Post-prandial (2h)</button>
        <button type="button" class="pc-chip" data-chip-toggle>Coucher</button>
      </div>
    </div>

    <div class="pc-module-grid pc-module-grid--2col pt-2">
      <button type="button" class="pc-btn pc-btn--tinted" data-sheet-open="quick-add-sheet">
        Ouvrir Bottom Sheet (+)
      </button>
      <button type="button" class="pc-btn pc-btn--secondary" data-replay-loader>
        Rejouer le Loader PC
      </button>
    </div>
  </div>
</section>
