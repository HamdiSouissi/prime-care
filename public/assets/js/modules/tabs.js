/**
 * Bottom Tab Bar & Central "+" Quick Actions Button (§6.5)
 */
import { openSheet } from './sheet.js';
import { triggerHaptic } from './haptics.js';

export function initTabs() {
  const plusBtn = document.getElementById('pc-tabbar-plus');
  if (plusBtn) {
    plusBtn.addEventListener('click', (e) => {
      e.preventDefault();
      triggerHaptic(10);
      openSheet('quick-add-sheet');
    });
  }
}
