/**
 * Cybercrime reporting portal — welcome screen, step workflow, secure submit.
 * Live Chat page context only (no extra chat UI on this page).
 */
(function () {
  'use strict';

  var root = document.querySelector('.pax-ccs-portal');
  var form = document.getElementById('pax-ccs-intake-form');
  var config = window.paxCybercrimeIntake || {};

  if (!root || !form) {
    return;
  }

  var currentStep = 1;
  var phase = 'welcome';
  var welcomeEl = document.getElementById('pax-ccs-welcome');
  var loginGateEl = document.getElementById('pax-ccs-login-gate');
  var workflowEl = document.getElementById('pax-ccs-workflow');
  var localeInput = document.getElementById('pax-ccs-locale');
  var reviewEl = document.getElementById('pax-ccs-review');
  var errorEl = document.getElementById('pax-ccs-form-error');
  var successEl = document.getElementById('pax-ccs-success');
  var refEl = document.getElementById('pax-ccs-ref-value');
  var submitBtn = document.getElementById('pax-ccs-submit');
  var startBtn = document.getElementById('pax-ccs-start');
  var loginContinueBtn = document.getElementById('pax-ccs-login-continue');
  var loginBackBtn = document.getElementById('pax-ccs-login-back');
  var chatBtn = document.getElementById('pax-ccs-chat-support');

  function isLoggedIn() {
    if (config.isLoggedIn === true) {
      return true;
    }
    if (window.PAX_AUTH_CONFIG && window.PAX_AUTH_CONFIG.isLoggedIn) {
      return true;
    }
    return false;
  }

  function loginUrl() {
    if (config.loginUrl) {
      return config.loginUrl;
    }
    if (window.PAX_AUTH_CONFIG && window.PAX_AUTH_CONFIG.accountPageUrl) {
      var resume = window.location.pathname + '?' + (config.resumeParam || 'pdx_ccs_start') + '=1';
      return window.PAX_AUTH_CONFIG.accountPageUrl + '?return_to=' + encodeURIComponent(resume);
    }
    return '/account/';
  }

  function redirectToLogin() {
    var url = loginUrl();
    if (url) {
      window.location.href = url;
    }
  }

  function showLoginGate() {
    setPhase('login-gate');
    window.scrollTo({ top: (loginGateEl || root).offsetTop - 24, behavior: 'smooth' });
  }

  function requestLoginToContinue() {
    if (!requiresLogin() || isLoggedIn()) {
      startReporting();
      return;
    }
    showLoginGate();
  }

  function requiresLogin() {
    return config.requireLogin !== false;
  }

  function resumeParamName() {
    return config.resumeParam || 'pdx_ccs_start';
  }

  function clearResumeParam() {
    try {
      var params = new URLSearchParams(window.location.search);
      if (!params.has(resumeParamName())) {
        return;
      }
      params.delete(resumeParamName());
      var query = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    } catch (e) {}
  }

  function maybeResumeAfterLogin() {
    try {
      var params = new URLSearchParams(window.location.search);
      if (params.get(resumeParamName()) !== '1' || !isLoggedIn()) {
        return;
      }
      clearResumeParam();
      startReporting();
    } catch (e) {}
  }

  function t(key) {
    var strings = {
      ar: {
        identity: 'الهوية',
        incident: 'الحادث',
        evidence: 'الأدلة',
        files: 'ملف(ات)',
        none: '—',
        yes: 'نعم',
        no: 'لا',
      },
      de: {
        identity: 'Identität',
        incident: 'Vorfall',
        evidence: 'Beweise',
        files: 'Datei(en)',
        none: '—',
        yes: 'Ja',
        no: 'Nein',
      },
    };
    var lang = root.getAttribute('data-ccs-lang') || 'ar';
    return (strings[lang] && strings[lang][key]) || key;
  }

  function setPageContext(lang, referenceId) {
    window.PAXdesignPageContext = window.PAXdesignPageContext || {};
    window.PAXdesignPageContext.intent = 'cybercrime-support';
    if (lang) {
      window.PAXdesignPageContext.language = lang;
    }
    if (referenceId) {
      window.PAXdesignPageContext.referenceId = referenceId;
    }
  }

  function openSupportChat(referenceId) {
    var lang = root.getAttribute('data-ccs-lang') || 'ar';
    setPageContext(lang, referenceId || '');
    if (window.PAXdesignChat && typeof window.PAXdesignChat.openForCybercrime === 'function') {
      window.PAXdesignChat.openForCybercrime({
        language: lang,
        referenceId: referenceId || '',
      });
      return;
    }
    if (window.PAXdesignBooking && typeof window.PAXdesignBooking.open === 'function') {
      window.PAXdesignBooking.open();
      return;
    }
    var launcher = document.querySelector('.paxdesign-booking-button');
    if (launcher) {
      launcher.click();
    }
  }

  function setLang(lang) {
    if (lang !== 'ar' && lang !== 'de') {
      lang = 'ar';
    }
    root.setAttribute('data-ccs-lang', lang);
    root.setAttribute('lang', lang);
    root.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
    if (localeInput) {
      localeInput.value = lang;
    }
    root.querySelectorAll('.pax-ccs-t').forEach(function (el) {
      el.hidden = el.getAttribute('data-lang') !== lang;
    });
    root.querySelectorAll('[data-ccs-switch]').forEach(function (btn) {
      var active = btn.getAttribute('data-ccs-switch') === lang;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    updatePlaceholders(lang);
    updateSelectLabels(lang);
    setPageContext(lang, window.PAXdesignPageContext && window.PAXdesignPageContext.referenceId);
    try {
      localStorage.setItem('pax-ccs-lang', lang);
    } catch (e) {}
    if (phase === 'form' && currentStep === 4) {
      renderReview();
    }
  }

  function updatePlaceholders(lang) {
    form.querySelectorAll('[data-placeholder-ar]').forEach(function (input) {
      var ph = input.getAttribute(lang === 'de' ? 'data-placeholder-de' : 'data-placeholder-ar');
      if (ph) {
        input.setAttribute('placeholder', ph);
      }
    });
  }

  function updateSelectLabels(lang) {
    form.querySelectorAll('option[data-label-ar]').forEach(function (opt) {
      var label = opt.getAttribute(lang === 'de' ? 'data-label-de' : 'data-label-ar');
      if (label) {
        opt.textContent = label;
      }
    });
  }

  function getStepEl(step) {
    return form.querySelector('.pax-ccs-portal__step[data-step="' + step + '"]');
  }

  function updateProgress(step) {
    root.querySelectorAll('.pax-ccs-portal__progress-item').forEach(function (item) {
      var n = parseInt(item.getAttribute('data-progress-step'), 10);
      item.classList.toggle('is-active', n === step);
      item.classList.toggle('is-done', n < step);
    });
  }

  function setPhase(nextPhase) {
    phase = nextPhase;
    root.setAttribute('data-ccs-phase', nextPhase);

    if (welcomeEl) {
      welcomeEl.hidden = nextPhase !== 'welcome';
    }
    if (loginGateEl) {
      loginGateEl.hidden = nextPhase !== 'login-gate';
    }
    if (workflowEl) {
      workflowEl.hidden = nextPhase !== 'form';
    }
    if (form) {
      form.hidden = nextPhase !== 'form';
    }
    if (successEl) {
      successEl.hidden = nextPhase !== 'success';
    }
  }

  function showWelcome() {
    setPhase('welcome');
    window.scrollTo({ top: root.offsetTop - 24, behavior: 'smooth' });
  }

  function startReporting() {
    setPhase('form');
    showStep(1);
    var firstField = form.querySelector('#pax-ccs-full-name');
    if (firstField) {
      firstField.focus();
    }
  }

  function showStep(step) {
    form.querySelectorAll('.pax-ccs-portal__step').forEach(function (panel) {
      var n = parseInt(panel.getAttribute('data-step'), 10);
      var active = n === step;
      panel.hidden = !active;
      panel.classList.toggle('is-active', active);
    });
    currentStep = step;
    updateProgress(step);
    if (step === 4) {
      renderReview();
    }
    window.scrollTo({ top: (workflowEl || root).offsetTop - 24, behavior: 'smooth' });
  }

  function markInvalid(field) {
    var wrap = field.closest('.pax-ccs-portal__field') || field.closest('.pax-ccs-portal__declarations');
    if (wrap) {
      wrap.classList.add('is-invalid');
    }
  }

  function clearInvalid() {
    form.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
  }

  function validateStep(step) {
    clearInvalid();
    var panel = getStepEl(step);
    if (!panel) {
      return true;
    }
    var valid = true;
    panel.querySelectorAll('input, select, textarea').forEach(function (field) {
      if (field.type === 'file' || field.name === 'website_trap') {
        return;
      }
      if (field.type === 'checkbox') {
        if (field.required && !field.checked) {
          valid = false;
          markInvalid(field);
        }
        return;
      }
      if (!field.checkValidity()) {
        valid = false;
        markInvalid(field);
      }
    });
    if (!valid) {
      var invalid = panel.querySelector(':invalid');
      if (invalid) {
        invalid.focus();
      }
    }
    return valid;
  }

  function fieldValue(name) {
    var el = form.elements[name];
    if (!el) {
      return '';
    }
    if (el.type === 'checkbox') {
      return el.checked ? t('yes') : t('no');
    }
    return (el.value || '').trim();
  }

  function selectedLabel(selectEl) {
    if (!selectEl || !selectEl.options[selectEl.selectedIndex]) {
      return t('none');
    }
    return selectEl.options[selectEl.selectedIndex].textContent;
  }

  function fileSummary(input) {
    if (!input || !input.files || !input.files.length) {
      return t('none');
    }
    return input.files.length + ' ' + t('files');
  }

  function renderReview() {
    if (!reviewEl) {
      return;
    }
    var rows = [
      { label: document.querySelector('label[for="pax-ccs-full-name"]'), value: fieldValue('full_name') },
      { label: document.querySelector('label[for="pax-ccs-email"]'), value: fieldValue('email') },
      { label: document.querySelector('label[for="pax-ccs-phone"]'), value: fieldValue('phone') },
      { label: document.querySelector('label[for="pax-ccs-country"]'), value: fieldValue('country') },
      { label: document.querySelector('label[for="pax-ccs-category"]'), value: selectedLabel(form.elements.category) },
      { label: document.querySelector('label[for="pax-ccs-incident-date"]'), value: fieldValue('incident_date') + (fieldValue('incident_time') ? ' ' + fieldValue('incident_time') : '') },
      { label: document.querySelector('label[for="pax-ccs-platforms"]'), value: fieldValue('platforms') },
      { label: document.querySelector('label[for="pax-ccs-urgency"]'), value: selectedLabel(form.elements.urgency) },
      { label: document.querySelector('label[for="pax-ccs-financial-loss"]'), value: fieldValue('financial_loss') ? fieldValue('financial_loss') + ' ' + fieldValue('financial_currency') : t('none') },
      { label: document.querySelector('label[for="pax-ccs-description"]'), value: fieldValue('description') },
      { label: document.querySelector('label[for="pax-ccs-screenshots"]'), value: fileSummary(document.getElementById('pax-ccs-screenshots')) },
      { label: document.querySelector('label[for="pax-ccs-documents"]'), value: fileSummary(document.getElementById('pax-ccs-documents')) },
      { label: document.querySelector('label[for="pax-ccs-chats"]'), value: fileSummary(document.getElementById('pax-ccs-chats')) },
      { label: document.querySelector('label[for="pax-ccs-other"]'), value: fileSummary(document.getElementById('pax-ccs-other')) },
    ];

    reviewEl.innerHTML = rows.map(function (row) {
      var labelText = row.label ? row.label.textContent.trim() : '';
      return '<dl class="pax-ccs-portal__review-row"><dt>' + escapeHtml(labelText) + '</dt><dd>' + escapeHtml(row.value || t('none')) + '</dd></dl>';
    }).join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function hideError() {
    if (errorEl) {
      errorEl.hidden = true;
      errorEl.textContent = '';
    }
  }

  function showError(msg) {
    if (errorEl) {
      errorEl.hidden = false;
      errorEl.textContent = msg;
    }
  }

  if (startBtn) {
    startBtn.addEventListener('click', function () {
      requestLoginToContinue();
    });
  }

  if (loginContinueBtn) {
    loginContinueBtn.addEventListener('click', function () {
      redirectToLogin();
    });
  }

  if (loginBackBtn) {
    loginBackBtn.addEventListener('click', function () {
      showWelcome();
    });
  }

  form.addEventListener('click', function (e) {
    var next = e.target.closest('[data-ccs-next]');
    var back = e.target.closest('[data-ccs-back]');
    var backWelcome = e.target.closest('[data-ccs-back-welcome]');

    if (backWelcome) {
      e.preventDefault();
      showWelcome();
      return;
    }

    if (next) {
      e.preventDefault();
      var target = parseInt(next.getAttribute('data-ccs-next'), 10);
      if (validateStep(currentStep)) {
        showStep(target);
      }
    }
    if (back) {
      e.preventDefault();
      showStep(parseInt(back.getAttribute('data-ccs-back'), 10));
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    hideError();
    if (!validateStep(4)) {
      return;
    }
    if (requiresLogin() && !isLoggedIn()) {
      showLoginGate();
      return;
    }
    if (!config.ajaxUrl || !config.nonce) {
      showError('Configuration error.');
      return;
    }

    submitBtn.disabled = true;
    var data = new FormData(form);
    data.append('action', 'paxdesign_cybercrime_report');
    data.append('nonce', config.nonce);

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        if (!json || !json.success) {
          if (json && json.data && json.data.code === 'login_required') {
            showLoginGate();
            return;
          }
          var errMsg = (json && json.data && json.data.message) || 'Submit failed';
          if (json && json.data && json.data.detail) {
            errMsg += ' (' + json.data.detail + ')';
          }
          throw new Error(errMsg);
        }
        form.hidden = true;
        if (workflowEl) {
          workflowEl.hidden = true;
        }
        if (welcomeEl) {
          welcomeEl.hidden = true;
        }
        root.setAttribute('data-ccs-phase', 'success');
        if (successEl) {
          successEl.hidden = false;
        }
        if (refEl && json.data && json.data.referenceId) {
          refEl.textContent = json.data.referenceId;
          setPageContext(root.getAttribute('data-ccs-lang') || 'ar', json.data.referenceId);
        }
        window.scrollTo({ top: root.offsetTop - 24, behavior: 'smooth' });
      })
      .catch(function (err) {
        showError(err.message || 'Submit failed');
        submitBtn.disabled = false;
      });
  });

  root.querySelectorAll('[data-ccs-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setLang(btn.getAttribute('data-ccs-switch'));
    });
  });

  if (chatBtn) {
    chatBtn.addEventListener('click', function () {
      var referenceId = refEl ? (refEl.textContent || '').trim() : '';
      openSupportChat(referenceId);
    });
  }

  var saved = '';
  try {
    saved = localStorage.getItem('pax-ccs-lang') || '';
  } catch (e) {}
  setLang(saved === 'de' ? 'de' : 'ar');
  setPhase('welcome');
  maybeResumeAfterLogin();
})();
