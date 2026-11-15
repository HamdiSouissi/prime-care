/**
 * Prime Care — Self-hosted ApexCharts Initializer (§8.3 + I1)
 * Dynamically injects /assets/vendor/apexcharts.min.js only when a [data-pc-chart]
 * element is visible in the viewport (never blocking initial page load or LCP).
 */
let apexChartsPromise = null;

function ensureApexChartsLoaded() {
  if (typeof window.ApexCharts === 'function') {
    return Promise.resolve(window.ApexCharts);
  }
  if (apexChartsPromise) {
    return apexChartsPromise;
  }
  apexChartsPromise = new Promise((resolve) => {
    const script = document.createElement('script');
    script.src = document.body?.dataset?.apexchartsSrc || '/assets/vendor/apexcharts.min.js';
    const nonceEl = document.querySelector('script[nonce]');
    const nonce = nonceEl?.nonce || nonceEl?.getAttribute('nonce');
    if (nonce) {
      script.nonce = nonce;
    }
    script.onload = () => resolve(window.ApexCharts || null);
    script.onerror = () => resolve(null);
    document.head.appendChild(script);
  });
  return apexChartsPromise;
}

export function initCharts() {
  const containers = Array.from(document.querySelectorAll('[data-pc-chart]'));
  if (containers.length === 0) {
    return;
  }

  const instances = [];
  const rendered = new WeakSet();

  const renderContainer = async (el) => {
    if (rendered.has(el)) {
      return;
    }
    rendered.add(el);
    const Apex = await ensureApexChartsLoaded();
    if (typeof Apex !== 'function') {
      return;
    }
    const raw = el.getAttribute('data-chart-config');
    if (!raw) {
      return;
    }
    try {
      const cfg = JSON.parse(raw);
      const chart = new Apex(el, cfg);
      chart.render();
      instances.push(chart);
    } catch {
      // Ignore malformed JSON config
    }
  };

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            io.unobserve(entry.target);
            renderContainer(entry.target);
          }
        });
      },
      { rootMargin: '120px 0px' }
    );
    containers.forEach((el) => io.observe(el));
  } else {
    containers.forEach((el) => renderContainer(el));
  }

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
