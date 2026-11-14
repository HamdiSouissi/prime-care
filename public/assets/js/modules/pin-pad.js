/**
 * Trusted-Device Numeric PIN Pad (C1 & Additif B §9.6)
 * Submits ONLY the trusted-device `selector` (never a raw user_id), supports switching
 * between trusted profiles ("Qui êtes-vous ?"), 4–6 digit entry, and password fallback after 5 errors.
 */
import { triggerHaptic } from './haptics.js';
import { showToast } from './toast.js';

export function initPinPad() {
  const form = document.getElementById('pc-pin-form');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const pinInput = form.querySelector('input[name="pin"]');
  const selectorInput = form.querySelector('input[name="selector"]');
  const greetingEl = document.getElementById('pc-pin-greeting');
  const dotsWrap = document.getElementById('pc-pin-dots');
  const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.pc-pin-dot')) : [];
  const maxDigits = 6;
  let currentPin = '';
  let submitting = false;

  const updateDots = () => {
    if (pinInput instanceof HTMLInputElement) {
      pinInput.value = currentPin;
    }
    dots.forEach((dot, index) => {
      dot.classList.toggle('is-filled', index < currentPin.length);
    });
  };

  const triggerShakeError = (msg) => {
    triggerHaptic([30, 50, 30]);
    if (dotsWrap) {
      dotsWrap.classList.remove('is-shaking');
      void dotsWrap.offsetWidth;
      dotsWrap.classList.add('is-shaking');
    }
    currentPin = '';
    updateDots();
    if (msg) {
      showToast(msg, 'error');
    }
  };

  const submitPin = async () => {
    if (submitting || currentPin.length < 4) {
      return;
    }
    submitting = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const selector = selectorInput instanceof HTMLInputElement ? selectorInput.value : '';

    try {
      const response = await fetch('/api/auth/pin', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify({
          selector,
          pin: currentPin,
        }),
      });
      const result = await response.json();
      if (result.ok && result.data?.redirect) {
        triggerHaptic(15);
        window.location.href = result.data.redirect;
        return;
      }
      if (response.status === 429 || result.error?.code === 'RATE_LIMITED') {
        triggerShakeError(result.error?.message || 'Code PIN bloqué pendant 15 minutes.');
        window.setTimeout(() => {
          window.location.href = '/login?mode=password';
        }, 900);
        return;
      }
      triggerShakeError(result.error?.message || 'Code PIN incorrect.');
    } catch {
      form.submit();
    } finally {
      submitting = false;
    }
  };

  const appendDigit = (digit) => {
    if (submitting || currentPin.length >= maxDigits) {
      return;
    }
    triggerHaptic(8);
    currentPin += digit;
    updateDots();

    if (currentPin.length === 4) {
      window.setTimeout(() => {
        if (currentPin.length >= 4) {
          submitPin();
        }
      }, 160);
    }
  };

  const removeDigit = () => {
    if (submitting || currentPin.length === 0) {
      return;
    }
    triggerHaptic(6);
    currentPin = currentPin.slice(0, -1);
    updateDots();
  };

  // Profile selector ("Qui êtes-vous ?")
  document.querySelectorAll('[data-pin-profile-selector]').forEach((card) => {
    card.addEventListener('click', () => {
      const sel = card.getAttribute('data-pin-profile-selector') || '';
      const name = card.getAttribute('data-pin-profile-name') || '';
      const locked = card.getAttribute('data-pin-profile-locked') === '1';

      if (locked) {
        showToast('Ce code PIN est bloqué 15 min. Veuillez vous connecter par mot de passe.', 'error');
        window.location.href = '/login?mode=password';
        return;
      }

      document.querySelectorAll('[data-pin-profile-selector]').forEach((el) => {
        el.classList.toggle('is-selected', el === card);
      });

      if (selectorInput instanceof HTMLInputElement) {
        selectorInput.value = sel;
      }
      if (greetingEl && name) {
        greetingEl.textContent = `Bonjour ${name}`;
      }

      currentPin = '';
      updateDots();
      triggerHaptic(10);
    });
  });

  form.querySelectorAll('[data-pin-digit]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const d = btn.getAttribute('data-pin-digit');
      if (d !== null) {
        appendDigit(d);
      }
    });
  });

  const backBtn = form.querySelector('[data-pin-backspace]');
  if (backBtn) {
    backBtn.addEventListener('click', removeDigit);
  }

  document.addEventListener('keydown', (e) => {
    const activeTag = document.activeElement?.tagName;
    if (activeTag === 'INPUT' || activeTag === 'TEXTAREA') {
      return;
    }
    if (e.key >= '0' && e.key <= '9') {
      appendDigit(e.key);
    } else if (e.key === 'Backspace') {
      removeDigit();
    } else if (e.key === 'Enter' && currentPin.length >= 4) {
      e.preventDefault();
      submitPin();
    }
  });
}
