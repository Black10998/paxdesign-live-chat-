(function () {
  'use strict';

  var LOGO_LINK_SELECTORS = [
    'header#dtr-main-header .dtr-header-left a.dtr-logo.logo-default',
    '#dtr-responsive-header a.dtr-logo.logo-default',
  ];
  var MAX_RETRIES = 40;
  var RETRY_MS = 100;

  function findLogoRoot(link) {
    return (
      link.querySelector('#pax-isolated-logo.paxlogo-wrap') ||
      link.querySelector('.pax-isolated-logo.paxlogo-wrap') ||
      link.querySelector('.paxlogo-wrap')
    );
  }

  function ensurePaxShadowFilter(svg) {
    if (!svg) {
      return null;
    }
    if (svg.dataset.pdxPaxShadowFilter) {
      return svg.dataset.pdxPaxShadowFilter;
    }

    var filterId = 'pdx-pax-shadow-' + Math.random().toString(36).slice(2, 8);
    var defs = svg.querySelector('defs');
    if (!defs) {
      defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      svg.insertBefore(defs, svg.firstChild);
    }

    var filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
    filter.setAttribute('id', filterId);
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
    shadow.setAttribute('flood-opacity', '0.75');
    filter.appendChild(shadow);
    defs.appendChild(filter);

    svg.dataset.pdxPaxShadowFilter = filterId;
    return filterId;
  }

  function applyPaxShadow(link) {
    if (!link || link.dataset.pdxPaxShadow === '1') {
      return true;
    }

    var root = findLogoRoot(link);
    if (!root) {
      return false;
    }

    var svg = root.querySelector('svg.paxlogo-svg');
    var pax = root.querySelector('.paxlogo-pax');
    if (!svg || !pax) {
      return false;
    }

    var filterId = ensurePaxShadowFilter(svg);
    if (filterId) {
      pax.setAttribute('filter', 'url(#' + filterId + ')');
    }

    link.dataset.pdxPaxShadow = '1';
    return true;
  }

  function scan() {
    var done = true;
    LOGO_LINK_SELECTORS.forEach(function (selector) {
      document.querySelectorAll(selector).forEach(function (link) {
        if (!applyPaxShadow(link)) {
          done = false;
        }
      });
    });
    return done;
  }

  function boot() {
    if (scan()) {
      return;
    }

    var attempts = 0;
    var timer = window.setInterval(function () {
      attempts += 1;
      if (scan() || attempts >= MAX_RETRIES) {
        window.clearInterval(timer);
      }
    }, RETRY_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
