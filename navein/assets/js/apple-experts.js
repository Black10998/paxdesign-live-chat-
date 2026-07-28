/**
 * Apple Unsere Experten — section reveal.
 */
(function () {
  'use strict';

  var root = document.getElementById('pax-apple-experts');
  if (!root) return;

  var sections = root.querySelectorAll('[data-experts-reveal]');
  if (!sections.length) return;

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function revealAll() {
    sections.forEach(function (section) {
      section.classList.add('is-visible');
    });
  }

  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealAll();
    return;
  }

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
  );

  sections.forEach(function (section) {
    observer.observe(section);
  });
})();
