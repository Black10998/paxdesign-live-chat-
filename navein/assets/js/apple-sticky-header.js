/**
 * Apple-inspired sticky header — continuously visible, constant size.
 * Desktop: header is visible on load and stays visible while scrolling.
 * Glass densifies with scroll; no hide/show, no shrink, no slideInDown pop.
 */
(function ($) {
  'use strict';

  var MQ = window.matchMedia('(min-width: 993px)');
  var RANGE = 80;

  var ticking = false;
  var $win = $(window);
  var $body;
  var $header;
  var $responsive;
  var root;

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function clamp01(n) {
    return n < 0 ? 0 : n > 1 ? 1 : n;
  }

  function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
  }

  function setProgress(progress, scrolled) {
    if (!root) {
      return;
    }

    root.style.setProperty('--dtr-apple-header-progress', String(progress));
    root.style.setProperty('--dtr-apple-header-shade', progress.toFixed(4));
    /* Always fully in view — never translate above the viewport. */
    root.style.setProperty('--dtr-apple-header-ty', '0px');

    $body.toggleClass('dtr-apple-header-scrolled', scrolled);
    $header.toggleClass('is-scrolled', scrolled);

    if ($responsive.length) {
      $responsive.toggleClass('is-scrolled', scrolled);
    }
  }

  function resetMobileProgress() {
    if (!root) {
      return;
    }
    root.style.removeProperty('--dtr-apple-header-progress');
    root.style.removeProperty('--dtr-apple-header-shade');
    root.style.removeProperty('--dtr-apple-header-ty');
  }

  function syncState() {
    if (!$header || !$header.length) {
      ticking = false;
      return;
    }

    var y = $win.scrollTop() || 0;

    if (!MQ.matches) {
      resetMobileProgress();
      var mobileScrolled = y > 18;
      $body.toggleClass('dtr-apple-header-scrolled', mobileScrolled);
      $header.toggleClass('is-scrolled', mobileScrolled);
      $header.removeClass('header-fixed');
      if ($responsive.length) {
        $responsive.toggleClass('is-scrolled', mobileScrolled);
      }
      ticking = false;
      return;
    }

    var progress;

    if (prefersReducedMotion()) {
      progress = y > 12 ? 1 : 0;
    } else {
      progress = easeOutCubic(clamp01(y / RANGE));
    }

    var scrolled = progress > 0.45;
    setProgress(progress, scrolled);

    /*
     * Keep legacy header-fixed in sync for theme hooks, but Apple CSS
     * forces relative positioning + animation:none so slideInDown never runs.
     * Apply early so the bar never jumps from relative → fixed → animated.
     */
    if ($body.hasClass('show-onscroll')) {
      $header.toggleClass('header-fixed', y > 0 || scrolled);
    }

    ticking = false;
  }

  function onScroll() {
    if (ticking) {
      return;
    }
    ticking = true;
    window.requestAnimationFrame(syncState);
  }

  function boot() {
    $body = $(document.body);
    $header = $('#dtr-header-global');
    $responsive = $('#dtr-responsive-header');
    root = document.documentElement;

    if (!$header.length) {
      return;
    }

    $body.addClass('dtr-apple-sticky-header');
    $header.addClass('dtr-apple-bar');

    root.style.setProperty('--dtr-apple-header-progress', '0');
    root.style.setProperty('--dtr-apple-header-shade', '0');
    root.style.setProperty('--dtr-apple-header-ty', '0px');

    syncState();
    $win.off('scroll.appleSticky resize.appleSticky');
    $win.on('scroll.appleSticky', onScroll);
    $win.on('resize.appleSticky', onScroll);

    if (typeof MQ.addEventListener === 'function') {
      MQ.addEventListener('change', onScroll);
    } else if (typeof MQ.addListener === 'function') {
      MQ.addListener(onScroll);
    }
  }

  $(boot);
})(jQuery);
