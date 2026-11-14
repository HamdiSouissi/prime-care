/**
 * Collapsing iOS Large Title navigation bar via IntersectionObserver (§6.5).
 */
export function initNavbar() {
  const navbar = document.querySelector('.pc-navbar');
  const largeHeader = document.querySelector('.pc-large-header');

  if (!navbar || !largeHeader) {
    return;
  }

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          navbar.classList.toggle('is-scrolled', !entry.isIntersecting);
        }
      },
      { threshold: 0.15, rootMargin: '-48px 0px 0px 0px' }
    );
    observer.observe(largeHeader);
  }
}
