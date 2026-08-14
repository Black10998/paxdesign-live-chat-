/**
 * Apple Leistungen page — reveal, local nav, header offset.
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function headerOffset() {
    var header = document.getElementById('dtr-main-header') || document.querySelector('header#dtr-header-global, #dtr-header-global');
    var height = header ? Math.round(header.getBoundingClientRect().height) : 52;
    if (height < 44) {
      height = 52;
    }
    document.documentElement.style.setProperty('--ls-header-offset', height + 'px');
    if (document.body) {
      document.body.style.setProperty('--ls-header-offset', height + 'px');
    }
    var page = document.querySelector('.pax-ls');
    if (page) {
      page.style.setProperty('--ls-header-offset', height + 'px');
    }
  }

  function initReveal() {
    var nodes = document.querySelectorAll('[data-ls-reveal]');
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
      { root: null, rootMargin: '0px 0px -8% 0px', threshold: 0.12 }
    );
    nodes.forEach(function (el) {
      io.observe(el);
    });
  }

  function initAnchors() {
    document.querySelectorAll('.pax-ls-localnav a[href^="#"], .pax-ls-skip').forEach(function (link) {
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

  function initSpy() {
    var links = Array.prototype.slice.call(document.querySelectorAll('.pax-ls-localnav__links a[href^="#"]'));
    var sections = links
      .map(function (link) {
        return document.querySelector(link.getAttribute('href'));
      })
      .filter(Boolean);
    if (!links.length || !sections.length || !('IntersectionObserver' in window)) {
      return;
    }

    var current = '';
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            current = '#' + entry.target.id;
          }
        });
        links.forEach(function (link) {
          if (link.getAttribute('href') === current) {
            link.classList.add('is-active');
          } else {
            link.classList.remove('is-active');
          }
        });
      },
      { root: null, rootMargin: '-35% 0px -55% 0px', threshold: 0 }
    );
    sections.forEach(function (section) {
      io.observe(section);
    });
  }

  function boot() {
    headerOffset();
    initReveal();
    initAnchors();
    initSpy();
    window.addEventListener('resize', headerOffset, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
