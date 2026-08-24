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
  var activeReportEl = document.getElementById('pax-ccs-active-report');
  var activeRefEl = document.getElementById('pax-ccs-active-ref');
  var activeStatusBadgeEl = document.getElementById('pax-ccs-active-status-badge');
  var activeStatusIconEl = document.getElementById('pax-ccs-active-status-icon');
  var activeStatusLabelEl = document.getElementById('pax-ccs-active-status-label');
  var decisionCardEl = document.getElementById('pax-ccs-decision-card');
  var decisionIconEl = document.getElementById('pax-ccs-decision-icon');
  var decisionLabelEl = document.getElementById('pax-ccs-decision-label');
  var decisionReasonWrapEl = document.getElementById('pax-ccs-decision-reason-wrap');
  var decisionReasonHeadingEl = document.getElementById('pax-ccs-decision-reason-heading');
  var decisionReasonEl = document.getElementById('pax-ccs-decision-reason');
  var decisionExplanationEl = document.getElementById('pax-ccs-decision-explanation');
  var decisionNextWrapEl = document.getElementById('pax-ccs-decision-next-wrap');
  var decisionNextHeadingEl = document.getElementById('pax-ccs-decision-next-heading');
  var decisionNextEl = document.getElementById('pax-ccs-decision-next');
  var activeCategoryEl = document.getElementById('pax-ccs-active-category');
  var activeSubmittedEl = document.getElementById('pax-ccs-active-submitted');
  var activeTimelineEl = document.getElementById('pax-ccs-active-timeline');
  var activeAttachmentsEl = document.getElementById('pax-ccs-active-attachments');
  var activeReplyWrap = document.getElementById('pax-ccs-active-reply-wrap');
  var activeReplyInput = document.getElementById('pax-ccs-active-reply');
  var activeReplySubmit = document.getElementById('pax-ccs-active-reply-submit');
  var activeReplyError = document.getElementById('pax-ccs-active-reply-error');
  var closedLockEl = document.getElementById('pax-ccs-closed-lock');
  var closedLockTitleEl = document.getElementById('pax-ccs-closed-lock-title');
  var closedLockTextEl = document.getElementById('pax-ccs-closed-lock-text');
  var openNewReportBtn = document.getElementById('pax-ccs-open-new-report');
  var activeAiBlock = document.querySelector('.pax-ccs-portal__ai-block');
  var activeChatBtn = document.getElementById('pax-ccs-active-chat');
  var refreshReportBtn = document.getElementById('pax-ccs-refresh-report');
  var backHistoryBtn = document.getElementById('pax-ccs-back-history');
  var attachmentsFoldEl = document.getElementById('pax-ccs-attachments-fold');
  var reportHistoryEl = document.getElementById('pax-ccs-report-history');
  var historyListEl = document.getElementById('pax-ccs-history-list');
  var startUnreadBadge = document.getElementById('pax-ccs-start-unread');
  var unreadBadgeEl = document.getElementById('pax-ccs-unread-badge');

  var activeReport = null;
  var reportHistoryData = [];
  var reportPollTimer = null;
  var expandedTimelineId = null;
  var timelineAccordionBound = false;
  var lastKnownLatestTimelineId = null;
  var lastKnownTimelineCount = 0;

  var phoneCodeEl = document.getElementById('pax-ccs-phone-code');
  var phoneLocalEl = document.getElementById('pax-ccs-phone-local');
  var phoneHiddenEl = document.getElementById('pax-ccs-phone');
  var countrySearchEl = document.getElementById('pax-ccs-country-search');
  var countryHiddenEl = document.getElementById('pax-ccs-country');
  var countryListEl = document.getElementById('pax-ccs-country-list');
  var countryPickerEl = document.getElementById('pax-ccs-country-picker');
  var identityDocEl = document.getElementById('pax-ccs-identity-doc');
  var categoryEl = document.getElementById('pax-ccs-category');
  var categoryCardsEl = document.getElementById('pax-ccs-category-cards');
  var platformChipsEl = document.getElementById('pax-ccs-platform-chips');
  var platformsInputEl = document.getElementById('pax-ccs-platforms');
  var evidenceCoachEl = document.getElementById('pax-ccs-evidence-coach');
  var caseDossierEl = document.getElementById('pax-ccs-case-dossier');
  var continueFormBtn = document.getElementById('pax-ccs-continue-form');
  var originalRequestEl = document.getElementById('pax-ccs-original-request');
  var checksListEl = document.getElementById('pax-ccs-checks-list');
  var nextActionEl = document.getElementById('pax-ccs-next-action');
  var resubmitIdentityEl = document.getElementById('pax-ccs-resubmit-identity');
  var resubmitEvidenceEl = document.getElementById('pax-ccs-resubmit-evidence');
  var resubmitSubmitEl = document.getElementById('pax-ccs-resubmit-submit');
  var resubmitBlockEl = document.getElementById('pax-ccs-resubmit');
  var resubmitPreviewEl = document.getElementById('pax-ccs-resubmit-preview');
  var evidenceSuccessEl = document.getElementById('pax-ccs-evidence-success');
  var resubmitPreviewUrls = [];
  var evidenceSuccessUntil = 0;
  var countries = Array.isArray(config.countries) ? config.countries.slice() : [];
  var countriesByCode = {};
  var selectedCountryCode = '';

  countries.forEach(function (country) {
    if (country && country.code) {
      countriesByCode[country.code] = country;
    }
  });

  countries.sort(function (a, b) {
    return countryName(a).localeCompare(countryName(b), getLang(), { sensitivity: 'base' });
  });

  function countryName(country) {
    if (!country || !country.name) {
      return '';
    }
    var lang = getLang();
    return country.name[lang] || country.name.en || country.name.de || '';
  }

  function syncPhoneField() {
    if (!phoneHiddenEl) {
      return;
    }
    var dial = phoneCodeEl ? (phoneCodeEl.value || '').trim() : '';
    var local = phoneLocalEl ? phoneLocalEl.value.replace(/[^\d\s\-().]/g, '').trim() : '';
    phoneHiddenEl.value = dial && local ? (dial + ' ' + local).trim() : local;
  }

  function renderCountryOptions(filter) {
    if (!countryListEl) {
      return;
    }
    var query = String(filter || '').trim().toLowerCase();
    var matches = countries.filter(function (country) {
      if (!query) {
        return true;
      }
      var label = (country.flag + ' ' + countryName(country)).toLowerCase();
      return label.indexOf(query) !== -1 || String(country.code || '').toLowerCase().indexOf(query) !== -1;
    }).slice(0, 40);

    countryListEl.innerHTML = matches.map(function (country) {
      var label = country.flag + ' ' + countryName(country);
      return '<li role="option" tabindex="-1" data-country-code="' + escapeHtml(country.code) + '">' + escapeHtml(label) + '</li>';
    }).join('');
  }

  function openCountryList() {
    if (!countryListEl || !countrySearchEl) {
      return;
    }
    renderCountryOptions(countrySearchEl.value);
    countryListEl.hidden = false;
    countrySearchEl.setAttribute('aria-expanded', 'true');
  }

  function closeCountryList() {
    if (!countryListEl || !countrySearchEl) {
      return;
    }
    countryListEl.hidden = true;
    countrySearchEl.setAttribute('aria-expanded', 'false');
  }

  function selectCountry(code) {
    var country = countriesByCode[code];
    if (!country || !countryHiddenEl || !countrySearchEl) {
      return;
    }
    selectedCountryCode = code;
    countryHiddenEl.value = code;
    countrySearchEl.value = country.flag + ' ' + countryName(country);
    closeCountryList();
    if (countryPickerEl) {
      countryPickerEl.classList.remove('is-invalid');
    }
  }

  function refreshCountrySearchLabel() {
    if (!countrySearchEl || !selectedCountryCode) {
      return;
    }
    var country = countriesByCode[selectedCountryCode];
    if (country) {
      countrySearchEl.value = country.flag + ' ' + countryName(country);
    }
  }

  function initCountryPicker() {
    if (!countrySearchEl || !countryListEl) {
      return;
    }
    renderCountryOptions('');
    countrySearchEl.addEventListener('focus', function () {
      openCountryList();
    });
    countrySearchEl.addEventListener('input', function () {
      selectedCountryCode = '';
      if (countryHiddenEl) {
        countryHiddenEl.value = '';
      }
      openCountryList();
    });
    countrySearchEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeCountryList();
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        var first = countryListEl.querySelector('[data-country-code]');
        if (first) {
          selectCountry(first.getAttribute('data-country-code'));
        }
      }
    });
    countryListEl.addEventListener('click', function (e) {
      var item = e.target.closest('[data-country-code]');
      if (!item) {
        return;
      }
      selectCountry(item.getAttribute('data-country-code'));
    });
    document.addEventListener('click', function (e) {
      if (!countryPickerEl || countryPickerEl.contains(e.target)) {
        return;
      }
      closeCountryList();
    });
  }

  if (phoneCodeEl) {
    phoneCodeEl.addEventListener('change', syncPhoneField);
  }
  if (phoneLocalEl) {
    phoneLocalEl.addEventListener('input', syncPhoneField);
  }
  if (identityDocEl) {
    identityDocEl.addEventListener('change', function () {
      var wrap = identityDocEl.closest('.pax-ccs-portal__field');
      if (wrap) {
        wrap.classList.remove('is-invalid');
      }
    });
  }
  initCountryPicker();
  initGuidedInterview();

  function setPhoneFromStored(phone, countryCode) {
    phone = String(phone || '').trim();
    if (!phone || !phoneLocalEl) {
      return;
    }
    var compact = phone.replace(/\s+/g, '');
    var best = null;
    var bestLen = 0;
    if (phoneCodeEl) {
      var options = phoneCodeEl.querySelectorAll('option[data-country-code]');
      options.forEach(function (opt) {
        var dial = String(opt.value || '').replace(/\s/g, '');
        var code = String(opt.getAttribute('data-country-code') || '').toUpperCase();
        if (countryCode && code === String(countryCode).toUpperCase() && dial) {
          best = opt;
          bestLen = dial.length;
        } else if (!best && dial && compact.indexOf(dial) === 0 && dial.length > bestLen) {
          best = opt;
          bestLen = dial.length;
        }
      });
      if (best) {
        phoneCodeEl.value = best.value;
        var local = compact;
        if (bestLen && compact.indexOf(String(best.value).replace(/\s/g, '')) === 0) {
          local = compact.slice(String(best.value).replace(/\s/g, '').length);
        }
        phoneLocalEl.value = local.replace(/^\+/, '');
      } else {
        phoneLocalEl.value = phone.replace(/^\+\d{1,4}\s*/, '');
      }
    } else {
      phoneLocalEl.value = phone;
    }
    syncPhoneField();
  }

  function setCategory(value) {
    if (!categoryEl) {
      return;
    }
    categoryEl.value = value || '';
    if (categoryCardsEl) {
      categoryCardsEl.querySelectorAll('[data-ccs-category]').forEach(function (card) {
        card.classList.toggle('is-selected', card.getAttribute('data-ccs-category') === categoryEl.value);
        card.setAttribute('aria-pressed', card.classList.contains('is-selected') ? 'true' : 'false');
      });
    }
    updateEvidenceCoach();
  }

  function updateEvidenceCoach() {
    if (!evidenceCoachEl || !categoryEl) {
      return;
    }
    var key = categoryEl.value || '';
    var text = i18nText('evidenceCoach.' + key, '');
    if (text && text !== 'evidenceCoach.' + key) {
      evidenceCoachEl.hidden = false;
      evidenceCoachEl.textContent = text;
    } else {
      evidenceCoachEl.hidden = true;
      evidenceCoachEl.textContent = '';
    }
  }

  function togglePlatformChip(name) {
    if (!platformsInputEl || !name) {
      return;
    }
    var current = (platformsInputEl.value || '').split(',').map(function (part) {
      return part.trim();
    }).filter(Boolean);
    var idx = current.indexOf(name);
    if (idx === -1) {
      current.push(name);
    } else {
      current.splice(idx, 1);
    }
    platformsInputEl.value = current.join(', ');
    syncPlatformChips();
  }

  function syncPlatformChips() {
    if (!platformChipsEl || !platformsInputEl) {
      return;
    }
    var current = (platformsInputEl.value || '').toLowerCase();
    platformChipsEl.querySelectorAll('[data-ccs-platform]').forEach(function (chip) {
      var name = chip.getAttribute('data-ccs-platform') || '';
      chip.classList.toggle('is-selected', name && current.indexOf(name.toLowerCase()) !== -1);
    });
  }

  function initGuidedInterview() {
    if (categoryCardsEl) {
      categoryCardsEl.addEventListener('click', function (e) {
        var card = e.target.closest('[data-ccs-category]');
        if (!card) {
          return;
        }
        setCategory(card.getAttribute('data-ccs-category'));
      });
      setCategory(categoryEl ? categoryEl.value : '');
    }
    if (categoryEl) {
      categoryEl.addEventListener('change', function () {
        setCategory(categoryEl.value);
      });
    }
    if (platformChipsEl) {
      platformChipsEl.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-ccs-platform]');
        if (!chip) {
          return;
        }
        togglePlatformChip(chip.getAttribute('data-ccs-platform'));
      });
    }
    if (platformsInputEl) {
      platformsInputEl.addEventListener('input', syncPlatformChips);
      syncPlatformChips();
    }
  }

  function getLang() {
    var lang = root.getAttribute('data-ccs-lang') || 'ar';
    return lang === 'de' || lang === 'en' ? lang : 'ar';
  }

  function appendLocale(body) {
    if (body && typeof body.append === 'function') {
      body.append('locale', getLang());
    }
    return body;
  }

  function pickLangMap(map, fallback) {
    if (map && typeof map === 'object') {
      if (map[getLang()]) {
        return map[getLang()];
      }
      if (getLang() !== 'en' && map.ar) {
        return map.ar;
      }
    }
    return fallback || '';
  }

  function statusLabelForReport(report) {
    if (!report) {
      return '';
    }
    var packed = pickLangMap(report.status_label_i18n, '');
    if (packed) {
      return packed;
    }
    var badgeKey = report.customer_status || statusBadgeKey(report.status || '');
    var badges = i18nBundle().statusBadges || {};
    var badge = badges[badgeKey] || {};
    if (badge.label && badge.label[getLang()]) {
      return badge.label[getLang()];
    }
    if (badge.label && badge.label.ar && getLang() !== 'en') {
      return badge.label.ar;
    }
    return '';
  }

  function localizedNextAction(report) {
    if (!report) {
      return '';
    }
    var packed = pickLangMap(report.next_action_i18n, '');
    if (packed) {
      return packed;
    }
    if ((report.status || '') === 'rejected') {
      return activeReportText('rejected_next', '');
    }
    return report.next_action || '';
  }

  function localizedTimelineBody(entry) {
    if (!entry) {
      return '';
    }
    var packed = pickLangMap(entry.body_i18n, '');
    if (packed) {
      return packed;
    }
    var meta = entry.meta && typeof entry.meta === 'object' ? entry.meta : {};
    if (meta.event === 'status_change' && meta.to) {
      var label = statusLabelForReport({ status: meta.to, customer_status: statusBadgeKey(meta.to), status_label_i18n: entry.status_label_i18n });
      var tpl = i18nText('timeline.statusChanged', '');
      if (tpl && label) {
        return tpl.replace('%s', label);
      }
      if (label) {
        return label;
      }
    }
    return entry.body || '';
  }

  function i18nBundle() {
    return (config && config.i18n) || {};
  }

  function i18nText(path, fallback) {
    var parts = String(path || '').split('.');
    var node = i18nBundle();
    for (var i = 0; i < parts.length; i++) {
      if (!node || typeof node !== 'object') {
        node = null;
        break;
      }
      node = node[parts[i]];
    }
    if (node && typeof node === 'object' && node[getLang()]) {
      return node[getLang()];
    }
    return fallback !== undefined ? fallback : path;
  }

  function statusBadgeKey(status) {
    var map = i18nBundle().statusBadgeMap || {};
    var key;
    for (key in map) {
      if (!Object.prototype.hasOwnProperty.call(map, key)) {
        continue;
      }
      var statuses = map[key] || [];
      if (statuses.indexOf(status) !== -1) {
        return key;
      }
    }
    return 'under_review';
  }

  function isReportActive(report) {
    if (!report) {
      return false;
    }
    if (report.is_active === false || report.is_active === 0 || report.is_active === '0') {
      return false;
    }
    var status = String(report.status || report.customer_status || '');
    return status !== 'closed' && status !== 'resolved' && status !== 'rejected';
  }

  function clearReportRefParam() {
    try {
      var params = new URLSearchParams(window.location.search);
      if (!params.has('ref')) {
        return;
      }
      params.delete('ref');
      var query = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    } catch (e) {}
  }

  function needsEvidenceUpload(report) {
    if (!report) {
      return false;
    }
    var status = String(report.status || report.customer_status || '');
    return status === 'waiting_for_customer';
  }

  function revokeResubmitPreviewUrls() {
    resubmitPreviewUrls.forEach(function (url) {
      try {
        URL.revokeObjectURL(url);
      } catch (e) {}
    });
    resubmitPreviewUrls = [];
  }

  function renderResubmitPreview() {
    if (!resubmitPreviewEl) {
      return;
    }
    revokeResubmitPreviewUrls();
    var items = [];
    function appendFiles(input) {
      if (!input || !input.files) {
        return;
      }
      Array.prototype.forEach.call(input.files, function (file) {
        items.push({ file: file, source: input.id });
      });
    }
    appendFiles(resubmitEvidenceEl);
    appendFiles(resubmitIdentityEl);
    if (!items.length) {
      resubmitPreviewEl.hidden = true;
      resubmitPreviewEl.innerHTML = '';
      return;
    }
    resubmitPreviewEl.hidden = false;
    resubmitPreviewEl.innerHTML = items.map(function (item, index) {
      var name = escapeHtml(item.file.name || 'file');
      var thumb = '';
      if (item.file.type && item.file.type.indexOf('image/') === 0) {
        var objectUrl = URL.createObjectURL(item.file);
        resubmitPreviewUrls.push(objectUrl);
        thumb = '<img src="' + objectUrl + '" alt="">';
      } else {
        thumb = '<span aria-hidden="true">📄</span>';
      }
      return '<li class="pax-ccs-portal__resubmit-preview-item">'
        + thumb
        + '<span class="pax-ccs-portal__resubmit-preview-name">' + name + '</span>'
        + '</li>';
    }).join('');
  }

  function clearResubmitInputs() {
    if (resubmitIdentityEl) {
      resubmitIdentityEl.value = '';
    }
    if (resubmitEvidenceEl) {
      resubmitEvidenceEl.value = '';
    }
    renderResubmitPreview();
  }

  function showEvidenceSuccess(message) {
    if (!evidenceSuccessEl) {
      return;
    }
    evidenceSuccessUntil = Date.now() + 8000;
    evidenceSuccessEl.textContent = message || activeReportText('evidence_success', 'Evidence submitted successfully.');
    evidenceSuccessEl.hidden = false;
  }

  function updateEvidenceUi(report) {
    var waiting = needsEvidenceUpload(report);
    if (resubmitBlockEl) {
      resubmitBlockEl.hidden = !waiting;
      resubmitBlockEl.classList.toggle('pax-ccs-portal__resubmit--active', waiting);
    }
    if (resubmitSubmitEl) {
      resubmitSubmitEl.hidden = !waiting;
      resubmitSubmitEl.classList.toggle('pax-ccs-portal__btn--primary', waiting);
      resubmitSubmitEl.classList.toggle('pax-ccs-portal__btn--ghost', !waiting);
    }
    if (activeReplySubmit) {
      activeReplySubmit.hidden = waiting;
    }
    if (activeReplyWrap) {
      activeReplyWrap.classList.toggle('pax-ccs-portal__reply-wrap--evidence-needed', waiting);
    }
    if (!waiting) {
      clearResubmitInputs();
      if (evidenceSuccessEl && Date.now() > evidenceSuccessUntil) {
        evidenceSuccessEl.hidden = true;
      }
    }
  }

  function appendResubmitFiles(body) {
    if (!body || typeof body.append !== 'function') {
      return;
    }
    if (resubmitEvidenceEl && resubmitEvidenceEl.files && resubmitEvidenceEl.files.length) {
      Array.prototype.forEach.call(resubmitEvidenceEl.files, function (file, index) {
        var filename = file && file.name ? file.name : ('evidence-' + (index + 1));
        body.append('evidence_other[]', file, filename);
      });
    }
    if (resubmitIdentityEl && resubmitIdentityEl.files && resubmitIdentityEl.files[0]) {
      var idFile = resubmitIdentityEl.files[0];
      body.append('identity_document', idFile, idFile.name || 'identity-document');
    }
  }

  function scrollToEvidenceUpload() {
    if (activeReport) {
      updateEvidenceUi(activeReport);
    }
    if (activeReplyWrap) {
      activeReplyWrap.classList.add('pax-ccs-portal__reply-wrap--evidence-focus');
      try {
        activeReplyWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } catch (e) {
        activeReplyWrap.scrollIntoView();
      }
    }
    window.setTimeout(function () {
      if (resubmitEvidenceEl) {
        try {
          resubmitEvidenceEl.focus({ preventScroll: true });
        } catch (err) {
          resubmitEvidenceEl.focus();
        }
      }
      if (activeReplyWrap) {
        activeReplyWrap.classList.remove('pax-ccs-portal__reply-wrap--evidence-focus');
      }
    }, 1600);
  }

  function timelineEvidenceInlineHtml(entry) {
    if (!entry || !entryHasEvidenceRequest(entry)) {
      return '';
    }
    var canUpload = isReportActive(activeReport) && needsEvidenceUpload(activeReport);
    var title = activeReportText('evidence_request_inline', 'Evidence Required');
    var hint = activeReportText('evidence_request_hint', 'Please upload the requested evidence here.');
    var action = activeReportText('evidence_request_action', 'Upload Evidence / رفع الأدلة');
    var buttonHtml = canUpload
      ? '<button type="button" class="pax-ccs-portal__evidence-request-btn" data-ccs-upload-evidence>'
        + escapeHtml(action) + '</button>'
      : '';
    return '<div class="pax-ccs-portal__evidence-request" data-ccs-evidence-card="1">'
      + '<div class="pax-ccs-portal__evidence-request-icon" aria-hidden="true">📎</div>'
      + '<p class="pax-ccs-portal__evidence-request-kicker">' + escapeHtml(title) + '</p>'
      + '<p class="pax-ccs-portal__evidence-request-hint">' + escapeHtml(hint) + '</p>'
      + buttonHtml
      + '</div>';
  }

  function bindTimelineEvidenceActions() {
    if (!activeTimelineEl) {
      return;
    }
    activeTimelineEl.querySelectorAll('[data-ccs-upload-evidence]').forEach(function (btn) {
      if (btn.getAttribute('data-bound') === '1') {
        return;
      }
      btn.setAttribute('data-bound', '1');
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        scrollToEvidenceUpload();
      });
    });
  }

  function applyReportLifecycle(report) {
    if (!report) {
      return;
    }
    var isActive = isReportActive(report);
    root.classList.toggle('pax-ccs-portal--report-closed', !isActive);
    if (activeReplyWrap) {
      activeReplyWrap.hidden = !isActive;
    }
    if (closedLockEl) {
      closedLockEl.hidden = isActive;
    }
    if (activeAiBlock) {
      activeAiBlock.hidden = !isActive;
    }
    if (backHistoryBtn) {
      backHistoryBtn.hidden = isActive;
    }
    if (!isActive) {
      if (closedLockTitleEl) {
        closedLockTitleEl.textContent = activeReportText('closed_title', closedLockTitleEl.textContent || '');
      }
      if (closedLockTextEl) {
        closedLockTextEl.textContent = activeReportText('read_only', closedLockTextEl.textContent || '');
      }
      if (activeReplyInput) {
        activeReplyInput.value = '';
      }
      clearResubmitInputs();
      if (activeReplyError) {
        activeReplyError.hidden = true;
        activeReplyError.textContent = '';
      }
      if (evidenceSuccessEl) {
        evidenceSuccessEl.hidden = true;
      }
    }
    updateEvidenceUi(report);
    updateStartButtonLabel();
    if (phase === 'active-report') {
      startReportPolling();
    }
  }

  function statusIconSvg(key) {
    var common = 'class="pax-ccs-status-icon pax-ccs-status-icon--' + key + '" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"';
    if (key === 'rejected') {
      return '<svg ' + common + '><circle cx="12" cy="12" r="10" fill="currentColor"/><rect x="11" y="6.2" width="2" height="8.2" rx="1" fill="#fff"/><rect x="11" y="16.2" width="2" height="2.1" rx="1" fill="#fff"/></svg>';
    }
    if (key === 'resolved') {
      return '<svg ' + common + '><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7.2 12.3l3.1 3.1 6.5-6.6" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if (key === 'waiting_for_customer') {
      return '<svg ' + common + '><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M12 7.2v5.1l3.2 1.9" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if (key === 'collecting') {
      return '<svg ' + common + '><circle cx="12" cy="12" r="10" fill="currentColor"/><circle cx="12" cy="12" r="3.1" fill="#fff"/></svg>';
    }
    if (key === 'closed') {
      return '<svg ' + common + '><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M8.2 8.2l7.6 7.6M15.8 8.2l-7.6 7.6" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
    }
    return '<svg ' + common + '><circle cx="12" cy="12" r="10" fill="currentColor"/><circle cx="12" cy="12" r="3.4" fill="none" stroke="#fff" stroke-width="2"/></svg>';
  }

  function updateStatusBadge(report) {
    if (!activeStatusBadgeEl || !activeStatusLabelEl || !report) {
      return;
    }
    var badgeKey = report.customer_status || statusBadgeKey(report.status || '');
    var badges = i18nBundle().statusBadges || {};
    var badge = badges[badgeKey] || {};
    var label = statusLabelForReport(report);
    activeStatusBadgeEl.className = 'pax-ccs-portal__status-hero pax-ccs-portal__status-hero--' + badgeKey;
    if (activeStatusIconEl) {
      activeStatusIconEl.innerHTML = statusIconSvg(badgeKey);
    }
    activeStatusLabelEl.textContent = label;
    activeStatusBadgeEl.hidden = !label;
    renderDecisionCard(report, badgeKey, label);
  }

  function renderDecisionCard(report, badgeKey, label) {
    if (!decisionCardEl) {
      return;
    }
    var isRejected = (report.status || '') === 'rejected' || badgeKey === 'rejected';
    if (!isRejected) {
      decisionCardEl.hidden = true;
      return;
    }
    var rejection = report.rejection && typeof report.rejection === 'object' ? report.rejection : {};
    var lang = getLang();
    var reasonI18n = rejection.reason_i18n || {};
    var reason = pickLangMap(reasonI18n, rejection.reason || '');
    var explanation = (rejection.explanation || '').trim();
    var next = localizedNextAction(report) || activeReportText('rejected_next', '');
    decisionCardEl.hidden = false;
    decisionCardEl.className = 'pax-ccs-portal__decision pax-ccs-portal__decision--rejected';
    if (decisionIconEl) {
      decisionIconEl.innerHTML = statusIconSvg('rejected');
    }
    if (decisionLabelEl) {
      decisionLabelEl.textContent = label || activeReportText('status', 'Rejected');
    }
    if (decisionReasonWrapEl && decisionReasonEl && decisionReasonHeadingEl) {
      if (reason) {
        decisionReasonHeadingEl.textContent = activeReportText('rejection_heading', 'Rejection reason');
        decisionReasonEl.textContent = reason;
        decisionReasonWrapEl.hidden = false;
      } else {
        decisionReasonWrapEl.hidden = true;
      }
    }
    if (decisionExplanationEl) {
      if (explanation) {
        decisionExplanationEl.textContent = explanation;
        decisionExplanationEl.hidden = false;
      } else {
        decisionExplanationEl.hidden = true;
      }
    }
    if (decisionNextWrapEl && decisionNextEl && decisionNextHeadingEl) {
      if (next) {
        decisionNextHeadingEl.textContent = activeReportText('rejected_next_heading', 'Next action');
        decisionNextEl.textContent = next;
        decisionNextWrapEl.hidden = false;
      } else {
        decisionNextWrapEl.hidden = true;
      }
    }
  }

  function categoryLabel(category) {
    var categories = i18nBundle().categories || {};
    var labels = categories[category];
    if (labels && labels[getLang()]) {
      return labels[getLang()];
    }
    return category || '';
  }

  function mapServerError(json, fallbackKey) {
    var code = json && json.data && json.data.code;
    if (code) {
      var localized = i18nText('errors.' + code, '');
      if (localized) {
        return localized;
      }
    }
    if (json && json.data && json.data.message) {
      return json.data.message;
    }
    return i18nText('errors.' + (fallbackKey || 'submit'), 'Error');
  }

  function timelineSubjectLabel(entry) {
    if (!entry) {
      return '';
    }
    if (entry.subject_key) {
      var translated = i18nText('subjects.' + entry.subject_key, '');
      if (translated) {
        return translated;
      }
    }
    return entry.subject || '';
  }

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

  function getChatSessionId() {
    try {
      return localStorage.getItem('paxdesign-chat-session') || sessionStorage.getItem('paxdesign-chat-session') || '';
    } catch (e) {
      return '';
    }
  }

  function authorLabel(type) {
    if (type === 'customer') {
      return i18nText('customerFallback', 'Customer');
    }
    return i18nText('supportTeam', 'PAXDesign Support Team');
  }

  function timelineSenderLabel(entry) {
    if (!entry) {
      return authorLabel('');
    }
    if (entry.sender_key === 'customer' || entry.author_type === 'customer') {
      var name = (entry.customer_name || '').trim();
      if (!name && activeReport && activeReport.customer_display_name) {
        name = String(activeReport.customer_display_name).trim();
      }
      if (name) {
        return name;
      }
      return i18nText('customerFallback', 'Customer');
    }
    return i18nText('supportTeam', 'PAXDesign Support Team');
  }

  function hasTimelineSubject(entry) {
    return !!timelineSubjectLabel(entry);
  }

  function formatDate(value) {
    if (!value) {
      return '—';
    }
    var d = new Date(String(value).replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) {
      return value;
    }
    try {
      var localeMap = { ar: 'ar', de: 'de-AT', en: 'en-GB' };
      return d.toLocaleString(localeMap[getLang()] || 'ar', { dateStyle: 'medium', timeStyle: 'short' });
    } catch (e) {
      return value;
    }
  }

  function historyText(key, fallback) {
    return i18nText('ticketHistory.' + key, fallback);
  }

  function activeReportText(key, fallback) {
    return i18nText('activeReport.' + key, fallback);
  }

  function updateUnreadBadges(count) {
    var unread = parseInt(count, 10) || 0;
    var label = historyText('unread', 'New');
    if (unreadBadgeEl) {
      if (unread > 0) {
        unreadBadgeEl.hidden = false;
        unreadBadgeEl.textContent = unread > 99 ? '99+' : String(unread);
        unreadBadgeEl.setAttribute('aria-label', label + ': ' + unread);
      } else {
        unreadBadgeEl.hidden = true;
        unreadBadgeEl.textContent = '';
      }
    }
    if (startUnreadBadge) {
      if (unread > 0 && phase === 'welcome') {
        startUnreadBadge.hidden = false;
        startUnreadBadge.textContent = unread > 99 ? '99+' : String(unread);
      } else {
        startUnreadBadge.hidden = true;
        startUnreadBadge.textContent = '';
      }
    }
  }

  function updateStartUnreadFromReports(reports) {
    var total = 0;
    (reports || []).forEach(function (report) {
      if (report && isReportActive(report) && (parseInt(report.unread_count, 10) || 0) > 0) {
        total += parseInt(report.unread_count, 10) || 0;
      }
    });
    if (phase === 'welcome') {
      updateUnreadBadges(total);
    }
  }

  function markReportRead(referenceId) {
    if (!referenceId || !config.ajaxUrl || !config.nonce || !isLoggedIn()) {
      return Promise.resolve(null);
    }
    var body = new FormData();
    body.append('action', 'paxdesign_cybercrime_mark_read');
    body.append('nonce', config.nonce);
    body.append('reference_id', referenceId);
    appendLocale(body);
    return fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json && json.success && json.data && json.data.report) {
          return json.data.report;
        }
        return null;
      })
      .catch(function () { return null; });
  }

  function fetchReportHistory() {
    if (!config.ajaxUrl || !config.nonce || !isLoggedIn()) {
      return Promise.resolve([]);
    }
    var url = config.ajaxUrl;
    var body = new FormData();
    body.append('action', 'paxdesign_cybercrime_report_list');
    body.append('nonce', config.nonce);
    appendLocale(body);
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success || !json.data) {
          return [];
        }
        reportHistoryData = Array.isArray(json.data.reports) ? json.data.reports : [];
        renderReportHistory(reportHistoryData);
        updateStartUnreadFromReports(reportHistoryData);
        return reportHistoryData;
      })
      .catch(function () { return []; });
  }

  function renderReportHistory(reports) {
    if (!reportHistoryEl || !historyListEl) {
      return;
    }
    var closed = (reports || []).filter(function (report) {
      return report && !isReportActive(report);
    });
    if (!closed.length) {
      reportHistoryEl.hidden = true;
      historyListEl.innerHTML = '';
      return;
    }
    reportHistoryEl.hidden = false;
    historyListEl.innerHTML = closed.map(function (report) {
      var ref = escapeHtml(report.reference_id || '');
      var statusLabel = escapeHtml(statusLabelForReport(report) || report.status || '');
      var category = escapeHtml(report.category_label || report.category || '');
      var when = escapeHtml(formatDate(report.updated_at || report.created_at || ''));
      var unread = parseInt(report.unread_count, 10) || 0;
      var unreadHtml = unread > 0
        ? '<span class="pax-ccs-portal__unread-badge">' + escapeHtml(historyText('unread', 'New')) + (unread > 1 ? ' (' + unread + ')' : '') + '</span>'
        : '';
      return '<li class="pax-ccs-portal__history-item pax-ccs-portal__history-item--closed">'
        + '<button type="button" class="pax-ccs-portal__history-btn" data-history-ref="' + ref + '">'
        + '<span class="pax-ccs-portal__history-ref"><code>' + ref + '</code></span>'
        + '<span class="pax-ccs-portal__history-meta">' + category + ' · ' + statusLabel + ' · ' + when + '</span>'
        + unreadHtml
        + '</button></li>';
    }).join('');
    historyListEl.querySelectorAll('[data-history-ref]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ref = btn.getAttribute('data-history-ref') || '';
        if (!ref) {
          return;
        }
        fetchActiveReport(ref).then(function (report) {
          if (report) {
            showActiveReport(report, true);
          }
        });
      });
    });
  }

  function updateStartButtonLabel() {
    if (!startBtn) {
      return;
    }
    var mode = isReportActive(activeReport) ? 'view' : 'start';
    startBtn.querySelectorAll('[data-ccs-start-label]').forEach(function (el) {
      el.hidden = el.getAttribute('data-ccs-start-label') !== mode;
    });
  }

  function fetchActiveReport(reference) {
    if (!config.ajaxUrl || !config.nonce || !isLoggedIn()) {
      return Promise.resolve(null);
    }
    var body = new FormData();
    body.append('nonce', config.nonce);
    if (reference) {
      body.append('action', 'paxdesign_cybercrime_report_detail');
      body.append('reference', reference);
    } else {
      body.append('action', 'paxdesign_cybercrime_active_report');
    }
    appendLocale(body);
    return fetch(config.ajaxUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json || !json.success) {
          return null;
        }
        if (reference && json.data && json.data.report) {
          return json.data.report;
        }
        if (json.data && json.data.active && json.data.report) {
          return json.data.report;
        }
        return null;
      })
      .catch(function () { return null; });
  }

  function mergeCaseUpdate(incoming) {
    if (!incoming || !incoming.reference_id) {
      return incoming;
    }
    var current = activeReport;
    if (!current || current.reference_id !== incoming.reference_id) {
      return incoming;
    }
    var merged = {};
    Object.keys(current).forEach(function (key) {
      merged[key] = current[key];
    });
    Object.keys(incoming).forEach(function (key) {
      var value = incoming[key];
      if (value == null) {
        return;
      }
      if ((key === 'timeline' || key === 'attachments' || key === 'original_request' || key === 'checks') && (!value || (Array.isArray(value) && !value.length))) {
        return;
      }
      merged[key] = value;
    });
    return merged;
  }

  function caseSyncTimestamp(report) {
    if (!report || !report.updated_at) {
      return 0;
    }
    var parsed = Date.parse(String(report.updated_at));
    return isNaN(parsed) ? 0 : parsed;
  }

  function caseSyncFingerprint(report) {
    if (!report) {
      return '';
    }
    var rejection = report.rejection && typeof report.rejection === 'object' ? report.rejection : {};
    var timeline = Array.isArray(report.timeline) ? report.timeline : null;
    return [
      report.reference_id || '',
      report.status || '',
      report.customer_status || '',
      report.is_active === false || report.is_active === 0 || report.is_active === '0' ? '0' : '1',
      report.updated_at || '',
      report.sync_revision || '',
      report.timeline_evidence_signature || '',
      report.attachments_signature || '',
      report.attachments_count || 0,
      report.next_action || '',
      report.unread_count || 0,
      rejection.reason_key || '',
      rejection.reason || '',
      rejection.explanation || '',
      rejection.decided_at || '',
      timeline ? (String(timeline.length) + ':' + (getNewestTimelineEntryId(timeline) || '')) : 'compact'
    ].join('|');
  }

  function applyIncomingReport(report, options) {
    if (!report || !report.reference_id || !isLoggedIn()) {
      return;
    }
    options = options || {};
    var source = options.force ? 'mutation' : (options.source || 'poll');
    if (!options.force && !shouldApplyIncomingReport(report, source)) {
      return;
    }
    if (!options.force && activeReport && activeReport.reference_id === report.reference_id) {
      var incomingTs = caseSyncTimestamp(report);
      var currentTs = caseSyncTimestamp(activeReport);
      if (incomingTs > 0 && currentTs > 0 && incomingTs < currentTs) {
        return;
      }
    }
    var merged = mergeCaseUpdate(report);
    if (merged.reference_id) {
      setPageContext(root.getAttribute('data-ccs-lang') || 'ar', merged.reference_id);
    }
    var statusChanged = !!(activeReport && (activeReport.status || '') !== (merged.status || ''));
    if (!options.force && activeReport && caseSyncFingerprint(merged) === caseSyncFingerprint(activeReport)) {
      rememberSync(merged);
      return;
    }
    rememberSync(merged);
    if (phase === 'form' && (merged.is_draft || merged.status === 'draft')) {
      prefillFormFromReport(merged);
      var wfStep = merged.workflow && parseInt(merged.workflow.step, 10);
      if (wfStep >= 1 && wfStep <= 4) {
        showStep(wfStep);
      }
    } else if (!merged.is_draft && merged.status && merged.status !== 'draft' && (phase === 'form' || phase === 'welcome')) {
      showActiveReport(merged, false);
    } else if (phase === 'active-report' && activeReport && activeReport.reference_id === merged.reference_id) {
      applyReportRefresh(merged, options);
    } else if (phase === 'active-report' || options.show) {
      showActiveReport(merged, false);
    }
    if (statusChanged) {
      fetchReportHistory();
    }
  }

  function timelineText(key) {
    return i18nText(key, key);
  }

  function getVisibleTimelineSorted(entries) {
    return (entries || []).filter(function (entry) {
      if (!entry || entry.author_type === 'ai' || entry.channel === 'chat') {
        return false;
      }
      if (entry.customer_visible === false) {
        return false;
      }
      return !!(entry.body && String(entry.body).trim());
    }).slice().sort(function (a, b) {
      var aTime = String(a.created_at || '') + String(a.id != null ? a.id : '');
      var bTime = String(b.created_at || '') + String(b.id != null ? b.id : '');
      return bTime.localeCompare(aTime);
    });
  }

  function getOfficialTimelineSorted(entries) {
    return getVisibleTimelineSorted(entries);
  }

  function getNewestTimelineEntryId(entries) {
    var sorted = getOfficialTimelineSorted(entries);
    if (!sorted.length) {
      return null;
    }
    var newest = sorted[0];
    return String(newest.id != null ? newest.id : 0);
  }

  function timelineHasNewActivity(entries) {
    var newestId = getNewestTimelineEntryId(entries);
    var count = getOfficialTimelineSorted(entries).length;
    if (newestId === null) {
      return false;
    }
    if (lastKnownLatestTimelineId === null) {
      return false;
    }
    return newestId !== lastKnownLatestTimelineId || count > lastKnownTimelineCount;
  }

  function rememberTimelineSnapshot(entries) {
    lastKnownLatestTimelineId = getNewestTimelineEntryId(entries);
    lastKnownTimelineCount = getOfficialTimelineSorted(entries).length;
  }

  function resetTimelineTracking() {
    lastKnownLatestTimelineId = null;
    lastKnownTimelineCount = 0;
    expandedTimelineId = null;
  }

  function applyReportRefresh(report, options) {
    if (!report) {
      return;
    }
    options = options || {};
    var newActivity = options.forceNewest || timelineHasNewActivity(report.timeline || []);
    var prevUnread = activeReport ? (parseInt(activeReport.unread_count, 10) || 0) : 0;

    activeReport = report;
    updateStatusBadge(report);
    if (activeCategoryEl) {
      activeCategoryEl.textContent = categoryLabel(report.category || '');
    }
    updateUnreadBadges(parseInt(report.unread_count, 10) || prevUnread);
    renderTimeline(report.timeline || [], { forceNewest: newActivity });
    renderAttachments(report.attachments || []);
    renderCaseDossier(report);
    applyReportLifecycle(report);
    if (phase === 'active-report' && report.reference_id && (parseInt(report.unread_count, 10) || 0) > 0) {
      markReportRead(report.reference_id);
    }
    if (newActivity && activeTimelineEl) {
      var openItem = activeTimelineEl.querySelector('.pax-ccs-portal__accordion-item.is-open');
      if (openItem) {
        try {
          openItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } catch (e) {}
      }
    }
  }

  function bindTimelineAccordion() {
    if (timelineAccordionBound || !activeTimelineEl) {
      return;
    }
    timelineAccordionBound = true;
    activeTimelineEl.addEventListener('click', function (e) {
      var trigger = e.target.closest('.pax-ccs-portal__accordion-trigger');
      if (!trigger || !activeTimelineEl.contains(trigger)) {
        return;
      }
      e.preventDefault();
      var item = trigger.closest('.pax-ccs-portal__accordion-item');
      if (!item) {
        return;
      }
      var entryId = item.getAttribute('data-entry-id');
      if (!entryId) {
        return;
      }
      if (item.classList.contains('is-open')) {
        expandedTimelineId = null;
        setExpandedTimelineItem(null);
        return;
      }
      expandedTimelineId = entryId;
      setExpandedTimelineItem(entryId);
    });
  }

  function setExpandedTimelineItem(entryId) {
    if (!activeTimelineEl) {
      return;
    }
    activeTimelineEl.querySelectorAll('.pax-ccs-portal__accordion-item').forEach(function (item) {
      var isOpen = entryId !== null && item.getAttribute('data-entry-id') === entryId;
      item.classList.toggle('is-open', isOpen);
      var trigger = item.querySelector('.pax-ccs-portal__accordion-trigger');
      if (trigger) {
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      }
    });
  }

  /**
   * @param {Array} entries
   * @param {{forceNewest?: boolean}} options
   */
  function renderTimeline(entries, options) {
    if (!activeTimelineEl) {
      return;
    }
    options = options || {};
    var sorted = getOfficialTimelineSorted(entries);
    if (!sorted.length) {
      activeTimelineEl.innerHTML = '<p class="pax-ccs-portal__accordion-empty">' + escapeHtml(i18nText('emptyTimeline', '—')) + '</p>';
      resetTimelineTracking();
      return;
    }

    var newestId = String(sorted[0].id != null ? sorted[0].id : 0);
    var openId = newestId;
    if (!options.forceNewest && expandedTimelineId &&
        sorted.some(function (entry, index) {
          return String(entry.id != null ? entry.id : index) === expandedTimelineId;
        })) {
      openId = expandedTimelineId;
    }
    if (options.forceNewest) {
      openId = newestId;
    }
    expandedTimelineId = openId;
    rememberTimelineSnapshot(entries);

    activeTimelineEl.innerHTML = sorted.map(function (entry, index) {
      var entryId = String(entry.id != null ? entry.id : index);
      var isOpen = entryId === openId;
      var sender = timelineSenderLabel(entry);
      var when = formatDate(entry.created_at || '');
      var body = escapeHtml(localizedTimelineBody(entry) || '').replace(/\n/g, '<br>');
      var evidenceBadge = entryHasEvidenceRequest(entry)
        ? '<span class="pax-ccs-portal__accordion-evidence-badge">📎 ' + escapeHtml(activeReportText('evidence_request_inline', 'Evidence Required')) + '</span>'
        : '';
      var panelId = 'pax-ccs-acc-panel-' + entryId;
      var triggerId = 'pax-ccs-acc-trigger-' + entryId;

      return '<article class="pax-ccs-portal__accordion-item' + (isOpen ? ' is-open' : '') + '" data-entry-id="' + escapeHtml(entryId) + '">'
        + '<button type="button" class="pax-ccs-portal__accordion-trigger" id="' + escapeHtml(triggerId) + '"'
        + ' aria-expanded="' + (isOpen ? 'true' : 'false') + '" aria-controls="' + escapeHtml(panelId) + '">'
        + '<span class="pax-ccs-portal__accordion-head">'
        + '<span class="pax-ccs-portal__accordion-head-row">'
        + '<span class="pax-ccs-portal__accordion-sender">' + escapeHtml(sender) + evidenceBadge + '</span>'
        + '<span class="pax-ccs-portal__accordion-when">' + escapeHtml(when) + '</span>'
        + '</span>'
        + '<span class="pax-ccs-portal__accordion-chevron" aria-hidden="true"></span>'
        + '</button>'
        + '<div class="pax-ccs-portal__accordion-panel" id="' + escapeHtml(panelId) + '" role="region" aria-labelledby="' + escapeHtml(triggerId) + '">'
        + '<div class="pax-ccs-portal__accordion-panel-inner">'
        + '<div class="pax-ccs-portal__accordion-message">' + body + '</div>'
        + timelineEvidenceInlineHtml(entry)
        + '</div>'
        + '</div>'
        + '</article>';
    }).join('');

    bindTimelineAccordion();
    bindTimelineEvidenceActions();
  }

  function renderAttachments(files) {
    if (!activeAttachmentsEl) {
      return;
    }
    var hasFiles = files && files.length;
    if (attachmentsFoldEl) {
      attachmentsFoldEl.hidden = !hasFiles;
    }
    if (!hasFiles) {
      activeAttachmentsEl.innerHTML = '';
      return;
    }
    activeAttachmentsEl.innerHTML = files.map(function (file) {
      var name = escapeHtml(file.name || 'file');
      if (file.url) {
        var url = escapeHtml(file.url);
        if (file.is_image) {
          return '<li class="pax-ccs-portal__attachment-item"><a href="' + url + '" target="_blank" rel="noopener">'
            + '<img src="' + url + '" alt="' + name + '" loading="lazy"><span>' + name + '</span></a></li>';
        }
        return '<li><a href="' + url + '" target="_blank" rel="noopener">' + name + '</a></li>';
      }
      return '<li>' + name + '</li>';
    }).join('');
  }

  function checkStatusLabel(status) {
    if (status === 'fail' || status === 'rejected') {
      return activeReportText('check_rejected', 'Rejected — correction needed');
    }
    if (status === 'review' || status === 'pending_review') {
      return activeReportText('check_review', 'Pending team review');
    }
    return activeReportText('check_accepted', 'Accepted for review');
  }

  function firstIncompleteStep(report) {
    if (report && report.workflow && report.workflow.step) {
      var wf = parseInt(report.workflow.step, 10);
      if (wf >= 1 && wf <= 4) {
        return wf;
      }
    }
    var orig = (report && report.original_request) || {};
    if (!orig.reporter_phone || !orig.reporter_country) {
      return 1;
    }
    if (!orig.category || !orig.incident_at || !orig.platforms || !orig.description) {
      return 2;
    }
    return 3;
  }

  function prefillFormFromReport(report) {
    if (!report || !form) {
      return;
    }
    var orig = report.original_request || {};
    var nameEl = document.getElementById('pax-ccs-full-name');
    var emailEl = document.getElementById('pax-ccs-email');
    if (nameEl && orig.reporter_name) {
      nameEl.value = orig.reporter_name;
    }
    if (emailEl && orig.reporter_email) {
      emailEl.value = orig.reporter_email;
    }
    if (orig.category) {
      setCategory(orig.category);
    }
    var dateEl = document.getElementById('pax-ccs-incident-date');
    var timeEl = document.getElementById('pax-ccs-incident-time');
    var dateVal = orig.incident_date || (orig.incident_at ? String(orig.incident_at).slice(0, 10) : '');
    if (dateEl && dateVal) {
      dateEl.value = dateVal;
    }
    if (timeEl && orig.incident_time) {
      timeEl.value = orig.incident_time;
    }
    if (platformsInputEl && orig.platforms) {
      platformsInputEl.value = orig.platforms;
      syncPlatformChips();
    }
    var descEl = document.getElementById('pax-ccs-description');
    if (descEl && orig.description) {
      descEl.value = orig.description;
    }
    var lossEl = document.getElementById('pax-ccs-financial-loss');
    if (lossEl && orig.financial_loss) {
      lossEl.value = orig.financial_loss;
    }
    var urgencyEl = document.getElementById('pax-ccs-urgency');
    if (urgencyEl && orig.urgency) {
      urgencyEl.value = orig.urgency;
    }
    if (orig.reporter_phone) {
      setPhoneFromStored(orig.reporter_phone, orig.country_code || '');
    }
    var countryCode = String(orig.country_code || '').toUpperCase();
    if (!countryCode && orig.reporter_country) {
      var hint = String(orig.reporter_country).toLowerCase();
      Object.keys(countriesByCode).forEach(function (code) {
        var country = countriesByCode[code];
        var names = country && country.name ? [country.name.en, country.name.de, country.name.ar] : [];
        if (names.some(function (name) { return name && String(name).toLowerCase() === hint; })) {
          countryCode = code;
        }
      });
    }
    if (countryCode) {
      selectCountry(countryCode);
    }
    var accEl = document.getElementById('pax-ccs-identity-accuracy');
    if (accEl && orig.identity_accuracy) {
      accEl.checked = true;
    }
    var decls = orig.declarations || {};
    var truthfulEl = document.getElementById('pax-ccs-decl-truthful');
    var falseEl = document.getElementById('pax-ccs-decl-false');
    var verifyEl = document.getElementById('pax-ccs-decl-verify');
    if (truthfulEl && decls.truthful) {
      truthfulEl.checked = true;
    }
    if (falseEl && decls.false_reports) {
      falseEl.checked = true;
    }
    if (verifyEl && decls.verification) {
      verifyEl.checked = true;
    }
    var chatSession = document.getElementById('pax-ccs-chat-session');
    if (!chatSession) {
      chatSession = document.createElement('input');
      chatSession.type = 'hidden';
      chatSession.name = 'chat_session_id';
      chatSession.id = 'pax-ccs-chat-session';
      form.appendChild(chatSession);
    }
    if (window.PAXdesignChat && report.chat_session_id) {
      chatSession.value = report.chat_session_id;
    }
  }

  function continueDraftOnPage() {
    if (!activeReport || !(activeReport.is_draft || activeReport.status === 'draft')) {
      return;
    }
    var hasId = (activeReport.attachments || []).some(function (file) {
      return file && file.field === 'identity_document';
    });
    if (identityDocEl) {
      identityDocEl.required = !hasId;
    }
    prefillFormFromReport(activeReport);
    setPhase('form');
    showStep(firstIncompleteStep(activeReport));
  }

  function onCcsCaseUpdated(report) {
    if (!report || !report.reference_id || !isLoggedIn()) {
      return;
    }
    applyIncomingReport(report);
    fetchActiveReport(report.reference_id).then(function (full) {
      if (full) {
        applyIncomingReport(full);
      }
    });
  }

  function isStructuredCaseDescription(desc) {
    desc = String(desc || '').trim();
    if (!desc) {
      return true;
    }
    if (/\b(Date:|Platforms:|Financial loss:)\b/i.test(desc)) {
      return true;
    }
    return desc.length > 220;
  }

  function renderCaseDossier(report) {
    if (!caseDossierEl || !report) {
      return;
    }
    caseDossierEl.hidden = false;
    if (nextActionEl) {
      if ((report.status || '') === 'rejected') {
        nextActionEl.textContent = localizedNextAction(report) || activeReportText('rejected_next', '');
      } else {
        nextActionEl.textContent = localizedNextAction(report);
      }
    }
    if (continueFormBtn) {
      continueFormBtn.hidden = !(report.is_draft || report.status === 'draft');
    }
    if (originalRequestEl) {
      var orig = report.original_request || {};
      var incidentDate = orig.incident_date || '';
      if (!incidentDate && (orig.incident_at || report.incident_at)) {
        incidentDate = String(orig.incident_at || report.incident_at).slice(0, 10);
      }
      var loss = orig.financial_loss || report.financial_loss || '';
      var lossText = '';
      if (loss) {
        if (String(loss).toLowerCase() === 'no' || loss === '0') {
          lossText = i18nText('review.no_loss', 'No');
        } else {
          lossText = String(loss);
          var curr = orig.financial_currency || report.financial_currency || '';
          if (curr) lossText += ' ' + curr;
        }
      }
      var rows = [
        [activeReportText('category', 'Incident type'), categoryLabel(orig.category || report.category || '')],
        [i18nText('review.incident', 'Incident date'), incidentDate],
        [i18nText('review.platforms', 'Affected platforms'), orig.platforms || report.platforms || ''],
        [i18nText('review.loss', 'Financial loss'), lossText],
        [i18nText('review.identity', 'Identity'), orig.reporter_name || report.reporter_name || '']
      ];
      var desc = orig.description || report.description || '';
      var showDesc = desc && !isStructuredCaseDescription(desc);
      originalRequestEl.innerHTML = rows.filter(function (row) {
        return row[1];
      }).map(function (row) {
        return '<div class="pax-ccs-portal__active-meta-row"><dt>' + escapeHtml(row[0] || '') + '</dt><dd>' + escapeHtml(String(row[1])) + '</dd></div>';
      }).join('') + (showDesc
        ? '<div class="pax-ccs-portal__active-meta-row pax-ccs-portal__active-meta-row--desc"><dt>' + escapeHtml(i18nText('review.notes', 'Notes')) + '</dt><dd>' + escapeHtml(desc) + '</dd></div>'
        : '');
    }
    if (checksListEl) {
      var checks = report.checks || {};
      var files = checks.files || [];
      var corrections = report.correction_required || checks.customer_corrections || [];
      var html = files.map(function (file) {
        var st = file.customer_status || file.status || '';
        var extra = (file.customer_corrections || []).join(' ');
        return '<li class="pax-ccs-portal__check-item pax-ccs-portal__check-item--' + escapeHtml(file.status || '') + '">'
          + '<strong>' + escapeHtml(file.filename || 'file') + '</strong>'
          + ' <span>' + escapeHtml(checkStatusLabel(st)) + '</span>'
          + (extra ? '<p>' + escapeHtml(extra) + '</p>' : '')
          + '</li>';
      }).join('');
      if (corrections.length && !files.length) {
        html += '<li class="pax-ccs-portal__check-item pax-ccs-portal__check-item--fail"><p>' + escapeHtml(corrections.join(' ')) + '</p></li>';
      }
      checksListEl.innerHTML = html;
    }
  }

  function showActiveReport(report, forceNewest) {
    if (!report || !activeReportEl) {
      return;
    }
    if (!activeReport || activeReport.reference_id !== report.reference_id) {
      resetTimelineTracking();
    }
    rememberSync(report);
    activeReport = report;
    setPhase('active-report');
    applyReportLifecycle(report);
    if (activeRefEl) {
      activeRefEl.textContent = report.reference_id || '';
    }
    updateStatusBadge(report);
    if (activeCategoryEl) {
      activeCategoryEl.textContent = categoryLabel(report.category || '');
    }
    if (activeSubmittedEl) {
      activeSubmittedEl.textContent = formatDate(report.created_at || '');
    }
    renderTimeline(report.timeline || [], { forceNewest: forceNewest !== false });
    renderAttachments(report.attachments || []);
    renderCaseDossier(report);
    updateEvidenceUi(report);
    if (activeReplyError) {
      activeReplyError.hidden = true;
    }
    updateUnreadBadges(parseInt(report.unread_count, 10) || 0);
    markReportRead(report.reference_id || '').then(function (updated) {
      if (updated) {
        rememberSync(updated);
        activeReport = updated;
        updateUnreadBadges(0);
        updateStartUnreadFromReports(reportHistoryData);
      } else {
        updateUnreadBadges(0);
      }
    });
    setPageContext(root.getAttribute('data-ccs-lang') || 'ar', report.reference_id || '');
    try {
      var params = new URLSearchParams(window.location.search);
      params.set('ref', report.reference_id || '');
      params.delete(resumeParamName());
      var query = params.toString();
      window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    } catch (e) {}
    window.scrollTo({ top: activeReportEl.offsetTop - 12, behavior: 'smooth' });
  }

  var reportVisibilityBound = false;
  var REPORT_POLL_MS = 2000;
  var reportFetchSeq = 0;
  var syncSnapshot = null;
  var mutationDepth = 0;

  function beginMutation() {
    mutationDepth += 1;
  }

  function endMutation() {
    mutationDepth = Math.max(0, mutationDepth - 1);
  }

  function entryMeta(entry) {
    var meta = entry && entry.meta;
    if (meta && typeof meta === 'object') {
      return meta;
    }
    if (typeof meta === 'string' && meta.trim()) {
      try {
        var parsed = JSON.parse(meta);
        if (parsed && typeof parsed === 'object') {
          return parsed;
        }
      } catch (error) {
        return {};
      }
    }
    return {};
  }

  function entryHasEvidenceRequest(entry) {
    if (!entry) {
      return false;
    }
    if (entry.evidence_request_active === 0 || entry.evidence_request_active === '0' || entry.evidence_request_active === false) {
      return false;
    }
    if (entry.evidence_request_active === 1 || entry.evidence_request_active === '1' || entry.evidence_request_active === true) {
      return true;
    }
    var meta = entryMeta(entry);
    if (emptyEvidenceMeta(meta)) {
      return false;
    }
    if (entry.request_evidence === 1 || entry.request_evidence === '1' || entry.request_evidence === true) {
      return true;
    }
    return meta.request_evidence === 1 || meta.request_evidence === '1' || meta.request_evidence === true;
  }

  function emptyEvidenceMeta(meta) {
    return !!(meta && (meta.evidence_fulfilled === 1 || meta.evidence_fulfilled === '1' || meta.evidence_fulfilled === true));
  }

  function syncFromReport(report) {
    if (!report || typeof report !== 'object') {
      return null;
    }
    return {
      updatedAt: String(report.updated_at || ''),
      timelineMaxId: parseInt(report.timeline_max_id, 10) || 0,
      timelineCount: parseInt(report.timeline_count, 10) || 0,
      timelineEvidenceSignature: String(report.timeline_evidence_signature || ''),
      attachmentsCount: parseInt(report.attachments_count, 10) || 0,
      attachmentsSignature: String(report.attachments_signature || ''),
      status: String(report.status || ''),
      syncRevision: String(report.sync_revision || '')
    };
  }

  function compareSync(incoming, current) {
    if (!incoming || !current) {
      return 0;
    }
    if (incoming.syncRevision && current.syncRevision && incoming.syncRevision === current.syncRevision) {
      return 0;
    }
    if (incoming.timelineMaxId !== current.timelineMaxId) {
      return incoming.timelineMaxId > current.timelineMaxId ? 1 : -1;
    }
    if (incoming.timelineCount !== current.timelineCount) {
      return incoming.timelineCount > current.timelineCount ? 1 : -1;
    }
    if (incoming.timelineEvidenceSignature !== current.timelineEvidenceSignature) {
      return incoming.timelineEvidenceSignature > current.timelineEvidenceSignature ? 1 : -1;
    }
    if (incoming.attachmentsCount !== current.attachmentsCount) {
      return incoming.attachmentsCount > current.attachmentsCount ? 1 : -1;
    }
    if (incoming.attachmentsSignature !== current.attachmentsSignature) {
      return incoming.attachmentsSignature > current.attachmentsSignature ? 1 : -1;
    }
    if (incoming.updatedAt !== current.updatedAt) {
      return incoming.updatedAt > current.updatedAt ? 1 : -1;
    }
    if (incoming.status !== current.status) {
      if (incoming.updatedAt >= current.updatedAt) {
        return 1;
      }
      return incoming.updatedAt > current.updatedAt ? 1 : -1;
    }
    return 0;
  }

  function shouldApplyIncomingReport(report, source) {
    var incoming = syncFromReport(report);
    if (!incoming) {
      return false;
    }
    if (!syncSnapshot) {
      return true;
    }
    var cmp = compareSync(incoming, syncSnapshot);
    if (source === 'poll' || source === 'fetch') {
      if (mutationDepth > 0 && cmp <= 0) {
        return false;
      }
      if (cmp < 0) {
        return false;
      }
    }
    return true;
  }

  function rememberSync(report) {
    var incoming = syncFromReport(report);
    if (incoming) {
      syncSnapshot = incoming;
    }
  }

  function pollActiveReport() {
    if (phase !== 'active-report' || !activeReport || !activeReport.reference_id) {
      return;
    }
    var seq = ++reportFetchSeq;
    var ref = activeReport.reference_id;
    fetchActiveReport(ref).then(function (report) {
      if (seq !== reportFetchSeq) {
        return;
      }
      if (report) {
        applyIncomingReport(report, { source: 'poll' });
      }
    });
  }

  function startReportPolling() {
    if (!activeReport || !activeReport.reference_id) {
      return;
    }
    if (!reportPollTimer) {
      pollActiveReport();
      reportPollTimer = window.setInterval(pollActiveReport, REPORT_POLL_MS);
    }
    if (!reportVisibilityBound) {
      reportVisibilityBound = true;
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden && phase === 'active-report') {
          pollActiveReport();
        }
      });
    }
  }

  function stopReportPolling() {
    if (reportPollTimer) {
      window.clearInterval(reportPollTimer);
      reportPollTimer = null;
    }
  }

  function bootstrapActiveReport() {
    if (!isLoggedIn()) {
      updateStartButtonLabel();
      return Promise.resolve(false);
    }
    fetchReportHistory();
    var paramsRef = '';
    try {
      paramsRef = new URLSearchParams(window.location.search).get('ref') || '';
    } catch (e) {}
    if (config.activeReport && config.activeReport.reference_id) {
      activeReport = config.activeReport;
      rememberSync(config.activeReport);
      showActiveReport(config.activeReport);
      return Promise.resolve(true);
    }
    return fetchActiveReport(paramsRef || '').then(function (report) {
      if (report && (isReportActive(report) || paramsRef)) {
        showActiveReport(report);
        return true;
      }
      activeReport = null;
      updateStartButtonLabel();
      return false;
    });
  }

  function viewActiveOrStart() {
    if (activeReport && isReportActive(activeReport)) {
      showActiveReport(activeReport);
      return;
    }
    if (requiresLogin() && !isLoggedIn()) {
      showLoginGate();
      return;
    }
    fetchActiveReport('').then(function (report) {
      if (report && isReportActive(report)) {
        showActiveReport(report);
        return;
      }
      activeReport = null;
      clearReportRefParam();
      startReporting();
    });
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
      fetchActiveReport('').then(function (report) {
        if (report && isReportActive(report)) {
          showActiveReport(report);
          return;
        }
        activeReport = null;
        clearReportRefParam();
        startReporting();
      });
    } catch (e) {}
  }

  function t(key) {
    return i18nText('review.' + key, key);
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
    var openChat = function () {
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
    };
    if (window.PAXdesignWidgetLoader && typeof window.PAXdesignWidgetLoader.ensureChat === 'function') {
      window.PAXdesignWidgetLoader.ensureChat().then(openChat).catch(openChat);
      return;
    }
    openChat();
  }

  function setLang(lang) {
    if (lang !== 'ar' && lang !== 'de' && lang !== 'en' && lang !== 'tr') {
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
    refreshCountrySearchLabel();
    setPageContext(lang, window.PAXdesignPageContext && window.PAXdesignPageContext.referenceId);
    try {
      localStorage.setItem('pax-ccs-lang', lang);
    } catch (e) {}
    if (phase === 'form' && currentStep === 4) {
      renderReview();
    }
    if (phase === 'active-report' && activeReport) {
      updateStatusBadge(activeReport);
      if (activeCategoryEl) {
        activeCategoryEl.textContent = categoryLabel(activeReport.category || '');
      }
      if (activeSubmittedEl) {
        activeSubmittedEl.textContent = formatDate(activeReport.created_at || '');
      }
      renderTimeline(activeReport.timeline || [], { forceNewest: false });
    }
  }

  function placeholderAttr(lang) {
    return 'data-placeholder-' + lang;
  }

  function labelAttr(lang) {
    return 'data-label-' + lang;
  }

  function updatePlaceholders(lang) {
    form.querySelectorAll('[data-placeholder-ar]').forEach(function (input) {
      var ph = input.getAttribute(placeholderAttr(lang));
      if (ph) {
        input.setAttribute('placeholder', ph);
      }
    });
  }

  function updateSelectLabels(lang) {
    form.querySelectorAll('option[data-label-ar]').forEach(function (opt) {
      var label = opt.getAttribute(labelAttr(lang));
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
    if (activeReportEl) {
      activeReportEl.hidden = nextPhase !== 'active-report';
    }
    if (nextPhase !== 'active-report') {
      stopReportPolling();
    }
  }

  function showWelcome() {
    setPhase('welcome');
    window.scrollTo({ top: root.offsetTop - 24, behavior: 'smooth' });
  }

  function startReporting() {
    if (activeReport && !isReportActive(activeReport)) {
      activeReport = null;
      clearReportRefParam();
    }
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
    if (countryPickerEl) {
      countryPickerEl.classList.remove('is-invalid');
    }
  }

  function validateStep(step) {
    clearInvalid();
    hideMissingBanners();
    var panel = getStepEl(step);
    if (!panel) {
      return true;
    }
    if (step === 1) {
      syncPhoneField();
    }
    var valid = true;
    panel.querySelectorAll('input, select, textarea').forEach(function (field) {
      if (field.type === 'file' || field.name === 'website_trap') {
        return;
      }
      if (field.id === 'pax-ccs-country-search' || field.id === 'pax-ccs-phone') {
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
    if (step === 1) {
      if (countryHiddenEl && !countryHiddenEl.value.trim()) {
        valid = false;
        if (countryPickerEl) {
          countryPickerEl.classList.add('is-invalid');
        }
        if (countrySearchEl) {
          countrySearchEl.focus();
        }
      }
      if (identityDocEl && (!identityDocEl.files || !identityDocEl.files.length)) {
        valid = false;
        markInvalid(identityDocEl);
      }
      syncPhoneField();
      if (phoneHiddenEl && phoneHiddenEl.value.replace(/\D/g, '').length < 6) {
        valid = false;
        if (phoneLocalEl) {
          markInvalid(phoneLocalEl);
        }
      }
    }
    if (step === 3) {
      var evidenceInputs = [
        document.getElementById('pax-ccs-screenshots'),
        document.getElementById('pax-ccs-documents'),
        document.getElementById('pax-ccs-chats'),
        document.getElementById('pax-ccs-other')
      ];
      var hasEvidence = evidenceInputs.some(function (input) {
        return input && input.files && input.files.length;
      });
      if (!hasEvidence) {
        valid = false;
        evidenceInputs.forEach(function (input) {
          if (input) {
            markInvalid(input);
          }
        });
      }
    }
    if (!valid) {
      showMissingBanner(step);
      var invalid = panel.querySelector('.is-invalid input, .is-invalid select, .is-invalid textarea, .is-invalid .pax-ccs-portal__country-picker');
      if (invalid) {
        if (invalid.classList && invalid.classList.contains('pax-ccs-portal__country-picker') && countrySearchEl) {
          countrySearchEl.focus();
        } else if (invalid.focus) {
          invalid.focus();
        }
      } else {
        var nativeInvalid = panel.querySelector(':invalid');
        if (nativeInvalid && nativeInvalid.focus) {
          nativeInvalid.focus();
        }
      }
    }
    return valid;
  }

  function showMissingBanner(step) {
    var banner = document.getElementById('pax-ccs-missing-' + step);
    if (!banner) {
      return;
    }
    banner.hidden = false;
    banner.textContent = i18nText('guided.continue_blocked', 'Complete the required answers in this step before continuing.');
  }

  function hideMissingBanners() {
    [1, 2, 3].forEach(function (step) {
      var banner = document.getElementById('pax-ccs-missing-' + step);
      if (banner) {
        banner.hidden = true;
        banner.textContent = '';
      }
    });
  }

  function validateAllSteps() {
    for (var step = 1; step <= 4; step++) {
      if (!validateStep(step)) {
        if (step !== currentStep) {
          showStep(step);
        }
        return false;
      }
    }
    return true;
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

  function countryDisplayValue() {
    var code = fieldValue('country');
    var country = countriesByCode[code];
    return country ? (country.flag + ' ' + countryName(country)) : code;
  }

  function renderReview() {
    if (!reviewEl) {
      return;
    }
    syncPhoneField();
    var rows = [
      { label: document.querySelector('label[for="pax-ccs-full-name"]'), value: fieldValue('full_name') },
      { label: document.querySelector('label[for="pax-ccs-email"]'), value: fieldValue('email') },
      { label: document.querySelector('label[for="pax-ccs-phone-local"]'), value: fieldValue('phone') },
      { label: document.querySelector('label[for="pax-ccs-country-search"]'), value: countryDisplayValue() },
      { label: document.querySelector('label[for="pax-ccs-identity-doc"]'), value: fileSummary(identityDocEl) },
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
      viewActiveOrStart();
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
    if (!validateAllSteps()) {
      return;
    }
    if (requiresLogin() && !isLoggedIn()) {
      showLoginGate();
      return;
    }
    if (!config.ajaxUrl || !config.nonce) {
      showError(i18nText('errors.config', 'Configuration error.'));
      return;
    }

    submitBtn.disabled = true;
    var data = new FormData(form);
    data.append('action', 'paxdesign_cybercrime_report');
    data.append('nonce', config.nonce);
    appendLocale(data);
    var chatSid = getChatSessionId();
    if (chatSid) {
      data.append('chat_session_id', chatSid);
    }

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
          if (json && json.data && json.data.code === 'active_report_exists' && json.data.activeReport) {
            showActiveReport(json.data.activeReport);
            return;
          }
          if (json && json.data && json.data.code === 'document_check_failed') {
            var checkMsg = mapServerError(json, 'submit');
            var extra = json.data.corrections;
            if (Array.isArray(extra) && extra.length) {
              checkMsg = extra.join(' ');
            }
            throw new Error(checkMsg);
          }
          var errMsg = mapServerError(json, 'submit');
          if (json && json.data && json.data.detail) {
            errMsg += ' (' + json.data.detail + ')';
          }
          throw new Error(errMsg);
        }
        if (json.data && json.data.referenceId) {
          fetchActiveReport(json.data.referenceId).then(function (report) {
            if (report) {
              showActiveReport(report);
              return;
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
            if (refEl) {
              refEl.textContent = json.data.referenceId;
            }
            setPageContext(root.getAttribute('data-ccs-lang') || 'ar', json.data.referenceId);
            window.scrollTo({ top: root.offsetTop - 24, behavior: 'smooth' });
          });
          return;
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
        showError(err.message || i18nText('errors.submit', 'Submit failed'));
        submitBtn.disabled = false;
      });
  });

  root.querySelectorAll('[data-ccs-switch]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var next = btn.getAttribute('data-ccs-switch');
      setLang(next);
      if (window.PaxSiteI18n && typeof window.PaxSiteI18n.setLang === 'function') {
        window.PaxSiteI18n.setLang(next);
      }
    });
  });

  if (activeReplySubmit && activeReplyInput) {
    activeReplySubmit.addEventListener('click', function () {
      if (!activeReport || !activeReport.reference_id || !isReportActive(activeReport)) {
        return;
      }
      var message = (activeReplyInput.value || '').trim();
      if (!message) {
        return;
      }
      if (activeReplyError) {
        activeReplyError.hidden = true;
      }
      activeReplySubmit.disabled = true;
      beginMutation();
      var body = new FormData();
      body.append('action', 'paxdesign_cybercrime_customer_reply');
      body.append('nonce', config.nonce);
      body.append('reference', activeReport.reference_id);
      body.append('message', message);
      appendLocale(body);
      fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (!json || !json.success) {
            throw new Error(mapServerError(json, 'reply'));
          }
          activeReplyInput.value = '';
          if (json.data && json.data.report) {
            showActiveReport(json.data.report, true);
          }
        })
        .catch(function (err) {
          if (activeReplyError) {
            activeReplyError.hidden = false;
            activeReplyError.textContent = err.message || i18nText('errors.reply', 'Reply failed');
          }
        })
        .finally(function () {
          activeReplySubmit.disabled = false;
          endMutation();
        });
    });
  }

  if (resubmitSubmitEl) {
    resubmitSubmitEl.addEventListener('click', function () {
      if (!activeReport || !activeReport.reference_id || !isReportActive(activeReport)) {
        return;
      }
      var waiting = needsEvidenceUpload(activeReport);
      var hasFiles = (resubmitIdentityEl && resubmitIdentityEl.files && resubmitIdentityEl.files.length)
        || (resubmitEvidenceEl && resubmitEvidenceEl.files && resubmitEvidenceEl.files.length);
      var note = activeReplyInput ? (activeReplyInput.value || '').trim() : '';
      if (waiting && !hasFiles) {
        if (activeReplyError) {
          activeReplyError.hidden = false;
          activeReplyError.textContent = i18nText('errors.evidence_files_required', 'Please attach at least one evidence file before submitting.');
        }
        return;
      }
      if (!hasFiles && !note) {
        if (activeReplyError) {
          activeReplyError.hidden = false;
          activeReplyError.textContent = i18nText('errors.message_required', 'Please attach a file or add a message.');
        }
        return;
      }
      if (activeReplyError) {
        activeReplyError.hidden = true;
      }
      resubmitSubmitEl.disabled = true;
      beginMutation();
      var body = new FormData();
      body.append('action', 'paxdesign_cybercrime_customer_resubmit');
      body.append('nonce', config.nonce);
      body.append('reference', activeReport.reference_id);
      if (waiting) {
        body.append('evidence_resubmit', '1');
        body.append('pax_evidence_resubmit', '1');
      }
      if (note) {
        body.append('message', note);
      }
      appendResubmitFiles(body);
      appendLocale(body);
      fetch(config.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (!json || !json.success) {
            var msg = mapServerError(json, 'reply');
            if (json && json.data && json.data.code === 'evidence_files_required') {
              msg = i18nText('errors.evidence_files_required', 'Please attach at least one evidence file before submitting.');
            }
            if (json && json.data && Array.isArray(json.data.corrections) && json.data.corrections.length) {
              msg = json.data.corrections.join(' ');
            }
            throw new Error(msg);
          }
          if (hasFiles && json.data && parseInt(json.data.uploaded_count, 10) === 0) {
            throw new Error(i18nText('errors.upload_failed', 'Your files could not be stored. Please try again.'));
          }
          if (activeReplyInput) {
            activeReplyInput.value = '';
          }
          if (resubmitIdentityEl) {
            resubmitIdentityEl.value = '';
          }
          if (resubmitEvidenceEl) {
            resubmitEvidenceEl.value = '';
          }
          renderResubmitPreview();
          if (json.data && json.data.message) {
            showEvidenceSuccess(json.data.message);
          }
          if (json.data && json.data.report) {
            applyIncomingReport(json.data.report, { force: true, source: 'mutation' });
          }
        })
        .catch(function (err) {
          if (activeReplyError) {
            activeReplyError.hidden = false;
            activeReplyError.textContent = err.message || i18nText('errors.reply', 'Update failed');
          }
        })
        .finally(function () {
          resubmitSubmitEl.disabled = false;
          endMutation();
        });
    });
  }

  if (resubmitEvidenceEl) {
    resubmitEvidenceEl.addEventListener('change', renderResubmitPreview);
  }
  if (resubmitIdentityEl) {
    resubmitIdentityEl.addEventListener('change', renderResubmitPreview);
  }

  if (continueFormBtn) {
    continueFormBtn.addEventListener('click', function () {
      continueDraftOnPage();
    });
  }

  window.addEventListener('pax-ccs-case-updated', function (event) {
    var report = event && event.detail ? event.detail.report : null;
    onCcsCaseUpdated(report);
  });

  if (activeChatBtn) {
    activeChatBtn.addEventListener('click', function () {
      openSupportChat(activeReport && activeReport.reference_id ? activeReport.reference_id : '');
    });
  }

  if (backHistoryBtn) {
    backHistoryBtn.addEventListener('click', function () {
      stopReportPolling();
      activeReport = null;
      root.classList.remove('pax-ccs-portal--report-closed');
      setPhase('welcome');
      fetchReportHistory();
      window.scrollTo({ top: (welcomeEl || root).offsetTop - 12, behavior: 'smooth' });
    });
  }

  if (openNewReportBtn) {
    openNewReportBtn.addEventListener('click', function () {
      stopReportPolling();
      startReporting();
    });
  }

  if (refreshReportBtn) {
    refreshReportBtn.addEventListener('click', function () {
      if (!activeReport || !activeReport.reference_id) {
        bootstrapActiveReport();
        return;
      }
      fetchActiveReport(activeReport.reference_id).then(function (report) {
        if (report) {
          applyIncomingReport(report, { force: true });
        }
      });
    });
  }

  if (chatBtn) {
    chatBtn.addEventListener('click', function () {
      var referenceId = refEl ? (refEl.textContent || '').trim() : '';
      openSupportChat(referenceId);
    });
  }

  function initCoverageMarquee() {
    var track = root.querySelector('.pax-ccs-portal__coverage-track');
    if (!track) {
      return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      track.classList.add('is-marquee-static');
      track.style.removeProperty('--ccs-marquee-distance');
      return;
    }

    track.classList.remove('is-marquee-static');

    var segment = track.children[0];
    if (!segment) {
      return;
    }

    var distance = segment.offsetWidth;
    if (track.children.length > 1) {
      distance = track.children[1].offsetLeft - segment.offsetLeft;
    }

    if (!distance) {
      return;
    }

    track.style.setProperty('--ccs-marquee-distance', '-' + Math.round(distance) + 'px');
    track.style.setProperty('--ccs-marquee-duration', '52s');
  }

  var coverageMarqueeResizeTimer = null;
  function scheduleCoverageMarqueeInit() {
    window.requestAnimationFrame(initCoverageMarquee);
  }

  scheduleCoverageMarqueeInit();
  window.addEventListener('resize', function () {
    if (coverageMarqueeResizeTimer) {
      window.clearTimeout(coverageMarqueeResizeTimer);
    }
    coverageMarqueeResizeTimer = window.setTimeout(scheduleCoverageMarqueeInit, 200);
  });
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(scheduleCoverageMarqueeInit).catch(function () {});
  }

  var saved = '';
  try {
    saved = localStorage.getItem('pax_site_lang') || localStorage.getItem('pax-ccs-lang') || '';
  } catch (e) {}
  if (window.PAX_SITE_I18N && window.PAX_SITE_I18N.lang) {
    saved = window.PAX_SITE_I18N.lang;
  }
  setLang(saved === 'de' || saved === 'en' || saved === 'tr' || saved === 'ar' ? saved : 'ar');
  bootstrapActiveReport().then(function (shown) {
    if (!shown) {
      setPhase('welcome');
      maybeResumeAfterLogin();
    }
  });
})();
