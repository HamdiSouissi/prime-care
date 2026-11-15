/**
 * Prime Care — Phase 2 Medical Interactions Module
 * - E06 Live measure threshold evaluation, hypo protocol alert (< 0.70 g/L), plausibility confirmation
 * - E08 Variable insulin correction table highlight ("Dose selon le tableau du responsable")
 * - 1-tap intake Give ("Donné"), Skip ("Non pris"), Quick Care Check ("Soin rapide"), and Handover Read ("Marquer comme lu")
 * - E10 Food favorites 1-tap autofill & Client-side Canvas photo compression (max 1600px JPEG 0.80)
 */
import { openSheet, closeSheet } from './sheet.js';
import { showToast } from './toast.js';
import { triggerHaptic } from './haptics.js';
import { enqueueOfflineAction, generateClientUuid } from '../offline-queue.js';

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') || '' : '';
}

/**
 * Compress an image File client-side to max 1600px JPEG (quality 0.80) using Canvas.
 */
async function compressImageFile(file, maxDim = 1600, quality = 0.8) {
  if (!file || !file.type.startsWith('image/')) {
    return file;
  }
  try {
    const bitmap = await createImageBitmap(file);
    let { width, height } = bitmap;
    if (width > maxDim || height > maxDim) {
      const ratio = Math.min(maxDim / width, maxDim / height);
      width = Math.max(1, Math.round(width * ratio));
      height = Math.max(1, Math.round(height * ratio));
    }
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      return file;
    }
    ctx.drawImage(bitmap, 0, 0, width, height);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    if (!blob) {
      return file;
    }
    const baseName = file.name.replace(/\.[^.]+$/, '') || 'photo';
    return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg' });
  } catch {
    return file;
  }
}

export function initMedical() {
  // 1. Open Measure Sheet pre-configured for a given type_code / expectation_id / context
  const typeSelect = document.getElementById('measure-type-select');
  const valInput = document.getElementById('measure-value-input');
  const val2Group = document.getElementById('measure-value2-group');
  const val3Group = document.getElementById('measure-value3-group');
  const unitDisplay = document.getElementById('measure-unit-display');
  const expIdInput = document.getElementById('measure-expectation-id');
  const liveBadge = document.getElementById('measure-live-badge');
  const liveText = document.getElementById('measure-live-text');
  const hypoBox = document.getElementById('measure-hypo-protocol');
  const plausBox = document.getElementById('measure-plausibility-box');
  const plausMsg = document.getElementById('measure-plausibility-msg');
  const plausCheck = document.getElementById('measure-plausibility-check');
  const confirmImplausibleInput = document.getElementById('measure-confirm-implausible');

  const syncMeasureTypeUi = () => {
    if (!(typeSelect instanceof HTMLSelectElement)) {
      return;
    }
    const opt = typeSelect.selectedOptions[0];
    if (!opt) {
      return;
    }
    const code = opt.value;
    const unit = opt.getAttribute('data-unit') || '';
    if (unitDisplay) {
      unitDisplay.textContent = unit;
    }
    const isBp = code === 'blood_pressure';
    val2Group?.classList.toggle('is-hidden', !isBp);
    val3Group?.classList.toggle('is-hidden', !isBp);
    evaluateLiveMeasure();
  };

  const evaluateLiveMeasure = () => {
    if (!(typeSelect instanceof HTMLSelectElement) || !(valInput instanceof HTMLInputElement)) {
      return;
    }
    const opt = typeSelect.selectedOptions[0];
    if (!opt) {
      return;
    }
    const raw = valInput.value.replace(',', '.').trim();
    const num = Number(raw);
    if (raw === '' || Number.isNaN(num)) {
      if (liveBadge && liveText) {
        liveBadge.className = 'pc-live-eval pc-live-eval--neutral';
        liveText.textContent = 'Saisissez une valeur pour évaluer le seuil';
      }
      hypoBox?.classList.add('is-hidden');
      plausBox?.classList.add('is-hidden');
      return;
    }

    const code = opt.value;
    const min = Number(opt.getAttribute('data-min') || '0');
    const max = Number(opt.getAttribute('data-max') || '9999');
    const critLow = opt.getAttribute('data-crit-low') ? Number(opt.getAttribute('data-crit-low')) : null;
    const low = opt.getAttribute('data-low') ? Number(opt.getAttribute('data-low')) : null;
    const high = opt.getAttribute('data-high') ? Number(opt.getAttribute('data-high')) : null;
    const critHigh = opt.getAttribute('data-crit-high') ? Number(opt.getAttribute('data-crit-high')) : null;

    // Plausibility check
    if (num < min || num > max) {
      plausBox?.classList.remove('is-hidden');
      if (plausMsg) {
        plausMsg.textContent = `Valeur hors bornes plausibles (${min} – ${max}). Cochez pour confirmer.`;
      }
    } else {
      plausBox?.classList.add('is-hidden');
    }

    // Threshold check
    let statusClass = 'pc-live-eval--ok';
    let label = 'Normal (dans la cible)';
    if (critLow !== null && num < critLow) {
      statusClass = 'pc-live-eval--critical';
      label = code === 'glycemia' ? 'CRITIQUE BAS : Hypoglycémie (< 0,70 g/L)' : 'CRITIQUE BAS';
    } else if (critHigh !== null && num > critHigh) {
      statusClass = 'pc-live-eval--critical';
      label = code === 'glycemia' ? 'CRITIQUE HAUT : Hyperglycémie (> 2,50 g/L)' : 'CRITIQUE HAUT';
    } else if (low !== null && num < low) {
      statusClass = 'pc-live-eval--warn';
      label = 'À surveiller (bas)';
    } else if (high !== null && num > high) {
      statusClass = 'pc-live-eval--warn';
      label = 'À surveiller (haut)';
    }

    if (liveBadge && liveText) {
      liveBadge.className = `pc-live-eval ${statusClass}`;
      liveText.textContent = label;
    }

    if (code === 'glycemia' && critLow !== null && num < critLow) {
      hypoBox?.classList.remove('is-hidden');
    } else {
      hypoBox?.classList.add('is-hidden');
    }
  };

  typeSelect?.addEventListener('change', syncMeasureTypeUi);
  valInput?.addEventListener('input', evaluateLiveMeasure);
  plausCheck?.addEventListener('change', () => {
    if (confirmImplausibleInput instanceof HTMLInputElement && plausCheck instanceof HTMLInputElement) {
      confirmImplausibleInput.value = plausCheck.checked ? '1' : '0';
    }
  });

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) {
      return;
    }

    // Open measure sheet for specific type
    const mTrigger = target.closest('[data-open-measure-sheet]');
    if (mTrigger) {
      e.preventDefault();
      const code = mTrigger.getAttribute('data-open-measure-sheet') || 'glycemia';
      const expId = mTrigger.getAttribute('data-expectation-id') || '';
      const ctx = mTrigger.getAttribute('data-measure-context') || '';

      if (typeSelect instanceof HTMLSelectElement) {
        typeSelect.value = code;
        syncMeasureTypeUi();
      }
      if (expIdInput instanceof HTMLInputElement) {
        expIdInput.value = expId;
      }
      if (ctx) {
        const radio = document.querySelector(`#measure-context-group input[name="context"][value="${ctx}"]`);
        if (radio instanceof HTMLInputElement) {
          radio.checked = true;
        }
      }
      openSheet('sheet-measure');
      return;
    }

    // Open meal sheet for specific meal_type
    const mealTrigger = target.closest('[data-open-meal-sheet]');
    if (mealTrigger) {
      e.preventDefault();
      const mType = mealTrigger.getAttribute('data-open-meal-sheet') || 'lunch';
      const radio = document.querySelector(`#form-quick-meal input[name="meal_type"][value="${mType}"]`);
      if (radio instanceof HTMLInputElement) {
        radio.checked = true;
      }
      openSheet('sheet-meal');
      return;
    }

    // Open variable insulin validation sheet
    const insTrigger = target.closest('[data-open-insulin-give]');
    if (insTrigger) {
      e.preventDefault();
      const intakeId = insTrigger.getAttribute('data-open-insulin-give') || '';
      const title = insTrigger.getAttribute('data-insulin-title') || 'Insuline rapide';
      const idField = document.getElementById('insulin-intake-id');
      const form = document.getElementById('form-insulin-give');
      const heading = document.getElementById('sheet-insulin-title');
      if (idField instanceof HTMLInputElement) {
        idField.value = intakeId;
      }
      if (form instanceof HTMLFormElement) {
        form.action = `/treatments/intakes/${intakeId}/give`;
      }
      if (heading) {
        heading.textContent = title;
      }
      openSheet('sheet-insulin-give');
      return;
    }

    // Open skip intake sheet
    const skipTrigger = target.closest('[data-open-skip-id]');
    if (skipTrigger) {
      e.preventDefault();
      const intakeId = skipTrigger.getAttribute('data-open-skip-id') || '';
      const title = skipTrigger.getAttribute('data-skip-title') || 'Médicament';
      const idField = document.getElementById('skip-intake-id');
      const form = document.getElementById('form-intake-skip');
      const label = document.getElementById('skip-medication-label');
      if (idField instanceof HTMLInputElement) {
        idField.value = intakeId;
      }
      if (form instanceof HTMLFormElement) {
        form.action = `/treatments/intakes/${intakeId}/skip`;
      }
      if (label) {
        label.textContent = `Motif obligatoire pour « ${title} » :`;
      }
      openSheet('sheet-intake-skip');
      return;
    }

    // 1-tap Give intake ("Donné") via JSON API (with Phase 3 client_uuid & offline queue fallback)
    const giveBtn = target.closest('[data-quick-give-id]');
    if (giveBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      const intakeId = giveBtn.getAttribute('data-quick-give-id');
      if (!intakeId || giveBtn.disabled) {
        return;
      }
      const clientUuid = generateClientUuid();
      const endpoint = `/api/intakes/${intakeId}/give`;
      if (navigator.onLine === false) {
        enqueueOfflineAction(endpoint, { client_uuid: clientUuid }, `Prise #${intakeId}`);
        return;
      }
      giveBtn.disabled = true;
      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ client_uuid: clientUuid }),
      })
        .then(async (res) => ({ status: res.status, body: await res.json() }))
        .then(({ status, body }) => {
          if (body.ok) {
            triggerHaptic(15);
            showToast(body.data?.message || 'Prise validée.', 'success');
            window.setTimeout(() => window.location.reload(), 350);
          } else {
            giveBtn.disabled = false;
            showToast(body.error?.message || `Erreur (${status})`, 'error');
          }
        })
        .catch(() => {
          giveBtn.disabled = false;
          enqueueOfflineAction(endpoint, { client_uuid: clientUuid }, `Prise #${intakeId}`);
        });
      return;
    }

    // 1-tap Quick Care Check ("Soin rapide") via JSON API (with Phase 3 client_uuid & offline queue fallback)
    const careBtn = target.closest('[data-quick-care-id]');
    if (careBtn instanceof HTMLButtonElement) {
      e.preventDefault();
      const careItemId = Number(careBtn.getAttribute('data-quick-care-id') || '0');
      if (!careItemId || careBtn.disabled) {
        return;
      }
      const clientUuid = generateClientUuid();
      if (navigator.onLine === false) {
        enqueueOfflineAction('/api/care-checks', { care_item_id: careItemId, client_uuid: clientUuid }, 'Soin rapide');
        closeSheet();
        return;
      }
      careBtn.disabled = true;
      fetch('/api/care-checks', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ care_item_id: careItemId, client_uuid: clientUuid }),
      })
        .then((res) => res.json())
        .then((body) => {
          careBtn.disabled = false;
          if (body.ok) {
            triggerHaptic(12);
            showToast(`Soin « ${body.data?.label || 'Soin'} » horodaté à ${body.data?.time_local || ''}.`, 'success');
            closeSheet();
            if (window.location.pathname === '/journal') {
              window.setTimeout(() => window.location.reload(), 350);
            }
          } else {
            showToast(body.error?.message || 'Erreur lors de la validation du soin.', 'error');
          }
        })
        .catch(() => {
          careBtn.disabled = false;
          enqueueOfflineAction('/api/care-checks', { care_item_id: careItemId, client_uuid: clientUuid }, 'Soin rapide');
          closeSheet();
        });
      return;
    }

    // 1-tap Food Favorite autofill
    const favChip = target.closest('[data-fav-desc]');
    if (favChip) {
      e.preventDefault();
      const desc = favChip.getAttribute('data-fav-desc') || '';
      const carbs = favChip.getAttribute('data-fav-carbs') || '';
      const drink = favChip.getAttribute('data-fav-drink') || '';
      const descField = document.getElementById('meal-description-input');
      const carbsField = document.getElementById('meal-carbs-input');
      const drinkField = document.getElementById('meal-drink-input');
      if (descField instanceof HTMLTextAreaElement) {
        descField.value = desc;
      }
      if (carbsField instanceof HTMLInputElement && carbs !== '') {
        carbsField.value = carbs;
      }
      if (drinkField instanceof HTMLInputElement && drink !== '') {
        drinkField.value = drink;
      }
      triggerHaptic(8);
    }
  });

  // 2. Variable insulin live lookup in admin correction table ("Dose selon le tableau du responsable")
  const insGlyInput = document.getElementById('insulin-glycemia-input');
  const insDoseInput = document.getElementById('insulin-dose-input');
  const insBadge = document.getElementById('insulin-suggested-badge');
  const corrRows = document.querySelectorAll('#insulin-correction-rows .pc-corr-row');

  insGlyInput?.addEventListener('input', () => {
    if (!(insGlyInput instanceof HTMLInputElement)) {
      return;
    }
    const gVal = Number(insGlyInput.value.replace(',', '.').trim());
    corrRows.forEach((r) => r.classList.remove('is-matched'));
    if (Number.isNaN(gVal) || insGlyInput.value.trim() === '') {
      if (insBadge) {
        insBadge.textContent = 'Saisir la glycémie';
      }
      return;
    }

    corrRows.forEach((row) => {
      const min = Number(row.getAttribute('data-min') || '0');
      const max = Number(row.getAttribute('data-max') || '99');
      const dose = row.getAttribute('data-dose') || '0';
      if (gVal >= min && gVal <= max) {
        row.classList.add('is-matched');
        if (insDoseInput instanceof HTMLInputElement) {
          insDoseInput.value = dose;
        }
        if (insBadge) {
          insBadge.textContent = `${dose} UI (selon le tableau)`;
        }
      }
    });
  });

  // 3. Client-side Canvas image compression (max 1600px JPEG 0.80) on [data-compress-image] inputs
  document.querySelectorAll('input[type="file"][data-compress-image]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    input.addEventListener('change', async () => {
      const file = input.files?.[0];
      if (!file) {
        return;
      }
      const compressed = await compressImageFile(file, 1600, 0.8);
      if (compressed && compressed !== file) {
        try {
          const dt = new DataTransfer();
          dt.items.add(compressed);
          input.files = dt.files;
        } catch {
          // Keep original if DataTransfer is restricted
        }
      }
    });
  });
}
