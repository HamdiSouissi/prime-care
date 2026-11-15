/**
 * PWA Service Worker Registration, Standalone Detection & Install Prompt (§4 bis.2).
 */
import { openSheet } from './sheet.js';
import { showToast } from './toast.js';

const DISMISS_KEY = 'pc_install_banner_dismissed_until';
const SEVEN_DAYS_MS = 7 * 24 * 60 * 60 * 1000;

let deferredInstallPrompt = null;

export function isStandaloneMode() {
  return (
    window.matchMedia?.('(display-mode: standalone)').matches === true ||
    window.navigator.standalone === true ||
    new URLSearchParams(window.location.search).get('source') === 'pwa'
  );
}

export function initPwa() {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
        // Ignore SW registration failure on insecure local origin
      });
    });
  }

  const standalone = isStandaloneMode();
  if (standalone) {
    document.documentElement.classList.add('is-standalone');
  }

  // Update standalone status badge on /install
  const statusBadge = document.getElementById('pwa-standalone-status');
  if (statusBadge) {
    statusBadge.textContent = standalone
      ? 'Mode actif : Application plein écran (Standalone)'
      : 'Mode : Navigateur web (non installé)';
  }

  // Home install banner visibility (only in browser mode & if not dismissed within 7 days)
  const homeBanner = document.getElementById('pc-home-install-banner');
  const dismissedUntil = Number(localStorage.getItem(DISMISS_KEY) || '0');
  if (homeBanner && !standalone && Date.now() > dismissedUntil) {
    homeBanner.classList.remove('is-hidden');
  }

  document.querySelectorAll('[data-dismiss-install-banner]').forEach((btn) => {
    btn.addEventListener('click', () => {
      localStorage.setItem(DISMISS_KEY, String(Date.now() + SEVEN_DAYS_MS));
      homeBanner?.classList.add('is-hidden');
    });
  });

  const nativeInstallBtn = document.getElementById('pwa-native-install-btn');
  const homeInstallCta = document.getElementById('pc-home-install-cta');

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    nativeInstallBtn?.classList.remove('is-hidden');
  });

  const triggerPromptOrGuide = async (e) => {
    if (deferredInstallPrompt) {
      e.preventDefault();
      deferredInstallPrompt.prompt();
      const choice = await deferredInstallPrompt.userChoice;
      if (choice.outcome === 'accepted') {
        showToast('Prime Care a été ajoutée à votre écran d’accueil.', 'success');
        homeBanner?.classList.add('is-hidden');
      }
      deferredInstallPrompt = null;
    }
  };

  nativeInstallBtn?.addEventListener('click', triggerPromptOrGuide);
  homeInstallCta?.addEventListener('click', triggerPromptOrGuide);

  document.querySelectorAll('[data-install-pwa]').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (deferredInstallPrompt) {
        await triggerPromptOrGuide(e);
      } else {
        openSheet('install-pwa-sheet');
      }
    });
  });
}
