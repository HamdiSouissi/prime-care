/**
 * Prime Care — Animated Launch Loader (§6.8 + Additif B §9.5 + Phase 2 §4 bis.1)
 *
 * Rules:
 * - Minimum display duration: 2 000 ms (configurable via data-min-ms / app.loader_min_ms)
 * - Maximum safety timeout: 4 000 ms
 * - Smooth fade-out: 300 ms (opacity: 0 + scale(1.02), then pointer-events: none & removal)
 * - Shown ONLY on:
 *   1. First load of the browser session (sessionStorage.getItem('pc_loader_shown') !== '1')
 *   2. Full browser reload (F5 / Ctrl+R / Pull-to-refresh: navigation.type === 'reload')
 *   3. Post-login redirect (server flash sets data-force-loader="1")
 * - NEVER shown on internal page-to-page navigation links.
 */
const SESSION_KEY = 'pc_loader_shown';
const DEFAULT_MIN_MS = 2000;
const MAX_DURATION_MS = 4000;
const FADEOUT_MS = 300;

export function initLaunchLoader() {
  const loader = document.getElementById('pc-launch-loader');
  if (!loader) {
    return;
  }

  const minDurationMs = Number(loader.dataset.minMs || DEFAULT_MIN_MS) || DEFAULT_MIN_MS;
  const forceShow = loader.dataset.forceLoader === '1' || loader.dataset.forceShow === '1';
  const alreadyShown = sessionStorage.getItem(SESSION_KEY) === '1';

  // Detect full browser reload via Navigation Timing Level 2
  let isReload = false;
  try {
    const navEntries = performance.getEntriesByType?.('navigation');
    if (navEntries && navEntries.length > 0 && navEntries[0].type === 'reload') {
      isReload = true;
    }
  } catch {
    isReload = false;
  }

  const shouldShow = !alreadyShown || isReload || forceShow;

  if (!shouldShow) {
    loader.classList.add('is-dismissed');
    loader.remove();
    return;
  }

  sessionStorage.setItem(SESSION_KEY, '1');
  const startTime = performance.now();

  const dismiss = () => {
    if (!loader.isConnected || loader.classList.contains('is-dismissed')) {
      return;
    }
    loader.classList.add('is-dismissed');
    window.setTimeout(() => {
      if (loader.isConnected) {
        loader.remove();
      }
    }, FADEOUT_MS);
  };

  const scheduleDismiss = () => {
    const elapsed = performance.now() - startTime;
    const remaining = Math.max(0, minDurationMs - elapsed);
    window.setTimeout(dismiss, remaining);
  };

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(scheduleDismiss).catch(scheduleDismiss);
  } else {
    scheduleDismiss();
  }

  window.setTimeout(dismiss, MAX_DURATION_MS);
}

/**
 * Show or hide the 32px breathing monogram loader for operations > 1s.
 */
export function setMiniLoader(containerSelector, active = true) {
  const el = typeof containerSelector === 'string'
    ? document.querySelector(containerSelector)
    : containerSelector;
  if (!el) {
    return;
  }
  el.classList.toggle('hidden', !active);
}
