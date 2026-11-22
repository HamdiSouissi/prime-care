/**
 * Bottom Sheets & iOS Destructive Confirmation Alerts (§6.5)
 * Supports drag-to-close on handle, backdrop click, Escape key, focus trapping, and sheet switching.
 */
import { triggerHaptic } from './haptics.js';

let activeSheetBackdrop = null;
let previousFocusedElement = null;

export function openSheet(sheetId) {
  const backdrop = document.getElementById(sheetId);
  if (!backdrop) {
    return;
  }

  if (activeSheetBackdrop && activeSheetBackdrop !== backdrop) {
    activeSheetBackdrop.classList.remove('is-open');
    activeSheetBackdrop.setAttribute('aria-hidden', 'true');
  } else {
    previousFocusedElement = document.activeElement;
  }

  activeSheetBackdrop = backdrop;
  backdrop.classList.add('is-open');
  backdrop.setAttribute('aria-hidden', 'false');
  document.documentElement.classList.add('has-open-sheet');
  document.body.style.overflow = 'hidden';
  triggerHaptic(8);

  const firstFocusable = backdrop.querySelector(
    'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), select, textarea, button, [href]'
  );
  if (firstFocusable instanceof HTMLElement) {
    window.setTimeout(() => firstFocusable.focus(), 80);
  }
}

export function closeSheet(backdrop = activeSheetBackdrop) {
  if (!backdrop) {
    return;
  }

  const sheet = backdrop.querySelector('.pc-sheet');
  if (sheet instanceof HTMLElement) {
    sheet.style.transform = '';
  }

  backdrop.classList.remove('is-open');
  backdrop.setAttribute('aria-hidden', 'true');
  document.documentElement.classList.remove('has-open-sheet');
  document.body.style.overflow = '';

  if (activeSheetBackdrop === backdrop) {
    activeSheetBackdrop = null;
  }

  if (previousFocusedElement instanceof HTMLElement) {
    previousFocusedElement.focus();
    previousFocusedElement = null;
  }
}

export function initSheets() {
  const unlockIfNoOpenSheet = () => {
    if (!document.querySelector('.pc-sheet-backdrop.is-open, .pc-alert-backdrop.is-open')) {
      document.documentElement.classList.remove('has-open-sheet');
      document.body.style.overflow = '';
    }
  };
  unlockIfNoOpenSheet();
  window.addEventListener('pageshow', unlockIfNoOpenSheet);

  const plusBtn = document.getElementById('pc-tabbar-plus');
  if (plusBtn) {
    plusBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openSheet('quick-add-sheet');
    });
  }

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) {
      return;
    }

    const switchTrigger = target.closest('[data-switch-sheet]');
    if (switchTrigger) {
      e.preventDefault();
      const nextId = switchTrigger.getAttribute('data-switch-sheet');
      if (nextId) {
        openSheet(nextId);
      }
      return;
    }

    const openTrigger = target.closest('[data-open-sheet], [data-sheet-open]');
    if (openTrigger) {
      e.preventDefault();
      const id = openTrigger.getAttribute('data-open-sheet') || openTrigger.getAttribute('data-sheet-open');
      if (id) {
        openSheet(id);
      }
      return;
    }

    const closeTrigger = target.closest('[data-close-sheet], [data-sheet-close]');
    if (closeTrigger) {
      e.preventDefault();
      const backdrop = closeTrigger.closest('.pc-sheet-backdrop, .pc-alert-backdrop');
      if (backdrop instanceof HTMLElement) {
        closeSheet(backdrop);
      }
      return;
    }

    if (target.classList.contains('pc-sheet-backdrop') || target.classList.contains('pc-alert-backdrop')) {
      closeSheet(target);
    }
  });

  // Escape key & Tab focus trap
  document.addEventListener('keydown', (e) => {
    if (!activeSheetBackdrop) {
      return;
    }

    if (e.key === 'Escape') {
      e.preventDefault();
      closeSheet(activeSheetBackdrop);
      return;
    }

    if (e.key === 'Tab') {
      const focusables = Array.from(
        activeSheetBackdrop.querySelectorAll(
          'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )
      );
      if (focusables.length === 0) {
        return;
      }
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  // Touch drag-to-close on bottom sheets
  document.querySelectorAll('.pc-sheet').forEach((sheet) => {
    if (!(sheet instanceof HTMLElement)) {
      return;
    }
    let startY = 0;
    let currentY = 0;
    let dragging = false;

    const handle = sheet.querySelector('.pc-sheet__handle, .pc-sheet__header') || sheet;
    handle.addEventListener(
      'touchstart',
      (e) => {
        if (sheet.scrollTop > 0) {
          return;
        }
        startY = e.touches[0].clientY;
        dragging = true;
        sheet.style.transition = 'none';
      },
      { passive: true }
    );

    handle.addEventListener(
      'touchmove',
      (e) => {
        if (!dragging) {
          return;
        }
        currentY = e.touches[0].clientY - startY;
        if (currentY > 0) {
          sheet.style.transform = `translateY(${currentY}px)`;
        }
      },
      { passive: true }
    );

    handle.addEventListener('touchend', () => {
      if (!dragging) {
        return;
      }
      dragging = false;
      sheet.style.transition = '';
      if (currentY > 90) {
        const backdrop = sheet.closest('.pc-sheet-backdrop');
        if (backdrop instanceof HTMLElement) {
          closeSheet(backdrop);
        }
      } else {
        sheet.style.transform = '';
      }
      currentY = 0;
    });
  });
}
