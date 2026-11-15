/**
 * Prime Care — Phase 3 Frontend Module
 * - E05 Slide punch button + accessible keyboard sheet + Haversine GPS capture + Camera QR scanner (BarcodeDetector & jsQR)
 * - E16 Team Planning bottom sheets (create, edit/replace, duplicate, templates)
 * - §3.8 Alert Center handle sheet + quick choices + Web Push VAPID browser subscription
 */
import { openSheet, closeSheet } from './sheet.js';
import { showToast } from './toast.js';
import { triggerHaptic } from './haptics.js';

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') || '' : '';
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

async function captureGpsPosition(timeoutMs = 3500) {
  if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
    return null;
  }
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        resolve({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
          accuracy_m: Math.round(pos.coords.accuracy || 0),
        });
      },
      () => resolve(null),
      { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 15000 }
    );
  });
}

export function initPhase3() {
  initSlidePunch();
  initQrScanner();
  initPlanningSheets();
  initAlertCenter();
  initWebPushSubscription();
}

function initSlidePunch() {
  const slideEl = document.querySelector('[data-slide-punch]');
  const thumb = document.querySelector('[data-slide-punch-thumb]');
  const widget = document.querySelector('[data-punch-widget]');

  if (!(slideEl instanceof HTMLElement) || !(thumb instanceof HTMLButtonElement)) {
    return;
  }

  const punchType = slideEl.getAttribute('data-punch-type') || 'in';
  const vMode = widget?.getAttribute('data-verification-mode') || 'gps';

  // Keyboard accessibility: Enter / Space opens the confirmation sheet ("Confirmer mon arrivée à HHhMM")
  thumb.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      openSheet('punch-confirm-sheet');
    }
  });

  // Pre-populate GPS coordinates when submitting accessible confirmation form
  const accForm = document.querySelector('[data-accessible-punch-form]');
  if (accForm instanceof HTMLFormElement) {
    accForm.addEventListener('submit', async (e) => {
      if ((vMode === 'gps' || vMode === 'both') && !accForm.hasAttribute('data-gps-checked')) {
        e.preventDefault();
        accForm.setAttribute('data-gps-checked', '1');
        const coords = await captureGpsPosition();
        if (coords) {
          const latEl = accForm.querySelector('[data-punch-lat]');
          const lonEl = accForm.querySelector('[data-punch-lon]');
          const accEl = accForm.querySelector('[data-punch-acc]');
          if (latEl instanceof HTMLInputElement) latEl.value = String(coords.latitude);
          if (lonEl instanceof HTMLInputElement) lonEl.value = String(coords.longitude);
          if (accEl instanceof HTMLInputElement) accEl.value = String(coords.accuracy_m);
        }
        accForm.submit();
      }
    });
  }

  let pendingGdprCallback = null;

  const bindGdprAcceptButtons = () => {
    document.querySelectorAll('[data-accept-gdpr-btn]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (btn instanceof HTMLButtonElement) {
          btn.disabled = true;
        }
        try {
          const res = await fetch('/api/attendance/gdpr-accept', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify({ accepted: true }),
          });
          const body = await res.json();
          if (btn instanceof HTMLButtonElement) {
            btn.disabled = false;
          }
          if (res.ok && body.ok) {
            widget?.setAttribute('data-gdpr-accepted', '1');
            const inlineNotice = document.getElementById('pc-gdpr-notice');
            if (inlineNotice) {
              inlineNotice.classList.add('is-hidden');
            }
            closeSheet();
            if (typeof pendingGdprCallback === 'function') {
              const nextFn = pendingGdprCallback;
              pendingGdprCallback = null;
              await nextFn();
            } else {
              showToast('Mention d’information RGPD validée.', 'success');
            }
          } else {
            showToast(body.error?.message || 'Erreur lors de la validation RGPD.', 'error');
          }
        } catch {
          if (btn instanceof HTMLButtonElement) {
            btn.disabled = false;
          }
          showToast('Erreur réseau lors de la validation RGPD.', 'error');
        }
      });
    });
  };
  bindGdprAcceptButtons();

  // Pointer drag interaction using discrete CSS classes (CSP-safe: zero inline style mutations)
  let dragging = false;
  let startX = 0;

  const clearStepClasses = () => {
    slideEl.classList.remove(
      'pc-slide-punch--step-1',
      'pc-slide-punch--step-2',
      'pc-slide-punch--step-3',
      'pc-slide-punch--done'
    );
  };

  const applyCompletedPunchUi = (localTime, type) => {
    slideEl.classList.remove('pc-slide-punch--step-1', 'pc-slide-punch--step-2', 'pc-slide-punch--step-3');
    slideEl.classList.add('pc-slide-punch--done', 'pc-slide-punch--completed');
    const labelEl = slideEl.querySelector('[data-slide-punch-label]');
    if (labelEl) {
      labelEl.textContent = type === 'out'
        ? `Départ pointé à ${localTime}`
        : `Arrivée pointée à ${localTime}`;
    }
  };

  const submitSlidePunch = async () => {
    slideEl.classList.add('pc-slide-punch--done');
    triggerHaptic(20);

    let coords = null;
    if (vMode === 'gps' || vMode === 'both') {
      coords = await captureGpsPosition();
    }

    try {
      const res = await fetch('/api/attendance/punch', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({
          type: punchType,
          method: 'slide',
          latitude: coords?.latitude ?? null,
          longitude: coords?.longitude ?? null,
          accuracy_m: coords?.accuracy_m ?? null,
        }),
      });
      const body = await res.json();
      if (res.status === 403 && body.error?.code === 'GDPR_NOTICE_REQUIRED') {
        clearStepClasses();
        pendingGdprCallback = () => submitSlidePunch();
        openSheet('gdpr-notice-sheet');
        return;
      }
      if (body.ok) {
        const localTime = body.data?.local_time || '';
        applyCompletedPunchUi(localTime, punchType);
        showToast(body.data?.message || 'Pointage enregistré.', 'success');
        window.setTimeout(() => window.location.reload(), 900);
      } else {
        clearStepClasses();
        showToast(body.error?.message || 'Erreur lors du pointage.', 'error');
      }
    } catch {
      clearStepClasses();
      showToast('Erreur réseau lors du pointage.', 'error');
    }
  };

  thumb.addEventListener('pointerdown', (e) => {
    dragging = true;
    startX = e.clientX;
    thumb.setPointerCapture(e.pointerId);
  });

  thumb.addEventListener('pointermove', (e) => {
    if (!dragging) {
      return;
    }
    const maxDrag = Math.max(120, slideEl.clientWidth - 64);
    const dx = Math.max(0, e.clientX - startX);
    const ratio = dx / maxDrag;

    clearStepClasses();
    if (ratio >= 0.82) {
      dragging = false;
      submitSlidePunch();
    } else if (ratio >= 0.58) {
      slideEl.classList.add('pc-slide-punch--step-3');
    } else if (ratio >= 0.32) {
      slideEl.classList.add('pc-slide-punch--step-2');
    } else if (ratio >= 0.12) {
      slideEl.classList.add('pc-slide-punch--step-1');
    }
  });

  const endDrag = () => {
    if (!dragging) {
      return;
    }
    dragging = false;
    clearStepClasses();
  };

  // Intercept QR manual form and forgot-punch form for GDPR 403 chaining too
  const bindPunchFormGdprChain = (formEl, endpoint) => {
    if (!(formEl instanceof HTMLFormElement)) {
      return;
    }
    formEl.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitViaApi = async () => {
        const fd = new FormData(formEl);
        const payload = {};
        fd.forEach((v, k) => {
          if (typeof v === 'string' && k !== '_csrf') {
            payload[k] = v;
          }
        });
        try {
          const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify(payload),
          });
          const body = await res.json();
          if (res.status === 403 && body.error?.code === 'GDPR_NOTICE_REQUIRED') {
            pendingGdprCallback = () => submitViaApi();
            openSheet('gdpr-notice-sheet');
            return;
          }
          if (body.ok) {
            closeSheet();
            showToast(body.data?.message || 'Pointage enregistré.', 'success');
            window.setTimeout(() => window.location.reload(), 500);
          } else {
            showToast(body.error?.message || 'Erreur lors du pointage.', 'error');
          }
        } catch {
          showToast('Erreur réseau lors du pointage.', 'error');
        }
      };
      await submitViaApi();
    });
  };

  bindPunchFormGdprChain(document.getElementById('pc-qr-manual-form'), '/api/attendance/punch');
  bindPunchFormGdprChain(document.querySelector('#forgot-punch-sheet form'), '/api/attendance/declare');

  thumb.addEventListener('pointerup', endDrag);
  thumb.addEventListener('pointercancel', endDrag);
}

function initQrScanner() {
  let mediaStream = null;
  let scanLoopActive = false;

  const stopScanner = () => {
    scanLoopActive = false;
    if (mediaStream) {
      mediaStream.getTracks().forEach((t) => t.stop());
      mediaStream = null;
    }
  };

  let jsQrPromise = null;
  const ensureJsQrLoaded = () => {
    if (typeof window.jsQR === 'function') {
      return Promise.resolve(window.jsQR);
    }
    if (jsQrPromise) {
      return jsQrPromise;
    }
    jsQrPromise = new Promise((resolve) => {
      const script = document.createElement('script');
      script.src = document.body?.dataset?.jsqrSrc || '/assets/vendor/jsQR.min.js';
      const nonceEl = document.querySelector('script[nonce]');
      const nonce = nonceEl?.nonce || nonceEl?.getAttribute('nonce');
      if (nonce) {
        script.nonce = nonce;
      }
      script.onload = () => resolve(window.jsQR || null);
      script.onerror = () => resolve(null);
      document.head.appendChild(script);
    });
    return jsQrPromise;
  };

  document.querySelectorAll('[data-open-qr-scanner]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      openSheet('qr-punch-sheet');
      await ensureJsQrLoaded();
      const video = document.getElementById('pc-qr-video');
      const canvas = document.getElementById('pc-qr-canvas');
      const statusEl = document.getElementById('pc-qr-status');
      const inputEl = document.getElementById('qr-payload-input');
      const formEl = document.getElementById('pc-qr-manual-form');

      if (!(video instanceof HTMLVideoElement) || !(canvas instanceof HTMLCanvasElement)) {
        return;
      }

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        if (statusEl) {
          statusEl.textContent = 'Caméra non disponible sur ce navigateur — utilisez le code de secours ci-dessous.';
        }
        return;
      }

      try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' },
          audio: false,
        });
        video.srcObject = mediaStream;
        await video.play();
        scanLoopActive = true;

        const nativeDetector =
          'BarcodeDetector' in window
            ? new window.BarcodeDetector({ formats: ['qr_code'] })
            : null;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        const tick = async () => {
          if (!scanLoopActive) {
            return;
          }
          if (video.readyState >= HTMLMediaElement.HAVE_ENOUGH_DATA) {
            let decodedText = null;
            if (nativeDetector) {
              try {
                const codes = await nativeDetector.detect(video);
                if (codes && codes.length > 0 && codes[0].rawValue) {
                  decodedText = codes[0].rawValue;
                }
              } catch {
                // Fallback to jsQR
              }
            }
            if (!decodedText && typeof window.jsQR === 'function' && ctx) {
              canvas.width = video.videoWidth || 320;
              canvas.height = video.videoHeight || 240;
              ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
              const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
              const qr = window.jsQR(imgData.data, imgData.width, imgData.height);
              if (qr && qr.data) {
                decodedText = qr.data;
              }
            }

            if (decodedText && decodedText.startsWith('PRIMECARE-HOME:')) {
              stopScanner();
              triggerHaptic(20);
              if (inputEl instanceof HTMLInputElement) {
                inputEl.value = decodedText;
              }
              if (formEl instanceof HTMLFormElement) {
                formEl.submit();
              }
              return;
            }
          }
          window.requestAnimationFrame(tick);
        };

        window.requestAnimationFrame(tick);
      } catch {
        if (statusEl) {
          statusEl.textContent = 'Accès caméra refusé — saisissez le code figurant sous le QR code.';
        }
      }
    });
  });

  document.querySelectorAll('[data-stop-qr-scanner]').forEach((btn) => {
    btn.addEventListener('click', stopScanner);
  });
}

function initPlanningSheets() {
  // Template selector auto-fills start/end times
  const tplSelect = document.querySelector('[data-shift-template-select]');
  if (tplSelect instanceof HTMLSelectElement) {
    tplSelect.addEventListener('change', () => {
      const opt = tplSelect.selectedOptions[0];
      if (!opt || !opt.value) {
        return;
      }
      const startHm = opt.getAttribute('data-start') || '08:00';
      const endHm = opt.getAttribute('data-end') || '20:00';
      const spans = opt.getAttribute('data-spans') === '1';

      const startDateEl = document.getElementById('create-shift-start-date');
      const startTimeEl = document.getElementById('create-shift-start-time');
      const endDateEl = document.getElementById('create-shift-end-date');
      const endTimeEl = document.getElementById('create-shift-end-time');

      if (startTimeEl instanceof HTMLInputElement) startTimeEl.value = startHm;
      if (endTimeEl instanceof HTMLInputElement) endTimeEl.value = endHm;
      if (startDateEl instanceof HTMLInputElement && endDateEl instanceof HTMLInputElement && startDateEl.value) {
        const d = new Date(`${startDateEl.value}T12:00:00`);
        if (spans) {
          d.setDate(d.getDate() + 1);
        }
        endDateEl.value = d.toISOString().slice(0, 10);
      }
    });
  }

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) {
      return;
    }

    // Open create shift sheet for a specific date
    const createTrigger = target.closest('[data-open-create-shift-date]');
    if (createTrigger) {
      e.preventDefault();
      const ymd = createTrigger.getAttribute('data-open-create-shift-date') || '';
      const startDateEl = document.getElementById('create-shift-start-date');
      const endDateEl = document.getElementById('create-shift-end-date');
      if (startDateEl instanceof HTMLInputElement && ymd) startDateEl.value = ymd;
      if (endDateEl instanceof HTMLInputElement && ymd) endDateEl.value = ymd;
      openSheet('shift-create-sheet');
      return;
    }

    // Open edit/replace shift sheet
    const editTrigger = target.closest('[data-open-edit-shift]');
    if (editTrigger) {
      e.preventDefault();
      const shiftId = editTrigger.getAttribute('data-open-edit-shift') || '0';
      const editForm = document.getElementById('shift-edit-form');
      const delForm = document.getElementById('shift-delete-form');
      if (editForm instanceof HTMLFormElement) {
        editForm.action = `/planning/shifts/${shiftId}/update`;
      }
      if (delForm instanceof HTMLFormElement) {
        delForm.action = `/planning/shifts/${shiftId}/delete`;
      }

      const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement) {
          el.value = val || '';
        }
      };

      setVal('edit-shift-user', editTrigger.getAttribute('data-shift-user-id'));
      setVal('edit-shift-replaced', editTrigger.getAttribute('data-shift-replaced-id'));
      setVal('edit-shift-template', editTrigger.getAttribute('data-shift-template-id'));
      setVal('edit-shift-start-date', editTrigger.getAttribute('data-shift-start-date'));
      setVal('edit-shift-start-time', editTrigger.getAttribute('data-shift-start-time'));
      setVal('edit-shift-end-date', editTrigger.getAttribute('data-shift-end-date'));
      setVal('edit-shift-end-time', editTrigger.getAttribute('data-shift-end-time'));
      setVal('edit-shift-notes', editTrigger.getAttribute('data-shift-notes'));

      openSheet('shift-edit-sheet');
    }
  });
}

function initAlertCenter() {
  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) {
      return;
    }

    const handleBtn = target.closest('[data-open-handle-alert]');
    if (handleBtn) {
      e.preventDefault();
      const alertId = handleBtn.getAttribute('data-open-handle-alert') || '0';
      const alertTitle = handleBtn.getAttribute('data-alert-title') || '';
      const form = document.getElementById('alert-handle-form');
      const sub = document.getElementById('alert-handle-subtitle');
      if (form instanceof HTMLFormElement) {
        form.action = `/alerts/${alertId}/handle`;
      }
      if (sub) {
        sub.textContent = alertTitle;
      }
      openSheet('alert-handle-sheet');
      return;
    }

    const choiceChip = target.closest('[data-quick-alert-choice]');
    if (choiceChip) {
      e.preventDefault();
      const choice = choiceChip.getAttribute('data-quick-alert-choice') || '';
      const hiddenEl = document.getElementById('alert-quick-choice');
      const noteEl = document.getElementById('alert-handle-note');
      document.querySelectorAll('[data-quick-alert-choice]').forEach((c) => c.classList.remove('is-active'));
      choiceChip.classList.add('is-active');
      if (hiddenEl instanceof HTMLInputElement) {
        hiddenEl.value = choice;
      }
      if (noteEl instanceof HTMLTextAreaElement && noteEl.value.trim() === '') {
        noteEl.value = choice;
      }
    }
  });
}

function showSystemPushBanner(title, bodyText) {
  const existing = document.getElementById('pc-system-push-banner');
  if (existing) {
    existing.remove();
  }
  const banner = document.createElement('div');
  banner.id = 'pc-system-push-banner';
  banner.className = 'pc-push-system-banner';
  banner.setAttribute('role', 'status');
  banner.innerHTML = `
    <div class="pc-push-system-banner__icon">PC</div>
    <div class="pc-push-system-banner__body">
      <div class="pc-push-system-banner__meta">
        <span>CHROME · WEB PUSH VAPID · ${window.location.host}</span>
        <span>À l’instant</span>
      </div>
      <div class="pc-push-system-banner__title"></div>
      <div class="pc-push-system-banner__text"></div>
    </div>
  `;
  const titleEl = banner.querySelector('.pc-push-system-banner__title');
  const textEl = banner.querySelector('.pc-push-system-banner__text');
  if (titleEl) titleEl.textContent = title;
  if (textEl) textEl.textContent = bodyText;
  document.body.appendChild(banner);
  window.setTimeout(() => banner.remove(), 8000);
}

function initWebPushSubscription() {
  const pushBtn = document.querySelector('[data-enable-web-push]');
  const badge = document.getElementById('pc-push-permission-badge');
  const testForm = document.querySelector('form[action="/profile/push-test"]');

  if (testForm instanceof HTMLFormElement) {
    testForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        const res = await fetch('/api/push/test', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-Token': getCsrfToken(),
          },
          body: JSON.stringify({ test: true }),
        });
        const body = await res.json();
        const notifTitle = 'Test Prime Care réussi';
        const notifBody = 'Les notifications temps réel (Web Push VAPID) fonctionnent sur cet appareil.';

        if ('Notification' in window && Notification.permission === 'granted') {
          if ('serviceWorker' in navigator) {
            const reg = await navigator.serviceWorker.ready;
            await reg.showNotification(notifTitle, {
              body: notifBody,
              icon: '/assets/brand/icon-192.png',
              badge: '/assets/brand/icon-192.png',
              tag: 'prime-care-test',
            });
          } else {
            new Notification(notifTitle, { body: notifBody, icon: '/assets/brand/icon-192.png' });
          }
        }

        showSystemPushBanner(notifTitle, notifBody);
        showToast(body.data?.message || 'Notification de test envoyée.', 'success');
      } catch {
        testForm.submit();
      }
    });
  }

  if (typeof Notification !== 'undefined' && badge) {
    if (Notification.permission === 'granted') {
      badge.textContent = 'Push actif';
      badge.className = 'pc-badge pc-badge--ok';
    } else if (Notification.permission === 'denied') {
      badge.textContent = 'Push bloqué';
      badge.className = 'pc-badge pc-badge--danger';
    }
  }

  if (!(pushBtn instanceof HTMLButtonElement)) {
    return;
  }

  pushBtn.addEventListener('click', async () => {
    const vapidKey = pushBtn.getAttribute('data-vapid-public-key') || '';
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      showToast('Les notifications Web Push ne sont pas prises en charge sur ce navigateur.', 'warning');
      return;
    }
    if (!vapidKey) {
      showToast('Clé publique VAPID non configurée sur le serveur.', 'error');
      return;
    }

    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        showToast('Autorisation de notification refusée.', 'warning');
        return;
      }

      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey),
      });

      const res = await fetch('/api/push/subscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify(sub.toJSON()),
      });

      const body = await res.json();
      if (body.ok) {
        triggerHaptic(15);
        showToast('Notifications Web Push activées sur cet appareil.', 'success');
        if (badge) {
          badge.textContent = 'Push actif';
          badge.className = 'pc-badge pc-badge--ok';
        }
      } else {
        showToast(body.error?.message || 'Erreur lors de l’enregistrement push.', 'error');
      }
    } catch {
      showToast('Impossible d’activer l’abonnement Web Push sur cet appareil.', 'error');
    }
  });
}
