/**
 * Apple-inspired sticky header — desktop scroll-linked compacting.
 * Progressively shrinks the glass bar as the page scrolls (no hard snap).
 */
(function ($) {
  'use strict';

  var MQ = window.matchMedia('(min-width: 993px)');
  var RANGE = 110;
  var HEIGHT_EXPANDED = 52;
  var HEIGHT_COMPACT = 44;
  var LOGO_SCALE_COMPACT = 0.9;
  var BTN_SCALE_COMPACT = 0.96;

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

  /* Smooth ease-out so early scroll moves gently, then settles. */
  function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
  }

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function setProgress(progress, compact) {
    if (!root) {
      return;
    }

    var height = lerp(HEIGHT_EXPANDED, HEIGHT_COMPACT, progress);
    var logoScale = lerp(1, LOGO_SCALE_COMPACT, progress);
    var btnScale = lerp(1, BTN_SCALE_COMPACT, progress);
    var shade = progress;

    root.style.setProperty('--dtr-apple-header-progress', String(progress));
    root.style.setProperty('--dtr-apple-header-live-height', height.toFixed(2) + 'px');
    root.style.setProperty('--dtr-apple-header-logo-scale', logoScale.toFixed(4));
    root.style.setProperty('--dtr-apple-header-btn-scale', btnScale.toFixed(4));
    root.style.setProperty('--dtr-apple-header-shade', shade.toFixed(4));

    $body.toggleClass('dtr-apple-header-scrolled', compact);
    $header.toggleClass('is-scrolled', compact);

    if ($responsive.length) {
      $responsive.toggleClass('is-scrolled', compact);
    }
  }

  function resetMobileProgress() {
    if (!root) {
      return;
    }
    root.style.removeProperty('--dtr-apple-header-progress');
    root.style.removeProperty('--dtr-apple-header-live-height');
    root.style.removeProperty('--dtr-apple-header-logo-scale');
    root.style.removeProperty('--dtr-apple-header-btn-scale');
    root.style.removeProperty('--dtr-apple-header-shade');
  }

  function syncState() {
    if (!$header || !$header.length) {
      ticking = false;
      return;
    }

    var y = $win.scrollTop() || 0;

    if (!MQ.matches) {
      // Mobile keeps a simple compact threshold; no progressive desktop morph.
      resetMobileProgress();
      var mobileCompact = y > 18;
      $body.toggleClass('dtr-apple-header-scrolled', mobileCompact);
      $header.toggleClass('is-scrolled', mobileCompact);
      $header.removeClass('header-fixed');
      if ($responsive.length) {
        $responsive.toggleClass('is-scrolled', mobileCompact);
      }
      ticking = false;
      return;
    }

    var raw = prefersReducedMotion() ? (y > RANGE * 0.35 ? 1 : 0) : clamp01(y / RANGE);
    var progress = prefersReducedMotion() ? raw : easeOutCubic(raw);
    var compact = progress > 0.55;

    setProgress(progress, compact);

    // Legacy hook without menu color class swapping (avoids abrupt ink flashes).
    if ($body.hasClass('show-onscroll')) {
      $header.toggleClass('header-fixed', compact);
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

    // Seed defaults before first paint sync.
    root.style.setProperty('--dtr-apple-header-progress', '0');
    root.style.setProperty('--dtr-apple-header-live-height', HEIGHT_EXPANDED + 'px');
    root.style.setProperty('--dtr-apple-header-logo-scale', '1');
    root.style.setProperty('--dtr-apple-header-btn-scale', '1');
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
