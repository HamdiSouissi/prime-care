/**
 * Prime Care — Main ES2020+ Application Entrypoint
 */
import { initLaunchLoader, setMiniLoader } from './modules/loader.js';
import { initNavbar } from './modules/navbar.js';
import { initTabs } from './modules/tabs.js';
import { initSheets } from './modules/sheet.js';
import { initToasts, showToast } from './modules/toast.js';
import { initSegmented } from './modules/segmented.js';
import { initPinPad } from './modules/pin-pad.js';
import { initTransitions } from './modules/transitions.js';
import { initPwa } from './modules/pwa.js';
import { initCharts } from './modules/charts.js';
import { initMedical } from './modules/medical.js';
import { initPhase3 } from './modules/phase3.js';
import { initOfflineQueue } from './offline-queue.js';
import { triggerHaptic } from './modules/haptics.js';

function initPasswordToggles() {
  document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const wrap = btn.closest('.pc-input-wrap');
      const input = wrap?.querySelector('input');
      if (!(input instanceof HTMLInputElement)) {
        return;
      }
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.setAttribute('aria-label', isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
  });

  // Password strength indicator
  document.querySelectorAll('[data-password-strength]').forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }
    const meterId = input.getAttribute('data-password-strength');
    const meter = meterId ? document.getElementById(meterId) : null;
    if (!meter) {
      return;
    }
    input.addEventListener('input', () => {
      const val = input.value;
      let score = 0;
      if (val.length >= 10) score++;
      if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
      if (/\d/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      const labels = ['Très faible (10 caractères min.)', 'Faible', 'Correct', 'Robuste', 'Excellent'];
      const classes = ['text-danger', 'text-danger', 'text-warn', 'text-ok', 'text-ok'];
      meter.textContent = val.length === 0 ? 'Minimum 10 caractères avec majuscule, chiffre et symbole.' : `Robustesse : ${labels[score]}`;
      meter.className = `pc-help ${val.length === 0 ? '' : classes[score]}`;
    });
  });
}

function initFieldValidation() {
  document.querySelectorAll('.pc-input, .pc-select, .pc-textarea').forEach((field) => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
      return;
    }
    field.addEventListener('input', () => {
      field.classList.remove('has-error');
      const wrap = field.closest('.pc-field');
      const err = wrap?.querySelector('.pc-field-error');
      if (err) {
        err.remove();
      }
    });
  });
}

function initInstantSearch() {
  const searchInput = document.querySelector('[data-team-search]');
  if (!(searchInput instanceof HTMLInputElement)) {
    return;
  }
  const items = Array.from(document.querySelectorAll('[data-team-item]'));
  const emptyEl = document.getElementById('pc-team-empty-filter');

  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;
    items.forEach((item) => {
      const haystack = (item.getAttribute('data-team-item') || '').toLowerCase();
      const match = q === '' || haystack.includes(q);
      item.classList.toggle('hidden', !match);
      if (match) {
        visibleCount++;
      }
    });
    if (emptyEl) {
      emptyEl.classList.toggle('hidden', visibleCount > 0);
    }
  });
}

function initCopyButtons() {
  document.querySelectorAll('[data-copy-text]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const text = btn.getAttribute('data-copy-text') || '';
      if (!text) {
        return;
      }
      try {
        await navigator.clipboard.writeText(text);
        triggerHaptic(10);
        showToast('Copié dans le presse-papiers.', 'success');
      } catch {
        showToast('Copie effectuée.', 'info');
      }
    });
  });
}

function initAvatarCropAndUpload() {
  const fileInput = document.getElementById('pc-avatar-input');
  const form = document.getElementById('pc-avatar-form');
  if (!(fileInput instanceof HTMLInputElement) || !(form instanceof HTMLFormElement)) {
    return;
  }

  fileInput.addEventListener('change', async () => {
    const file = fileInput.files?.[0];
    if (!file) {
      return;
    }

    setMiniLoader('#pc-avatar-loader', true);
    try {
      // Client-side square crop & compression to 480x480 JPEG before sending
      const bitmap = await createImageBitmap(file);
      const side = Math.min(bitmap.width, bitmap.height);
      const sx = Math.round((bitmap.width - side) / 2);
      const sy = Math.round((bitmap.height - side) / 2);

      const canvas = document.createElement('canvas');
      canvas.width = 480;
      canvas.height = 480;
      const ctx = canvas.getContext('2d');
      if (ctx) {
        ctx.drawImage(bitmap, sx, sy, side, side, 0, 0, 480, 480);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
        if (blob) {
          const dt = new DataTransfer();
          dt.items.add(new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
          fileInput.files = dt.files;
        }
      }
    } catch {
      // Fallback to raw file if createImageBitmap fails
    }

    form.submit();
  });
}

function initPhase4() {
  // 1. Animated counters on Admin 7-day trends (disabled if prefers-reduced-motion)
  const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-count-up]').forEach((el) => {
    const rawTarget = parseFloat(el.getAttribute('data-count-up') || '0');
    const decimals = parseInt(el.getAttribute('data-count-decimals') || '0', 10);
    if (Number.isNaN(rawTarget)) {
      return;
    }

    const formatVal = (n) => n.toFixed(decimals).replace('.', ',');
    if (prefersReducedMotion || rawTarget === 0) {
      el.textContent = formatVal(rawTarget);
      return;
    }

    const duration = 650;
    const startTime = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - startTime) / duration);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = formatVal(rawTarget * eased);
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = formatVal(rawTarget);
      }
    };
    requestAnimationFrame(step);
  });

  // 2. Print report buttons ([data-print-report])
  document.querySelectorAll('[data-print-report]').forEach((btn) => {
    btn.addEventListener('click', () => {
      window.print();
    });
  });

  // 3. Fullscreen image viewer toggle ([data-fullscreen-target])
  document.querySelectorAll('[data-fullscreen-target]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-fullscreen-target');
      const target = targetId ? document.getElementById(targetId) : null;
      if (target && target.requestFullscreen) {
        target.requestFullscreen().catch(() => {});
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initLaunchLoader();
  initNavbar();
  initTabs();
  initSheets();
  initToasts();
  initSegmented();
  initPinPad();
  initTransitions();
  initPwa();
  initCharts();
  initMedical();
  initPhase3();
  initPhase4();
  initOfflineQueue();
  initPasswordToggles();
  initFieldValidation();
  initInstantSearch();
  initCopyButtons();
  initAvatarCropAndUpload();
});
