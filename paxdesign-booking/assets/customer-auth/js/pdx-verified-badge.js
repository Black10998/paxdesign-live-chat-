/**
 * PAXDesign Verified Badge — LinkedIn-style shield + checkmark (account dashboard only).
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
      '<path class="pdx-vb__shield" d="M12 1.8l8.2 3.2v6.1c0 5.4-3.5 10.2-8.2 11.9-4.7-1.7-8.2-6.5-8.2-11.9V5L12 1.8z"/>' +
      '<path class="pdx-vb__check" d="M8.4 11.9l2.3 2.3 5.1-5.2"/>' +
    '</svg>';
  }

  function tooltipForContext(context) {
    return TIPS[context] || TIPS.email;
  }

  function render(verified, opts) {
    opts = opts || {};
    if (!verified) return '';

    var size = Math.max(12, Math.min(20, opts.size || 15));
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
