/**
 * PAXDesign Verified Badge — shield + checkmark (server-gated via verified flag).
 */
(function (global) {
  'use strict';

  var TIPS = {
    account: 'Verified Account',
    email: 'Email Verified',
  };

  function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function svgMarkup(size) {
    return '<svg class="pdx-vb" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
      '<path class="pdx-vb__shield" d="M12 2.4l7.2 2.8v5.8c0 4.9-3.1 9.2-7.2 10.8-4.1-1.6-7.2-5.9-7.2-10.8V5.2L12 2.4z"/>' +
      '<path class="pdx-vb__check" d="M8.2 12.1l2.1 2.1 5.5-5.6"/>' +
    '</svg>';
  }

  function tooltipForContext(context) {
    return TIPS[context] || TIPS.email;
  }

  function render(verified, opts) {
    opts = opts || {};
    if (!verified) return '';

    var size = Math.max(12, Math.min(24, opts.size || 16));
    var context = opts.context || 'email';
    var tip = opts.tooltip || tooltipForContext(context);
    var cls = 'pdx-verified-badge' + (opts.inline ? ' pdx-verified-badge--inline' : '');
    if (opts.className) cls += ' ' + opts.className;

    return '<span class="' + cls + '" role="img" tabindex="0" aria-label="' + escHtml(tip) + '" data-pdx-tip="' + escHtml(tip) + '">' +
      svgMarkup(size) +
    '</span>';
  }

  function nameWithBadge(name, verified, opts) {
    opts = opts || {};
    opts.context = opts.context || 'account';
    return '<span class="pdx-name-with-badge">' +
      escHtml(name || 'Account') +
      render(verified, opts) +
    '</span>';
  }

  global.PDXVerifiedBadge = {
    render: render,
    nameWithBadge: nameWithBadge,
    tooltipForContext: tooltipForContext,
  };
})(typeof window !== 'undefined' ? window : this);
