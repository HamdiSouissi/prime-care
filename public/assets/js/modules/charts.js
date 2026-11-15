/**
 * Prime Care — Self-hosted ApexCharts Initializer (§8.3)
 * Renders all [data-pc-chart] elements using window.ApexCharts.
 */
export function initCharts() {
  const containers = document.querySelectorAll('[data-pc-chart]');
  if (containers.length === 0 || typeof window.ApexCharts !== 'function') {
    return;
  }

  const instances = [];

  containers.forEach((el) => {
    const raw = el.getAttribute('data-chart-config');
    if (!raw) {
      return;
    }
    try {
      const cfg = JSON.parse(raw);
      const chart = new window.ApexCharts(el, cfg);
      chart.render();
      instances.push(chart);
    } catch {
      // Ignore malformed JSON config
    }
  });

  // Re-render charts on theme change
  const observer = new MutationObserver(() => {
    instances.forEach((c) => {
      try {
        c.render();
      } catch {
        // Ignore
      }
    });
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
}
