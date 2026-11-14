/**
 * Prime Care — Animated Launch Loader (§6.8 + Additif B §9.5) & Mini Loader
 */
const SESSION_KEY = 'pc_launch_loader_shown';
const MIN_DURATION_MS = 1650;
const MAX_DURATION_MS = 4000;

export function initLaunchLoader() {
  const loader = document.getElementById('pc-launch-loader');
  if (!loader) {
    return;
  }

  const forceShow = loader.dataset.forceLoader === '1' || loader.dataset.forceShow === '1';
  const alreadyShown = sessionStorage.getItem(SESSION_KEY) === '1';

  if (alreadyShown && !forceShow) {
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
    }, 320);
  };

  const scheduleDismiss = () => {
    const elapsed = performance.now() - startTime;
    const remaining = Math.max(0, MIN_DURATION_MS - elapsed);
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
