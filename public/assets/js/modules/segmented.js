/**
 * Segmented Controls, Steppers, Selection Chips & Live Theme Switching (§6.2, §6.5).
 */
import { showToast } from './toast.js';
import { triggerHaptic } from './haptics.js';

export function applyTheme(theme) {
  const root = document.documentElement;
  if (theme === 'light' || theme === 'dark') {
    root.setAttribute('data-theme', theme);
  } else {
    root.setAttribute('data-theme', 'auto');
  }
  try {
    localStorage.setItem('pc_theme', theme);
  } catch {
    // Ignore storage restriction
  }
}

export function initSegmented() {
  // Restore local theme preference if present and html doesn't already specify a server user theme
  try {
    const saved = localStorage.getItem('pc_theme');
    const currentAttr = document.documentElement.getAttribute('data-theme');
    if (saved && (!currentAttr || currentAttr === 'auto')) {
      applyTheme(saved);
    }
  } catch {
    // Ignore
  }

  // Generic segmented controls
  document.querySelectorAll('[data-segmented]').forEach((group) => {
    const buttons = group.querySelectorAll('.pc-segmented__btn');
    const targetInputSelector = group.getAttribute('data-segmented-input');
    const isThemeControl = group.hasAttribute('data-theme-switcher');

    buttons.forEach((btn) => {
      btn.addEventListener('click', async () => {
        buttons.forEach((b) => {
          b.classList.remove('is-active');
          b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('is-active');
        btn.setAttribute('aria-pressed', 'true');
        triggerHaptic(8);

        const value = btn.getAttribute('data-value') || '';
        if (targetInputSelector) {
          const input = document.querySelector(targetInputSelector);
          if (input instanceof HTMLInputElement) {
            input.value = value;
          }
        }

        if (isThemeControl && value) {
          applyTheme(value);
          if (group.getAttribute('data-persist-theme') === '1') {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
              const res = await fetch('/api/profile/theme', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Accept': 'application/json',
                  'X-CSRF-Token': csrf,
                },
                body: JSON.stringify({ theme: value }),
              });
              const json = await res.json();
              if (json.ok) {
                showToast('Thème appliqué.', 'success');
              }
            } catch {
              // Offline or guest preview
            }
          }
        }
      });
    });
  });

  // Steppers
  document.querySelectorAll('[data-stepper]').forEach((stepper) => {
    const valEl = stepper.querySelector('.pc-stepper__val');
    const input = stepper.querySelector('input[type="hidden"]');
    const min = Number(stepper.getAttribute('data-min') ?? 0);
    const max = Number(stepper.getAttribute('data-max') ?? 999);
    const step = Number(stepper.getAttribute('data-step') ?? 1);

    stepper.querySelectorAll('[data-stepper-dir]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const dir = Number(btn.getAttribute('data-stepper-dir') || 1);
        const current = Number(valEl?.textContent || 0);
        const next = Math.min(max, Math.max(min, current + dir * step));
        if (valEl) {
          valEl.textContent = String(next);
        }
        if (input instanceof HTMLInputElement) {
          input.value = String(next);
        }
        triggerHaptic(6);
      });
    });
  });

  // Selection chips
  document.querySelectorAll('.pc-chip[data-chip-toggle]').forEach((chip) => {
    chip.addEventListener('click', () => {
      chip.classList.toggle('is-selected');
      triggerHaptic(6);
    });
  });
}
