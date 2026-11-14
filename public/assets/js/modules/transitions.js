/**
 * View Transitions API & Link Prefetching on touchstart/mouseenter (§6.6, §6.7).
 */
const prefetchedUrls = new Set();

function prefetchUrl(href) {
  if (!href || prefetchedUrls.has(href)) {
    return;
  }
  try {
    const url = new URL(href, window.location.origin);
    if (url.origin !== window.location.origin || url.pathname.startsWith('/logout')) {
      return;
    }
    prefetchedUrls.add(url.href);
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = url.href;
    link.as = 'document';
    document.head.appendChild(link);
  } catch {
    // Ignore invalid URLs
  }
}

export function initTransitions() {
  const handleHoverOrTouch = (e) => {
    const target = e.target;
    if (!(target instanceof Element)) {
      return;
    }
    const anchor = target.closest('a[href]');
    if (!(anchor instanceof HTMLAnchorElement)) {
      return;
    }
    const href = anchor.getAttribute('href') || '';
    if (href.startsWith('/') && !href.startsWith('//') && !anchor.hasAttribute('data-no-prefetch')) {
      prefetchUrl(href);
    }
  };

  document.addEventListener('touchstart', handleHoverOrTouch, { passive: true });
  document.addEventListener('mouseenter', handleHoverOrTouch, { capture: true, passive: true });
}
