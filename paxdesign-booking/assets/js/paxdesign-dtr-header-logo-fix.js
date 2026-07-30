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
      stop.setAttribute('stop-color', BRAND_GREEN);
    });
  }

  function disableShine(pax) {
    if (!pax) {
      return;
    }
    var shine = pax.querySelector('.paxlogo-pax-shine');
    if (!shine) {
      return;
    }
    shine.setAttribute('opacity', '0');
    shine.setAttribute('fill', BRAND_GREEN);
    shine.style.setProperty('opacity', '0', 'important');
    shine.style.setProperty('animation', 'none', 'important');
    shine.style.setProperty('visibility', 'hidden', 'important');
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

    var existing = defs.querySelector('#' + FILTER_ID);
    if (existing) {
      existing.remove();
    }

    var filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
    filter.setAttribute('id', FILTER_ID);
    filter.setAttribute('x', '-60%');
    filter.setAttribute('y', '-60%');
    filter.setAttribute('width', '220%');
    filter.setAttribute('height', '220%');
    filter.setAttribute('color-interpolation-filters', 'sRGB');

    var shadow = document.createElementNS('http://www.w3.org/2000/svg', 'feDropShadow');
    shadow.setAttribute('dx', '0');
    shadow.setAttribute('dy', '2');
    shadow.setAttribute('stdDeviation', '2');
    shadow.setAttribute('flood-color', '#000000');
    shadow.setAttribute(
      'flood-opacity',
      document.body.classList.contains('dtr-apple-sticky-header') ? '0.88' : '0.8'
    );
    filter.appendChild(shadow);
    defs.appendChild(filter);
    return FILTER_ID;
  }

  function enhanceDesktopLogo(link) {
    if (!link) {
      return false;
    }

    var root = link.querySelector('#pax-isolated-logo.paxlogo-wrap');
    if (!root) {
      return false;
    }

    var svg = root.querySelector('svg.paxlogo-svg');
    var pax = root.querySelector('.paxlogo-pax');
    if (!svg || !pax) {
      return false;
    }

    fixGradientStops(svg);

    var paxText = pax.querySelector('text');
    if (paxText) {
      paxText.setAttribute('fill', BRAND_GREEN);
    }

    disableShine(pax);

    var filterId = ensureShadowFilter(svg);
    if (filterId) {
      pax.setAttribute('filter', 'url(#' + filterId + ')');
    }

    link.dataset.pdxDesktopPaxFix = '1';
    root.dataset.pdxDesktopPaxFix = '1';
    pax.classList.add('pdx-header-pax-shadow');
    return true;
  }

  function scan() {
    document.querySelectorAll(DESKTOP_SELECTOR).forEach(enhanceDesktopLogo);
  }

  function boot() {
    scan();

    var observer = new MutationObserver(function () {
      scan();
    });
    observer.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });

    var ticks = 0;
    var interval = window.setInterval(function () {
      scan();
      ticks += 1;
      if (ticks >= 120) {
        window.clearInterval(interval);
        observer.disconnect();
        scan();
      }
    }, 500);

    window.addEventListener('load', scan, { passive: true });
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) {
        scan();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
