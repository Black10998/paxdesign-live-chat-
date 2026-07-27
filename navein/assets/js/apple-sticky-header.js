/**
 * Apple-inspired sticky header behavior.
 * Continuous sticky glass bar with compact-on-scroll (no slideInDown jump).
 */
(function ($) {
  'use strict';

  var COMPACT_AT = 18;
  var MQ = window.matchMedia('(min-width: 993px)');
  var ticking = false;
  var $win = $(window);
  var $body;
  var $header;
  var $nav;
  var $responsive;

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function syncState() {
    if (!$header || !$header.length) {
      return;
    }

    var y = $win.scrollTop() || 0;
    var compact = y > COMPACT_AT;

    $body.toggleClass('dtr-apple-header-scrolled', compact);
    $header.toggleClass('is-scrolled', compact);

    // Keep legacy theme hooks in sync, but CSS disables slideInDown/fixed jump.
    if (MQ.matches && $body.hasClass('show-onscroll')) {
      $header.toggleClass('header-fixed', compact);
      if ($nav.length) {
        $nav.toggleClass('dtr-menu-alt', compact);
        $nav.toggleClass('dtr-menu-default', !compact);
      }
    }

    if ($responsive.length) {
      $responsive.toggleClass('is-scrolled', compact);
    }

    ticking = false;
  }

  function onScroll() {
    if (ticking) {
      return;
    }
    ticking = true;
    if (prefersReducedMotion()) {
      syncState();
      return;
    }
    window.requestAnimationFrame(syncState);
  }

  function boot() {
    $body = $(document.body);
    $header = $('#dtr-header-global');
    $nav = $('#dtr-header-global .main-navigation');
    $responsive = $('#dtr-responsive-header');

    if (!$header.length) {
      return;
    }

    $body.addClass('dtr-apple-sticky-header');
    $header.addClass('dtr-apple-bar');

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
