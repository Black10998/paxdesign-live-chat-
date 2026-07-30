(function () {
  'use strict';

  var DESKTOP_SELECTOR = 'header#dtr-main-header .dtr-header-left a.dtr-logo.logo-default';
  var FILTER_ID = 'pdx-header-pax-shadow';
  var BRAND_GREEN = '#CCFF00';

  function fixGradientStops(svg) {
    if (!svg) {
      return;
    }
    svg.querySelectorAll('linearGradient stop').forEach(function (stop) {
      var color = (stop.getAttribute('stop-color') || '').toUpperCase();
      if (color === '#BFFF00') {
        stop.setAttribute('stop-color', BRAND_GREEN);
      }
    });
  }

  function ensureShadowFilter(svg) {
    if (!svg) {
      return null;
    }
    var defs = svg.querySelector('defs');
    if (!defs) {
      defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      svg.insertBefore(defs, svg.firstChild);
    }
    if (defs.querySelector('#' + FILTER_ID)) {
      return FILTER_ID;
    }

    var filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
    filter.setAttribute('id', FILTER_ID);
    filter.setAttribute('x', '-50%');
    filter.setAttribute('y', '-50%');
    filter.setAttribute('width', '200%');
    filter.setAttribute('height', '200%');
    filter.setAttribute('color-interpolation-filters', 'sRGB');

    var shadow = document.createElementNS('http://www.w3.org/2000/svg', 'feDropShadow');
    shadow.setAttribute('dx', '0');
    shadow.setAttribute('dy', '1.5');
    shadow.setAttribute('stdDeviation', '1.35');
    shadow.setAttribute('flood-color', '#000000');
    var opacity = document.body.classList.contains('dtr-apple-sticky-header') ? '0.78' : '0.72';
    shadow.setAttribute('flood-opacity', opacity);
    filter.appendChild(shadow);
    defs.appendChild(filter);
    return FILTER_ID;
  }

  function enhanceDesktopLogo(link) {
    if (!link || link.dataset.pdxDesktopPaxFix === '1') {
      return;
    }

    var root = link.querySelector('#pax-isolated-logo.paxlogo-wrap');
    if (!root) {
      return;
    }

    link.dataset.pdxDesktopPaxFix = '1';

    var svg = root.querySelector('svg.paxlogo-svg');
    var pax = root.querySelector('.paxlogo-pax');
    if (!svg || !pax) {
      return;
    }

    fixGradientStops(svg);

    var paxText = pax.querySelector('text');
    if (paxText) {
      paxText.setAttribute('fill', BRAND_GREEN);
    }

    var filterId = ensureShadowFilter(svg);
    if (filterId) {
      pax.setAttribute('filter', 'url(#' + filterId + ')');
      pax.classList.add('pdx-header-pax-shadow');
    }
  }

  function scan() {
    document.querySelectorAll(DESKTOP_SELECTOR).forEach(enhanceDesktopLogo);
  }

  function boot() {
    scan();
    var observer = new MutationObserver(scan);
    observer.observe(document.documentElement, { childList: true, subtree: true });
    window.setTimeout(function () {
      observer.disconnect();
      scan();
    }, 10000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
