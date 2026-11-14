/**
 * Haptic feedback helper using navigator.vibrate when supported.
 */
export function triggerHaptic(pattern = 10) {
  try {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }
    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
      navigator.vibrate(pattern);
    }
  } catch {
    // Ignore on unsupported devices (e.g. iOS Safari)
  }
}
