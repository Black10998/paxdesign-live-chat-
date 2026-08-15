/**
 * Apple Referenzen page — rails, reveal, sticky nav, filters, reduced motion.
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function headerOffset() {
    var header = document.getElementById('dtr-main-header') || document.querySelector('#dtr-header-global');
    var height = header ? Math.round(header.getBoundingClientRect().height) : 52;
    if (height < 44) {
      height = 52;
    }
    var value = height + 'px';
    document.documentElement.style.setProperty('--rf-header-offset', value);
    if (document.body) {
      document.body.style.setProperty('--rf-header-offset', value);
    }
    var page = document.querySelector('.pax-rf');
    if (page) {
      page.style.setProperty('--rf-header-offset', value);
    }
  }

  function initReveal() {
    var nodes = document.querySelectorAll('[data-rf-reveal], [data-rf-stage]');
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
      { root: null, rootMargin: '0px 0px -10% 0px', threshold: 0.12 }
    );
    nodes.forEach(function (el) {
      io.observe(el);
    });
  }

  function initAnchors() {
    document.querySelectorAll('.pax-rf-localnav a[href^="#"], .pax-rf-skip, .pax-rf-hl[href^="#"]').forEach(function (link) {
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
    var links = Array.prototype.slice.call(
      document.querySelectorAll('.pax-rf-localnav__links a[href^="#"]')
    );
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
      { root: null, rootMargin: '-30% 0px -55% 0px', threshold: 0 }
    );
    sections.forEach(function (section) {
      io.observe(section);
    });
  }

  function initRails() {
    document.querySelectorAll('[data-rf-rail]').forEach(function (rail) {
      var name = rail.getAttribute('data-rf-rail');
      var nav = document.querySelector('[data-rf-rail-nav="' + name + '"]');
      if (!nav) {
        return;
      }
      var prev = nav.querySelector('[data-rf-rail-prev]');
      var next = nav.querySelector('[data-rf-rail-next]');
      var amount = function () {
        var card = rail.querySelector('.pax-rf-hl, .pax-rf-shot');
        if (card) {
          return Math.round(card.getBoundingClientRect().width + 16);
        }
        return Math.max(320, Math.round(rail.clientWidth * 0.86));
      };
      var go = function (dir) {
        rail.scrollBy({
          left: dir * amount(),
          behavior: prefersReducedMotion() ? 'auto' : 'smooth'
        });
      };
      if (prev) {
        prev.addEventListener('click', function () {
          go(-1);
        });
      }
      if (next) {
        next.addEventListener('click', function () {
          go(1);
        });
      }
    });
  }

  function initFilters() {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-rf-filter]'));
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-rf-cats]'));
    if (!tabs.length || !items.length) {
      return;
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var filter = tab.getAttribute('data-rf-filter') || 'all';
        tabs.forEach(function (btn) {
          var active = btn === tab;
          btn.classList.toggle('is-active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        items.forEach(function (item) {
          var cats = ' ' + (item.getAttribute('data-rf-cats') || '') + ' ';
          var show = filter === 'all' || cats.indexOf(' ' + filter + ' ') !== -1;
          item.classList.toggle('is-hidden', !show);
          if (show) {
            item.removeAttribute('hidden');
          } else {
            item.setAttribute('hidden', 'hidden');
          }
        });
      });
    });
  }

  function boot() {
    headerOffset();
    initReveal();
    initAnchors();
    initSpy();
    initRails();
    initFilters();
    window.addEventListener('resize', headerOffset, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
