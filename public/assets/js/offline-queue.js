/**
 * Prime Care — Offline Queue & IndexedDB Sync Engine (§4.1 / Phase 3)
 *
 * Stores medical writes performed while offline (measures, intakes, photo-less meals,
 * notes, care checks) with a unique `client_uuid` and the actual capture timestamp,
 * displays the pending counter badge, and replays them idempotently when connectivity returns.
 */
import { showToast } from './modules/toast.js';
import { closeSheet } from './modules/sheet.js';

const DB_NAME = 'prime_care_offline';
const DB_VERSION = 1;
const STORE_NAME = 'pending_actions';

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') || '' : '';
}

export function generateClientUuid() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i++) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}

function openOfflineDb() {
  return new Promise((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      reject(new Error('IndexedDB non disponible'));
      return;
    }
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'client_uuid' });
        store.createIndex('created_at', 'created_at', { unique: false });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

export async function enqueueOfflineAction(endpoint, payload, label = 'Saisie médicale') {
  const clientUuid = payload.client_uuid || generateClientUuid();
  const clientRecordedAt = payload.client_recorded_at || new Date().toISOString();
  const item = {
    client_uuid: clientUuid,
    endpoint,
    payload: {
      ...payload,
      client_uuid: clientUuid,
      client_recorded_at: clientRecordedAt,
    },
    label,
    created_at: Date.now(),
  };

  try {
    const db = await openOfflineDb();
    await new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      tx.objectStore(STORE_NAME).put(item);
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => reject(tx.error);
    });
  } catch {
    // Fallback if IndexedDB is unavailable in private mode
  }

  closeSheet();
  await refreshOfflineQueueUi();
  showToast('Enregistré hors ligne', 'info');
  return item;
}

export async function getPendingOfflineActions() {
  try {
    const db = await openOfflineDb();
    return await new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readonly');
      const req = tx.objectStore(STORE_NAME).getAll();
      req.onsuccess = () => {
        const list = Array.isArray(req.result) ? req.result : [];
        list.sort((a, b) => (a.created_at || 0) - (b.created_at || 0));
        resolve(list);
      };
      req.onerror = () => reject(req.error);
    });
  } catch {
    return [];
  }
}

export async function removeOfflineAction(clientUuid) {
  try {
    const db = await openOfflineDb();
    await new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      tx.objectStore(STORE_NAME).delete(clientUuid);
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => reject(tx.error);
    });
  } catch {
    // Ignore
  }
}

export async function clearOfflineQueue() {
  try {
    const db = await openOfflineDb();
    await new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, 'readwrite');
      tx.objectStore(STORE_NAME).clear();
      tx.oncomplete = () => resolve(true);
      tx.onerror = () => reject(tx.error);
    });
  } catch {
    // Ignore
  }
}

export async function refreshOfflineQueueUi() {
  const banner = document.getElementById('pc-offline-banner');
  const queueBar = document.getElementById('pc-offline-queue-bar');
  const countEl = document.getElementById('pc-offline-queue-count');

  const isOffline = typeof navigator !== 'undefined' && navigator.onLine === false;
  if (banner) {
    banner.classList.toggle('is-hidden', !isOffline);
  }

  const pending = await getPendingOfflineActions();
  if (queueBar && countEl) {
    const count = pending.length;
    queueBar.classList.toggle('is-hidden', count === 0);
    countEl.textContent = count > 1 ? `${count} saisies en attente` : `${count} saisie en attente`;
  }
}

let isSyncing = false;

export async function syncOfflineQueue() {
  if (isSyncing || (typeof navigator !== 'undefined' && navigator.onLine === false)) {
    return;
  }
  const pending = await getPendingOfflineActions();
  if (pending.length === 0) {
    await refreshOfflineQueueUi();
    return;
  }

  isSyncing = true;
  let syncedCount = 0;

  for (const item of pending) {
    try {
      const res = await fetch(item.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify(item.payload),
      });

      const body = await res.json().catch(() => ({}));

      if (res.ok && body.ok) {
        await removeOfflineAction(item.client_uuid);
        syncedCount++;
      } else if (res.status === 409) {
        // Conflict (e.g. intake already validated by another caregiver): notify & dequeue
        await removeOfflineAction(item.client_uuid);
        showToast(
          body.error?.message || `Conflit sur « ${item.label} » : déjà validée par un autre membre.`,
          'warning'
        );
      } else if (res.status >= 400 && res.status < 500 && res.status !== 408 && res.status !== 429) {
        // Validation/permission error: notify & dequeue so queue doesn't block forever
        await removeOfflineAction(item.client_uuid);
        showToast(body.error?.message || `Saisie « ${item.label} » refusée (${res.status}).`, 'error');
      }
    } catch {
      // Network still unstable: stop replay and retry on next online event
      break;
    }
  }

  isSyncing = false;
  await refreshOfflineQueueUi();

  if (syncedCount > 0) {
    showToast(
      syncedCount > 1
        ? `Saisies synchronisées (${syncedCount})`
        : 'Saisies synchronisées',
      'success'
    );
  }
}

function updateMealPhotoOfflineGuard() {
  const mealForm = document.getElementById('form-quick-meal');
  if (!(mealForm instanceof HTMLFormElement)) {
    return;
  }
  const fileInputs = Array.from(mealForm.querySelectorAll('input[type="file"]'));
  const hasPhoto = fileInputs.some((f) => f instanceof HTMLInputElement && f.files && f.files.length > 0);
  const isOffline = typeof navigator !== 'undefined' && navigator.onLine === false;
  const hint = document.getElementById('meal-offline-photo-hint');
  const submitBtn = document.getElementById('meal-submit-btn');
  const submitLabel = document.getElementById('meal-submit-btn-label');

  if (hint) {
    hint.classList.toggle('is-hidden', !(isOffline || (isOffline && hasPhoto)));
  }

  if (submitBtn instanceof HTMLButtonElement) {
    if (isOffline && hasPhoto) {
      submitBtn.disabled = true;
      if (submitLabel) {
        submitLabel.textContent = 'Photo : réseau nécessaire';
      }
    } else {
      submitBtn.disabled = false;
      if (submitLabel) {
        submitLabel.textContent = 'Enregistrer le repas';
      }
    }
  }
}

/**
 * Intercepts all §5 medical forms (Measures, Donné/Insuline, Non donné, Si besoin,
 * Repas sans photo, Notes & Transmissions) and submits via JSON API with automatic
 * offline fallback on network error (`TypeError`) or `navigator.onLine === false`.
 */
export function initOfflineQueue() {
  refreshOfflineQueueUi();
  updateMealPhotoOfflineGuard();

  window.addEventListener('online', () => {
    refreshOfflineQueueUi();
    updateMealPhotoOfflineGuard();
    syncOfflineQueue();
  });

  window.addEventListener('offline', () => {
    refreshOfflineQueueUi();
    updateMealPhotoOfflineGuard();
  });

  const syncBtn = document.getElementById('pc-offline-sync-btn');
  syncBtn?.addEventListener('click', () => {
    syncOfflineQueue();
  });

  document.querySelectorAll('#form-quick-meal input[type="file"]').forEach((inp) => {
    inp.addEventListener('change', updateMealPhotoOfflineGuard);
  });

  const bindOfflineCapableForm = (formSelector, resolveEndpoint, labelBuilder) => {
    const form = document.querySelector(formSelector);
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    let uuidInput = form.querySelector('input[name="client_uuid"]');
    if (!(uuidInput instanceof HTMLInputElement)) {
      uuidInput = document.createElement('input');
      uuidInput.type = 'hidden';
      uuidInput.name = 'client_uuid';
      uuidInput.value = generateClientUuid();
      form.appendChild(uuidInput);
    }

    form.addEventListener('submit', async (e) => {
      const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
      const hasFile = fileInputs.some((f) => f instanceof HTMLInputElement && f.files && f.files.length > 0);

      // Meal with photo requires network: block if offline, or let multipart submit when online
      if (hasFile) {
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
          e.preventDefault();
          updateMealPhotoOfflineGuard();
          showToast('Photo : réseau nécessaire', 'warning');
        }
        return;
      }

      e.preventDefault();

      const fd = new FormData(form);
      const payload = {};
      fd.forEach((v, k) => {
        if (typeof v === 'string' && k !== '_csrf') {
          payload[k] = v;
        }
      });

      const clientUuid = uuidInput.value || generateClientUuid();
      const clientRecordedAt = new Date().toISOString();
      payload.client_uuid = clientUuid;
      payload.client_recorded_at = clientRecordedAt;

      const apiEndpoint = typeof resolveEndpoint === 'function' ? resolveEndpoint(form, payload) : resolveEndpoint;
      const label = labelBuilder(payload);

      if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        await enqueueOfflineAction(apiEndpoint, payload, label);
        uuidInput.value = generateClientUuid();
        form.reset();
        return;
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn instanceof HTMLButtonElement) {
        submitBtn.disabled = true;
      }

      try {
        const res = await fetch(apiEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-Token': getCsrfToken(),
          },
          body: JSON.stringify(payload),
        });

        const body = await res.json().catch(() => ({}));
        if (submitBtn instanceof HTMLButtonElement) {
          submitBtn.disabled = false;
        }

        if (res.ok && body.ok) {
          closeSheet();
          uuidInput.value = generateClientUuid();
          showToast(body.data?.message || `${label} enregistrée.`, 'success');
          window.setTimeout(() => window.location.reload(), 300);
        } else {
          showToast(body.error?.message || `Erreur (${res.status})`, 'error');
        }
      } catch {
        if (submitBtn instanceof HTMLButtonElement) {
          submitBtn.disabled = false;
        }
        await enqueueOfflineAction(apiEndpoint, payload, label);
        uuidInput.value = generateClientUuid();
        form.reset();
      }
    });
  };

  bindOfflineCapableForm('#form-quick-measure', '/api/measures', (p) => `Mesure (${p.type_code || 'constante'})`);
  bindOfflineCapableForm(
    '#form-insulin-give',
    (form, p) => form.getAttribute('data-api-form') || `/api/intakes/${p.intake_id || 0}/give`,
    () => 'Injection d’insuline'
  );
  bindOfflineCapableForm(
    '#form-intake-skip',
    (form, p) => form.getAttribute('data-api-form') || `/api/intakes/${p.intake_id || 0}/skip`,
    () => 'Prise non donnée'
  );
  bindOfflineCapableForm('#form-as-needed', '/api/intakes/as-needed', () => 'Prise « Si besoin »');
  bindOfflineCapableForm('#form-quick-meal', '/api/meals', () => 'Repas');
  bindOfflineCapableForm('#form-quick-note', '/api/notes', () => 'Note du journal');

  // Purge cached authenticated pages & IndexedDB queue on logout
  document.querySelectorAll('[data-logout-form], form[action="/logout"]').forEach((form) => {
    form.addEventListener('submit', () => {
      clearOfflineQueue();
      if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'PURGE_AUTH_CACHE' });
      }
    });
  });
}
