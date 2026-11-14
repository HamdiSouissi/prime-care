/**
 * PWA Service Worker Registration & Install Prompt (Android beforeinstallprompt + iOS Sheet).
 */
import { openSheet } from './sheet.js';
import { showToast } from './toast.js';

let deferredInstallPrompt = null;

export function initPwa() {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
        // Ignore SW registration failure on insecure local origin
      });
    });
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
  });

  document.querySelectorAll('[data-install-pwa]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        const choice = await deferredInstallPrompt.userChoice;
        if (choice.outcome === 'accepted') {
          showToast('Application installée sur votre téléphone.', 'success');
        }
        deferredInstallPrompt = null;
      } else {
        openSheet('install-pwa-sheet');
      }
    });
  });
}
