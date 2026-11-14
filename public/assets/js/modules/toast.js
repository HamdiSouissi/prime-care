/**
 * Discrete top Toast notifications with animated SVG checkmark (§6.5, §6.6).
 */
import { triggerHaptic } from './haptics.js';

export function showToast(message, type = 'success', duration = 3600) {
  let container = document.getElementById('pc-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'pc-toast-container';
    container.className = 'pc-toast-container';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-atomic', 'true');
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `pc-toast pc-toast--${type}`;

  let iconSvg = '';
  if (type === 'success') {
    triggerHaptic(10);
    iconSvg = `<svg class="pc-toast__check text-ok" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>`;
  } else if (type === 'error') {
    triggerHaptic([20, 40, 20]);
    iconSvg = `<svg class="text-danger" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>`;
  } else {
    iconSvg = `<svg class="text-brand" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`;
  }

  const textSpan = document.createElement('span');
  textSpan.className = 'flex-1';
  textSpan.textContent = message;

  toast.innerHTML = iconSvg;
  toast.appendChild(textSpan);
  container.appendChild(toast);

  window.setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-8px)';
    toast.style.transition = 'opacity 200ms, transform 200ms';
    window.setTimeout(() => toast.remove(), 220);
  }, duration);
}

export function initToasts() {
  const existingToasts = document.querySelectorAll('#pc-toast-container .pc-toast');
  existingToasts.forEach((toast) => {
    if (!(toast instanceof HTMLElement)) {
      return;
    }
    if (toast.classList.contains('pc-toast--success')) {
      triggerHaptic(10);
    }
    window.setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-8px)';
      toast.style.transition = 'opacity 200ms, transform 200ms';
      window.setTimeout(() => toast.remove(), 220);
    }, 3800);
  });
}
