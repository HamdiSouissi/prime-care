/**
 * iOS-style Numeric PIN Pad (§3.1)
 * Supports 4–6 digit entry, dot indicators, keyboard input, shake + vibration on error.
 */
import { triggerHaptic } from './haptics.js';
import { showToast } from './toast.js';

export function initPinPad() {
  const form = document.getElementById('pc-pin-form');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const pinInput = form.querySelector('input[name="pin"]');
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
    const userId = form.querySelector('input[name="user_id"]')?.value || '';

    try {
      const response = await fetch('/api/auth/pin', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify({
          user_id: userId,
          pin: currentPin,
        }),
      });
      const result = await response.json();
      if (result.ok && result.data?.redirect) {
        triggerHaptic(15);
        window.location.href = result.data.redirect;
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
