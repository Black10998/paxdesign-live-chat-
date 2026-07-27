/**
 * Apple product pages — reveal, anchors, and Softwareentwicklung extras.
 * Scoped; does not touch global header scroll logic.
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function initReveal() {
    var nodes = document.querySelectorAll('[data-aap-reveal]');
    if (!nodes.length) {
      return;
    }

    if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
      nodes.forEach(function (el) {
        el.classList.add('is-in');
      });
      return;
    }

    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
          }
        });
      },
      {
        root: null,
        rootMargin: '0px 0px -8% 0px',
        threshold: 0.12
      }
    );

    nodes.forEach(function (el) {
      io.observe(el);
    });
  }

  function initAnchors() {
    document.querySelectorAll('a.pax-aap-btn[href^="#"]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        var id = link.getAttribute('href');
        if (!id || id === '#') {
          return;
        }
        var target = document.querySelector(id);
        if (!target) {
          return;
        }
        e.preventDefault();
        target.scrollIntoView({
          behavior: prefersReducedMotion() ? 'auto' : 'smooth',
          block: 'start'
        });
      });
    });
  }

  function initSoftwareOrbit() {
    var orbit = document.querySelector('[data-sw-orbit]');
    if (!orbit) {
      return;
    }

    var nodes = orbit.querySelectorAll('[data-sw-node]');
    if (prefersReducedMotion()) {
      orbit.classList.add('is-ready');
      return;
    }

    nodes.forEach(function (node, index) {
      node.style.transitionDelay = 120 + index * 90 + 'ms';
    });

    requestAnimationFrame(function () {
      orbit.classList.add('is-ready');
    });
  }

  function initSoftwareMarquee() {
    var track = document.querySelector('[data-sw-marquee]');
    if (!track) {
      return;
    }

    var ribbon = track.closest('.pax-sw-ribbon');
    if (!ribbon) {
      return;
    }

    ribbon.addEventListener('mouseenter', function () {
      track.classList.add('is-paused');
    });
    ribbon.addEventListener('mouseleave', function () {
      track.classList.remove('is-paused');
    });
  }

  function boot() {
    initReveal();
    initAnchors();
    initSoftwareOrbit();
    initSoftwareMarquee();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
