<?php

declare(strict_types=1);

if (!can('vitals', 'create')) {
    return;
}

$ctx = quick_add_context();
$measureTypes = $ctx['measure_types'] ?? [];
$careItems = $ctx['care_items'] ?? [];
$favorites = $ctx['food_favorites'] ?? [];
$asNeededList = $ctx['as_needed'] ?? [];
$rotation = $ctx['insulin_rotation'] ?? ['suggested_code' => 'abdomen_g', 'suggested_label' => 'Abdomen gauche', 'last_label' => 'Aucun', 'sites' => []];
$defaultMealType = (string) ($ctx['default_meal_type'] ?? 'lunch');
$defaultGlyContext = (string) ($ctx['default_glycemia_context'] ?? 'fasting');
$nowLocalInput = \App\Services\ScheduleService::utcToLocalParis(gmdate('Y-m-d H:i:s'), 'Y-m-d\TH:i');
?>
<!-- 1. Hub Saisie rapide (+) -->
<div class="pc-sheet-backdrop" id="quick-add-sheet" role="dialog" aria-modal="true" aria-labelledby="quick-add-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="quick-add-title">Saisie rapide</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <div class="pc-quick-grid">
      <button type="button" class="pc-quick-tile" data-open-measure-sheet="glycemia">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--vitals">
          <?= icon('droplet', '', 22) ?>
        </span>
        <div class="text-left">
          <div class="font-semibold">Glycémie</div>
          <div class="text-caption text-muted">Capillaire · g/L</div>
        </div>
      </button>

      <button type="button" class="pc-quick-tile" data-open-measure-sheet="blood_pressure">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--vitals">
          <?= icon('heart-pulse', '', 22) ?>
        </span>
        <div class="text-left">
          <div class="font-semibold">Tension</div>
          <div class="text-caption text-muted">Syst. / Diast.</div>
        </div>
      </button>

      <button type="button" class="pc-quick-tile" data-open-measure-sheet="temperature">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--vitals">
          <?= icon('thermometer', '', 22) ?>
        </span>
        <div class="text-left">
          <div class="font-semibold">Constantes</div>
          <div class="text-caption text-muted">Temp., SpO2, Poids</div>
        </div>
      </button>

      <button type="button" class="pc-quick-tile" data-switch-sheet="sheet-as-needed">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--meds">
          <?= icon('pill', '', 22) ?>
        </span>
        <div class="text-left">
          <div class="font-semibold">Médicament</div>
          <div class="text-caption text-muted">Prise / Si besoin</div>
        </div>
      </button>

      <button type="button" class="pc-quick-tile" data-switch-sheet="sheet-meal">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--meals">
          <?= icon('utensils', '', 22) ?>
        </span>
        <div class="text-left">
          <div class="font-semibold">Repas</div>
          <div class="text-caption text-muted">Photo &amp; Quantité</div>
        </div>
      </button>

      <button type="button" class="pc-quick-tile" data-switch-sheet="sheet-note">
        <span class="pc-quick-tile__icon pc-quick-tile__icon--notes">
          <?= icon('notebook-pen', '', 22) ?>
        </span>
        <div class="text-left">
          <div class="font-semibold">Note / Soin</div>
          <div class="text-caption text-muted">Transmission</div>
        </div>
      </button>
    </div>
  </div>
</div>

<!-- 2. E06 — Saisie de mesure (Bottom Sheet temps réel + protocole hypo) -->
<div class="pc-sheet-backdrop" id="sheet-measure" role="dialog" aria-modal="true" aria-labelledby="sheet-measure-title">
  <div class="pc-sheet pc-sheet--tall">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="sheet-measure-title">Nouvelle mesure</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <form method="POST" action="/health/measures" id="form-quick-measure" data-api-form="/api/measures" class="pc-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="expectation_id" id="measure-expectation-id" value="">
      <input type="hidden" name="confirm_implausible" id="measure-confirm-implausible" value="0">

      <!-- Sélecteur de type de mesure -->
      <div class="pc-field">
        <label class="pc-label" for="measure-type-select">Type de constante</label>
        <select name="type_code" id="measure-type-select" class="pc-input">
          <?php foreach ($measureTypes as $mt): ?>
            <option
              value="<?= e((string) $mt['code']) ?>"
              data-unit="<?= e((string) $mt['unit']) ?>"
              data-decimals="<?= (int) ($mt['decimals'] ?? 1) ?>"
              data-min="<?= (float) $mt['min_value'] ?>"
              data-max="<?= (float) $mt['max_value'] ?>"
              data-crit-low="<?= e((string) ($mt['crit_low'] ?? '')) ?>"
              data-low="<?= e((string) ($mt['low'] ?? '')) ?>"
              data-high="<?= e((string) ($mt['high'] ?? '')) ?>"
              data-crit-high="<?= e((string) ($mt['crit_high'] ?? '')) ?>"
            >
              <?= e((string) $mt['label']) ?> (<?= e((string) $mt['unit']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Saisie numérique principale XL + badge temps réel -->
      <div class="pc-measure-entry-box">
        <div class="pc-measure-entry-row">
          <div class="pc-measure-entry-field">
            <label class="pc-label" for="measure-value-input" id="measure-value-label">Valeur mesurée</label>
            <div class="pc-measure-input-wrap">
              <input
                type="text"
                inputmode="decimal"
                name="value"
                id="measure-value-input"
                class="pc-input pc-input--metric"
                placeholder="1,10"
                autocomplete="off"
                required
              >
              <span class="pc-measure-unit-badge" id="measure-unit-display">g/L</span>
            </div>
          </div>

          <div class="pc-measure-entry-field is-hidden" id="measure-value2-group">
            <label class="pc-label" for="measure-value2-input">Diastolique</label>
            <div class="pc-measure-input-wrap">
              <input
                type="text"
                inputmode="numeric"
                name="value2"
                id="measure-value2-input"
                class="pc-input pc-input--metric"
                placeholder="80"
                autocomplete="off"
              >
              <span class="pc-measure-unit-badge">mmHg</span>
            </div>
          </div>
        </div>

        <!-- Champ pouls associé pour la tension -->
        <div class="pc-field mt-2 is-hidden" id="measure-value3-group">
          <label class="pc-label" for="measure-value3-input">Pouls associé (optionnel, bpm)</label>
          <input
            type="text"
            inputmode="numeric"
            name="value3"
            id="measure-value3-input"
            class="pc-input"
            placeholder="72"
          >
        </div>

        <!-- Badge de statut temps réel (Normal / À surveiller / Critique) -->
        <div id="measure-live-badge" class="pc-live-eval pc-live-eval--neutral" aria-live="polite">
          <span class="pc-live-eval__dot"></span>
          <span id="measure-live-text">Saisissez une valeur pour évaluer le seuil</span>
        </div>
      </div>

      <!-- Alerte immédiate Protocole d'hypoglycémie (< 0,70 g/L) -->
      <div id="measure-hypo-protocol" class="pc-alert-card pc-alert-card--critical is-hidden" role="alert">
        <div class="pc-alert-card__header">
          <?= icon('alert-triangle', '', 20) ?>
          <strong>URGENCE HYPOGLYCÉMIE (&lt; 0,70 g/L)</strong>
        </div>
        <ol class="pc-alert-card__steps">
          <li><strong>Resucrer immédiatement</strong> : 15 g de glucides rapides (3 morceaux de sucre ou 1 verre de jus de fruits 15 cL).</li>
          <li>Mettre M. Benali au repos assis ou allongé. Ne rien donner par la bouche en cas de malaise.</li>
          <li><strong>Contrôler la glycémie à +15 min</strong>. Si &lt; 0,70 g/L persiste ou signes neurologiques, appeler le <strong>15 (SAMU)</strong>.</li>
        </ol>
        <div class="flex gap-2 mt-2">
          <a href="tel:15" class="pc-btn pc-btn--danger pc-btn--sm">Appeler le 15</a>
          <a href="/patient" class="pc-btn pc-btn--ghost pc-btn--sm">Fiche d’urgence</a>
        </div>
      </div>

      <!-- Confirmation valeur hors bornes plausibles -->
      <div id="measure-plausibility-box" class="pc-alert-card pc-alert-card--warn is-hidden" role="alert">
        <div class="pc-alert-card__header">
          <?= icon('alert-triangle', '', 18) ?>
          <span id="measure-plausibility-msg">Valeur inhabituelle détectée.</span>
        </div>
        <label class="pc-checkbox-row mt-2">
          <input type="checkbox" id="measure-plausibility-check">
          <span>Je confirme avoir revérifié cette valeur sur l’appareil.</span>
        </label>
      </div>

      <!-- Contexte (pré-sélectionné automatiquement selon l'heure) -->
      <div class="pc-field" id="measure-context-group">
        <span class="pc-label">Contexte de la mesure</span>
        <div class="pc-chip-group" role="radiogroup" aria-label="Contexte de la mesure">
          <?php
          $contexts = [
              'fasting' => 'À jeun',
              'pre_meal' => 'Avant repas',
              'post_meal' => 'Après repas (2h)',
              'bedtime' => 'Coucher',
              'symptom' => 'Symptôme / Contrôle',
              'routine' => 'Routine',
          ];
          foreach ($contexts as $cCode => $cLabel):
          ?>
            <label class="pc-chip-radio">
              <input
                type="radio"
                name="context"
                value="<?= e($cCode) ?>"
                <?= $defaultGlyContext === $cCode ? 'checked' : '' ?>
              >
              <span><?= e($cLabel) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Horodatage & Commentaire -->
      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="measure-time-input">Date et heure</label>
          <input
            type="datetime-local"
            name="measured_at"
            id="measure-time-input"
            class="pc-input"
            value="<?= e($nowLocalInput) ?>"
          >
        </div>
        <div class="pc-field">
          <label class="pc-label" for="measure-comment-input">Observation (optionnel)</label>
          <input
            type="text"
            name="comment"
            id="measure-comment-input"
            class="pc-input"
            placeholder="Ex. avant déjeuner, après marche..."
          >
        </div>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--lg pc-btn--block">
        <?= icon('check', '', 20) ?>
        <span>Enregistrer la mesure</span>
      </button>
    </form>
  </div>
</div>

<!-- 3. E08 — Validation d'insuline variable & grille de correction du responsable -->
<div class="pc-sheet-backdrop" id="sheet-insulin-give" role="dialog" aria-modal="true" aria-labelledby="sheet-insulin-title">
  <div class="pc-sheet pc-sheet--tall">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <div>
        <h2 class="pc-sheet__title" id="sheet-insulin-title">Insuline rapide · Grille</h2>
        <p class="text-caption text-muted">Dose selon le tableau du responsable</p>
      </div>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <form method="POST" action="/treatments/intakes/0/give" id="form-insulin-give" class="pc-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="intake_id" id="insulin-intake-id" value="">

      <div class="pc-field">
        <label class="pc-label" for="insulin-glycemia-input">Glycémie capillaire associée (obligatoire, g/L)</label>
        <div class="pc-measure-input-wrap">
          <input
            type="text"
            inputmode="decimal"
            name="glycemia_value"
            id="insulin-glycemia-input"
            class="pc-input pc-input--metric"
            placeholder="1,45"
            required
          >
          <span class="pc-measure-unit-badge">g/L</span>
        </div>
      </div>

      <!-- Grille de correction en lecture seule avec surbrillance automatique de la tranche -->
      <div class="pc-card pc-card--subtle">
        <div class="flex items-center justify-between mb-2">
          <span class="text-footnote font-semibold">Dose selon le tableau du responsable</span>
          <span class="pc-badge pc-badge--meds" id="insulin-suggested-badge">Saisir la glycémie</span>
        </div>
        <div class="pc-corr-table" id="insulin-correction-rows">
          <div class="pc-corr-row" data-min="0.00" data-max="0.70" data-dose="0">
            <span>&lt; 0,70 g/L</span>
            <strong>0 UI · Protocole resucrage</strong>
          </div>
          <div class="pc-corr-row" data-min="0.70" data-max="1.30" data-dose="4">
            <span>0,70 – 1,30 g/L</span>
            <strong>4 UI (dose de base)</strong>
          </div>
          <div class="pc-corr-row" data-min="1.31" data-max="1.80" data-dose="6">
            <span>1,31 – 1,80 g/L</span>
            <strong>6 UI (+2 UI)</strong>
          </div>
          <div class="pc-corr-row" data-min="1.81" data-max="2.50" data-dose="8">
            <span>1,81 – 2,50 g/L</span>
            <strong>8 UI (+4 UI)</strong>
          </div>
          <div class="pc-corr-row" data-min="2.51" data-max="6.00" data-dose="10">
            <span>&gt; 2,50 g/L</span>
            <strong>10 UI (+6 UI + alerte)</strong>
          </div>
        </div>
      </div>

      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="insulin-dose-input">Dose administrée (UI)</label>
          <input
            type="text"
            inputmode="decimal"
            name="dose_given"
            id="insulin-dose-input"
            class="pc-input font-mono font-bold"
            placeholder="4"
            required
          >
        </div>

        <div class="pc-field">
          <label class="pc-label" for="insulin-site-select">Site d’injection (rotation)</label>
          <select name="injection_site" id="insulin-site-select" class="pc-input" required>
            <?php foreach (\App\Services\InsulinService::INJECTION_SITES as $sCode => $sLabel): ?>
              <option value="<?= e($sCode) ?>" <?= ($rotation['suggested_code'] ?? '') === $sCode ? 'selected' : '' ?>>
                <?= e($sLabel) ?><?= ($rotation['suggested_code'] ?? '') === $sCode ? ' (Conseillé)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="text-caption text-muted">Dernier site : <?= e((string) ($rotation['last_label'] ?? 'Aucun')) ?></span>
        </div>
      </div>

      <div class="pc-field">
        <label class="pc-label" for="insulin-comment-input">Commentaire (optionnel)</label>
        <input type="text" name="comment" id="insulin-comment-input" class="pc-input" placeholder="Ex. stylo neuf, avant repas...">
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--lg pc-btn--block">
        <?= icon('check', '', 20) ?>
        <span>Valider l’injection d’insuline</span>
      </button>
    </form>
  </div>
</div>

<!-- 4. Validation de prise non donnée ("Non pris" avec motif obligatoire) -->
<div class="pc-sheet-backdrop" id="sheet-intake-skip" role="dialog" aria-modal="true" aria-labelledby="sheet-skip-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="sheet-skip-title">Médicament non pris</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <form method="POST" action="/treatments/intakes/0/skip" id="form-intake-skip" class="pc-stack">
      <?= csrf_field() ?>
      <input type="hidden" name="intake_id" id="skip-intake-id" value="">

      <p class="text-subhead text-muted" id="skip-medication-label">Indiquez le motif obligatoire de non-prise :</p>

      <div class="pc-chip-group" role="radiogroup" aria-label="Motif de non-prise">
        <?php foreach (\App\Services\IntakeService::SKIP_REASONS as $rCode => $rLabel): ?>
          <label class="pc-chip-radio">
            <input type="radio" name="reason" value="<?= e($rCode) ?>" <?= $rCode === 'refus_patient' ? 'checked' : '' ?> required>
            <span><?= e($rLabel) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="pc-field">
        <label class="pc-label" for="skip-comment-input">Précision (obligatoire si « Autre »)</label>
        <input type="text" name="comment" id="skip-comment-input" class="pc-input" placeholder="Détaillez le motif...">
      </div>

      <button type="submit" class="pc-btn pc-btn--danger pc-btn--lg pc-btn--block">
        <span>Confirmer « Non pris »</span>
      </button>
    </form>
  </div>
</div>

<!-- 5. Prise "Si besoin" (Paracétamol, etc.) -->
<div class="pc-sheet-backdrop" id="sheet-as-needed" role="dialog" aria-modal="true" aria-labelledby="sheet-as-needed-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="sheet-as-needed-title">Prise « Si besoin » &amp; Traitements</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <div class="pc-stack">
      <a href="/today" class="pc-btn pc-btn--tinted pc-btn--block">
        <?= icon('calendar-days', '', 18) ?>
        <span>Voir toutes les prises programmées d’aujourd’hui</span>
      </a>

      <?php if ($asNeededList !== []): ?>
        <form method="POST" action="/treatments/as-needed" data-api-form="/api/intakes/as-needed" class="pc-stack">
          <?= csrf_field() ?>
          <div class="pc-field">
            <label class="pc-label" for="as-needed-rx-select">Médicament « Si besoin »</label>
            <select name="prescription_id" id="as-needed-rx-select" class="pc-input" required>
              <?php foreach ($asNeededList as $an): ?>
                <option value="<?= (int) $an['prescription_id'] ?>">
                  <?= e((string) $an['medication_name']) ?> · <?= number_format((float) $an['dose'], 0, ',', ' ') ?> <?= e((string) $an['unit']) ?>
                  (intervalle min. <?= (int) ($an['min_interval_h'] ?? 6) ?>h)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="pc-field">
            <label class="pc-label" for="as-needed-comment">Motif / Symptôme (ex. douleurs articulaires)</label>
            <input type="text" name="comment" id="as-needed-comment" class="pc-input" placeholder="Précisez la raison de la prise..." required>
          </div>

          <button type="submit" class="pc-btn pc-btn--primary pc-btn--block">
            <?= icon('check', '', 18) ?>
            <span>Enregistrer la prise « Si besoin »</span>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 6. E10 — Saisie de repas (avec photos Avant/Après compressées par canvas & jauge 4 segments) -->
<div class="pc-sheet-backdrop" id="sheet-meal" role="dialog" aria-modal="true" aria-labelledby="sheet-meal-title">
  <div class="pc-sheet pc-sheet--tall">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="sheet-meal-title">Enregistrer un repas</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <form method="POST" action="/meals" enctype="multipart/form-data" id="form-quick-meal" data-api-form="/api/meals" class="pc-stack">
      <?= csrf_field() ?>

      <!-- Type de repas (déduit automatiquement selon l'heure) -->
      <div class="pc-field">
        <span class="pc-label">Moment du repas</span>
        <div class="pc-chip-group" role="radiogroup" aria-label="Type de repas">
          <?php foreach (\App\Services\MealService::MEAL_TYPES as $mCode => $mLabel): ?>
            <label class="pc-chip-radio">
              <input type="radio" name="meal_type" value="<?= e($mCode) ?>" <?= $defaultMealType === $mCode ? 'checked' : '' ?>>
              <span><?= e($mLabel) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Aliments favoris en 1 tap -->
      <?php if ($favorites !== []): ?>
        <div class="pc-field">
          <span class="pc-label">Favoris rapides (1 tap)</span>
          <div class="pc-chip-group">
            <?php foreach ($favorites as $fav): ?>
              <button
                type="button"
                class="pc-fav-chip"
                data-fav-desc="<?= e((string) $fav['label']) ?>"
                data-fav-carbs="<?= e((string) ($fav['default_carbs_g'] ?? '')) ?>"
                data-fav-drink="<?= e((string) ($fav['default_drink'] ?? '')) ?>"
              >
                <?= icon('sparkles', '', 14) ?>
                <span><?= e(mb_strimwidth((string) $fav['label'], 0, 38, '…')) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="pc-field">
        <label class="pc-label" for="meal-description-input">Composition du repas (facultative si photo jointe)</label>
        <textarea
          name="description"
          id="meal-description-input"
          class="pc-input"
          rows="2"
          placeholder="Ex. Filet de cabillaud, riz basmati, courgettes vapeur... (ou laissez vide avec une photo)"
        ></textarea>
      </div>

      <!-- Quantité consommée : barre segmentée 4 segments (0 / 25 / 50 / 75 / 100 %) -->
      <div class="pc-field">
        <span class="pc-label">Quantité mangée</span>
        <div class="pc-portion-selector" role="radiogroup" aria-label="Quantité mangée">
          <?php
          $portions = [
              0 => '0 % (Refus)',
              25 => '25 % (Peu)',
              50 => '50 % (Moitié)',
              75 => '75 % (Bien)',
              100 => '100 % (Tout)',
          ];
          foreach ($portions as $pVal => $pTxt):
          ?>
            <label class="pc-portion-option">
              <input type="radio" name="portion_eaten" value="<?= $pVal ?>" <?= $pVal === 100 ? 'checked' : '' ?>>
              <span><?= e($pTxt) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="meal-carbs-input">Glucides estimés (g)</label>
          <input type="number" name="carbs_g" id="meal-carbs-input" class="pc-input font-mono" min="0" max="300" placeholder="45">
        </div>
        <div class="pc-field">
          <label class="pc-label" for="meal-drink-input">Boisson / Hydratation</label>
          <input type="text" name="drink_type" id="meal-drink-input" class="pc-input" placeholder="Eau plate (250 mL)">
        </div>
      </div>

      <!-- Photos Avant / Après (compression canvas client 1600px JPEG 0.8) -->
      <div class="pc-grid-2">
        <div class="pc-field">
          <label class="pc-label" for="meal-photo-avant">Photo Avant repas</label>
          <input type="file" name="photo_avant" id="meal-photo-avant" class="pc-input" accept="image/jpeg,image/png,image/webp" data-compress-image>
        </div>
        <div class="pc-field">
          <label class="pc-label" for="meal-photo-apres">Photo Après repas</label>
          <input type="file" name="photo_apres" id="meal-photo-apres" class="pc-input" accept="image/jpeg,image/png,image/webp" data-compress-image>
        </div>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--lg pc-btn--block">
        <?= icon('check', '', 20) ?>
        <span>Enregistrer le repas</span>
      </button>
    </form>
  </div>
</div>

<!-- 7. E13 — Note, Transmission de fin de poste & Soins rapides en 1 tap -->
<div class="pc-sheet-backdrop" id="sheet-note" role="dialog" aria-modal="true" aria-labelledby="sheet-note-title">
  <div class="pc-sheet pc-sheet--tall">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <h2 class="pc-sheet__title" id="sheet-note-title">Note, Transmission &amp; Soins</h2>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <!-- Soins rapides en 1 tap -->
    <?php if ($careItems !== []): ?>
      <div class="mb-4">
        <div class="text-caption font-semibold text-muted uppercase mb-2">Soins rapides (horodatage en 1 tap)</div>
        <div class="pc-care-quick-grid">
          <?php foreach ($careItems as $ci): ?>
            <button
              type="button"
              class="pc-care-quick-btn"
              data-quick-care-id="<?= (int) $ci['id'] ?>"
              data-quick-care-label="<?= e((string) $ci['label']) ?>"
            >
              <?= icon((string) ($ci['icon_name'] ?: 'check-circle'), '', 18) ?>
              <span><?= e((string) $ci['label']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" action="/journal/notes" id="form-quick-note" data-api-form="/api/notes" class="pc-stack">
      <?= csrf_field() ?>

      <div class="pc-field">
        <span class="pc-label">Catégorie</span>
        <div class="pc-chip-group" role="radiogroup" aria-label="Catégorie de la note">
          <?php
          $noteCats = [
              'general' => 'Général',
              'humeur' => 'Humeur',
              'douleur' => 'Douleur',
              'sommeil' => 'Sommeil',
              'famille' => 'Famille',
              'medical' => 'Médical',
          ];
          foreach ($noteCats as $nCode => $nLabel):
          ?>
            <label class="pc-chip-radio">
              <input type="radio" name="category" value="<?= e($nCode) ?>" <?= $nCode === 'general' ? 'checked' : '' ?>>
              <span><?= e($nLabel) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pc-field">
        <label class="pc-label" for="note-content-input">Observation ou consigne pour la relève</label>
        <textarea
          name="content"
          id="note-content-input"
          class="pc-input"
          rows="3"
          placeholder="Ex. Garde calme, bonne hydratation, surveiller la glycémie avant le dîner..."
          required
        ></textarea>
      </div>

      <div class="pc-stack gap-2">
        <label class="pc-checkbox-row">
          <input type="checkbox" name="is_important" value="1">
          <span><strong>Marquer comme important</strong> (remonte dans les points d’attention)</span>
        </label>

        <label class="pc-checkbox-row">
          <input type="checkbox" name="is_handover" id="note-is-handover-check" value="1">
          <span><strong>Transmission de fin de poste</strong> (joint automatiquement le résumé du poste et affiche le bandeau à la relève)</span>
        </label>
      </div>

      <?php
      $shiftAutoSummary = [];
      try {
          $shiftAutoSummary = \App\Services\HandoverService::buildShiftSummary(null, 1);
      } catch (\Throwable) {
          $shiftAutoSummary = [];
      }
      ?>
      <div class="pc-card pc-card--subtle" id="handover-auto-summary-preview">
        <div class="flex items-center justify-between mb-1">
          <span class="text-caption font-semibold uppercase text-brand">Récapitulatif automatique du poste</span>
          <span class="text-caption font-mono text-muted">
            <?= e((string) ($shiftAutoSummary['from_local'] ?? '08:00')) ?> &rarr; <?= e((string) ($shiftAutoSummary['to_local'] ?? date('H:i'))) ?>
          </span>
        </div>
        <div class="text-footnote font-semibold">
          <?= e((string) ($shiftAutoSummary['summary_text'] ?? 'Mesures, prises, repas et soins du poste agrégés automatiquement')) ?>
        </div>
        <div class="pc-handover-banner__chips mt-2">
          <span class="pc-chip"><?= (int) ($shiftAutoSummary['measures_total'] ?? 0) ?> mesure(s)</span>
          <span class="pc-chip"><?= (int) ($shiftAutoSummary['intakes_given'] ?? 0) ?> prise(s) donnée(s)</span>
          <span class="pc-chip"><?= (int) ($shiftAutoSummary['meals_count'] ?? 0) ?> repas (<?= (int) ($shiftAutoSummary['avg_portion_pct'] ?? 100) ?> %)</span>
          <span class="pc-chip"><?= (int) ($shiftAutoSummary['care_checks_count'] ?? 0) ?> soin(s)</span>
        </div>
      </div>

      <button type="submit" class="pc-btn pc-btn--primary pc-btn--lg pc-btn--block">
        <?= icon('check', '', 20) ?>
        <span>Publier dans le journal</span>
      </button>
    </form>
  </div>
</div>

<!-- 8. Guide d'installation iPhone en 3 étapes (§4 bis.2) -->
<div class="pc-sheet-backdrop" id="sheet-ios-install" role="dialog" aria-modal="true" aria-labelledby="sheet-ios-install-title">
  <div class="pc-sheet">
    <div class="pc-sheet__handle" aria-hidden="true"></div>
    <div class="pc-sheet__header">
      <div>
        <h2 class="pc-sheet__title" id="sheet-ios-install-title">Installer sur iPhone (Safari)</h2>
        <p class="text-caption text-muted">Plein écran autonome en 3 étapes rapides</p>
      </div>
      <button type="button" class="pc-btn pc-btn--ghost pc-btn--icon" data-sheet-close aria-label="Fermer">
        <?= icon('x', '', 22) ?>
      </button>
    </div>

    <div class="pc-stack gap-3">
      <ol class="pc-install-steps">
        <li>
          <span class="pc-install-steps__num">1</span>
          <div>
            <strong>Ouvrir Prime Care dans Safari</strong>
            <p class="text-footnote text-muted">Assurez-vous d’utiliser le navigateur Safari sur iPhone ou iPad (iOS 16.4+).</p>
          </div>
        </li>
        <li>
          <span class="pc-install-steps__num">2</span>
          <div>
            <strong>Appuyer sur le bouton « Partager »</strong>
            <p class="text-footnote text-muted">Touchez l’icône carrée avec une flèche vers le haut dans la barre inférieure de Safari.</p>
          </div>
        </li>
        <li>
          <span class="pc-install-steps__num">3</span>
          <div>
            <strong>Choisir « Sur l’écran d’accueil » puis « Ajouter »</strong>
            <p class="text-footnote text-muted">Lancez ensuite Prime Care depuis votre écran d’accueil pour profiter du plein écran sans barre navigateur.</p>
          </div>
        </li>
      </ol>

      <button type="button" class="pc-btn pc-btn--primary pc-btn--block" data-sheet-close>
        <?= icon('check', '', 18) ?>
        <span>J’ai compris</span>
      </button>
    </div>
  </div>
</div>
