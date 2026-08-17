/**
 * Apple footer — reveals, mobile accordions, GitHub modal.
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function initReveal(root) {
    var nodes = root.querySelectorAll('[data-pax-af-reveal]');
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

  function initAccordions(root) {
    var mq = window.matchMedia('(max-width: 900px)');
    var cols = root.querySelectorAll('[data-pax-af-acc]');

    function syncDesktop() {
      cols.forEach(function (col) {
        var btn = col.querySelector('.pax-af__col-toggle');
        if (!mq.matches) {
          col.classList.remove('is-open');
          if (btn) {
            btn.setAttribute('aria-expanded', 'false');
          }
        }
      });
    }

    cols.forEach(function (col) {
      var btn = col.querySelector('.pax-af__col-toggle');
      if (!btn) {
        return;
      }
      btn.addEventListener('click', function () {
        if (!mq.matches) {
          return;
        }
        var open = !col.classList.contains('is-open');
        cols.forEach(function (other) {
          if (other !== col) {
            other.classList.remove('is-open');
            var ob = other.querySelector('.pax-af__col-toggle');
            if (ob) {
              ob.setAttribute('aria-expanded', 'false');
            }
          }
        });
        col.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });

    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncDesktop);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncDesktop);
    }
  }

  function initGithubModal(root) {
    var openBtn = root.querySelector('[data-pax-github-open]');
    var modal = root.querySelector('#pax-af-github-modal');
    if (!openBtn || !modal) {
      return;
    }

    var lastFocus = null;
    var closeEls = modal.querySelectorAll('[data-pax-github-close]');

    function openModal() {
      lastFocus = document.activeElement;
      modal.hidden = false;
      modal.removeAttribute('hidden');
      modal.setAttribute('aria-hidden', 'false');
      modal.classList.add('is-open');
      document.body.classList.add('pax-af-modal-open');
      var closeBtn = modal.querySelector('.pax-af-github-modal__close');
      if (closeBtn) {
        closeBtn.focus();
      }
    }

    function closeModal() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('pax-af-modal-open');
      window.setTimeout(function () {
        if (!modal.classList.contains('is-open')) {
          modal.hidden = true;
          modal.setAttribute('hidden', 'hidden');
        }
      }, prefersReducedMotion() ? 0 : 280);
      if (lastFocus && typeof lastFocus.focus === 'function') {
        try {
          lastFocus.focus();
        } catch (e) {
          // ignore
        }
      }
    }

    openBtn.addEventListener('click', function (e) {
      e.preventDefault();
      openModal();
    });

    closeEls.forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        closeModal();
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  }

  function boot() {
    document.body.classList.add('dtr-apple-footer');
    var root = document.querySelector('[data-pax-af]');
    if (!root) {
      return;
    }
    initReveal(root);
    initAccordions(root);
    initGithubModal(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
