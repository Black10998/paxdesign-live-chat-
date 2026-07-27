/**
 * Apple homepage — reveals, anchors, Sign Up trigger.
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function initReveal() {
    var nodes = document.querySelectorAll('[data-ph-reveal]');
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

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
  }

  function openAuthSignup(email) {
    var headerBtn = document.querySelector(
      '#pdx-auth-bar .pdx-auth-signup-btn, .pdx-auth-signup-btn, [data-pdx-auth-open], [data-auth-open="register"]'
    );
    if (headerBtn) {
      headerBtn.click();
      if (email) {
        window.setTimeout(function () {
          var input = document.querySelector(
            '#pdx-auth-overlay input[type="email"], #pdx-auth-overlay input[name="email"], .pdx-auth-form input[type="email"]'
          );
          if (input) {
            input.value = email;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
          }
        }, 180);
      }
      return true;
    }
    var overlay = document.getElementById('pdx-auth-overlay');
    if (overlay) {
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.classList.add('pdx-auth-open');
      return true;
    }
    return false;
  }

  function initSignup() {
    document.querySelectorAll('[data-pax-signup]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!openAuthSignup()) {
          window.location.href = '/kontakt/';
        }
      });
    });

    document.querySelectorAll('[data-pax-signup-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var input = form.querySelector('input[type="email"], input[name="email"]');
        var note = form.querySelector('[data-pax-signup-note]');
        var email = input ? String(input.value || '').trim() : '';

        if (!email || !isValidEmail(email)) {
          if (note) {
            note.hidden = false;
            note.textContent = 'Bitte geben Sie eine gültige E‑Mail-Adresse ein.';
          }
          if (input) {
            input.focus();
          }
          return;
        }

        if (note) {
          note.hidden = true;
          note.textContent = '';
        }

        if (!openAuthSignup(email)) {
          window.location.href = '/kontakt/';
        }
      });
    });
  }

  function boot() {
    initReveal();
    initSignup();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
