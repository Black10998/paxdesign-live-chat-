/**
 * Apple Impressum — subtle section reveal + TOC active state.
 */
(function () {
  'use strict';

  var root = document.getElementById('pax-apple-impressum');
  if (!root) return;

  var sections = root.querySelectorAll('[data-legal-section]');
  var navLinks = root.querySelectorAll('.pax-legal__nav-list a');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function revealAll() {
    sections.forEach(function (section) {
      section.classList.add('is-visible');
    });
  }

  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealAll();
  } else {
    var revealObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);
          }
        });
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
    );
    sections.forEach(function (section) {
      revealObserver.observe(section);
    });
  }

  if (!navLinks.length || !('IntersectionObserver' in window)) return;

  var linkById = {};
  navLinks.forEach(function (link) {
    var id = (link.getAttribute('href') || '').replace(/^#/, '');
    if (id) linkById[id] = link;
  });

  var activeId = '';
  function setActive(id) {
    if (!id || id === activeId) return;
    activeId = id;
    navLinks.forEach(function (link) {
      link.classList.toggle('is-active', link === linkById[id]);
    });
  }

  var spyObserver = new IntersectionObserver(
    function (entries) {
      var visible = entries
        .filter(function (entry) {
          return entry.isIntersecting;
        })
        .sort(function (a, b) {
          return a.boundingClientRect.top - b.boundingClientRect.top;
        });
      if (visible[0] && visible[0].target.id) {
        setActive(visible[0].target.id);
      }
    },
    {
      rootMargin: '-20% 0px -60% 0px',
      threshold: [0, 0.25, 0.5],
    }
  );

  sections.forEach(function (section) {
    spyObserver.observe(section);
  });

  navLinks.forEach(function (link) {
    link.addEventListener('click', function () {
      var id = (link.getAttribute('href') || '').replace(/^#/, '');
      if (id) setActive(id);
    });
  });
})();
