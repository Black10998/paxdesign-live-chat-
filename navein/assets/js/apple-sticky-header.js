/**
 * Apple-inspired sticky header — desktop scroll-linked slide.
 * Full header (logo, nav, search, actions) moves as one unit at constant size.
 *
 * The theme’s delayed slideInDown (nav hidden above, then pop) is disabled.
 * Instead, translateY of the whole bar is scrubbed to scroll so it slides
 * down into the sticky slot progressively — no size shrink.
 */
(function ($) {
  'use strict';

  var MQ = window.matchMedia('(min-width: 993px)');
  var RANGE = 72;
  var HEADER_H = 52;

  var ticking = false;
  var $win = $(window);
  var $body;
  var $header;
  var $mainHeader;
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

  function setReveal(progress, revealed) {
    if (!root) {
      return;
    }

    /*
     * Progressive slide-down of the complete bar:
     * ty = (1 - progress) * -HEADER_H  →  from above viewport into place.
     * Scrubbed to scroll (no 350px delay / CSS keyframe pop).
     */
    var tyPx = (1 - progress) * -HEADER_H;
    var shade = progress;

    root.style.setProperty('--dtr-apple-header-progress', String(progress));
    root.style.setProperty('--dtr-apple-header-ty', tyPx.toFixed(2) + 'px');
    root.style.setProperty('--dtr-apple-header-shade', shade.toFixed(4));

    $body.toggleClass('dtr-apple-header-scrolled', revealed);
    $header.toggleClass('is-scrolled', revealed);
    $header.toggleClass('is-revealed', progress > 0.02);

    if ($mainHeader.length) {
      $mainHeader.toggleClass('is-revealed', progress > 0.02);
    }

    if ($responsive.length) {
      $responsive.toggleClass('is-scrolled', revealed);
    }
  }

  function resetMobileProgress() {
    if (!root) {
      return;
    }
    root.style.removeProperty('--dtr-apple-header-progress');
    root.style.removeProperty('--dtr-apple-header-ty');
    root.style.removeProperty('--dtr-apple-header-shade');
  }

  function syncState() {
    if (!$header || !$header.length) {
      ticking = false;
      return;
    }

    var y = $win.scrollTop() || 0;

    if (!MQ.matches) {
      resetMobileProgress();
      var mobileCompact = y > 18;
      $body.toggleClass('dtr-apple-header-scrolled', mobileCompact);
      $header.toggleClass('is-scrolled', mobileCompact);
      $header.removeClass('header-fixed is-revealed');
      if ($mainHeader.length) {
        $mainHeader.removeClass('is-revealed');
      }
      if ($responsive.length) {
        $responsive.toggleClass('is-scrolled', mobileCompact);
      }
      ticking = false;
      return;
    }

    var progress;

    if (prefersReducedMotion()) {
      progress = y > 0 ? 1 : 0;
    } else {
      progress = easeOutCubic(clamp01(y / RANGE));
    }

    var revealed = progress > 0.55;
    setReveal(progress, revealed);

    if ($body.hasClass('show-onscroll')) {
      $header.toggleClass('header-fixed', revealed);
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
    $mainHeader = $('#dtr-main-header');
    $responsive = $('#dtr-responsive-header');
    root = document.documentElement;

    if (!$header.length) {
      return;
    }

    $body.addClass('dtr-apple-sticky-header');
    $header.addClass('dtr-apple-bar');

    root.style.setProperty('--dtr-apple-header-progress', '0');
    root.style.setProperty('--dtr-apple-header-ty', -HEADER_H + 'px');
    root.style.setProperty('--dtr-apple-header-shade', '0');

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
