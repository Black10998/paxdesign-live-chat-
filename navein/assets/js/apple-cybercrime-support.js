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
  var activeStatusLabelEl = document.getElementById('pax-ccs-active-status-label');
  var activeCategoryEl = document.getElementById('pax-ccs-active-category');
  var activeSubmittedEl = document.getElementById('pax-ccs-active-submitted');
  var activeTimelineEl = document.getElementById('pax-ccs-active-timeline');
  var activeAttachmentsEl = document.getElementById('pax-ccs-active-attachments');
  var activeReplyWrap = document.getElementById('pax-ccs-active-reply-wrap');
  var activeReplyInput = document.getElementById('pax-ccs-active-reply');
  var activeReplySubmit = document.getElementById('pax-ccs-active-reply-submit');
  var activeReplyError = document.getElementById('pax-ccs-active-reply-error');
  var activeClosedNote = document.getElementById('pax-ccs-active-closed-note');
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
  var emailInputEl = document.getElementById('pax-ccs-email');
  var emailFieldWrap = document.getElementById('pax-ccs-email-field-wrap');
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

  function getLang() {
    var lang = root.getAttribute('data-ccs-lang') || 'ar';
    return lang === 'de' || lang === 'en' ? lang : 'ar';
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
    return status !== 'closed' && status !== 'resolved';
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

  function applyReportLifecycle(report) {
    if (!report) {
      return;
    }
    var isActive = isReportActive(report);
    root.classList.toggle('pax-ccs-portal--report-closed', !isActive);
    if (activeReplyWrap) {
      activeReplyWrap.hidden = !isActive;
    }
    if (activeClosedNote) {
      activeClosedNote.hidden = isActive;
    }
    if (backHistoryBtn) {
      backHistoryBtn.hidden = isActive;
    }
    updateStartButtonLabel();
    if (phase === 'active-report') {
      if (isActive) {
        startReportPolling();
      } else {
        stopReportPolling();
      }
    }
  }

  function updateStatusBadge(report) {
    if (!activeStatusBadgeEl || !activeStatusLabelEl || !report) {
      return;
    }
    var badgeKey = report.customer_status || statusBadgeKey(report.status || '');
    var badges = i18nBundle().statusBadges || {};
    var badge = badges[badgeKey] || {};
    var label = (badge.label && badge.label[getLang()]) || report.status || '';
    var emoji = badge.emoji || '';
    activeStatusBadgeEl.className = 'pax-ccs-portal__status-hero pax-ccs-portal__status-hero--' + badgeKey;
    activeStatusLabelEl.textContent = (emoji ? emoji + ' ' : '') + label;
    activeStatusBadgeEl.hidden = !label;
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

  function accountEmailAddress() {
    if (config.accountEmail) {
      return String(config.accountEmail);
    }
    if (window.PAX_AUTH_CONFIG && window.PAX_AUTH_CONFIG.userEmail) {
      return String(window.PAX_AUTH_CONFIG.userEmail);
    }
    return '';
  }

  function isAccountEmailLocked() {
    return isLoggedIn() && (config.emailLocked === true || !!accountEmailAddress());
  }

  function applyAccountEmailField() {
    if (!emailInputEl || !isAccountEmailLocked()) {
      return;
    }
    var email = accountEmailAddress();
    if (!email) {
      return;
    }
    emailInputEl.value = email;
    emailInputEl.readOnly = true;
    emailInputEl.setAttribute('aria-readonly', 'true');
    emailInputEl.setAttribute('data-account-email-locked', '1');
    if (emailFieldWrap) {
      emailFieldWrap.classList.add('pax-ccs-portal__field--account-email-locked');
    }
  }

  function initAccountEmailField() {
    applyAccountEmailField();
    if (!emailInputEl) {
      return;
    }
    emailInputEl.addEventListener('input', function () {
      if (isAccountEmailLocked()) {
        applyAccountEmailField();
      }
    });
    emailInputEl.addEventListener('paste', function (e) {
      if (isAccountEmailLocked()) {
        e.preventDefault();
        applyAccountEmailField();
      }
    });
    emailInputEl.addEventListener('drop', function (e) {
      if (isAccountEmailLocked()) {
        e.preventDefault();
        applyAccountEmailField();
      }
    });
  }

  function accountEmailReviewValue(rawEmail) {
    if (!isAccountEmailLocked()) {
      return rawEmail;
    }
    var verified = i18nText('accountEmail.verifiedNote', 'Verified account email');
    return rawEmail + ' (' + verified + ')';
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
    var url = config.ajaxUrl + '?action=paxdesign_cybercrime_report_list&nonce=' + encodeURIComponent(config.nonce);
    return fetch(url, { credentials: 'same-origin' })
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
      var statusLabel = escapeHtml(report.status_label || report.status || '');
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
    var url = config.ajaxUrl + '?action=paxdesign_cybercrime_active_report&nonce=' + encodeURIComponent(config.nonce);
    if (reference) {
      url = config.ajaxUrl + '?action=paxdesign_cybercrime_report_detail&nonce=' + encodeURIComponent(config.nonce) + '&reference=' + encodeURIComponent(reference);
    }
    return fetch(url, { credentials: 'same-origin' })
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
      var body = escapeHtml(entry.body || '').replace(/\n/g, '<br>');
      var panelId = 'pax-ccs-acc-panel-' + entryId;
      var triggerId = 'pax-ccs-acc-trigger-' + entryId;

      return '<article class="pax-ccs-portal__accordion-item' + (isOpen ? ' is-open' : '') + '" data-entry-id="' + escapeHtml(entryId) + '">'
        + '<button type="button" class="pax-ccs-portal__accordion-trigger" id="' + escapeHtml(triggerId) + '"'
        + ' aria-expanded="' + (isOpen ? 'true' : 'false') + '" aria-controls="' + escapeHtml(panelId) + '">'
        + '<span class="pax-ccs-portal__accordion-head">'
        + '<span class="pax-ccs-portal__accordion-head-row">'
        + '<span class="pax-ccs-portal__accordion-sender">' + escapeHtml(sender) + '</span>'
        + '<span class="pax-ccs-portal__accordion-when">' + escapeHtml(when) + '</span>'
        + '</span>'
        + '<span class="pax-ccs-portal__accordion-chevron" aria-hidden="true"></span>'
        + '</button>'
        + '<div class="pax-ccs-portal__accordion-panel" id="' + escapeHtml(panelId) + '" role="region" aria-labelledby="' + escapeHtml(triggerId) + '">'
        + '<div class="pax-ccs-portal__accordion-panel-inner">'
        + '<div class="pax-ccs-portal__accordion-message">' + body + '</div>'
        + '</div>'
        + '</div>'
        + '</article>';
    }).join('');

    bindTimelineAccordion();
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
        return '<li><a href="' + escapeHtml(file.url) + '" target="_blank" rel="noopener">' + name + '</a></li>';
      }
      return '<li>' + name + '</li>';
    }).join('');
  }

  function showActiveReport(report, forceNewest) {
    if (!report || !activeReportEl) {
      return;
    }
    if (!activeReport || activeReport.reference_id !== report.reference_id) {
      resetTimelineTracking();
    }
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
    if (activeReplyError) {
      activeReplyError.hidden = true;
    }
    updateUnreadBadges(parseInt(report.unread_count, 10) || 0);
    markReportRead(report.reference_id || '').then(function (updated) {
      if (updated) {
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

  function startReportPolling() {
    stopReportPolling();
    if (!activeReport || !activeReport.reference_id) {
      return;
    }
    var poll = function () {
      if (phase !== 'active-report' || !activeReport || !activeReport.reference_id) {
        return;
      }
      fetchActiveReport(activeReport.reference_id).then(function (report) {
        if (report) {
          applyReportRefresh(report);
        }
      });
    };
    reportPollTimer = window.setInterval(poll, 5000);
    if (!reportVisibilityBound) {
      reportVisibilityBound = true;
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden && phase === 'active-report') {
          poll();
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
    if (lang !== 'ar' && lang !== 'de' && lang !== 'en') {
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
    applyAccountEmailField();
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
    if (!valid) {
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
      { label: document.querySelector('label[for="pax-ccs-email"]'), value: accountEmailReviewValue(fieldValue('email')) },
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
      setLang(btn.getAttribute('data-ccs-switch'));
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
      var body = new FormData();
      body.append('action', 'paxdesign_cybercrime_customer_reply');
      body.append('nonce', config.nonce);
      body.append('reference', activeReport.reference_id);
      body.append('message', message);
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
        });
    });
  }

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

  if (refreshReportBtn) {
    refreshReportBtn.addEventListener('click', function () {
      if (!activeReport || !activeReport.reference_id) {
        bootstrapActiveReport();
        return;
      }
      fetchActiveReport(activeReport.reference_id).then(function (report) {
        if (report) {
          applyReportRefresh(report);
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
    saved = localStorage.getItem('pax-ccs-lang') || '';
  } catch (e) {}
  setLang(saved === 'de' || saved === 'en' ? saved : 'ar');
  initAccountEmailField();
  window.addEventListener('pdx-session-updated', function () {
    if (window.PAX_AUTH_CONFIG) {
      config.isLoggedIn = !!window.PAX_AUTH_CONFIG.isLoggedIn;
      if (window.PAX_AUTH_CONFIG.userEmail) {
        config.accountEmail = window.PAX_AUTH_CONFIG.userEmail;
        config.emailLocked = !!window.PAX_AUTH_CONFIG.isLoggedIn;
      }
    }
    applyAccountEmailField();
  });
  bootstrapActiveReport().then(function (shown) {
    if (!shown) {
      setPhase('welcome');
      maybeResumeAfterLogin();
    }
  });
})();
