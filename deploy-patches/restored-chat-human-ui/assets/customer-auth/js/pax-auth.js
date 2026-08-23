/**
 * PaxDesign Auth — login/register UI, session handling, account dashboard.
 */
(function () {
  'use strict';

  var C = typeof PAX_AUTH_CONFIG !== 'undefined' ? PAX_AUTH_CONFIG : null;
  if (!C) return;
  var user = {
    logged_in: !!C.isLoggedIn,
    verified: !!C.emailVerified,
    display_name: C.userName || '',
    email: C.userEmail || '',
    id: C.userId || 0,
    avatar_url: C.avatarUrl || '',
    avatar_has_image: C.avatarHasImage !== false,
  };
  var returnModule = null;
  var currentView = 'login';
  var dashboardData = null;
  var authPageEl = null;
  var authPageFormEl = null;
  var accountAppEl = null;
  var accountSidebarEl = null;
  var accountMainEl = null;
  var accountState = {
    section: 'overview',
    detail: null,
    dashboard: null,
    profile: null,
    files: null,
    settings: null,
    loaded: false,
    masterCustomers: null,
    masterCustomer: null,
    masterLevels: null,
    masterSearch: '',
    masterPage: 1,
    masterPerPage: 50,
  };
  var accountMobileNavOpen = false;
  var accountMobileMenuBtn = null;
  var accountMobileBackdrop = null;
  var accountSignOutConfirmEl = null;
  var sessionSyncInFlight = false;
  var sessionSyncTimer = null;
  var SESSION_SYNC_INTERVAL_MS = 45000;
  var authBroadcast = null;
  var pendingAppleError = '';

  var SVG_GRADIENT = '<defs><linearGradient id="pdx-gradient-stroke" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse"><stop offset="0%" stop-color="black"></stop><stop offset="100%" stop-color="white"></stop></linearGradient></defs>';
  var SVG_EMAIL = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' + SVG_GRADIENT + '<g stroke="url(#pdx-gradient-stroke)" fill="none" stroke-width="1"><path d="M21.6365 5H3L12.2275 12.3636L21.6365 5Z"></path><path d="M16.5 11.5L22.5 6.5V17L16.5 11.5Z"></path><path d="M8 11.5L2 6.5V17L8 11.5Z"></path><path d="M9.5 12.5L2.81805 18.5002H21.6362L15 12.5L12 15L9.5 12.5Z"></path></g></svg>';
  var SVG_LOCK = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' + SVG_GRADIENT + '<g stroke="url(#pdx-gradient-stroke)" fill="none" stroke-width="1"><path d="M3.5 15.5503L9.20029 9.85L12.3503 13L11.6 13.7503H10.25L9.8 15.1003L8 16.0003L7.55 18.2503L5.5 19.6003H3.5V15.5503Z"></path><path d="M16 3.5H11L8.5 6L16 13.5L21 8.5L16 3.5Z"></path><path d="M16 10.5L18 8.5L15 5.5H13L12 6.5L16 10.5Z"></path></g></svg>';
  var SVG_USER = '<svg aria-hidden="true" width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="m15.626 11.769a6 6 0 1 0 -7.252 0 9.008 9.008 0 0 0 -5.374 8.231 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 9.008 9.008 0 0 0 -5.374-8.231zm-7.626-4.769a4 4 0 1 1 4 4 4 4 0 0 1 -4-4zm10 14h-12a1 1 0 0 1 -1-1 7 7 0 0 1 14 0 1 1 0 0 1 -1 1z"></path></svg>';

  var publicModules = C.publicModules || ['trust', 'create', 'workspace'];
  var authMenuOpen = false;
  var authMenuPositionBound = false;
  var profileOverlay = null;

  function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function customerPortalLang() {
    var htmlLang = (document.documentElement.getAttribute('lang') || document.documentElement.lang || '').toLowerCase();
    var navLang = (navigator.language || navigator.userLanguage || '').toLowerCase();
    var lang = htmlLang || navLang || 'de';
    if (lang.indexOf('ar') === 0) return 'ar';
    if (lang.indexOf('en') === 0) return 'en';
    return 'de';
  }

  function t(key, fallback) {
    var lang = customerPortalLang();
    var pack = C.accountUiL10n || {};
    var entry = pack[key];
    if (entry && entry[lang]) return entry[lang];
    if (entry && entry.en) return entry.en;
    return fallback !== undefined ? fallback : key;
  }

  function applyAccountLocale() {
    var lang = customerPortalLang();
    var rtl = lang === 'ar';
    if (accountAppEl) {
      accountAppEl.setAttribute('dir', rtl ? 'rtl' : 'ltr');
      accountAppEl.setAttribute('lang', lang);
    }
    document.body.classList.toggle('pdx-account-rtl', rtl && isAccountDashboard());
    syncAccountMobileMenuPosition();
  }

  function isAccountMobileViewport() {
    return window.matchMedia('(max-width: 900px)').matches;
  }

  function syncAccountMobileNavState() {
    if (!accountMobileMenuBtn) return;
    accountMobileMenuBtn.setAttribute('aria-expanded', accountMobileNavOpen ? 'true' : 'false');
    accountMobileMenuBtn.setAttribute('aria-label', accountMobileNavOpen
      ? t('close', 'Close')
      : t('account_menu', 'Account menu'));
    var openIcon = accountMobileMenuBtn.querySelector('.pdx-account-mobile-menu-icon--menu');
    var closeIcon = accountMobileMenuBtn.querySelector('.pdx-account-mobile-menu-icon--close');
    if (openIcon) openIcon.hidden = accountMobileNavOpen;
    if (closeIcon) closeIcon.hidden = !accountMobileNavOpen;
  }

  function getAccountOverlayRoot() {
    return document.getElementById('pdx-auth-isolated-shell') || document.body;
  }

  function isAccountSidebarPortaled() {
    return !!(accountSidebarEl && accountAppEl && accountSidebarEl.parentNode !== accountAppEl);
  }

  function syncAccountMobileOverlayMount() {
    if (!accountAppEl || !accountSidebarEl || !accountMobileBackdrop) return;
    if (isAccountMobileViewport()) {
      var dir = accountAppEl.getAttribute('dir') || (customerPortalLang() === 'ar' ? 'rtl' : 'ltr');
      var lang = accountAppEl.getAttribute('lang') || customerPortalLang();
      var overlayRoot = getAccountOverlayRoot();
      accountSidebarEl.setAttribute('dir', dir);
      accountSidebarEl.setAttribute('lang', lang);
      accountSidebarEl.classList.add('pdx-account-sidebar--mobile-overlay');
      accountMobileBackdrop.classList.add('pdx-account-mobile-backdrop--portal');
      accountMobileBackdrop.hidden = false;
      if (!isAccountSidebarPortaled()) {
        overlayRoot.appendChild(accountMobileBackdrop);
        overlayRoot.appendChild(accountSidebarEl);
      } else if (accountSidebarEl.parentNode !== overlayRoot) {
        overlayRoot.appendChild(accountMobileBackdrop);
        overlayRoot.appendChild(accountSidebarEl);
      }
    } else if (isAccountSidebarPortaled()) {
      accountSidebarEl.classList.remove('pdx-account-sidebar--mobile-overlay');
      accountMobileBackdrop.classList.remove('pdx-account-mobile-backdrop--portal');
      accountAppEl.insertBefore(accountMobileBackdrop, accountMainEl);
      accountAppEl.insertBefore(accountSidebarEl, accountMainEl);
      closeAccountMobileNav();
    }
  }

  function syncAccountMobileMenuPosition() {
    if (!accountMobileMenuBtn) return;
    var header = document.getElementById('pdx-account-header');
    var useHeader = isAccountMobileViewport() && header;
    accountMobileMenuBtn.hidden = !isAccountMobileViewport();
    if (accountMobileBackdrop) {
      accountMobileBackdrop.hidden = !isAccountMobileViewport();
    }
    if (useHeader && accountMobileMenuBtn.parentNode !== header) {
      header.insertBefore(accountMobileMenuBtn, header.firstChild);
      accountMobileMenuBtn.classList.add('pdx-account-mobile-menu--in-header');
    } else if (!useHeader && accountAppEl && accountMobileMenuBtn.parentNode !== accountAppEl) {
      accountMobileMenuBtn.classList.remove('pdx-account-mobile-menu--in-header');
      accountAppEl.insertBefore(accountMobileMenuBtn, accountMobileBackdrop || accountSidebarEl || accountMainEl);
    }
    syncAccountMobileOverlayMount();
  }

  function openAccountMobileNav() {
    if (!isAccountMobileViewport()) return;
    syncAccountMobileOverlayMount();
    accountMobileNavOpen = true;
    document.body.classList.add('pdx-account-mobile-nav-open');
    if (accountSidebarEl) {
      accountSidebarEl.setAttribute('aria-hidden', 'false');
    }
    syncAccountMobileNavState();
  }

  function closeAccountMobileNav() {
    accountMobileNavOpen = false;
    document.body.classList.remove('pdx-account-mobile-nav-open');
    if (accountSidebarEl) {
      accountSidebarEl.setAttribute('aria-hidden', 'true');
    }
    syncAccountMobileNavState();
  }

  function toggleAccountMobileNav() {
    if (accountMobileNavOpen) closeAccountMobileNav();
    else openAccountMobileNav();
  }

  function ensureAccountMobileChrome() {
    if (!accountAppEl || !accountSidebarEl) return;
    bindAccountSidebarEvents();
    if (!accountMobileBackdrop) {
      accountMobileBackdrop = document.createElement('div');
      accountMobileBackdrop.className = 'pdx-account-mobile-backdrop';
      accountMobileBackdrop.hidden = true;
      accountMobileBackdrop.addEventListener('click', closeAccountMobileNav);
      accountAppEl.insertBefore(accountMobileBackdrop, accountSidebarEl);
    }
    if (!accountMobileMenuBtn) {
      accountMobileMenuBtn = document.createElement('button');
      accountMobileMenuBtn.type = 'button';
      accountMobileMenuBtn.className = 'pdx-account-mobile-menu';
      accountMobileMenuBtn.innerHTML =
        '<span class="pdx-account-mobile-menu-icon pdx-account-mobile-menu-icon--menu">' + cxIcon('menu', 22) + '</span>' +
        '<span class="pdx-account-mobile-menu-icon pdx-account-mobile-menu-icon--close" hidden>' + cxIcon('close', 22) + '</span>';
      accountMobileMenuBtn.addEventListener('click', toggleAccountMobileNav);
      if (accountAppEl) {
        accountAppEl.insertBefore(accountMobileMenuBtn, accountMobileBackdrop);
      }
      window.addEventListener('resize', function () {
        if (!isAccountMobileViewport()) closeAccountMobileNav();
        syncAccountMobileMenuPosition();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && accountMobileNavOpen) closeAccountMobileNav();
      });
    }
    syncAccountMobileMenuPosition();
    syncAccountMobileOverlayMount();
    syncAccountMobileNavState();
    if (accountSidebarEl && isAccountMobileViewport()) {
      accountSidebarEl.setAttribute('aria-hidden', accountMobileNavOpen ? 'false' : 'true');
    }
  }

  function stripHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.innerHTML = String(s);
    return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
  }

  function renderTrustedNewsHtml(html) {
    if (!html) return '';
    return String(html);
  }

  function normalizeRestMessage(data) {
    if (!data || typeof data !== 'object') {
      return 'Something went wrong. Please try again.';
    }
    var msg = data.message;
    if (msg && typeof msg === 'object') {
      msg = msg.raw || msg.rendered || msg.message || '';
    }
    msg = stripHtml(msg || '');
    if (msg && msg.indexOf('<') === -1 && msg.length <= 280) {
      return msg;
    }
    if (msg && msg.length > 280) {
      return msg.slice(0, 280) + '…';
    }
    var code = String(data.code || data.error || '').toLowerCase();
    var map = {
      invalid_credentials: 'Invalid email or password.',
      invalid_email: 'Please enter a valid email address.',
      locked: 'Too many failed attempts. Please wait a moment and try again.',
      suspended: 'Your account has been suspended. Please contact support.',
      rest_cookie_invalid_nonce: 'Session expired. Please reload the page and try again.',
      rest_invalid_nonce: 'Session expired. Please reload the page and try again.',
      network: 'Network error. Please check your connection and try again.',
    };
    if (map[code]) return map[code];
    return 'Something went wrong. Please try again.';
  }

  function friendlyHttpError(status) {
    if (status === 401) return 'Invalid email or password.';
    if (status === 403) return 'Access denied. Please reload the page and try again.';
    if (status === 429) return 'Too many attempts. Please wait a moment and try again.';
    if (status >= 500) return 'Server error. Please try again in a moment.';
    return 'Something went wrong. Please try again.';
  }

  /** Server-driven PAXDesign verified badge — only when verified === true from API. */
  function accountStatusText(verified) {
    if (user.is_admin) return t('administrator', 'Administrator');
    return verified ? t('verified', 'Verified') : t('pending_verification', 'Pending verification');
  }

  function verifiedBadgeHtml(verified, opts) {
    if (window.PDXVerifiedBadge) return window.PDXVerifiedBadge.render(verified, opts);
    return '';
  }

  function nameWithBadge(name, verified, opts) {
    if (window.PDXVerifiedBadge) return window.PDXVerifiedBadge.nameWithBadge(name, verified, opts);
    return escHtml(name || t('account', 'Account'));
  }

  function accountDashboardNameWithBadge(name, verified, opts) {
    opts = opts || {};
    opts.context = opts.context || 'account';
    return '<span class="pdx-name-with-badge pdx-name-with-badge--account">' +
      '<span class="pdx-account-name-text">' + escHtml(name || t('account', 'Account')) + '</span>' +
      verifiedBadgeHtml(verified, opts) +
    '</span>';
  }

  function renderPublicUserIdentityHtml(opts) {
    opts = opts || {};
    var name = opts.name || user.display_name || t('account', 'Account');
    var avatarClass = opts.avatarClass || 'pdx-account-avatar--header';
    var showName = opts.showName !== false;
    var profile = opts.profile || accountProfileData();
    var avatarHtml = renderAccountAvatarHtml({ sizeClass: avatarClass, url: opts.url, profile: profile });
    return '<span class="pdx-public-user-identity">' +
      avatarHtml +
      (showName ? '<span class="pdx-public-user-name">' + escHtml(name) + '</span>' : '') +
    '</span>';
  }

  function cleanupLegacyHeaderIdentityNodes(root) {
    var scope = root || authBar;
    if (!scope) return;
    scope.querySelectorAll(
      '.pdx-auth-account-label, .pdx-auth-trigger, .pdx-auth-trigger-label, .pdx-name-with-badge, .pdx-name-with-badge--account, .pdx-public-user-identity, .pdx-public-user-name'
    ).forEach(function (node) {
      if (!node.closest('.pdx-auth-account-identity') && !node.closest('.pdx-auth-menu')) {
        node.remove();
      }
    });
  }

  function bindSignupButton(signupBtn) {
    if (!signupBtn || signupBtn.dataset.pdxBound === '1') return;
    signupBtn.dataset.pdxBound = '1';
    signupBtn.addEventListener('click', function () { navigateToAuthPage('login'); });
  }

  function syncHeaderAuthControls() {
    if (!authBar) return;
    var inner = authBar.querySelector('.pdx-auth-bar-inner');
    if (!inner) return;

    inner.querySelectorAll('.pdx-auth-portal-btn').forEach(function (node) { node.remove(); });

    var accountBtn = inner.querySelector('.pdx-auth-account-btn');
    var authMenuEl = inner.querySelector('.pdx-auth-menu');
    var signupBtn = inner.querySelector('.pdx-auth-signup-btn');

    authBar.classList.toggle('pdx-auth-bar--logged-in', !!user.logged_in);
    authBar.classList.toggle('pdx-auth-bar--logged-out', !user.logged_in);

    if (user.logged_in) {
      if (signupBtn) signupBtn.remove();
      if (accountBtn) {
        accountBtn.hidden = false;
        accountBtn.removeAttribute('hidden');
      }
    } else {
      if (accountBtn) {
        accountBtn.hidden = true;
        accountBtn.setAttribute('hidden', 'hidden');
        var identityEl = accountBtn.querySelector('.pdx-auth-account-identity');
        if (identityEl) identityEl.innerHTML = '';
      }
      closeAuthMenu();
      if (!signupBtn) {
        signupBtn = document.createElement('button');
        signupBtn.type = 'button';
        signupBtn.className = 'pdx-auth-signup-btn pdx-cx-btn pdx-auth-header-btn';
        signupBtn.textContent = t('sign_in', 'Anmelden');
        inner.insertBefore(signupBtn, accountBtn || authMenuEl);
        bindSignupButton(signupBtn);
      } else if (!signupBtn.parentNode) {
        inner.insertBefore(signupBtn, accountBtn || authMenuEl);
      }
      signupBtn.hidden = false;
      signupBtn.removeAttribute('hidden');
      signupBtn.style.removeProperty('display');
      signupBtn.style.removeProperty('visibility');
    }

    cleanupLegacyHeaderIdentityNodes(authBar);
    ensureHeaderUtilityCluster();
    stabilizeDesktopHeaderAuthLayout();
  }

  function renderHeaderUserIdentityHtml(opts) {
    opts = opts || {};
    var name = opts.name || user.display_name || t('account', 'Account');
    var showName = opts.showName !== false;
    var profile = opts.profile || accountProfileData();
    var avatarHtml = renderAccountAvatarHtml({
      sizeClass: 'pdx-account-avatar--header',
      url: opts.url,
      profile: profile,
      alt: '',
    });
    var levelHtml = showName ? renderCustomerLevelBadge(profile, { compact: true, header: true }) : '';
    var textHtml = showName
      ? '<span class="pdx-header-user-text">' +
          '<span class="pdx-header-user-name">' + escHtml(name) + '</span>' +
          levelHtml +
        '</span>'
      : '';
    return '<span class="pdx-header-user-identity">' + avatarHtml + textHtml + '</span>';
  }

  function defaultAvatarUrl() {
    return normalizeAvatarAssetUrl(C.defaultAvatarUrl || '');
  }

  function normalizeAvatarAssetUrl(url) {
    if (!url) return '';
    var normalized = String(url).replace(/(\/avatars\/pax-\d{2,3})\.svg(\?.*)?$/i, '$1.gif');
    if (normalized.indexOf('/avatars/pax-') !== -1 && /\.gif(\?|$)/i.test(normalized) && normalized.indexOf('?') === -1 && C.version) {
      normalized += '?v=' + encodeURIComponent(C.version);
    }
    return normalized;
  }

  function accountAvatarPresetUrl(presetId) {
    if (!presetId || presetId === 'pax-none') return '';
    var presets = accountAvatarPresets().concat(accountVipAvatarPresets());
    for (var i = 0; i < presets.length; i++) {
      if (presets[i].id === presetId) {
        return normalizeAvatarAssetUrl(presets[i].url || '');
      }
    }
    var sample = defaultAvatarUrl();
    if (sample && /\/avatars\/pax-\d{2,3}\.gif/i.test(sample)) {
      return normalizeAvatarAssetUrl(sample.replace(/pax-\d{2,3}\.gif/i, presetId + '.gif'));
    }
    if (/^pax-vip-\d{2}$/.test(presetId)) {
      var vipSample = accountVipAvatarPresets()[0];
      if (vipSample && vipSample.url) {
        return normalizeAvatarAssetUrl(String(vipSample.url).replace(/pax-vip-\d{2}\.(svg|gif)/i, presetId + '.gif'));
      }
    }
    return '';
  }

  function accountVipAvatarPresets() {
    if (!Array.isArray(C.vipAvatarPresets)) return [];
    return C.vipAvatarPresets.map(function (preset) {
      if (!preset) return preset;
      preset.url = normalizeAvatarAssetUrl(preset.url || '');
      return preset;
    });
  }

  function refreshAccountAvatarPresets() {
    return customerApiFetch('GET', '/customer/profile/avatars?_=' + Date.now()).then(function (data) {
      if (data && data._ok !== false && Array.isArray(data.presets) && data.presets.length) {
        C.avatarPresets = data.presets.map(function (preset) {
          if (!preset || preset.type === 'none' || preset.id === 'pax-none') return preset;
          preset.url = normalizeAvatarAssetUrl(preset.url || '');
          return preset;
        });
      }
      if (data && data._ok !== false && Array.isArray(data.vip_presets)) {
        C.vipAvatarPresets = data.vip_presets.map(function (preset) {
          if (!preset) return preset;
          preset.url = normalizeAvatarAssetUrl(preset.url || '');
          return preset;
        });
      }
    }).catch(function () {});
  }

  function isExplicitCustomerProfile(profile) {
    if (!profile || typeof profile !== 'object') return false;
    if (profile.id && user && user.id && Number(profile.id) !== Number(user.id)) return true;
    return profile.has_customer_level !== undefined || profile.customer_level !== undefined;
  }

  function accountLevelData(profile, opts) {
    opts = opts || {};
    profile = profile || accountProfileData();
    var strict = !!opts.strict || isExplicitCustomerProfile(profile);
    if (strict) {
      return {
        customer_level: Number(profile.customer_level) || 0,
        level_label: profile.level_label || '',
        level_title: profile.level_title || '',
        level_description: profile.level_description || '',
        has_customer_level: !!profile.has_customer_level,
      };
    }
    return {
      customer_level: profile.customer_level || user.customer_level || (C.customerLevel && C.customerLevel.customer_level) || 0,
      level_label: profile.level_label || user.level_label || (C.customerLevel && C.customerLevel.level_label) || '',
      level_title: profile.level_title || user.level_title || (C.customerLevel && C.customerLevel.level_title) || '',
      level_description: profile.level_description || user.level_description || (C.customerLevel && C.customerLevel.level_description) || '',
      has_customer_level: !!(profile.has_customer_level || user.has_customer_level || (C.customerLevel && C.customerLevel.has_customer_level)),
    };
  }

  function renderCustomerLevelBadge(profile, opts) {
    opts = opts || {};
    var level = accountLevelData(profile, opts);
    if (!level.has_customer_level || !level.level_label) return '';
    var compact = opts.compact ? ' pdx-account-level-badge--compact' : '';
    var header = opts.header ? ' pdx-account-level-badge--header' : '';
    var title = level.level_title ? ' title="' + escHtml(level.level_title + (level.level_description ? ' — ' + level.level_description : '')) + '"' : '';
    return '<span class="pdx-account-level-badge' + compact + header + '"' + title + '>' + escHtml(level.level_label) + '</span>';
  }

  function isMasterAdminUser() {
    return !!(user.is_master_admin || C.isMasterAdmin);
  }

  function accountProfileData() {
    var profile = accountState.profile || {};
    if (profile.profile && typeof profile.profile === 'object') {
      profile = profile.profile;
    }
    return profile;
  }

  function accountAvatarUrl(profile) {
    profile = profile || accountProfileData();
    if (profile.avatar_has_image === false) return '';
    if (profile.avatar_has_upload) {
      return normalizeAvatarAssetUrl(profile.avatar_url || user.avatar_url || C.avatarUrl || '');
    }
    var presetId = profile.avatar_preset || user.avatar_preset || '';
    if (presetId && presetId !== 'pax-none') {
      var presetUrl = accountAvatarPresetUrl(presetId);
      if (presetUrl) return presetUrl;
    }
    var url = profile.avatar_url || user.avatar_url || C.avatarUrl || '';
    url = normalizeAvatarAssetUrl(url);
    return url || defaultAvatarUrl();
  }

  function accountAvatarHasImage(profile) {
    profile = profile || accountProfileData();
    if (profile && profile.avatar_has_image === false) return false;
    if (profile && profile.avatar_has_image === true) return true;
    if (user.avatar_has_image === false) return false;
    if (profile.avatar_has_upload) return true;
    if (profile.avatar_preset === 'pax-none') return false;
    if (user.avatar_preset === 'pax-none') return false;
    return !!(profile.avatar_url || user.avatar_url || C.avatarUrl || defaultAvatarUrl());
  }

  function accountAvatarFallbackUrl(profile) {
    profile = profile || accountProfileData();
    if (!accountAvatarHasImage(profile)) return '';
    if (profile.avatar_has_upload) {
      var presetId = profile.avatar_preset || user.avatar_preset || '';
      if (presetId && presetId !== 'pax-none') {
        var presetUrl = accountAvatarPresetUrl(presetId);
        if (presetUrl) return presetUrl;
      }
    }
    return normalizeAvatarAssetUrl(profile.avatar_fallback_url || C.avatarFallbackUrl || defaultAvatarUrl());
  }

  function accountAvatarPresets() {
    if (!Array.isArray(C.avatarPresets)) return [];
    return C.avatarPresets.map(function (preset) {
      if (!preset || preset.type === 'none' || preset.id === 'pax-none') return preset;
      return {
        id: preset.id,
        label: preset.label,
        url: normalizeAvatarAssetUrl(preset.url || ''),
        type: preset.type,
      };
    });
  }

  function handleAccountAvatarImgError(img) {
    if (!img || img.dataset.pdxAvatarFailed === '1') return;
    var fallback = img.getAttribute('data-avatar-fallback') || defaultAvatarUrl();
    if (!fallback) {
      var wrap = img.closest('.pdx-account-avatar');
      if (wrap) wrap.remove();
      return;
    }
    var current = img.currentSrc || img.src || '';
    if (current && current.indexOf(fallback) !== -1) return;
    img.dataset.pdxAvatarFailed = '1';
    img.src = fallback;
  }

  var accountAvatarFallbackBound = false;
  function ensureAccountAvatarFallbackHandler() {
    if (accountAvatarFallbackBound) return;
    accountAvatarFallbackBound = true;
    document.addEventListener('error', function (e) {
      var img = e.target;
      if (!img || !img.classList || !img.classList.contains('pdx-account-avatar__img')) return;
      handleAccountAvatarImgError(img);
    }, true);
    window.__pdxAvatarFallback = handleAccountAvatarImgError;
  }

  function bindAccountAvatarFallbacks(root) {
    ensureAccountAvatarFallbackHandler();
    root = root || document;
    root.querySelectorAll('.pdx-account-avatar__img[data-avatar-fallback]').forEach(function (img) {
      img.removeAttribute('data-pdx-avatar-failed');
    });
  }

  var ACCOUNT_AVATAR_PX = {
    'pdx-account-avatar--header': 32,
    'pdx-account-avatar--menu': 44,
    'pdx-account-avatar--sidebar': 40,
    'pdx-account-avatar--profile-compact': 64,
  };

  function renderAccountAvatarHtml(opts) {
    opts = opts || {};
    var profile = opts.profile || accountProfileData();
    if (opts.show === false || !accountAvatarHasImage(profile)) {
      return '';
    }
    var url = opts.url || accountAvatarUrl(profile);
    if (!url) return '';
    var fallbackUrl = opts.fallbackUrl || accountAvatarFallbackUrl(profile);
    var sizeClass = opts.sizeClass || 'pdx-account-avatar--sidebar';
    var px = ACCOUNT_AVATAR_PX[sizeClass] || 40;
    var alt = opts.alt || user.display_name || t('account', 'Account');
    return '<span class="pdx-account-avatar ' + sizeClass + '" style="width:' + px + 'px;height:' + px + 'px;max-width:' + px + 'px;max-height:' + px + 'px;flex:0 0 ' + px + 'px">' +
      '<img class="pdx-account-avatar__img" src="' + escHtml(url) + '" data-avatar-fallback="' + escHtml(fallbackUrl) + '" alt="' + escHtml(alt) + '" width="' + px + '" height="' + px + '" loading="lazy" decoding="async" onerror="window.__pdxAvatarFallback&&window.__pdxAvatarFallback(this)" />' +
    '</span>';
  }

  function renderAccountAvatarPickerHtml(profile) {
    profile = profile || accountProfileData();
    var presets = accountAvatarPresets();
    if (!presets.length) return '';
    var currentPreset = profile.avatar_preset || '';
    var hasUpload = !!profile.avatar_has_upload;
    var html = '<div class="pdx-account-avatar-picker">' +
      '<div class="pdx-account-avatar-picker__title">' + escHtml(t('choose_paxdesign_avatar', 'Choose a PAXDesign avatar')) + '</div>' +
      '<div class="pdx-account-avatar-picker__grid" role="listbox" aria-label="' + escHtml(t('paxdesign_avatars', 'PAXDesign avatars')) + '">';
    presets.forEach(function (preset) {
      var isNone = preset.type === 'none' || preset.id === 'pax-none';
      var selected = !hasUpload && preset.id === currentPreset;
      if (isNone) {
        html += '<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--none' + (selected ? ' is-selected' : '') + '" role="option" data-avatar-preset="' + escHtml(preset.id) + '" aria-label="' + escHtml(preset.label || t('no_profile_picture', 'No profile picture')) + '" aria-selected="' + (selected ? 'true' : 'false') + '" title="' + escHtml(preset.label || t('no_profile_picture', 'No profile picture')) + '">' +
          '<span class="pdx-account-avatar-picker__none-mark" aria-hidden="true"></span>' +
          '<span class="pdx-account-avatar-picker__none-text">' + escHtml(preset.label || t('no_profile_picture', 'No profile picture')) + '</span>' +
        '</button>';
        return;
      }
      html += '<button type="button" class="pdx-account-avatar-picker__item' + (selected ? ' is-selected' : '') + '" role="option" data-avatar-preset="' + escHtml(preset.id) + '" aria-label="' + escHtml(preset.label || preset.id) + '" aria-selected="' + (selected ? 'true' : 'false') + '" title="' + escHtml(preset.label || preset.id) + '">' +
        '<img src="' + escHtml(normalizeAvatarAssetUrl(preset.url || '')) + '" alt="" width="48" height="48" loading="lazy" decoding="async" />' +
      '</button>';
    });
    html += '</div>';

    var vipPresets = accountVipAvatarPresets();
    if (vipPresets.length) {
      html += '<div class="pdx-account-avatar-picker__subtitle">' + escHtml(t('exclusive_level_avatars', 'PAXDesign Level avatars')) + '</div>' +
        '<div class="pdx-account-avatar-picker__grid pdx-account-avatar-picker__grid--vip" role="listbox" aria-label="' + escHtml(t('vip_avatars', 'VIP avatars')) + '">';
      vipPresets.forEach(function (preset) {
        var locked = !!preset.locked && !isMasterAdminUser();
        var selected = !hasUpload && !locked && preset.id === currentPreset;
        var lockLabel = t('vip_avatar_locked', 'Exclusive avatar — assigned by administrator only');
        html += '<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--vip' + (locked ? ' pdx-account-avatar-picker__item--locked' : '') + (selected ? ' is-selected' : '') + '" role="option"' +
          ' data-avatar-preset="' + escHtml(preset.id) + '"' +
          (locked ? ' data-avatar-locked="1" disabled aria-disabled="true"' : '') +
          ' aria-label="' + escHtml(locked ? lockLabel : (preset.label || preset.id)) + '"' +
          ' aria-selected="' + (selected ? 'true' : 'false') + '"' +
          ' title="' + escHtml(locked ? lockLabel : (preset.label || preset.id)) + '">' +
          '<img src="' + escHtml(preset.url || '') + '" alt="" width="48" height="48" loading="lazy" decoding="async" />' +
          (locked ? '<span class="pdx-account-avatar-picker__lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5zm0 2a3 3 0 0 1 3 3v3H9V6a3 3 0 0 1 3-3z"/></svg></span>' : '') +
        '</button>';
      });
      html += '</div>';
    }

    html += '</div>';
    return html;
  }

  function applyAccountUserFromPayload(payload) {
    if (!payload || typeof payload !== 'object') return;
    if (payload.display_name) user.display_name = payload.display_name;
    if (payload.email) user.email = payload.email;
    if (payload.verified !== undefined) user.verified = !!payload.verified;
    if (payload.avatar_url !== undefined) {
      user.avatar_url = normalizeAvatarAssetUrl(payload.avatar_url);
      C.avatarUrl = user.avatar_url;
    }
    if (payload.avatar_fallback_url !== undefined) {
      C.avatarFallbackUrl = normalizeAvatarAssetUrl(payload.avatar_fallback_url);
    }
    if (payload.avatar_has_image !== undefined) {
      user.avatar_has_image = !!payload.avatar_has_image;
    }
    if (payload.customer_level !== undefined) user.customer_level = payload.customer_level;
    if (payload.level_label !== undefined) user.level_label = payload.level_label;
    if (payload.level_title !== undefined) user.level_title = payload.level_title;
    if (payload.level_description !== undefined) user.level_description = payload.level_description;
    if (payload.has_customer_level !== undefined) user.has_customer_level = !!payload.has_customer_level;
    if (payload.is_master_admin !== undefined) {
      user.is_master_admin = !!payload.is_master_admin;
      C.isMasterAdmin = !!payload.is_master_admin;
    }
  }

  function applyAccountProfileUpdate(profile) {
    accountState.profile = profile || {};
    applyAccountUserFromPayload(accountState.profile);
    renderAccountApp();
    updateAuthBar();
  }

  function unwrapProfileResponse(raw) {
    if (!raw || raw._ok === false) return {};
    if (raw.profile && typeof raw.profile === 'object') return raw.profile;
    return raw;
  }

  function normalizeDashboardResponse(raw) {
    if (!raw || raw._status === 401 || raw._ok === false) return null;
    if (raw.code === 'pdx_email_unverified' || raw.code === 'pdx_account_suspended') return raw;
    var dash = raw.dashboard && typeof raw.dashboard === 'object' ? raw.dashboard : raw;
    if (!dash || typeof dash !== 'object') return null;
    dash.projects_active = Array.isArray(dash.projects_active) ? dash.projects_active : [];
    dash.projects_recent = Array.isArray(dash.projects_recent) ? dash.projects_recent : [];
    dash.orders_recent = Array.isArray(dash.orders_recent) ? dash.orders_recent : [];
    dash.news = Array.isArray(dash.news) ? dash.news : [];
    dash.notifications = Array.isArray(dash.notifications) ? dash.notifications : [];
    dash.unread_count = dash.unread_count || 0;
    dash.chat = dash.chat || {};
    if (dash.user) applyAccountUserFromPayload(dash.user);
    return dash;
  }

  function enrichAccountDashboard(dashboard) {
    if (!dashboard || dashboard.code) return Promise.resolve(dashboard);
    var tasks = [];
    if (!dashboard.news.length) {
      tasks.push(customerApiFetch('GET', '/customer/news').then(function (data) {
        if (data && data._ok !== false && Array.isArray(data.items) && data.items.length) {
          dashboard.news = data.items;
        }
      }));
    }
    if (!dashboard.orders_recent.length) {
      tasks.push(customerApiFetch('GET', '/customer/orders').then(function (data) {
        if (data && data._ok !== false && Array.isArray(data.orders) && data.orders.length) {
          dashboard.orders_recent = data.orders.slice(0, 5);
        }
      }));
    }
    if (!dashboard.projects_active.length) {
      tasks.push(customerApiFetch('GET', '/customer/projects').then(function (data) {
        if (data && data._ok !== false && Array.isArray(data.projects) && data.projects.length) {
          dashboard.projects_active = data.projects.filter(function (p) {
            return ['planning', 'in_progress', 'active', 'review'].indexOf(p.status) >= 0;
          });
          dashboard.projects_recent = data.projects.slice(0, 5);
        }
      }));
    }
    if (dashboard.unread_count === 0 && (!dashboard.notifications || !dashboard.notifications.length)) {
      tasks.push(customerApiFetch('GET', '/customer/notifications?limit=10').then(function (data) {
        if (data && data._ok !== false) {
          if (Array.isArray(data.items)) dashboard.notifications = data.items;
          if (data.unread_count) dashboard.unread_count = data.unread_count;
        }
      }));
    }
    return Promise.all(tasks).then(function () { return dashboard; });
  }

  var accountSidebarEventsBound = false;
  var accountMainEventsBound = false;

  function bindAccountSidebarEvents() {
    if (!accountSidebarEl || accountSidebarEventsBound) return;
    accountSidebarEventsBound = true;
    accountSidebarEl.addEventListener('click', function (e) {
      var closeBtn = e.target.closest('.pdx-account-sidebar-close');
      if (closeBtn) {
        e.preventDefault();
        e.stopPropagation();
        closeAccountMobileNav();
        return;
      }
      var navBtn = e.target.closest('[data-account-section]');
      if (navBtn) {
        setAccountSection(navBtn.getAttribute('data-account-section'));
        return;
      }
      var signOutBtn = e.target.closest('.pdx-account-signout');
      if (signOutBtn) {
        e.preventDefault();
        promptAccountSignOut();
      }
    });
  }

  function bindAccountMainEvents() {
    if (!accountMainEl || accountMainEventsBound) return;
    accountMainEventsBound = true;
    accountMainEl.addEventListener('click', function (e) {
      var jump = e.target.closest('[data-account-section]');
      if (!jump || jump.closest('form')) return;
      var section = jump.getAttribute('data-account-section');
      if (!section) return;
      e.preventDefault();
      setAccountSection(section);
    });
  }

  function accountLanguageLabel() {
    var lang = customerPortalLang();
    if (lang === 'ar') return t('language_ar', 'العربية');
    if (lang === 'en') return t('language_en', 'English');
    return t('language_de', 'Deutsch');
  }

  function renderAppleRow(opts) {
    opts = opts || {};
    var isLink = !!opts.href;
    var extra = '';
    if (opts.section) extra += ' data-account-section="' + escHtml(opts.section) + '"';
    if (isLink) extra += ' href="' + escHtml(opts.href) + '"';
    else extra += ' type="button"';
    if (opts.external) extra += ' target="_blank" rel="noopener"';
    var icon = opts.icon ? '<span class="pdx-apple-row__icon">' + cxIcon(opts.icon, 18) + '</span>' : '';
    var value = opts.value ? '<span class="pdx-apple-row__value">' + escHtml(opts.value) + '</span>' : '';
    var chevron = opts.chevron === false ? '' : '<span class="pdx-apple-row__chevron" aria-hidden="true">' + cxIcon('chevron', 14) + '</span>';
    var tag = isLink ? 'a' : 'button';
    return '<' + tag + ' class="pdx-apple-row' + (opts.className ? ' ' + opts.className : '') + '"' + extra + '>' +
      icon +
      '<span class="pdx-apple-row__text"><span class="pdx-apple-row__label">' + escHtml(opts.label || '') + '</span></span>' +
      value +
      chevron +
    '</' + tag + '>';
  }

  function renderAppleGroup(rows, caption) {
    var html = '<div class="pdx-apple-group">' + (rows || []).join('') + '</div>';
    if (caption) {
      html += '<p class="pdx-apple-caption">' + escHtml(caption) + '</p>';
    }
    return html;
  }

  function cxIcon(name, size) {
    if (window.PDXCustomerIcons) return window.PDXCustomerIcons.svg(name, size || 18);
    return '';
  }

  function pearlBtn(label, opts) {
    opts = opts || {};
    var cls = 'pdx-btn-pearl' + (opts.small ? ' pdx-btn-pearl--sm' : '') + (opts.inline ? ' pdx-btn-pearl--inline' : '');
    var iconHtml = opts.icon ? cxIcon(opts.icon, 16) : '';
    return '<button type="' + (opts.type || 'submit') + '" class="' + cls + '">' +
      '<span class="pdx-btn-pearl__wrap">' + iconHtml + '<span>' + escHtml(label) + '</span></span></button>';
  }

  function isAccountDashboard() {
    return document.body.classList.contains('pdx-account-dashboard-body') || (isAuthPage() && !!user.logged_in);
  }

  function portalBtn(label, opts) {
    opts = opts || {};
    var cls = 'pdx-portal-btn';
    if (opts.variant === 'secondary') cls += ' pdx-portal-btn--secondary';
    else if (opts.variant === 'destructive') cls += ' pdx-portal-btn--destructive';
    else if (opts.variant === 'ghost') cls += ' pdx-portal-btn--ghost';
    if (opts.small) cls += ' pdx-portal-btn--sm';
    if (opts.inline) cls += ' pdx-portal-btn--inline';
    if (opts.full) cls += ' pdx-portal-btn--full';
    if (opts.className) cls += ' ' + opts.className;
    var iconHtml = opts.icon ? cxIcon(opts.icon, 16) : '';
    var idAttr = opts.id ? ' id="' + escHtml(opts.id) + '"' : '';
    return '<button type="' + (opts.type || 'submit') + '" class="' + cls + '"' + idAttr + '>' +
      iconHtml + '<span>' + escHtml(label) + '</span></button>';
  }

  function actionBtn(label, opts) {
    if (isAccountDashboard()) return portalBtn(label, opts);
    return pearlBtn(label, opts);
  }

  function cxLoading(label) {
    return '<div class="pdx-cx-loading"><div class="pdx-cx-loading__spinner"></div><span>' + escHtml(label || t('loading', 'Loading…')) + '</span></div>';
  }

  function isRestNonceError(data) {
    if (!data) return false;
    var code = data.code || data.error || '';
    return code === 'rest_cookie_invalid_nonce' || code === 'rest_invalid_nonce';
  }

  function applySession(data, meta) {
    if (!data) return;
    meta = meta || {};
    var before = userSnapshot();
    if (data.nonce) C.nonce = data.nonce;
    var u = data.user || data;
    if (u.logged_in !== undefined) {
      user = u;
      if (u.avatar_url !== undefined) C.avatarUrl = normalizeAvatarAssetUrl(u.avatar_url);
      if (u.avatar_has_image !== undefined) user.avatar_has_image = !!u.avatar_has_image;
      C.isLoggedIn = !!u.logged_in;
      C.emailVerified = !!u.verified;
      C.userId = u.id || 0;
      C.userName = u.display_name || '';
      C.userEmail = u.email || '';
      if (u.logged_in) {
        clearPendingAppleError();
      }
    }
    updateAuthBar();
    updateAuthPagePanels();
    if (meta.reason === 'logout') {
      dashboardData = null;
      accountState.loaded = false;
      accountState.dashboard = null;
      closeCustomerPortal();
      closeProfileOverlay();
      closeOverlay();
      finalizeAccountLogoutUI();
    }
    try {
      var detail = Object.assign({}, user || {}, {
        reason: meta.reason || '',
      });
      window.dispatchEvent(new CustomEvent('pdx-session-updated', { detail: detail }));
    } catch (e) {}
    if (meta.broadcast !== false && sessionStateChanged(before, userSnapshot())) {
      broadcastSessionChange();
    }
  }

  function userSnapshot() {
    return {
      id: user.id || 0,
      logged_in: !!user.logged_in,
      verified: !!user.verified,
    };
  }

  function sessionStateChanged(before, after) {
    if (!before || !after) return true;
    return before.id !== after.id
      || before.logged_in !== after.logged_in
      || before.verified !== after.verified;
  }

  function detectSessionChangeReason(before, after) {
    if (!after.logged_in && before.logged_in) return 'logout';
    if (after.logged_in && !before.logged_in) return 'login';
    if (after.id && before.id && after.id !== before.id) return 'user_switch';
    if (after.verified !== before.verified) return 'verification';
    return 'session_update';
  }

  function broadcastSessionChange() {
    try {
      if (authBroadcast) {
        authBroadcast.postMessage({ type: 'session-changed', at: Date.now() });
      }
    } catch (e) {}
  }

  function syncSessionFromServer(trigger, options) {
    options = options || {};
    if (sessionSyncInFlight) return Promise.resolve(false);
    sessionSyncInFlight = true;
    var before = userSnapshot();
    var cacheBust = options.cacheBust ? ('?_=' + Date.now()) : '';
    return apiFetch('GET', '/auth/me' + cacheBust).then(function (data) {
      var after = {
        id: data.id || (data.user && data.user.id) || 0,
        logged_in: data.logged_in !== undefined ? !!data.logged_in : !!(data.user && data.user.logged_in),
        verified: data.verified !== undefined ? !!data.verified : !!(data.user && data.user.verified),
      };
      if (sessionStateChanged(before, after)) {
        applySession(data, {
          reason: detectSessionChangeReason(before, after),
          broadcast: false,
          trigger: trigger || '',
        });
        return true;
      }
      if (data.nonce) C.nonce = data.nonce;
      return false;
    }).catch(function () {
      return false;
    }).finally(function () {
      sessionSyncInFlight = false;
    });
  }

  function bindSessionAutoSync() {
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        syncSessionFromServer('visibility', { cacheBust: true });
      }
    });
    window.addEventListener('focus', function () {
      syncSessionFromServer('focus', { cacheBust: true });
    });
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) syncSessionFromServer('pageshow', { cacheBust: true });
    });
    try {
      authBroadcast = new BroadcastChannel('pdx-auth-session');
      authBroadcast.onmessage = function (ev) {
        if (!ev || !ev.data || ev.data.type !== 'session-changed') return;
        syncSessionFromServer('broadcast', { cacheBust: true });
      };
    } catch (e) {
      authBroadcast = null;
    }
    if (sessionSyncTimer) clearInterval(sessionSyncTimer);
    sessionSyncTimer = setInterval(function () {
      if (document.visibilityState === 'visible') {
        syncSessionFromServer('interval', { cacheBust: true });
      }
    }, SESSION_SYNC_INTERVAL_MS);
  }

  function isAuthPage() {
    return !!(C.isAuthPage || document.getElementById('pdx-auth-page'));
  }

  function accountPageUrl(view) {
    var base = C.accountPageUrl || '';
    if (!base) return '';
    try {
      var url = new URL(base, window.location.origin);
      if (view === 'register') url.searchParams.set('view', 'register');
      else if (view === 'forgot') url.searchParams.set('view', 'forgot');
      else if (view === 'reset') url.searchParams.set('view', 'reset');
      else url.searchParams.delete('view');
      return url.pathname + url.search + url.hash;
    } catch (e) {
      return base;
    }
  }

  function getStoredChatReturnTo() {
    try {
      return sessionStorage.getItem('pax_chat_return_to') || '';
    } catch (e) {
      return '';
    }
  }

  function clearStoredChatReturnTo() {
    try {
      sessionStorage.removeItem('pax_chat_return_to');
      sessionStorage.removeItem('pax_chat_pending_open');
    } catch (e) {}
  }

  function getReturnToParam() {
    try {
      var params = new URLSearchParams(window.location.search);
      var returnTo = params.get('return_to') || '';
      if (!returnTo) {
        returnTo = getStoredChatReturnTo();
      }
      if (!returnTo) return '';
      if (returnTo.charAt(0) === '/') {
        return returnTo;
      }
      var url = new URL(returnTo, window.location.origin);
      if (url.origin === window.location.origin) {
        return url.pathname + url.search + url.hash;
      }
    } catch (e) {}
    return '';
  }

  function redirectAfterAuthSuccess() {
    var returnTo = getReturnToParam();
    if (!returnTo) return false;
    clearStoredChatReturnTo();
    window.location.href = returnTo;
    return true;
  }

  function homePageUrl() {
    return C.homeUrl || '/';
  }

  function navigateToAuthPage(view) {
    if (isAuthPage()) {
      setAuthPageView(view || 'login');
      return;
    }
    var url = accountPageUrl(view || 'login');
    if (!url) {
      openOverlay(view || 'login');
      return;
    }
    if (window.location.href.split('#')[0] === url.split('#')[0]) {
      setAuthPageView(view || 'login');
      return;
    }
    window.location.href = url;
  }

  function refreshSessionNonce() {
    var url = (C.ajaxUrl || '/wp-admin/admin-ajax.php') + '?action=pdx_rest_nonce&_=' + Date.now();
    return fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (payload) {
        if (payload && payload.success && payload.data) {
          applySession(payload.data);
          return true;
        }
        return false;
      })
      .catch(function () { return false; });
  }

  function apiFetch(method, path, body, retried) {
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': C.nonce || '',
      },
    };
    if (body && method !== 'GET') opts.body = JSON.stringify(body);
    return fetch(C.restUrl + path, opts).then(function (r) {
      var contentType = (r.headers.get('content-type') || '').toLowerCase();
      if (contentType.indexOf('application/json') === -1) {
        return r.text().then(function () {
          return {
            success: false,
            _status: r.status,
            _ok: r.ok,
            message: friendlyHttpError(r.status),
          };
        });
      }
      return r.json().then(function (data) {
        if (!data || typeof data !== 'object') {
          data = { success: false, message: friendlyHttpError(r.status) };
        }
        data._status = r.status;
        data._ok = r.ok;
        if (!retried && isRestNonceError(data)) {
          return refreshSessionNonce().then(function (ok) {
            if (ok) return apiFetch(method, path, body, true);
            data.message = 'Session expired. Please reload the page and try again.';
            return data;
          });
        }
        if (!data.success && data.message) {
          data.message = normalizeRestMessage(data);
        }
        return data;
      }).catch(function () {
        return {
          success: false,
          _status: r.status,
          _ok: false,
          message: friendlyHttpError(r.status),
        };
      });
    }).catch(function () {
      return { success: false, error: 'network', message: 'Network error. Please try again.' };
    });
  }

  function refreshUser(meta) {
    meta = meta || {};
    return syncSessionFromServer(meta.trigger || 'refresh', { cacheBust: true }).then(function () {
      return user;
    });
  }

  function moduleRequiresAuth(moduleId) {
    return publicModules.indexOf(moduleId) < 0;
  }

  function canAccessModule(moduleId) {
    if (!moduleRequiresAuth(moduleId)) return true;
    if (!user.logged_in) return false;
    if (!user.verified && !user.is_admin) return false;
    return true;
  }

  /* ─── Auth bar ─────────────────────────────────────────── */
  var authBar = null;
  var authBtn = null;
  var authMenu = null;

  function removeLegacyTopbar() {
    var legacy = document.getElementById('pdx-account-topbar');
    if (legacy && legacy.parentNode) {
      legacy.parentNode.removeChild(legacy);
    }
    document.body.classList.remove('pdx-has-account-topbar');
  }

  function findUtilityClusterMount() {
    if (!window.matchMedia('(min-width: 993px)').matches) return null;
    var content = document.querySelector('#dtr-header-global .dtr-header-global-content');
    if (!content) return null;
    var cluster = content.querySelector(':scope > .dtr-header-utility-cluster');
    if (cluster) return cluster;
    return null;
  }

  function findHeaderMount() {
    var clusterMount = findUtilityClusterMount();
    if (clusterMount) return clusterMount;
    var desktop = window.matchMedia('(min-width: 993px)').matches;
    var selectors = desktop ? [
      '#dtr-header-global .dtr-header-global-content',
      '#dtr-header-global',
      'header .inside-header',
      '#masthead .inside-header',
      'header .header-inner',
      'header .site-header-main',
      'header .elementor-container',
      '#masthead',
      'header',
      '.site-header'
    ] : [
      '#dtr-responsive-header .container',
      '#dtr-responsive-header',
      'header .inside-header',
      '#masthead .inside-header',
      'header .header-inner',
      'header .site-header-main',
      'header .elementor-container',
      '#masthead',
      'header',
      '.site-header'
    ];
    for (var i = 0; i < selectors.length; i++) {
      var el = document.querySelector(selectors[i]);
      if (el) return el;
    }
    return null;
  }

  function ensureHeaderUtilityCluster() {
    if (!window.matchMedia('(min-width: 993px)').matches) return;
    var content = document.querySelector('#dtr-header-global .dtr-header-global-content');
    if (!content) return;

    var cluster = content.querySelector(':scope > .dtr-header-utility-cluster');
    if (!cluster) {
      cluster = document.createElement('div');
      cluster.className = 'dtr-header-utility-cluster';
      content.appendChild(cluster);
    }

    var search = content.querySelector('.dtr-search-modal-trigger, a.dtr-search-modal-trigger');
    var cta = content.querySelector('a.dtr-header-btn, .dtr-header-btn');
    var bar = document.getElementById('pdx-auth-bar');
    [search, cta, bar].forEach(function (el) {
      if (el && el.parentNode !== cluster) {
        cluster.appendChild(el);
      }
    });
  }

  function stabilizeDesktopHeaderAuthLayout() {
    if (!authBar || !window.matchMedia('(min-width: 993px)').matches) return;
    if (!authBar.classList.contains('pdx-auth-bar--header')) return;
    if (!authBar.closest('#dtr-header-global')) return;

    authBar.style.setProperty('position', 'relative', 'important');
    authBar.style.setProperty('top', 'auto', 'important');
    authBar.style.setProperty('right', 'auto', 'important');
    authBar.style.setProperty('left', 'auto', 'important');
    authBar.style.setProperty('bottom', 'auto', 'important');
    authBar.style.setProperty('z-index', '2', 'important');
    authBar.style.setProperty('transform', 'none', 'important');
    authBar.style.setProperty('opacity', '1', 'important');
    authBar.style.setProperty('visibility', 'visible', 'important');

    authBar.querySelectorAll('.pdx-auth-account-btn, .pdx-auth-signup-btn, .pdx-auth-trigger').forEach(function (el) {
      if (el.classList.contains('pdx-auth-signup-btn') && authBar.classList.contains('pdx-auth-bar--logged-in')) {
        return;
      }
      if (el.classList.contains('pdx-auth-account-btn') && authBar.classList.contains('pdx-auth-bar--logged-out')) {
        return;
      }
      if (el.hidden || el.hasAttribute('hidden')) {
        return;
      }
      el.style.setProperty('height', '28px', 'important');
      el.style.setProperty('min-height', '28px', 'important');
      el.style.setProperty('max-height', '28px', 'important');
      el.style.setProperty('top', '0', 'important');
      el.style.setProperty('border', '0', 'important');
      el.style.setProperty('box-shadow', 'none', 'important');
      el.style.setProperty('backdrop-filter', 'none', 'important');
      el.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
      el.style.setProperty('filter', 'none', 'important');
      if (el.classList.contains('pdx-auth-signup-btn')) {
        el.style.setProperty('background', '#000', 'important');
        el.style.setProperty('color', '#fff', 'important');
      } else {
        el.style.setProperty('background', 'transparent', 'important');
        el.style.setProperty('color', '#000', 'important');
      }
    });

    authBar.querySelectorAll('.pdx-account-level-badge--header, .pdx-account-level-badge').forEach(function (el) {
      el.style.setProperty('background', 'rgba(0,0,0,0.06)', 'important');
      el.style.setProperty('background-image', 'none', 'important');
      el.style.setProperty('color', '#3a3a3c', 'important');
      el.style.setProperty('border', '0.5px solid rgba(0,0,0,0.14)', 'important');
      el.style.setProperty('box-shadow', 'none', 'important');
    });

    authBar.querySelectorAll('svg').forEach(function (el) {
      el.style.setProperty('stroke', 'currentColor', 'important');
      el.style.setProperty('color', 'currentColor', 'important');
    });
  }

  function stabilizeMobileHeaderAuthLayout() {
    if (!authBar || !window.matchMedia('(max-width: 992px)').matches) return;
    if (!authBar.classList.contains('pdx-auth-bar--header')) return;
    if (!authBar.closest('#dtr-responsive-header')) return;

    authBar.style.setProperty('position', 'relative', 'important');
    authBar.style.setProperty('top', 'auto', 'important');
    authBar.style.setProperty('right', 'auto', 'important');
    authBar.style.setProperty('left', 'auto', 'important');
    authBar.style.setProperty('transform', 'none', 'important');
    authBar.style.setProperty('margin', '0 44px 0 auto', 'important');
  }

  function scheduleDesktopHeaderAuthLayoutReset() {
    ensureHeaderUtilityCluster();
    stabilizeDesktopHeaderAuthLayout();
    stabilizeMobileHeaderAuthLayout();
    setTimeout(function () {
      ensureHeaderUtilityCluster();
      stabilizeDesktopHeaderAuthLayout();
      stabilizeMobileHeaderAuthLayout();
    }, 0);
    setTimeout(function () {
      ensureHeaderUtilityCluster();
      stabilizeDesktopHeaderAuthLayout();
      stabilizeMobileHeaderAuthLayout();
    }, 50);
    setTimeout(function () {
      ensureHeaderUtilityCluster();
      stabilizeDesktopHeaderAuthLayout();
      stabilizeMobileHeaderAuthLayout();
    }, 250);
  }

  function mountAuthBar() {
    removeLegacyTopbar();
    dedupeAuthBars();
    authBar.classList.remove('pdx-auth-bar--topbar');
    if (isAuthPage()) {
      authBar.hidden = true;
      return;
    }
    authBar.hidden = false;
    var mount = findHeaderMount();
    if (mount) {
      mount.classList.add('pdx-header-has-auth');
      authBar.classList.add('pdx-auth-bar--header');
      mount.appendChild(authBar);
      scheduleDesktopHeaderAuthLayoutReset();
      return;
    }
    authBar.classList.add('pdx-auth-bar--header');
    document.body.appendChild(authBar);
  }

  function dedupeAuthBars() {
    var bars = document.querySelectorAll('#pdx-auth-bar');
    for (var i = 1; i < bars.length; i++) {
      if (bars[i].parentNode) {
        bars[i].parentNode.removeChild(bars[i]);
      }
    }
  }

  function createAuthBar() {
    dedupeAuthBars();
    var staleBar = document.getElementById('pdx-auth-bar');
    if (staleBar && staleBar.parentNode) {
      staleBar.parentNode.removeChild(staleBar);
    }
    authBar = document.createElement('div');
    authBar.id = 'pdx-auth-bar';
    authBar.className = 'pdx-cx-shell';
    authBar.innerHTML =
      '<div class="pdx-auth-bar-inner">' +
        '<button type="button" class="pdx-auth-signup-btn pdx-cx-btn pdx-auth-header-btn">' + escHtml(t('sign_in', 'Anmelden')) + '</button>' +
        '<button type="button" class="pdx-auth-account-btn pdx-cx-btn pdx-cx-btn--ghost pdx-auth-header-btn" aria-haspopup="true" aria-expanded="false" hidden>' +
          '<span class="pdx-auth-account-identity"></span>' +
        '</button>' +
        '<div class="pdx-auth-menu pdx-auth-menu--apple" hidden>' +
          '<div class="pdx-auth-menu-head"></div>' +
          '<div class="pdx-auth-menu-actions">' +
            renderHeaderMenuItem('portal', 'dashboard', t('customer_portal', 'Customer Portal')) +
            renderHeaderMenuItem('profile', 'user', t('my_profile', 'My Profile')) +
            renderHeaderMenuItem('account', 'settings', t('my_account', 'My Account')) +
          '</div>' +
          '<div class="pdx-auth-menu-footer">' +
            renderHeaderMenuItem('logout', 'logout', t('logout', 'Logout'), 'pdx-auth-menu-item--logout') +
          '</div>' +
        '</div>' +
      '</div>';

    authBtn = authBar.querySelector('.pdx-auth-account-btn');
    authMenu = authBar.querySelector('.pdx-auth-menu');

    if (authBtn) {
      authBtn.addEventListener('click', onAuthBarClick);
      authBtn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onAuthBarClick(); }
      });
    }

    authMenu.querySelectorAll('.pdx-auth-menu-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.dataset.action;
        closeAuthMenu();
        if (action === 'portal') openCustomerPortal('overview');
        else if (action === 'profile') openProfileOverlay();
        else if (action === 'account') openAccountPanel();
        else if (action === 'logout') doLogout();
      });
    });

    var signupBtn = authBar.querySelector('.pdx-auth-signup-btn');
    if (signupBtn) {
      bindSignupButton(signupBtn);
    }

    document.addEventListener('click', function (e) {
      if (!authBar || !authMenuOpen) return;
      if (!authBar.contains(e.target)) closeAuthMenu();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAuthMenu();
    });
    if (!authMenuPositionBound) {
      authMenuPositionBound = true;
      window.addEventListener('resize', positionAuthMenu);
      window.addEventListener('scroll', positionAuthMenu, true);
    }

    ensureAppleMenuFont();
    mountAuthBar();
    updateAuthBar();
  }

  function applyPublicHeaderLocale() {
    if (!authBar) return;
    var docDir = (document.documentElement.getAttribute('dir') || '').toLowerCase();
    var rtl = docDir === 'rtl' || customerPortalLang() === 'ar';
    authBar.setAttribute('dir', rtl ? 'rtl' : 'ltr');
  }

  function headerMenuSvg(paths, size) {
    size = size || 20;
    return '<svg class="pdx-auth-menu-svg" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + paths + '</svg>';
  }

  function headerMenuIcon(name, size) {
    var icons = {
      dashboard: '<rect x="3.4" y="3.4" width="7.2" height="8.4" rx="1.5"/><rect x="13.4" y="3.4" width="7.2" height="5" rx="1.5"/><rect x="13.4" y="11.4" width="7.2" height="9.2" rx="1.5"/><rect x="3.4" y="14.8" width="7.2" height="5.8" rx="1.5"/>',
      user: '<circle cx="12" cy="8" r="3.2"/><path d="M5.4 19.4c.75-3.15 3.2-4.8 6.6-4.8s5.85 1.65 6.6 4.8"/>',
      settings: '<circle cx="12" cy="12" r="2.85"/><path d="M12 3.25v2.1M12 18.65v2.1M4.75 4.75l1.48 1.48M17.77 17.77l1.48 1.48M3.25 12h2.1M18.65 12h2.1M4.75 19.25l1.48-1.48M17.77 6.23l1.48-1.48"/>',
      logout: '<path d="M9.6 20.4H6.1A2.1 2.1 0 0 1 4 18.3V5.7A2.1 2.1 0 0 1 6.1 3.6h3.5"/><path d="M15.2 16.4 20 12l-4.8-4.4"/><path d="M19.6 12H9.5"/>',
      chevron: '<path d="M9.4 5.8 15.6 12 9.4 18.2"/>',
      check: '<path d="M5.4 12.4 10 17l8.6-9.6"/>'
    };
    return headerMenuSvg(icons[name] || icons.user, size);
  }

  function ensureAppleMenuFont() {
    if (document.getElementById('pdx-apple-menu-font')) return;
    var link = document.createElement('link');
    link.id = 'pdx-apple-menu-font';
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,510;14..32,590;14..32,600&display=swap';
    document.head.appendChild(link);
  }

  function renderHeaderMenuItem(action, icon, label, extraClass) {
    var isLogout = extraClass && extraClass.indexOf('logout') !== -1;
    return '<button type="button" class="pdx-auth-menu-item' + (extraClass ? ' ' + extraClass : '') + '" data-action="' + escHtml(action) + '">' +
      '<span class="pdx-auth-menu-item__icon">' + headerMenuIcon(icon, 20) + '</span>' +
      '<span class="pdx-auth-menu-item__label">' + escHtml(label) + '</span>' +
      (isLogout ? '' : '<span class="pdx-auth-menu-item__chevron" aria-hidden="true">' + headerMenuIcon('chevron', 14) + '</span>') +
    '</button>';
  }

  function accountStatusLabel() {
    if (!user.logged_in) return t('signed_out', 'Signed out');
    return accountStatusText(user.verified);
  }

  function updateAuthBar() {
    if (!authMenu) return;
    if (isAuthPage() && authBar) {
      authBar.hidden = true;
    }
    syncHeaderAuthControls();
    var accountBtn = authBar ? authBar.querySelector('.pdx-auth-account-btn') : null;
    var identityEl = accountBtn ? accountBtn.querySelector('.pdx-auth-account-identity') : null;
    var head = authMenu.querySelector('.pdx-auth-menu-head');
    var label = user.logged_in ? (user.display_name || t('account', 'Account')) : t('account', 'Account');

    if (identityEl) {
      if (user.logged_in) {
        identityEl.innerHTML = renderHeaderUserIdentityHtml({
          name: label,
          showName: window.matchMedia('(min-width: 769px)').matches,
        });
      } else {
        identityEl.textContent = '';
        identityEl.innerHTML = '';
      }
    }
    if (accountBtn) {
      accountBtn.classList.remove('pdx-auth-account-btn--verified');
      accountBtn.setAttribute('aria-label', user.logged_in ? t('account_menu', 'Account menu') : t('account', 'Account'));
    }

    if (user.logged_in && head) {
      var statusClass = user.verified ? 'pdx-auth-menu-status--verified' : 'pdx-auth-menu-status--pending';
      if (user.is_admin) statusClass = 'pdx-auth-menu-status--admin';
      head.innerHTML =
        '<div class="pdx-auth-menu-identity">' +
          renderAccountAvatarHtml({ sizeClass: 'pdx-account-avatar--menu' }) +
          '<div class="pdx-auth-menu-identity-text">' +
            '<div class="pdx-auth-menu-name">' + escHtml(user.display_name || t('account', 'Account')) + '</div>' +
            '<div class="pdx-auth-menu-email">' + escHtml(user.email || '') + '</div>' +
            '<div class="pdx-auth-menu-status ' + statusClass + '">' +
              (user.verified || user.is_admin ? '<span class="pdx-auth-menu-status__icon">' + headerMenuIcon('check', 12) + '</span>' : '') +
              '<span class="pdx-auth-menu-status__label">' + escHtml(accountStatusLabel()) + '</span>' +
            '</div>' +
          '</div>' +
        '</div>';
      authMenu.setAttribute('hidden', 'hidden');
    } else {
      if (head) head.innerHTML = '';
      closeAuthMenu();
      authMenu.setAttribute('hidden', 'hidden');
    }
    if (authBar) bindAccountAvatarFallbacks(authBar);
    if (authMenu) bindAccountAvatarFallbacks(authMenu);
    applyPublicHeaderLocale();
    scheduleDesktopHeaderAuthLayoutReset();
  }

  function positionAuthMenu() {
    if (!authMenu || !authBtn || !authMenuOpen) return;
    var rect = authBtn.getBoundingClientRect();
    var gap = 8;
    var width = Math.min(320, window.innerWidth - 24);
    if (width < 240) width = Math.max(200, window.innerWidth - 24);
    var rtl = !!(authBar && authBar.getAttribute('dir') === 'rtl');
    authMenu.style.position = 'fixed';
    authMenu.style.width = width + 'px';
    authMenu.style.maxWidth = 'calc(100vw - 24px)';
    authMenu.style.zIndex = '10050';
    var top = Math.round(rect.bottom + gap);
    var height = authMenu.offsetHeight || 0;
    if (height && top + height > window.innerHeight - 12) {
      var above = Math.round(rect.top - height - gap);
      if (above >= 12) top = above;
      else top = Math.max(12, window.innerHeight - height - 12);
    }
    authMenu.style.top = top + 'px';
    if (rtl) {
      var left = Math.round(rect.left);
      if (left + width > window.innerWidth - 12) left = window.innerWidth - width - 12;
      authMenu.style.left = Math.max(12, left) + 'px';
      authMenu.style.right = 'auto';
    } else {
      var right = Math.round(window.innerWidth - rect.right);
      if (right < 12) right = 12;
      authMenu.style.right = right + 'px';
      authMenu.style.left = 'auto';
    }
  }

  function openAuthMenu() {
    if (!user.logged_in || !authMenu || !authBtn) return;
    authMenu.hidden = false;
    authMenu.classList.add('is-open');
    authBtn.setAttribute('aria-expanded', 'true');
    authMenuOpen = true;
    if (authBar) authBar.classList.add('pdx-auth-bar--menu-open');
    positionAuthMenu();
  }

  function closeAuthMenu() {
    if (!authMenu || !authBtn) return;
    authMenu.classList.remove('is-open');
    authBtn.setAttribute('aria-expanded', 'false');
    authMenuOpen = false;
    if (authBar) authBar.classList.remove('pdx-auth-bar--menu-open');
  }

  function onAuthBarClick() {
    if (!user.logged_in) {
      navigateToAuthPage('login');
      return;
    }
    if (authMenuOpen) closeAuthMenu();
    else openAuthMenu();
  }

  function openAccountPanel() {
    if (C.accountPageUrl || isAuthPage()) {
      if (isAuthPage()) setAccountSection('overview');
      else window.location.href = accountPageUrl() + '#/overview';
      return;
    }
    if (window.PDXDock && window.PDXDock.openPanel) {
      window.PDXDock.openPanel('account');
    }
  }

  function openProfileOverlay() {
    if (C.accountPageUrl || isAuthPage()) {
      if (isAuthPage()) setAccountSection('personal');
      else window.location.href = accountPageUrl() + '#/personal';
      return;
    }
    if (!profileOverlay) {
      profileOverlay = document.createElement('div');
      profileOverlay.id = 'pdx-profile-overlay';
      profileOverlay.className = 'pdx-cx-shell pdx-profile-overlay--apple';
      profileOverlay.setAttribute('role', 'dialog');
      profileOverlay.setAttribute('aria-modal', 'true');
      profileOverlay.setAttribute('aria-label', t('my_profile', 'My Profile'));
      profileOverlay.innerHTML =
        '<div class="pdx-profile-card">' +
          '<button type="button" class="pdx-auth-close" aria-label="' + escHtml(t('close', 'Close')) + '">&times;</button>' +
          '<div class="pdx-profile-card-title">' + escHtml(t('my_profile', 'My Profile')) + '</div>' +
          '<div class="pdx-profile-card-body"></div>' +
          '<div class="pdx-profile-card-actions">' +
            '<button type="button" class="pdx-portal-btn pdx-profile-open-account">' + cxIcon('settings', 16) + escHtml(t('my_account', 'My Account')) + '</button>' +
            '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-profile-logout">' + cxIcon('logout', 16) + escHtml(t('logout', 'Logout')) + '</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(profileOverlay);
      profileOverlay.querySelector('.pdx-auth-close').addEventListener('click', closeProfileOverlay);
      profileOverlay.addEventListener('click', function (e) {
        if (e.target === profileOverlay) closeProfileOverlay();
      });
      profileOverlay.querySelector('.pdx-profile-open-account').addEventListener('click', function () {
        closeProfileOverlay();
        openAccountPanel();
      });
      profileOverlay.querySelector('.pdx-profile-logout').addEventListener('click', function () {
        closeProfileOverlay();
        doLogout();
      });
    }
    var body = profileOverlay.querySelector('.pdx-profile-card-body');
    body.innerHTML =
      '<div class="pdx-profile-identity">' +
        renderAccountAvatarHtml({ sizeClass: 'pdx-account-avatar--profile-compact' }) +
        '<div class="pdx-profile-identity-text">' +
          '<div class="pdx-profile-identity-name">' + escHtml(user.display_name || '—') + '</div>' +
          '<div class="pdx-profile-identity-email">' + escHtml(user.email || '—') + '</div>' +
        '</div>' +
      '</div>' +
      '<div class="pdx-profile-row"><span class="pdx-profile-label">' + escHtml(t('account_status', 'Account Status')) + '</span><span class="pdx-profile-value pdx-profile-value--status">' + escHtml(accountStatusText(user.verified)) + '</span></div>' +
      '<div class="pdx-profile-row"><span class="pdx-profile-label">' + escHtml(t('login_status', 'Login Status')) + '</span><span class="pdx-profile-value">' + escHtml(user.logged_in ? t('signed_in', 'Signed in') : t('signed_out', 'Signed out')) + '</span></div>';
    profileOverlay.classList.add('is-open');
    document.body.classList.add('pdx-no-scroll');
  }

  function closeProfileOverlay() {
    if (!profileOverlay) return;
    profileOverlay.classList.remove('is-open');
    document.body.classList.remove('pdx-no-scroll');
  }

  function finalizeAccountLogoutUI() {
    closeAccountMobileNav();
    closeAccountSignOutConfirm();
    document.body.classList.remove('pdx-account-dashboard-body', 'pdx-account-mobile-nav-open', 'pdx-account-rtl');
    if (accountMobileBackdrop) {
      accountMobileBackdrop.hidden = true;
    }
    if (accountSidebarEl) {
      accountSidebarEl.setAttribute('aria-hidden', 'true');
      accountSidebarEl.style.display = 'none';
    }
    if (accountAppEl) {
      accountAppEl.hidden = true;
    }
    try {
      window.history.replaceState({}, '', window.location.pathname + window.location.search);
    } catch (e) {}
    var guestPanel = document.getElementById('pdx-auth-page-guest');
    if (guestPanel) {
      guestPanel.hidden = false;
      guestPanel.scrollIntoView({ block: 'start' });
    }
    syncAuthPageSegment();
  }

  function ensureAccountSignOutConfirm() {
    if (accountSignOutConfirmEl) return accountSignOutConfirmEl;
    accountSignOutConfirmEl = document.createElement('div');
    accountSignOutConfirmEl.id = 'pdx-account-signout-confirm';
    accountSignOutConfirmEl.className = 'pdx-account-signout-confirm';
    accountSignOutConfirmEl.hidden = true;
    accountSignOutConfirmEl.innerHTML =
      '<div class="pdx-account-signout-confirm__backdrop" data-signout-dismiss="1"></div>' +
      '<div class="pdx-account-signout-confirm__sheet" role="dialog" aria-modal="true" aria-labelledby="pdx-account-signout-confirm-title">' +
        '<h2 class="pdx-account-signout-confirm__title" id="pdx-account-signout-confirm-title"></h2>' +
        '<p class="pdx-account-signout-confirm__message"></p>' +
        '<div class="pdx-account-signout-confirm__actions">' +
          '<button type="button" class="pdx-portal-btn pdx-portal-btn--secondary pdx-account-signout-confirm__cancel"></button>' +
          '<button type="button" class="pdx-portal-btn pdx-portal-btn--destructive pdx-account-signout-confirm__confirm"></button>' +
        '</div>' +
      '</div>';
    var root = getAccountOverlayRoot();
    root.appendChild(accountSignOutConfirmEl);
    accountSignOutConfirmEl.querySelector('.pdx-account-signout-confirm__title').textContent = t('sign_out_confirm_title', 'Sign Out?');
    accountSignOutConfirmEl.querySelector('.pdx-account-signout-confirm__message').textContent = t('sign_out_confirm_message', 'Are you sure you want to sign out? You will be signed out of your account.');
    accountSignOutConfirmEl.querySelector('.pdx-account-signout-confirm__cancel').textContent = t('cancel', 'Cancel');
    accountSignOutConfirmEl.querySelector('.pdx-account-signout-confirm__confirm').textContent = t('sign_out', 'Sign Out');
    accountSignOutConfirmEl.querySelector('.pdx-account-signout-confirm__cancel').addEventListener('click', closeAccountSignOutConfirm);
    accountSignOutConfirmEl.querySelector('[data-signout-dismiss]').addEventListener('click', closeAccountSignOutConfirm);
    accountSignOutConfirmEl.querySelector('.pdx-account-signout-confirm__confirm').addEventListener('click', function () {
      closeAccountSignOutConfirm();
      doLogout();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && accountSignOutConfirmEl && !accountSignOutConfirmEl.hidden) {
        closeAccountSignOutConfirm();
      }
    });
    return accountSignOutConfirmEl;
  }

  function openAccountSignOutConfirm() {
    var dialog = ensureAccountSignOutConfirm();
    dialog.hidden = false;
    document.body.classList.add('pdx-account-signout-confirm-open');
    var confirmBtn = dialog.querySelector('.pdx-account-signout-confirm__confirm');
    if (confirmBtn) confirmBtn.focus();
  }

  function closeAccountSignOutConfirm() {
    if (!accountSignOutConfirmEl) return;
    accountSignOutConfirmEl.hidden = true;
    document.body.classList.remove('pdx-account-signout-confirm-open');
  }

  function promptAccountSignOut() {
    closeAccountMobileNav();
    openAccountSignOutConfirm();
  }

  function doLogout() {
    apiFetch('POST', '/auth/logout').then(function (data) {
      if (data && data.nonce) {
        applySession({
          nonce: data.nonce,
          user: data.user || { logged_in: false, verified: false, display_name: '', email: '', id: 0 },
        }, { reason: 'logout', broadcast: true });
      } else {
        user = { logged_in: false, verified: false, id: 0, display_name: '', email: '' };
        C.isLoggedIn = false;
        C.emailVerified = false;
        C.userId = 0;
        C.userName = '';
        C.userEmail = '';
        updateAuthBar();
        updateAuthPagePanels();
        finalizeAccountLogoutUI();
        broadcastSessionChange();
        try {
          window.dispatchEvent(new CustomEvent('pdx-session-updated', { detail: { reason: 'logout' } }));
        } catch (e) {}
      }
      notify(t('logged_out', 'Logged out.'), 'info');
      if (window.PDXDock && window.PDXDock.closePanel) window.PDXDock.closePanel();
    });
  }

  /* ─── Auth overlay ─────────────────────────────────────── */
  var overlay = null;
  var formEl = null;
  var inlineAuthMount = null;

  function activeAuthRoot() {
    return (inlineAuthMount && inlineAuthMount.container) || formEl;
  }

  function createOverlay() {
    overlay = document.createElement('div');
    overlay.id = 'pdx-auth-overlay';
    overlay.className = 'pdx-cx-shell';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', t('authentication', 'Anmeldung'));
    overlay.innerHTML =
      '<div class="pdx-auth-wrapper">' +
        '<button type="button" class="pdx-auth-close" aria-label="' + escHtml(t('close', 'Schließen')) + '">&times;</button>' +
        '<div class="pdx-auth-form-wrap"></div>' +
      '</div>';
    document.body.appendChild(overlay);
    overlay.querySelector('.pdx-auth-close').addEventListener('click', closeOverlay);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeOverlay();
    });
    formEl = overlay.querySelector('.pdx-auth-form-wrap');
  }

  function openOverlay(view, moduleId) {
    if (!overlay) return;
    if (moduleId) returnModule = moduleId;
    if (!inlineAuthMount && !isAuthPage() && C.accountPageUrl && (view === 'login' || view === 'register' || view === 'forgot')) {
      navigateToAuthPage(view);
      return;
    }
    currentView = view || 'login';
    renderAuthForm();
    overlay.classList.add('is-open');
    document.body.classList.add('pdx-no-scroll');
    var first = overlay.querySelector('input:not([type=submit])');
    if (first) setTimeout(function () { first.focus(); }, 100);
  }

  function closeOverlay() {
    overlay.classList.remove('is-open');
    document.body.classList.remove('pdx-no-scroll');
  }

  function renderAuthForm(target) {
    var mount = target || activeAuthRoot();
    if (!mount) return;
    if (target) {
      if (target === formEl) {
        inlineAuthMount = null;
      } else if (authPageFormEl && target === authPageFormEl) {
        inlineAuthMount = {
          container: target,
          compact: true,
          context: 'page',
          onSuccess: function () { updateAuthPagePanels(); },
        };
      } else {
        inlineAuthMount = inlineAuthMount || {};
        inlineAuthMount.container = target;
      }
    }
    var compact = !!(inlineAuthMount && inlineAuthMount.compact);
    var titles = {
      login: t('sign_in', 'Anmelden'),
      register: t('create_account', 'Konto erstellen'),
      forgot: t('forgot_password', 'Passwort vergessen'),
      reset: t('reset_password', 'Passwort zurücksetzen')
    };
    var subtitles = {
      login: t('welcome_back', 'Willkommen zurück. Melden Sie sich bei Ihrem PAXdesign-Konto an.'),
      register: t('create_account_sub', 'Erstellen Sie Ihr Konto, um Module und Anfragen zu nutzen.'),
      forgot: t('forgot_sub', 'Geben Sie Ihre E-Mail ein. Wir senden einen sicheren Reset-Link.'),
      reset: t('reset_sub', 'Wählen Sie ein neues, sicheres Passwort für Ihr Konto.'),
    };
    var headIcons = { login: 'login', register: 'register', forgot: 'mail', reset: 'lock' };
    var html = '<form class="pdx-auth-form pdx-auth-form--' + currentView + (compact ? ' pdx-auth-form--compact' : '') + '" novalidate>';
    if (!compact) {
      html += '<div class="pdx-cx-auth-head">';
      html += '<div class="pdx-cx-icon-wrap">' + cxIcon(headIcons[currentView] || 'login', 22) + '</div>';
      html += '<span class="pdx-auth-title">' + escHtml(titles[currentView] || t('sign_in', 'Anmelden')) + '</span>';
      html += '<p class="pdx-cx-auth-subtitle">' + escHtml(subtitles[currentView] || '') + '</p>';
      html += '</div>';
    }
    html += '<div class="pdx-auth-msg-slot"></div>';

    if (currentView === 'login' && isAuthPageFormMount()) {
      html += socialSignInTopBlockHtml();
    }

    html += '<div class="pdx-auth-fields">';

    if (currentView === 'login') {
      html += fieldInput('email', 'email', t('email', 'E-Mail'), 'mail', 'email', true);
      html += fieldInput('password', 'password', t('password', 'Passwort'), 'lock', 'current-password', true);
    } else if (currentView === 'register') {
      html += fieldInput('name', 'text', t('full_name', 'Vollständiger Name'), 'user', 'name', true);
      html += fieldInput('email', 'email', t('email', 'E-Mail'), 'mail', 'email', true);
      html += fieldInput('password', 'password', t('password_min', 'Passwort (mindestens 8 Zeichen)'), 'lock', 'new-password', true);
    } else if (currentView === 'forgot') {
      html += fieldInput('email', 'email', t('email', 'E-Mail'), 'mail', 'email', true);
    } else if (currentView === 'reset') {
      html += fieldInput('password', 'password', t('new_password', 'Neues Passwort'), 'lock', 'new-password', true);
      html += fieldInput('password2', 'password', t('confirm_password', 'Passwort bestätigen'), 'lock', 'new-password', true);
    }

    html += '</div>';

    if (currentView === 'login') {
      html += submitBtn(t('sign_in', 'Anmelden'), 'login');
      html += links([
        { view: 'forgot', label: t('forgot_password_q', 'Passwort vergessen?') },
        { view: 'register', label: t('create_account', 'Konto erstellen') },
      ]);
      if (!isAuthPageFormMount()) {
        html += appleSignInButtonHtml();
      }
    } else if (currentView === 'register') {
      html += submitBtn(t('create_account', 'Konto erstellen'), 'register');
      html += links([{ view: 'login', label: t('already_have_account', 'Bereits ein Konto? Anmelden') }]);
    } else if (currentView === 'forgot') {
      html += submitBtn(t('send_reset_link', 'Link senden'), 'mail');
      html += links([{ view: 'login', label: t('back_to_sign_in', 'Zurück zur Anmeldung') }]);
    } else if (currentView === 'reset') {
      html += submitBtn(t('reset_password', 'Passwort zurücksetzen'), 'lock');
      html += links([{ view: 'login', label: t('back_to_sign_in', 'Zurück zur Anmeldung') }]);
    }

    html += '</form>';
    mount.innerHTML = html;

    var form = mount.querySelector('.pdx-auth-form');
    form.addEventListener('submit', onAuthSubmit);
    mount.querySelectorAll('.pdx-auth-link').forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentView = btn.dataset.view;
        renderAuthForm(mount);
      });
    });
    bindSocialSignInButtons(mount);
    showPendingSocialError();
  }

  function mountInlineAuth(container, view, options) {
    if (!container) return;
    options = options || {};
    inlineAuthMount = {
      container: container,
      compact: !!options.compact,
      context: options.context || '',
      onSuccess: typeof options.onSuccess === 'function' ? options.onSuccess : null,
    };
    currentView = view || 'login';
    renderAuthForm(container);
    var first = container.querySelector('input:not([type=submit])');
    if (first) setTimeout(function () { first.focus(); }, 80);
  }

  function unmountInlineAuth(container) {
    if (!container) return;
    if (inlineAuthMount && inlineAuthMount.container === container) {
      inlineAuthMount = null;
    }
    container.innerHTML = '';
  }

  function finishAuthSuccess() {
    if (inlineAuthMount && inlineAuthMount.container) {
      if (inlineAuthMount.onSuccess) inlineAuthMount.onSuccess();
      return true;
    }
    closeOverlay();
    return false;
  }

  function fieldInput(name, type, label, iconName, autocomplete, required) {
    var id = 'pdx-auth-' + currentView + '-' + name;
    var html = '<div class="pdx-auth-field" data-field="' + name + '">';
    html += '<label class="pdx-auth-field-label" for="' + id + '">' + escHtml(label) + '</label>';
    html += '<div class="pdx-auth-input-container">';
    if (iconName) html += cxIcon(iconName, 18);
    html += '<input class="pdx-auth-input" id="' + id + '" name="' + name + '" type="' + type + '"';
    html += ' placeholder="' + escHtml(label) + '"';
    if (autocomplete) html += ' autocomplete="' + autocomplete + '"';
    if (required) html += ' required aria-required="true"';
    html += ' /></div></div>';
    return html;
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function validateAuthForm(view, fd) {
    var name;
    var email;
    var password;
    var password2;

    if (view === 'login') {
      email = String(fd.get('email') || '').trim();
      password = String(fd.get('password') || '');
      if (!email) return { message: 'Please enter your email address.', field: 'email' };
      if (!isValidEmail(email)) return { message: 'Please enter a valid email address.', field: 'email' };
      if (!password) return { message: 'Please enter your password.', field: 'password' };
      return null;
    }

    if (view === 'register') {
      name = String(fd.get('name') || '').trim();
      email = String(fd.get('email') || '').trim();
      password = String(fd.get('password') || '');
      if (!name) return { message: 'Please enter your name.', field: 'name' };
      if (!email) return { message: 'Please enter your email address.', field: 'email' };
      if (!isValidEmail(email)) return { message: 'Please enter a valid email address.', field: 'email' };
      if (password.length < 8) return { message: 'Password must be at least 8 characters.', field: 'password' };
      return null;
    }

    if (view === 'forgot') {
      email = String(fd.get('email') || '').trim();
      if (!email) return { message: 'Please enter your email address.', field: 'email' };
      if (!isValidEmail(email)) return { message: 'Please enter a valid email address.', field: 'email' };
      return null;
    }

    if (view === 'reset') {
      password = String(fd.get('password') || '');
      password2 = String(fd.get('password2') || '');
      if (password.length < 8) return { message: 'Password must be at least 8 characters.', field: 'password' };
      if (password !== password2) return { message: 'Passwords do not match.', field: 'password2' };
      return null;
    }

    return null;
  }

  function markFieldError(fieldName) {
    var root = activeAuthRoot();
    if (!root) return;
    root.querySelectorAll('.pdx-auth-field').forEach(function (el) {
      el.classList.remove('pdx-auth-field--error');
    });
    if (!fieldName) return;
    var field = root.querySelector('.pdx-auth-field[data-field="' + fieldName + '"]');
    if (field) {
      field.classList.add('pdx-auth-field--error');
      var input = field.querySelector('input');
      if (input) input.focus();
    }
  }

  function isAuthPageFormMount() {
    return !!(inlineAuthMount && inlineAuthMount.compact && (inlineAuthMount.context === 'page' || inlineAuthMount.context === 'chat'));
  }

  function isChatInlineAuthMount() {
    return !!(inlineAuthMount && inlineAuthMount.compact && inlineAuthMount.context === 'chat');
  }

  function appleSubmitBtn(label) {
    return '<div class="pdx-auth-submit-wrap">' +
      '<button type="submit" class="pdx-auth-submit">' + escHtml(label) + '</button></div>';
  }

  function appleWebStartUrl() {
    var base = C.appleStartUrl || ((C.restUrl || '') + '/auth/apple/start');
    if (!base) return '';
    var returnTo = getReturnToParam();
    if (!returnTo && isChatInlineAuthMount()) {
      returnTo = window.location.pathname + window.location.search + (window.location.hash || '');
    }
    if (!returnTo) {
      returnTo = window.location.pathname + window.location.search + '#/overview';
    }
    var join = base.indexOf('?') >= 0 ? '&' : '?';
    return base + join + 'return_to=' + encodeURIComponent(returnTo);
  }

  function githubWebStartUrl() {
    var base = C.githubStartUrl || ((C.restUrl || '') + '/auth/github/start');
    if (!base) return '';
    var returnTo = getReturnToParam();
    if (!returnTo && isChatInlineAuthMount()) {
      returnTo = window.location.pathname + window.location.search + (window.location.hash || '');
    }
    if (!returnTo) {
      returnTo = window.location.pathname + window.location.search + '#/overview';
    }
    var join = base.indexOf('?') >= 0 ? '&' : '?';
    return base + join + 'return_to=' + encodeURIComponent(returnTo);
  }

  function githubSignInButtonInnerHtml() {
    return '<button type="button" class="pdx-auth-github-btn" data-pdx-github-signin="1">' +
      '<span class="pdx-auth-github-icon" aria-hidden="true">' +
      '<svg width="16" height="16" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" focusable="false">' +
      '<path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>' +
      '</svg></span>' +
      t('sign_in_github', 'Mit GitHub anmelden') +
      '</button>';
  }

  function appleSignInButtonInnerHtml() {
    return '<button type="button" class="pdx-auth-apple-btn" data-pdx-apple-signin="1">' +
      '<span class="pdx-auth-apple-icon" aria-hidden="true">' +
      '<svg width="16" height="16" viewBox="0 0 814 1000" xmlns="http://www.w3.org/2000/svg" focusable="false">' +
      '<path fill="currentColor" d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-163.5-39.5c-76.5 0-103.7 40.8-165.9 40.8s-105.6-57-155.5-127C46.7 790.7 0 663 0 541.8c0-194.4 126.4-297.5 250.8-297.5 66.1 0 121.2 43.4 162.7 43.4 39.5 0 101.1-46 176.3-46 28.5 0 130.9 2.6 198.3 99.2zm-234-181.5c31.1-36.9 53.1-88.1 53.1-139.3 0-7.1-.6-14.3-1.9-20.1-50.6 1.9-110.8 33.7-147.1 75.8-28.5 32.4-55.1 83.6-55.1 135.5 0 7.8 1.3 15.6 1.9 18.1 3.2.6 8.4 1.3 13.6 1.3 45.4 0 102.5-30.4 135.5-71.3z"/>' +
      '</svg></span>' +
      t('sign_in_apple', 'Mit Apple anmelden') +
      '</button>';
  }

  function socialSignInTopBlockHtml() {
    if (currentView !== 'login' || !isAuthPageFormMount()) return '';
    var buttons = '';
    if (C.githubWebEnabled) buttons += githubSignInButtonInnerHtml();
    if (C.appleWebEnabled) buttons += appleSignInButtonInnerHtml();
    if (!buttons) return '';
    return '<div class="pdx-auth-social-wrap pdx-auth-social-wrap--lead">' +
      buttons +
      '</div>' +
      '<div class="pdx-auth-apple-divider pdx-auth-apple-divider--after-apple" aria-hidden="true"><span>' + escHtml(t('or', 'oder')) + '</span></div>';
  }

  function appleSignInTopBlockHtml() {
    return socialSignInTopBlockHtml();
  }

  function appleSignInButtonHtml() {
    if (!C.appleWebEnabled || currentView !== 'login' || !isAuthPageFormMount()) return '';
    return '<div class="pdx-auth-apple-divider" aria-hidden="true"><span>or</span></div>' +
      '<div class="pdx-auth-apple-wrap">' +
      appleSignInButtonInnerHtml() +
      '</div>';
  }

  function bindSocialSignInButtons(root) {
    if (!root) return;
    var githubBtn = root.querySelector('[data-pdx-github-signin]');
    if (githubBtn) {
      githubBtn.addEventListener('click', function () {
        clearPendingSocialError();
        var url = githubWebStartUrl();
        if (!url) {
          showFormMessage('Sign in with GitHub is not available right now.', 'error');
          return;
        }
        window.location.href = url;
      });
    }
    var appleBtn = root.querySelector('[data-pdx-apple-signin]');
    if (appleBtn) {
      appleBtn.addEventListener('click', function () {
        clearPendingSocialError();
        var url = appleWebStartUrl();
        if (!url) {
          showFormMessage('Sign in with Apple is not available right now.', 'error');
          return;
        }
        window.location.href = url;
      });
    }
  }

  function bindAppleSignInButton(root) {
    bindSocialSignInButtons(root);
  }

  function submitBtn(label, iconName) {
    if (isAuthPageFormMount()) {
      return appleSubmitBtn(label);
    }
    return '<div class="pdx-auth-submit-wrap">' + pearlBtn(label, { icon: iconName || 'check' }) + '</div>';
  }

  function setFormLoading(loading) {
    var root = activeAuthRoot();
    var btn = root && (root.querySelector('.pdx-auth-submit') || root.querySelector('.pdx-portal-btn') || root.querySelector('.pdx-btn-pearl'));
    if (btn) {
      btn.disabled = !!loading;
      btn.classList.toggle('is-loading', !!loading);
    }
  }

  function links(items) {
    var html = '<div class="pdx-auth-links">';
    items.forEach(function (item) {
      html += '<button type="button" class="pdx-auth-link" data-view="' + item.view + '">' + escHtml(item.label) + '</button>';
    });
    return html + '</div>';
  }

  function showFormMessage(msg, type) {
    var root = activeAuthRoot();
    var slot = root && root.querySelector('.pdx-auth-msg-slot');
    if (!slot) return;
    var safe = msg ? escHtml(stripHtml(String(msg))) : '';
    slot.innerHTML = safe ? '<div class="pdx-auth-message pdx-auth-message--' + type + '">' + safe + '</div>' : '';
  }

  function clearPendingSocialError() {
    pendingAppleError = '';
    try {
      sessionStorage.removeItem('pdx_apple_error');
      sessionStorage.removeItem('pdx_github_error');
    } catch (e) {}
    removeAppleErrorBanner();
    showFormMessage('', '');
  }

  function clearPendingAppleError() {
    clearPendingSocialError();
  }

  function showPendingSocialError() {
    if (user.logged_in) {
      clearPendingSocialError();
      return;
    }
    var msg = pendingAppleError;
    if (!msg) {
      try { msg = sessionStorage.getItem('pdx_github_error') || sessionStorage.getItem('pdx_apple_error') || ''; } catch (e) {}
    }
    if (!msg) return;
    showFormMessage(msg, 'error');
  }

  function showPendingAppleError() {
    showPendingSocialError();
  }

  function showAppleErrorBanner(msg) {
    if (!authPageEl || !msg) return;
    var banner = document.getElementById('pdx-apple-error-banner');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'pdx-apple-error-banner';
      banner.className = 'pdx-auth-message pdx-auth-message--error pdx-auth-apple-error-banner';
      banner.setAttribute('role', 'alert');
      var guest = document.getElementById('pdx-auth-page-guest');
      if (guest) {
        guest.insertBefore(banner, guest.firstChild);
      } else {
        authPageEl.insertBefore(banner, authPageEl.firstChild);
      }
    }
    banner.textContent = msg;
    banner.hidden = false;
  }

  function removeAppleErrorBanner() {
    var banner = document.getElementById('pdx-apple-error-banner');
    if (banner && banner.parentNode) {
      banner.parentNode.removeChild(banner);
    }
  }

  function captureSocialErrorFromUrl(params) {
    if (!params) return;
    if (params.get('pdx_github') === 'error') {
      var githubMsg = params.get('pdx_msg') || 'GitHub sign-in failed. Please try again.';
      try {
        githubMsg = decodeURIComponent(githubMsg.replace(/\+/g, ' '));
      } catch (e) {}
      pendingAppleError = githubMsg;
      try { sessionStorage.setItem('pdx_github_error', githubMsg); } catch (err) {}
      return;
    }
    captureAppleErrorFromUrl(params);
  }

  function captureAppleErrorFromUrl(params) {
    if (!params || params.get('pdx_apple') !== 'error') return;
    var appleMsg = params.get('pdx_msg') || 'Apple sign-in failed. Please try again.';
    try {
      appleMsg = decodeURIComponent(appleMsg.replace(/\+/g, ' '));
    } catch (e) {}
    pendingAppleError = appleMsg;
    try { sessionStorage.setItem('pdx_apple_error', appleMsg); } catch (err) {}
  }

  function clearPendingAppleErrorFromUrl(params) {
    if (!params || params.get('pdx_apple') !== 'error') return;
    setTimeout(function () {
      if (window.history && window.history.replaceState) {
        try {
          params.delete('pdx_apple');
          params.delete('pdx_msg');
          var cleanQuery = params.toString();
          window.history.replaceState({}, '', window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash);
        } catch (err) {}
      }
    }, 60000);
  }

  function onAuthSubmit(e) {
    e.preventDefault();
    var form = e.target;
    var fd = new FormData(form);
    showFormMessage('', '');
    markFieldError(null);

    var validationError = validateAuthForm(currentView, fd);
    if (validationError) {
      showFormMessage(validationError.message, 'error');
      markFieldError(validationError.field);
      return;
    }

    setFormLoading(true);

    function done() { setFormLoading(false); }

    if (currentView === 'login') {
      apiFetch('POST', '/auth/login', {
        email: fd.get('email'),
        password: fd.get('password'),
        remember: true,
      }).then(function (data) {
        done();
        if (!data.success) {
          showFormMessage(normalizeRestMessage(data), 'error');
          return;
        }
        applySession({ user: data.user || user, nonce: data.nonce }, { reason: 'login', broadcast: true });
        var inline = finishAuthSuccess();
        if (!inline && isAuthPage()) {
          if (redirectAfterAuthSuccess()) {
            return;
          }
          updateAuthPagePanels();
        }
        if (!inline && !isAuthPage()) notify(data.message || 'Logged in.', 'info');
        var mod = returnModule;
        returnModule = null;
        refreshUser().then(function () {
          claimGuestSessionIfNeeded().finally(function () {
            if (!inline) {
              if (mod && window.PDXDock && window.PDXDock.openPanel) {
                window.PDXDock.openPanel(mod);
              }
            }
          });
        });
      }).catch(done);
    } else if (currentView === 'register') {
      apiFetch('POST', '/auth/register', {
        name: fd.get('name'),
        email: fd.get('email'),
        password: fd.get('password'),
      }).then(function (data) {
        done();
        if (!data.success) {
          showFormMessage(data.message || 'Registration failed.', 'error');
          return;
        }
        showFormMessage(data.message, 'success');
        setTimeout(function () {
          currentView = 'login';
          renderAuthForm(activeAuthRoot());
          showFormMessage('Account created. Sign in after verifying your email.', 'success');
        }, 2000);
      }).catch(done);
    } else if (currentView === 'forgot') {
      apiFetch('POST', '/auth/forgot-password', { email: fd.get('email') }).then(function (data) {
        done();
        showFormMessage(data.message || 'Check your email.', 'success');
      }).catch(done);
    } else if (currentView === 'reset') {
      var p1 = fd.get('password');
      var params = new URLSearchParams(window.location.search);
      apiFetch('POST', '/auth/reset-password', {
        token: params.get('token') || '',
        uid: parseInt(params.get('uid') || '0', 10),
        password: p1,
      }).then(function (data) {
        done();
        if (!data.success) { showFormMessage(data.message, 'error'); return; }
        showFormMessage(data.message, 'success');
        setTimeout(function () { currentView = 'login'; renderAuthForm(); }, 2000);
      }).catch(done);
    }
  }

  function notify(msg, type) {
    if (window.PDXDock && window.PDXDock.showNotif) {
      window.PDXDock.showNotif(msg, type);
    }
  }

  function isolateAuthShell() {
    if (!isAuthPage()) return;
    var root = document.getElementById('pdx-auth-page');
    if (!root) return;

    document.documentElement.classList.add('pdx-auth-isolated');
    document.body.classList.add('pdx-auth-isolated');
    document.body.classList.toggle('pdx-auth-guest-body', !user.logged_in);
    document.body.classList.toggle('pdx-account-dashboard-body', !!user.logged_in);

    var shell = document.getElementById('pdx-auth-isolated-shell');
    if (!shell) {
      shell = document.createElement('div');
      shell.id = 'pdx-auth-isolated-shell';
      shell.className = 'pdx-auth-isolated-shell';
      shell.setAttribute('role', 'main');
      document.body.appendChild(shell);
    }

    if (root.parentNode !== shell) {
      shell.appendChild(root);
    }

    if (user.logged_in) {
      removeAuthShellHomeLogo();
      ensureAccountDashboardHeader(shell);
    } else {
      removeAccountDashboardHeader();
      ensureAuthShellHomeLogo(shell);
    }
    suppressAuthPageIntruders();
  }

  var AUTH_SITE_LOGO_SELECTORS = [
    'header#dtr-main-header .dtr-header-left a.dtr-logo.logo-default #pax-isolated-logo.paxlogo-wrap',
    'header#dtr-main-header .dtr-header-left a.dtr-logo.logo-default .paxlogo-wrap',
    '#dtr-responsive-header a.dtr-logo.logo-default .pax-isolated-logo.paxlogo-wrap',
    '#dtr-responsive-header a.dtr-logo.logo-default #pax-isolated-logo.paxlogo-wrap',
    '#dtr-responsive-header a.dtr-logo.logo-default .paxlogo-wrap',
  ];
  var authShellLogoRetryTimer = null;

  function uniquifyLogoHtml(html) {
    if (!html) return '';
    var uid = 'a' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    return html
      .replace(/\sid="pax-isolated-logo"/g, ' id="pdx-auth-shell-logo"')
      .replace(/\sid="paxlogo-/g, ' id="paxlogo-' + uid + '-')
      .replace(/url\(#paxlogo-/g, 'url(#paxlogo-' + uid + '-');
  }

  function findSiteLogoNode() {
    var i;
    for (i = 0; i < AUTH_SITE_LOGO_SELECTORS.length; i++) {
      var node = document.querySelector(AUTH_SITE_LOGO_SELECTORS[i]);
      if (node && (node.querySelector('.paxlogo-svg') || node.querySelector('.paxlogo-layout'))) {
        return node;
      }
    }
    return null;
  }

  function resolveSiteLogoHtml() {
    var raw = '';
    if (typeof window.PAXDESIGN_LOGO_SVG_MOBILE === 'string' && window.PAXDESIGN_LOGO_SVG_MOBILE.trim()) {
      raw = window.PAXDESIGN_LOGO_SVG_MOBILE;
    } else if (typeof window.PAXDESIGN_LOGO_SVG === 'string' && window.PAXDESIGN_LOGO_SVG.trim()) {
      raw = window.PAXDESIGN_LOGO_SVG;
    } else {
      var node = findSiteLogoNode();
      if (node) {
        return uniquifyLogoHtml(node.outerHTML);
      }
      return '';
    }
    return uniquifyLogoHtml(raw);
  }

  function enhanceAuthShellLogo(link) {
    if (!link) return;
    var svg = link.querySelector('svg.paxlogo-svg');
    var paxGroup = link.querySelector('.paxlogo-pax');
    if (!svg || !paxGroup) return;

    var shine = paxGroup.querySelector('.paxlogo-pax-shine');
    if (shine) {
      shine.setAttribute('opacity', '0');
    }
    var paxText = paxGroup.querySelector('text');
    if (paxText) {
      paxText.setAttribute('fill', '#CCFF00');
    }

    var defs = svg.querySelector('defs');
    if (!defs) {
      defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      svg.insertBefore(defs, svg.firstChild);
    }

    var filterId = 'pdx-auth-pax-shadow';
    if (!defs.querySelector('#' + filterId)) {
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
      shadow.setAttribute('flood-opacity', '0.72');
      filter.appendChild(shadow);
      defs.appendChild(filter);
    }

    paxGroup.setAttribute('filter', 'url(#' + filterId + ')');
    paxGroup.classList.add('pdx-auth-pax-mark');
  }

  function mountOfficialLogoInLink(link, logoHtml, officialClass) {
    link.innerHTML = logoHtml;
    link.classList.add(officialClass || 'pdx-auth-shell-home--official');
    var wrap = link.querySelector('.paxlogo-wrap');
    if (wrap) {
      wrap.classList.add('is-visible', 'pdx-auth-shell-logo');
      wrap.setAttribute('aria-hidden', 'false');
    }
    enhanceAuthShellLogo(link);
  }

  function removeAuthShellHomeLogo() {
    var link = document.getElementById('pdx-auth-shell-home');
    if (link && link.parentNode) {
      link.parentNode.removeChild(link);
    }
    if (authShellLogoRetryTimer) {
      clearInterval(authShellLogoRetryTimer);
      authShellLogoRetryTimer = null;
    }
  }

  function removeAccountDashboardHeader() {
    var header = document.getElementById('pdx-account-header');
    if (header && header.parentNode) {
      header.parentNode.removeChild(header);
    }
  }

  function mountShellLogoLink(link, officialClass) {
    if (!link) return;
    var homeUrl = homePageUrl();
    var siteName = C.siteName || 'Home';
    link.href = homeUrl;
    link.setAttribute('aria-label', 'Back to ' + siteName);

    if (link.getAttribute('data-pdx-official-logo') === '1') {
      return;
    }

    var logoHtml = resolveSiteLogoHtml();
    if (logoHtml) {
      mountOfficialLogoInLink(link, logoHtml, officialClass);
      link.setAttribute('data-pdx-official-logo', '1');
      if (authShellLogoRetryTimer) {
        clearInterval(authShellLogoRetryTimer);
        authShellLogoRetryTimer = null;
      }
      return;
    }

    if (!link.textContent.trim()) {
      link.innerHTML = '<span class="pdx-auth-shell-home__wordmark">' + escHtml(siteName) + '</span>';
    }

    if (!authShellLogoRetryTimer) {
      var attempts = 0;
      authShellLogoRetryTimer = setInterval(function () {
        attempts += 1;
        var html = resolveSiteLogoHtml();
        if (html) {
          clearInterval(authShellLogoRetryTimer);
          authShellLogoRetryTimer = null;
          var logoLink = link.id ? document.getElementById(link.id) : null;
          if (logoLink) {
            mountOfficialLogoInLink(logoLink, html, officialClass);
            logoLink.setAttribute('data-pdx-official-logo', '1');
          }
        } else if (attempts >= 60) {
          clearInterval(authShellLogoRetryTimer);
          authShellLogoRetryTimer = null;
        }
      }, 50);
    }
  }

  function ensureAccountDashboardHeader(shell) {
    if (!shell) return;
    var header = document.getElementById('pdx-account-header');
    if (!header) {
      header = document.createElement('div');
      header.id = 'pdx-account-header';
      header.className = 'pdx-account-header';
      header.setAttribute('role', 'banner');
      shell.insertBefore(header, shell.firstChild);
    }

    var link = document.getElementById('pdx-account-header-home');
    if (!link) {
      link = document.createElement('a');
      link.id = 'pdx-account-header-home';
      link.className = 'pdx-account-header-home pdx-auth-shell-home';
      header.appendChild(link);
    } else if (link.parentNode !== header) {
      header.appendChild(link);
    }

    mountShellLogoLink(link, 'pdx-account-header-home--official');
    if (accountMobileMenuBtn) syncAccountMobileMenuPosition();
  }

  function ensureAuthShellHomeLogo(shell) {
    if (!shell) return;
    var link = document.getElementById('pdx-auth-shell-home');
    if (!link) {
      link = document.createElement('a');
      link.id = 'pdx-auth-shell-home';
      link.className = 'pdx-auth-shell-home';
      shell.insertBefore(link, shell.firstChild);
    }
    mountShellLogoLink(link, 'pdx-auth-shell-home--official');
  }

  function suppressAuthPageIntruders() {
    var selectors = [
      '#paxdesign-booking-root',
      '#pdx-auth-bar',
      'header',
      'footer',
      '.site-header',
      '.site-footer',
      '#masthead',
      '#colophon',
      '.elementor-location-header',
      '.elementor-location-footer',
      '.entry-header',
      '.page-header',
      '.page-title',
      '[class*="cookie" i]',
      '[id*="cookie" i]',
      '[class*="Cookie" i]',
      '[id*="Cookie" i]',
    ];
    selectors.forEach(function (selector) {
      try {
        document.querySelectorAll(selector).forEach(function (el) {
          if (el.closest('#pdx-auth-isolated-shell')) return;
          el.setAttribute('aria-hidden', 'true');
          el.hidden = true;
          el.style.setProperty('display', 'none', 'important');
        });
      } catch (e) {}
    });

    Array.prototype.forEach.call(document.body.children, function (child) {
      if (!child || child.id === 'pdx-auth-isolated-shell' || child.id === 'wpadminbar') return;
      child.setAttribute('aria-hidden', 'true');
      child.hidden = true;
      child.style.setProperty('display', 'none', 'important');
    });
  }

  function initAuthPage() {
    authPageEl = document.getElementById('pdx-auth-page');
    if (!authPageEl) return;
    isolateAuthShell();
    authPageFormEl = document.getElementById('pdx-auth-page-form');
    accountAppEl = document.getElementById('pdx-account-app');
    accountSidebarEl = document.getElementById('pdx-account-sidebar');
    accountMainEl = document.getElementById('pdx-account-main');
    var params = new URLSearchParams(window.location.search);
    if (user.logged_in) {
      clearPendingAppleError();
    } else if (params.get('pdx_apple') === 'error' || params.get('pdx_github') === 'error') {
      captureSocialErrorFromUrl(params);
    } else {
      clearPendingAppleError();
    }
    clearPendingAppleErrorFromUrl(params);
    var initialView = params.get('view') || 'login';
    if (initialView === 'reset' || params.get('pdx_reset') === '1') {
      currentView = 'reset';
    } else if (initialView === 'register') {
      currentView = 'register';
    } else if (initialView === 'forgot') {
      currentView = 'forgot';
    } else {
      currentView = 'login';
    }
    bindAuthPageControls();
    parseAccountSectionFromHash();
    updateAuthPagePanels();
    if (!user.logged_in) {
      renderAuthForm(authPageFormEl);
      syncAuthPageSegment();
      showPendingAppleError();
    }
    window.addEventListener('hashchange', parseAccountSectionFromHash);
  }

  function accountNavGroups() {
    var groups = [
      {
        label: t('nav_group_account', 'Account'),
        items: [
          { id: 'overview', label: t('nav_overview', 'Overview'), icon: 'dashboard' },
          { id: 'personal', label: t('nav_personal', 'Personal Information'), icon: 'user' },
          { id: 'settings', label: t('nav_settings', 'Account Settings'), icon: 'settings' },
          { id: 'security', label: t('nav_security', 'Security & Privacy'), icon: 'lock' },
          { id: 'notifications', label: t('nav_notifications', 'Notifications'), icon: 'bell' },
          { id: 'preferences', label: t('nav_preferences', 'Notification Preferences'), icon: 'settings' },
        ],
      },
      {
        label: t('nav_group_work', 'Your Work'),
        items: [
          { id: 'projects', label: t('nav_projects', 'Projects'), icon: 'folder' },
          { id: 'orders', label: t('nav_orders', 'Requests'), icon: 'receipt' },
          { id: 'records', label: t('nav_records', 'Records / Ticket History'), icon: 'file' },
          { id: 'files', label: t('nav_files', 'Files & Invoices'), icon: 'file' },
        ],
      },
      {
        label: t('nav_group_updates', 'Updates'),
        items: [
          { id: 'news', label: t('nav_news', 'News'), icon: 'news' },
        ],
      },
      {
        label: t('nav_group_support', 'Support'),
        items: [
          { id: 'support', label: t('nav_support', 'Messages'), icon: 'message' },
          { id: 'services', label: t('nav_services', 'Services'), icon: 'package' },
        ],
      },
    ];
    if (isMasterAdminUser()) {
      groups.push({
        label: t('nav_group_administration', 'Administration'),
        items: [
          { id: 'administration', label: t('nav_administration', 'Customer Management'), icon: 'settings' },
        ],
      });
    }
    return groups;
  }

  function accountSectionTitle(section) {
    var titles = {
      overview: t('nav_overview', 'Overview'),
      personal: t('nav_personal', 'Personal Information'),
      security: t('nav_security', 'Security & Privacy'),
      settings: t('nav_settings', 'Account Settings'),
      preferences: t('nav_preferences', 'Notification Preferences'),
      administration: t('nav_administration', 'Customer Management'),
      projects: t('nav_projects', 'Projects'),
      orders: t('nav_orders', 'Requests'),
      records: t('nav_records', 'Records / Ticket History'),
      files: t('nav_files', 'Files & Invoices'),
      news: t('nav_news', 'News'),
      notifications: t('nav_notifications', 'Notifications'),
      support: t('nav_support', 'Messages'),
      services: t('nav_services', 'Services'),
    };
    return titles[section] || t('account', 'Account');
  }

  function accountSectionLead(section) {
    var leads = {
      overview: t('lead_overview', 'A snapshot of your projects, requests, and account activity.'),
      personal: t('lead_personal', 'Update your name, photo, and contact details.'),
      security: t('lead_security', 'Manage your password, verification, and account privacy.'),
      settings: t('lead_settings', 'Review account details and jump to the right control.'),
      preferences: t('lead_preferences', 'Choose which emails and alerts you receive.'),
      administration: t('lead_administration', 'Manage registered customer profiles, levels, and permissions.'),
      projects: t('lead_projects', 'Track active work and deliverables.'),
      orders: t('lead_orders', 'View requests, billing, and payment history.'),
      records: t('lead_records', 'Review closed Cybercrime Support reports in read-only mode.'),
      files: t('lead_files', 'Download shared files and invoices.'),
      news: t('lead_news', 'Announcements and updates from PAXDesign.'),
      notifications: t('lead_notifications', 'Your account alerts and activity notifications.'),
      support: t('lead_support', 'Continue your conversation with PAXDesign.'),
      services: t('lead_services', 'Browse services and start new requests.'),
    };
    return leads[section] || '';
  }

  function accountSectionToPortalTab(section) {
    var map = {
      overview: 'overview',
      projects: 'projects',
      orders: 'orders',
      records: 'records',
      support: 'chat',
      services: 'services',
      news: 'news',
      notifications: 'notifications',
    };
    return map[section] || 'overview';
  }

  function parseAccountSectionFromHash() {
    if (!isAuthPage() || !user.logged_in) return;
    var hash = (window.location.hash || '').replace(/^#\/?/, '');
    if (!hash) hash = 'overview';
    if (hash === 'chat') hash = 'support';
    if (hash === 'profile') hash = 'personal';
    if (hash === 'alerts') hash = 'notifications';
    if (hash === 'privacy') hash = 'security';
    if (hash === 'notification-preferences' || hash === 'prefs') hash = 'preferences';
    accountState.section = hash;
    if (accountState.loaded) renderAccountApp();
  }

  function setAccountSection(section, options) {
    options = options || {};
    if (!isAuthPage() || !user.logged_in) {
      if (C.accountPageUrl) {
        window.location.href = accountPageUrl() + '#/' + section;
      }
      return;
    }
    accountState.section = section || 'overview';
    accountState.detail = options.keepDetail ? accountState.detail : null;
    if (!options.skipHash) {
      try {
        window.history.replaceState({}, '', window.location.pathname + window.location.search + '#/' + accountState.section);
      } catch (e) {}
    }
    if (isAccountMobileViewport()) closeAccountMobileNav();
    if (section === 'administration' && isMasterAdminUser()) {
      Promise.all([loadMasterAdminLevels(), loadMasterAdminCustomers()]).then(function () {
        renderAccountApp();
      });
      return;
    }
    renderAccountApp();
  }

  function activePortalContainer() {
    if (isAuthPage() && accountMainEl) return accountMainEl;
    if (portalOverlay) {
      return portalOverlay.querySelector('.pdx-customer-portal-body');
    }
    return null;
  }

  function renderAccountSidebar() {
    if (!accountSidebarEl) return;
    accountSidebarEl.style.removeProperty('display');
    var profileActive = accountState.section === 'personal' ? ' is-active' : '';
    var html = '<div class="pdx-account-sidebar-mobile-head">' +
      '<span class="pdx-account-sidebar-mobile-title">' + escHtml(t('account_navigation', 'Account navigation')) + '</span>' +
      '<button type="button" class="pdx-account-sidebar-close" aria-label="' + escHtml(t('close', 'Close')) + '">' +
        cxIcon('close', 20) +
      '</button>' +
    '</div>' +
    '<div class="pdx-account-sidebar-user">' +
      '<button type="button" class="pdx-account-sidebar-profile' + profileActive + '" data-account-section="personal">' +
        '<div class="pdx-account-identity">' +
          renderAccountAvatarHtml({ sizeClass: 'pdx-account-avatar--sidebar' }) +
          '<div class="pdx-account-sidebar-name-row">' +
            '<div class="pdx-account-name-line pdx-account-sidebar-name">' + accountDashboardNameWithBadge(user.display_name || t('account', 'Account'), user.verified, { size: 14, inline: true, context: 'account' }) + '</div>' +
            renderCustomerLevelBadge(null, { compact: true }) +
            '<div class="pdx-account-sidebar-email">' + escHtml(user.email || '') + '</div>' +
          '</div>' +
        '</div>' +
      '</button>' +
    '</div><div class="pdx-account-sidebar-nav">';
    accountNavGroups().forEach(function (group) {
      html += '<div class="pdx-account-nav-group"><div class="pdx-account-nav-label">' + escHtml(group.label) + '</div>';
      group.items.forEach(function (item) {
        var active = accountState.section === item.id ? ' is-active' : '';
        html += '<button type="button" class="pdx-account-nav-btn' + active + '" data-account-section="' + item.id + '">' +
          cxIcon(item.icon, 16) + '<span class="pdx-account-nav-text">' + escHtml(item.label) + '</span></button>';
      });
      html += '</div>';
    });
    html += '</div><div class="pdx-account-sidebar-footer">' +
      '<a class="pdx-portal-btn pdx-portal-btn--secondary pdx-portal-btn--full pdx-account-website-link" href="' + escHtml(homePageUrl()) + '">' +
        cxIcon('chevron', 16) + escHtml(t('continue_website', 'Continue to website')) +
      '</a>' +
      '<button type="button" class="pdx-portal-btn pdx-portal-btn--destructive pdx-portal-btn--full pdx-account-signout">' + cxIcon('logout', 16) + escHtml(t('sign_out', 'Sign Out')) + '</button>' +
    '</div>';
    accountSidebarEl.innerHTML = html;
    if (isAccountMobileViewport()) {
      accountSidebarEl.setAttribute('role', 'dialog');
      accountSidebarEl.setAttribute('aria-modal', 'true');
      accountSidebarEl.setAttribute('aria-hidden', accountMobileNavOpen ? 'false' : 'true');
    } else {
      accountSidebarEl.removeAttribute('role');
      accountSidebarEl.removeAttribute('aria-modal');
      accountSidebarEl.removeAttribute('aria-hidden');
    }
  }

  function renderAccountPersonalSection(profile) {
    profile = profile || accountProfileData();
    var avatarUrl = accountAvatarUrl(profile);
    var hasUpload = !!profile.avatar_has_upload;
    var removePhotoBtn = hasUpload
      ? '<button type="button" class="pdx-account-avatar__remove" id="pdx-profile-avatar-remove">' + escHtml(t('remove_photo', 'Remove photo')) + '</button>'
      : '';
    var removePhotoMobileBtn = hasUpload
      ? '<button type="button" class="pdx-account-avatar__remove pdx-account-avatar__remove--mobile" id="pdx-profile-avatar-remove-mobile">' + escHtml(t('remove_photo', 'Remove photo')) + '</button>'
      : '';
    return '<div class="pdx-account-card pdx-account-card--photo">' +
      '<div class="pdx-account-photo-hero">' +
        renderAccountAvatarHtml({ url: avatarUrl, sizeClass: 'pdx-account-avatar--profile-hero', profile: profile }) +
        renderCustomerLevelBadge(profile) +
        (accountLevelData(profile).level_description ? '<div class="pdx-account-profile-level-desc">' + escHtml(accountLevelData(profile).level_description) + '</div>' : '') +
      '</div>' +
      '<div class="pdx-account-profile-avatar-block pdx-account-profile-avatar-block--desktop">' +
        '<label class="pdx-account-avatar__change">' +
          escHtml(t('change_photo', 'Change photo')) +
          '<input type="file" id="pdx-profile-avatar-input" class="pdx-account-avatar__file-input" accept="image/jpeg,image/png,image/webp" hidden />' +
        '</label>' +
        removePhotoBtn +
      '</div>' +
      '<div class="pdx-account-profile-avatar-block pdx-account-profile-avatar-block--mobile">' +
        '<div class="pdx-account-avatar-actions">' +
          '<button type="button" class="pdx-account-avatar__change-btn" id="pdx-profile-avatar-change-mobile">' + escHtml(t('change_photo', 'Change photo')) + '</button>' +
          removePhotoMobileBtn +
        '</div>' +
      '</div>' +
      renderAccountAvatarPickerHtml(profile) +
      '<form id="pdx-customer-profile-form" class="pdx-apple-form">' +
        field('display_name', t('display_name', 'Display name'), profile.display_name || user.display_name) +
        field('email', t('email', 'Email'), profile.email || user.email, 'email') +
        '<div class="pdx-apple-form-actions">' + actionBtn(t('save_changes', 'Save changes'), { type: 'submit', icon: 'check', small: true, inline: true }) + '</div>' +
      '</form>' +
    '</div>';
  }

  function renderAccountSecuritySection() {
    var status = accountStatusText(user.verified);
    return renderAppleGroup([
      renderAppleRow({ label: t('account_status', 'Account Status'), value: status, chevron: false, className: 'pdx-apple-row--static' }),
      renderAppleRow({ label: t('email', 'Email'), value: user.email || '—', section: 'personal' }),
    ], t('privacy_caption', 'Your email identifies this account. It is not shown publicly on the website.')) +
    '<div class="pdx-account-card">' +
      '<div class="pdx-account-card-title">' + escHtml(t('password', 'Password')) + '</div>' +
      '<form id="pdx-customer-security-form" class="pdx-apple-form">' +
        field('current_password', t('current_password', 'Current password'), '', 'password') +
        field('new_password', t('new_password', 'New password'), '', 'password') +
        field('confirm_password', t('confirm_password', 'Confirm new password'), '', 'password') +
        '<div class="pdx-apple-form-actions">' + actionBtn(t('update_password', 'Update password'), { type: 'submit', icon: 'lock', small: true, inline: true }) + '</div>' +
      '</form>' +
    '</div>';
  }

  function renderAccountSettingsSection() {
    var profile = accountProfileData();
    return renderAppleGroup([
      renderAppleRow({ icon: 'user', label: t('nav_personal', 'Personal Information'), section: 'personal' }),
      renderAppleRow({ icon: 'lock', label: t('nav_security', 'Security & Privacy'), section: 'security' }),
      renderAppleRow({ icon: 'bell', label: t('nav_notifications', 'Notifications'), section: 'notifications' }),
      renderAppleRow({ icon: 'settings', label: t('nav_preferences', 'Notification Preferences'), section: 'preferences' }),
    ]) +
    renderAppleGroup([
      renderAppleRow({ label: t('language', 'Language'), value: accountLanguageLabel(), chevron: false, className: 'pdx-apple-row--static' }),
      renderAppleRow({ label: t('email', 'Email'), value: profile.email || user.email || '—', section: 'personal' }),
    ], t('settings_caption', 'These controls stay on this device and follow the language of the website.')) +
    renderAppleGroup([
      renderAppleRow({ label: t('continue_website', 'Continue to website'), href: homePageUrl(), className: 'pdx-apple-row--link' }),
    ]);
  }

  function renderAccountPreferencesSection(settings) {
    settings = settings || {};
    var prefs = settings.notifications || {};
    var toggles = [
      { key: 'chat', label: t('notify_chat', 'Message notifications') },
      { key: 'project', label: t('notify_project', 'Project updates') },
      { key: 'order', label: t('notify_order', 'Request updates') },
      { key: 'news', label: t('notify_news', 'News announcements') },
      { key: 'security', label: t('notify_security', 'Security alerts') },
      { key: 'push_enabled', label: t('notify_push', 'Push notifications (mobile app)') },
    ];
    var html = '<div class="pdx-account-card pdx-account-card--toggles"><div class="pdx-account-card-title">' + escHtml(t('notification_preferences', 'Notification preferences')) + '</div>' +
      '<form id="pdx-customer-settings-form"><div class="pdx-portal-toggle-group">';
    toggles.forEach(function (item) {
      var checked = prefs[item.key] !== false ? ' checked' : '';
      html += '<label class="pdx-portal-toggle">' +
        '<span class="pdx-portal-toggle__label">' + escHtml(item.label) + '</span>' +
        '<span class="pdx-portal-toggle__switch">' +
          '<input type="checkbox" name="' + escHtml(item.key) + '"' + checked + ' />' +
          '<span class="pdx-portal-toggle__track" aria-hidden="true"></span>' +
        '</span>' +
      '</label>';
    });
    html += '</div><div class="pdx-portal-btn-row">' +
      actionBtn(t('save_preferences', 'Save preferences'), { type: 'submit', icon: 'check', small: true }) +
    '</div></form></div>';
    return html;
  }

  function bindAccountSettingsForm(container) {
    var form = container.querySelector('#pdx-customer-settings-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      customerApiFetch('POST', '/customer/settings', {
        notifications: {
          chat: !!fd.get('chat'),
          project: !!fd.get('project'),
          order: !!fd.get('order'),
          news: !!fd.get('news'),
          security: !!fd.get('security'),
          push_enabled: !!fd.get('push_enabled'),
        },
      }).then(function (data) {
        notify((data && data.message) || t('settings_saved', 'Settings saved.'), data && data._ok !== false ? 'info' : 'warn');
        if (data && data._ok !== false) {
          accountState.settings = data;
        }
      });
    });
  }

  function renderAccountFilesSection(files) {
    files = files || [];
    var html = '<div class="pdx-account-card"><div class="pdx-account-card-title">' + escHtml(t('files_invoices', 'Files & Invoices')) + '</div>';
    if (!files.length) {
      html += '<p class="pdx-portal-empty">' + escHtml(t('no_shared_files', 'No shared files yet. Project deliverables and invoices appear here.')) + '</p>';
    } else {
      files.forEach(function (file) {
        var href = file.download_url || file.url || '#';
        html += '<a class="pdx-portal-row pdx-portal-row--link" href="' + escHtml(href) + '" target="_blank" rel="noopener">' +
          '<strong>' + escHtml(file.name || file.filename || t('file', 'File')) + '</strong>' +
          '<span>' + escHtml(file.project_title || file.type || '') + '</span></a>';
      });
    }
    return html + '</div>';
  }

  function triggerProfileAvatarFilePicker(input) {
    if (!input) return;
    try {
      input.click();
    } catch (err) {
      /* iOS Safari: ensure hidden inputs remain activatable from touch handlers. */
      input.removeAttribute('hidden');
      input.style.position = 'fixed';
      input.style.left = '-9999px';
      input.style.width = '1px';
      input.style.height = '1px';
      input.style.opacity = '0';
      input.click();
      input.setAttribute('hidden', 'hidden');
      input.removeAttribute('style');
    }
  }

  function uploadProfileAvatarFile(file, avatarInput) {
    if (!file) return;
    var fd = new FormData();
    fd.append('avatar', file);
    customerApiFormData('/customer/profile/avatar', fd).then(function (data) {
      if (data && data._ok !== false && data.profile) {
        applyAccountProfileUpdate(data.profile);
        notify(t('avatar_updated', 'Profile picture updated.'), 'info');
      } else {
        notify((data && data.message) || t('avatar_upload_failed', 'Could not upload profile picture.'), 'error');
      }
      if (avatarInput) avatarInput.value = '';
    }).catch(function () {
      notify(t('avatar_upload_failed', 'Could not upload profile picture.'), 'error');
      if (avatarInput) avatarInput.value = '';
    });
  }

  function removeProfileAvatarPhoto(removeBtns) {
    removeBtns = removeBtns || [];
    removeBtns.forEach(function (btn) { if (btn) btn.disabled = true; });
    customerApiFetch('DELETE', '/customer/profile/avatar').then(function (data) {
      if (data && data._ok !== false && data.profile) {
        applyAccountProfileUpdate(data.profile);
        notify(t('photo_removed', 'Photo removed. Your PAXDesign avatar is restored.'), 'info');
      } else {
        notify((data && data.message) || t('avatar_upload_failed', 'Could not upload profile picture.'), 'error');
        removeBtns.forEach(function (btn) { if (btn) btn.disabled = false; });
      }
    }).catch(function () {
      notify(t('avatar_upload_failed', 'Could not upload profile picture.'), 'error');
      removeBtns.forEach(function (btn) { if (btn) btn.disabled = false; });
    });
  }

  function bindAccountPersonalForm(container) {
    var form = container.querySelector('#pdx-customer-profile-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        customerApiFetch('POST', '/customer/profile', {
          display_name: fd.get('display_name'),
          email: fd.get('email'),
        }).then(function (data) {
          notify((data && data.message) || t('profile_updated', 'Profile updated.'), data && data._ok ? 'info' : 'warn');
          if (data && data._ok) {
            accountState.profile = unwrapProfileResponse(data);
            applyAccountUserFromPayload(accountState.profile);
            refreshUser({ trigger: 'profile_update' });
            renderAccountApp();
          }
        });
      });
    }
    var avatarInput = container.querySelector('#pdx-profile-avatar-input');
    if (avatarInput) {
      avatarInput.addEventListener('change', function () {
        var file = avatarInput.files && avatarInput.files[0];
        if (!file) return;
        uploadProfileAvatarFile(file, avatarInput);
      });
    }
    var changeMobileBtn = container.querySelector('#pdx-profile-avatar-change-mobile');
    if (changeMobileBtn && avatarInput) {
      changeMobileBtn.addEventListener('click', function (e) {
        e.preventDefault();
        triggerProfileAvatarFilePicker(avatarInput);
      });
    }
    var removeBtn = container.querySelector('#pdx-profile-avatar-remove');
    var removeMobileBtn = container.querySelector('#pdx-profile-avatar-remove-mobile');
    var removeHandler = function () {
      removeProfileAvatarPhoto([removeBtn, removeMobileBtn]);
    };
    if (removeBtn) removeBtn.addEventListener('click', removeHandler);
    if (removeMobileBtn) removeMobileBtn.addEventListener('click', removeHandler);
    container.querySelectorAll('[data-avatar-preset]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.getAttribute('data-avatar-locked') === '1' || btn.disabled) return;
        var presetId = btn.getAttribute('data-avatar-preset');
        if (!presetId || btn.classList.contains('is-selected')) return;
        btn.disabled = true;
        customerApiFetch('POST', '/customer/profile/avatar/preset', { preset_id: presetId }).then(function (data) {
          if (data && data._ok !== false && data.profile) {
            applyAccountProfileUpdate(data.profile);
            notify(t('avatar_preset_updated', 'Avatar updated.'), 'info');
          } else {
            notify((data && data.message) || t('avatar_upload_failed', 'Could not upload profile picture.'), 'error');
            btn.disabled = false;
          }
        }).catch(function () {
          notify(t('avatar_upload_failed', 'Could not upload profile picture.'), 'error');
          btn.disabled = false;
        });
      });
    });
  }

  function bindAccountSecurityForm(container) {
    var form = container.querySelector('#pdx-customer-security-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      var p1 = String(fd.get('new_password') || '');
      var p2 = String(fd.get('confirm_password') || '');
      if (p1.length < 8) {
        notify(t('password_min_length', 'Password must be at least 8 characters.'), 'warn');
        return;
      }
      if (p1 !== p2) {
        notify(t('passwords_mismatch', 'Passwords do not match.'), 'warn');
        return;
      }
      customerApiFetch('POST', '/customer/profile', {
        current_password: fd.get('current_password'),
        new_password: p1,
      }).then(function (data) {
        notify((data && data.message) || t('security_updated', 'Security settings updated.'), data && data._ok ? 'info' : 'warn');
        if (data && data._ok) form.reset();
      });
    });
  }

  function adminPresetUrlFromCatalog(presetId, presets, vipPresets) {
    var all = (presets || []).concat(vipPresets || []);
    for (var i = 0; i < all.length; i++) {
      if (all[i] && all[i].id === presetId) {
        return normalizeAvatarAssetUrl(all[i].url || '');
      }
    }
    return accountAvatarPresetUrl(presetId);
  }

  function adminCustomerAvatarUrl(customer) {
    if (!customer) return '';
    if (customer.avatar_has_upload) {
      return normalizeAvatarAssetUrl(customer.avatar_url || '');
    }
    var presetId = customer.avatar_preset || '';
    if (presetId && presetId !== 'pax-none') {
      var presetUrl = adminPresetUrlFromCatalog(presetId, customer.standard_presets, customer.vip_presets);
      if (presetUrl) return presetUrl;
    }
    return normalizeAvatarAssetUrl(customer.avatar_url || '');
  }

  function adminCustomerHasAvatar(customer) {
    if (!customer) return false;
    if (customer.avatar_has_image === false) return false;
    if (customer.avatar_has_upload) return true;
    if (customer.avatar_preset === 'pax-none') return false;
    return !!(adminCustomerAvatarUrl(customer) || customer.avatar_has_image);
  }

  function renderAdminCustomerAvatarHtml(customer, sizeClass) {
    if (!adminCustomerHasAvatar(customer)) return '';
    var url = adminCustomerAvatarUrl(customer);
    if (!url) return '';
    sizeClass = sizeClass || 'pdx-account-avatar--profile-compact';
    var px = ACCOUNT_AVATAR_PX[sizeClass] || 64;
    var fallback = adminPresetUrlFromCatalog(customer.avatar_preset, customer.standard_presets, customer.vip_presets) || url;
    return '<span class="pdx-account-avatar ' + sizeClass + '" style="width:' + px + 'px;height:' + px + 'px;max-width:' + px + 'px;max-height:' + px + 'px;flex:0 0 ' + px + 'px">' +
      '<img class="pdx-account-avatar__img" src="' + escHtml(url) + '" data-avatar-fallback="' + escHtml(fallback) + '" alt="" width="' + px + '" height="' + px + '" loading="lazy" decoding="async" onerror="window.__pdxAvatarFallback&&window.__pdxAvatarFallback(this)" />' +
    '</span>';
  }

  function renderAdminCustomerPreviewPanel(customer) {
    var avatarType = customer.avatar_has_upload
      ? t('admin_avatar_uploaded', 'Personal uploaded photo')
      : (customer.avatar_preset === 'pax-none'
        ? t('no_profile_picture', 'No profile picture')
        : t('admin_avatar_preset', 'PAXDesign avatar'));
    var presetLabel = customer.avatar_preset || '—';
    var presets = customer.standard_presets || [];
    var vipPresets = customer.vip_presets || [];
    for (var i = 0; i < presets.length; i++) {
      if (presets[i].id === customer.avatar_preset) {
        presetLabel = presets[i].label || presetLabel;
        break;
      }
    }
    for (var j = 0; j < vipPresets.length; j++) {
      if (vipPresets[j].id === customer.avatar_preset) {
        presetLabel = vipPresets[j].label || presetLabel;
        break;
      }
    }
    return '<div class="pdx-account-admin-preview">' +
      '<div class="pdx-account-admin-preview__label">' + escHtml(t('admin_customer_preview', 'Customer account preview')) + '</div>' +
      '<p class="pdx-account-admin-preview__lead">' + escHtml(t('admin_customer_preview_lead', 'Read-only view of how this customer sees their profile.')) + '</p>' +
      '<div class="pdx-account-admin-preview__card">' +
        '<div class="pdx-account-profile-identity">' +
          renderAdminCustomerAvatarHtml(customer, 'pdx-account-avatar--profile-compact') +
          '<div class="pdx-account-profile-identity-text">' +
            '<div class="pdx-account-name-line pdx-account-profile-name">' + accountDashboardNameWithBadge(customer.display_name || t('account', 'Account'), customer.verified, { size: 14, inline: true, context: 'account' }) + '</div>' +
            renderCustomerLevelBadge(customer) +
            (customer.level_description ? '<div class="pdx-account-profile-level-desc">' + escHtml(customer.level_description) + '</div>' : '') +
            '<div class="pdx-account-profile-email">' + renderAdminCustomerEmail(customer) + '</div>' +
          '</div>' +
        '</div>' +
        '<dl class="pdx-account-admin-meta">' +
          '<div><dt>' + escHtml(t('admin_avatar_type', 'Profile image')) + '</dt><dd>' + escHtml(avatarType) + '</dd></div>' +
          '<div><dt>' + escHtml(t('admin_current_avatar', 'Current avatar')) + '</dt><dd>' + escHtml(presetLabel) + '</dd></div>' +
          '<div><dt>' + escHtml(t('account_status', 'Account status')) + '</dt><dd>' + escHtml((customer.account_status || 'active').charAt(0).toUpperCase() + (customer.account_status || 'active').slice(1)) + '</dd></div>' +
          (customer.last_login ? '<div><dt>' + escHtml(t('admin_last_login', 'Last login')) + '</dt><dd>' + escHtml(customer.last_login) + '</dd></div>' : '') +
        '</dl>' +
      '</div>' +
    '</div>';
  }

  function renderAdminCustomerAvatarPickers(customer) {
    var presets = customer.standard_presets || accountState.masterStandardPresets || [];
    var vipPresets = customer.vip_presets || [];
    var currentPreset = customer.avatar_preset || '';
    var hasUpload = !!customer.avatar_has_upload;
    var html = '<div class="pdx-account-admin-avatars">' +
      '<div class="pdx-account-admin-section-title">' + escHtml(t('admin_manage_avatars', 'Avatar assignment')) + '</div>' +
      '<p class="pdx-account-admin-section-lead">' + escHtml(t('admin_manage_avatars_lead', 'Assign standard or VIP avatars. Locked VIP avatars require granting before the customer can use them.')) + '</p>';

    if (hasUpload) {
      html += '<div class="pdx-account-admin-upload-note">' +
        '<span>' + escHtml(t('admin_customer_has_upload', 'This customer has a personal uploaded photo active.')) + '</span>' +
        '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-admin-remove-upload">' + escHtml(t('admin_remove_upload', 'Remove uploaded photo')) + '</button>' +
      '</div>';
    }

    html += '<div class="pdx-account-avatar-picker">' +
      '<div class="pdx-account-avatar-picker__title">' + escHtml(t('paxdesign_avatars', 'PAXDesign avatars')) + '</div>' +
      '<div class="pdx-account-avatar-picker__grid pdx-account-admin-avatar-grid" role="listbox">';
    presets.forEach(function (preset) {
      var isNone = preset.type === 'none' || preset.id === 'pax-none';
      var selected = !hasUpload && preset.id === currentPreset;
      if (isNone) {
        html += '<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--none pdx-admin-assign-avatar' + (selected ? ' is-selected' : '') + '" data-avatar-preset="' + escHtml(preset.id) + '" title="' + escHtml(preset.label || t('no_profile_picture', 'No profile picture')) + '">' +
          '<span class="pdx-account-avatar-picker__none-mark" aria-hidden="true"></span>' +
          '<span class="pdx-account-avatar-picker__none-text">' + escHtml(preset.label || t('no_profile_picture', 'No profile picture')) + '</span></button>';
        return;
      }
      html += '<button type="button" class="pdx-account-avatar-picker__item pdx-admin-assign-avatar' + (selected ? ' is-selected' : '') + '" data-avatar-preset="' + escHtml(preset.id) + '" title="' + escHtml(preset.label || preset.id) + '">' +
        '<img src="' + escHtml(normalizeAvatarAssetUrl(preset.url || '')) + '" alt="" width="48" height="48" loading="lazy" decoding="async" /></button>';
    });
    html += '</div>';

    if (vipPresets.length) {
      html += '<div class="pdx-account-avatar-picker__subtitle">' + escHtml(t('exclusive_level_avatars', 'PAXDesign Level avatars')) + '</div>' +
        '<div class="pdx-account-avatar-picker__grid pdx-account-avatar-picker__grid--vip pdx-account-admin-avatar-grid" role="listbox">';
      vipPresets.forEach(function (preset) {
        var locked = !!preset.locked;
        var granted = (customer.vip_avatar_grants || []).indexOf(preset.id) !== -1;
        var selected = !hasUpload && !locked && preset.id === currentPreset;
        var lockLabel = t('vip_avatar_locked', 'Exclusive avatar — assigned by administrator only');
        html += '<div class="pdx-account-admin-vip-tile">' +
          '<button type="button" class="pdx-account-avatar-picker__item pdx-account-avatar-picker__item--vip pdx-admin-assign-avatar' +
            (locked ? ' pdx-account-avatar-picker__item--locked' : '') +
            (selected ? ' is-selected' : '') + '" data-avatar-preset="' + escHtml(preset.id) + '"' +
            (locked ? ' data-avatar-locked="1" disabled aria-disabled="true"' : '') +
            ' title="' + escHtml(locked ? lockLabel : (preset.label || preset.id)) + '">' +
            '<img src="' + escHtml(preset.url || '') + '" alt="" width="48" height="48" loading="lazy" decoding="async" />' +
            (locked ? '<span class="pdx-account-avatar-picker__lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 1a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V11a2 2 0 0 0-2-2h-1V6a5 5 0 0 0-5-5zm0 2a3 3 0 0 1 3 3v3H9V6a3 3 0 0 1 3-3z"/></svg></span>' : '') +
          '</button>' +
          '<div class="pdx-account-admin-vip-tile__meta">' +
            '<span class="pdx-account-admin-vip-tile__label">' + escHtml(preset.label || preset.id) + '</span>' +
            (granted
              ? '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-admin-revoke-vip" data-vip-id="' + escHtml(preset.id) + '">' + escHtml(t('revoke', 'Revoke')) + '</button>'
              : '<button type="button" class="pdx-portal-btn pdx-portal-btn--secondary pdx-admin-grant-vip" data-vip-id="' + escHtml(preset.id) + '">' + escHtml(t('grant', 'Grant')) + '</button>') +
          '</div></div>';
      });
      html += '</div>';
    }
    html += '</div></div>';
    return html;
  }

  function renderAdminLevelPanel(customer) {
    var levelOptions = (accountState.masterLevels || []).map(function (lvl) {
      var selected = Number(customer.customer_level) === Number(lvl.level) ? ' selected' : '';
      return '<option value="' + escHtml(String(lvl.level)) + '"' + selected + '>' + escHtml(lvl.label) + '</option>';
    }).join('');
    var selectedLevel = null;
    (accountState.masterLevels || []).forEach(function (lvl) {
      if (Number(lvl.level) === Number(customer.customer_level)) selectedLevel = lvl;
    });
    var previewHtml = '';
    if (selectedLevel) {
      previewHtml = '<div class="pdx-account-admin-level-preview">' +
        '<div class="pdx-account-admin-level-preview__title">' + escHtml(t('admin_level_preview', 'Level preview')) + '</div>' +
        renderCustomerLevelBadge({ level_label: selectedLevel.label, level_title: selectedLevel.title, level_description: selectedLevel.description, has_customer_level: true }) +
        (selectedLevel.title ? '<div class="pdx-account-admin-level-preview__name">' + escHtml(selectedLevel.title) + '</div>' : '') +
        (selectedLevel.description ? '<div class="pdx-account-admin-level-preview__desc">' + escHtml(selectedLevel.description) + '</div>' : '') +
        (selectedLevel.avatar_id ? '<div class="pdx-account-admin-level-preview__avatar"><img src="' + escHtml(adminPresetUrlFromCatalog(selectedLevel.avatar_id, customer.standard_presets, customer.vip_presets)) + '" alt="" width="64" height="64" loading="lazy" /></div>' : '') +
      '</div>';
    }
    return '<div class="pdx-account-admin-levels">' +
      '<div class="pdx-account-admin-section-title">' + escHtml(t('admin_manage_level', 'PAXDesign level')) + '</div>' +
      '<p class="pdx-account-admin-section-lead">' + escHtml(t('admin_manage_level_lead', 'Assign a customer level (01–10). The matching VIP avatar is granted automatically.')) + '</p>' +
      previewHtml +
    '</div>';
  }

  function adminCustomerEmailLabel(customer) {
    if (!customer) return '';
    var email = String(customer.email || '').trim();
    if (email) return email;
    var login = String(customer.user_login || '').trim();
    if (login.indexOf('@') !== -1) return login;
    return '';
  }

  function renderAdminCustomerEmail(customer) {
    var email = adminCustomerEmailLabel(customer);
    return email ? escHtml(email) : escHtml(t('email_unavailable', 'Email unavailable'));
  }

  function renderAccountAdminSection() {
    var customer = accountState.masterCustomer;
    if (customer && customer.id) {
      var levelOptions = (accountState.masterLevels || []).map(function (lvl) {
        var selected = Number(customer.customer_level) === Number(lvl.level) ? ' selected' : '';
        return '<option value="' + escHtml(String(lvl.level)) + '"' + selected + '>' + escHtml(lvl.label) + '</option>';
      }).join('');
      return '<div class="pdx-account-admin-editor">' +
        '<div class="pdx-account-card pdx-account-admin-toolbar">' +
          '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-admin-back">' + escHtml(t('back_to_customers', 'Back to customers')) + '</button>' +
          '<div class="pdx-account-admin-toolbar__identity">' +
            renderAdminCustomerAvatarHtml(customer, 'pdx-account-avatar--sidebar') +
            '<div><div class="pdx-account-card-title">' + escHtml(customer.display_name || customer.email || ('#' + customer.id)) + '</div>' +
            renderCustomerLevelBadge(customer) +
            '<div class="pdx-account-admin-toolbar__email">' + renderAdminCustomerEmail(customer) + '</div></div>' +
          '</div>' +
        '</div>' +
        renderAdminCustomerPreviewPanel(customer) +
        '<div class="pdx-account-card">' +
          '<div class="pdx-account-admin-section-title">' + escHtml(t('admin_account_details', 'Account details')) + '</div>' +
          '<form id="pdx-master-customer-form" class="pdx-account-form">' +
            '<label class="pdx-label">' + escHtml(t('display_name', 'Display name')) + '<input class="pdx-input" name="display_name" value="' + escHtml(customer.display_name || '') + '" /></label>' +
            '<label class="pdx-label">' + escHtml(t('email', 'Email')) + '<input class="pdx-input" name="email" type="email" value="' + escHtml(adminCustomerEmailLabel(customer)) + '" /></label>' +
            '<label class="pdx-label">' + escHtml(t('account_status', 'Account status')) +
              '<select class="pdx-select" name="account_status">' +
                ['active', 'pending', 'suspended'].map(function (st) {
                  return '<option value="' + st + '"' + (customer.account_status === st ? ' selected' : '') + '>' + escHtml(st.charAt(0).toUpperCase() + st.slice(1)) + '</option>';
                }).join('') +
              '</select></label>' +
            '<label class="pdx-label">' + escHtml(t('customer_level', 'PAXDesign level')) +
              '<select class="pdx-select" name="customer_level" id="pdx-admin-level-select"><option value="0">' + escHtml(t('no_level', 'No level')) + '</option>' + levelOptions + '</select></label>' +
            '<label class="pdx-label">' + escHtml(t('admin_notes', 'Internal notes')) + '<textarea class="pdx-input" name="admin_notes" rows="3">' + escHtml(customer.admin_notes || '') + '</textarea></label>' +
            '<dl class="pdx-account-admin-meta pdx-account-admin-meta--inline">' +
              '<div><dt>' + escHtml(t('registered', 'Registered')) + '</dt><dd>' + escHtml(customer.registered || '—') + '</dd></div>' +
              '<div><dt>' + escHtml(t('verified', 'Verified')) + '</dt><dd>' + escHtml(customer.verified ? t('yes', 'Yes') : t('no', 'No')) + '</dd></div>' +
              (customer.last_login ? '<div><dt>' + escHtml(t('admin_last_login', 'Last login')) + '</dt><dd>' + escHtml(customer.last_login) + '</dd></div>' : '') +
            '</dl>' +
            '<button type="submit" class="pdx-portal-btn pdx-portal-btn--primary">' + escHtml(t('save_changes', 'Save changes')) + '</button>' +
          '</form>' +
        '</div>' +
        '<div class="pdx-account-card">' + renderAdminLevelPanel(customer) + '</div>' +
        '<div class="pdx-account-card">' + renderAdminCustomerAvatarPickers(customer) + '</div>' +
      '</div>';
    }

    var rows = (accountState.masterCustomers && accountState.masterCustomers.customers) || [];
    var total = accountState.masterCustomers && accountState.masterCustomers.total;
    var page = accountState.masterCustomers && accountState.masterCustomers.page ? Number(accountState.masterCustomers.page) : accountState.masterPage;
    var perPage = accountState.masterCustomers && accountState.masterCustomers.per_page ? Number(accountState.masterCustomers.per_page) : accountState.masterPerPage;
    var totalPages = total > 0 ? Math.ceil(total / perPage) : 1;
    var html = '<div class="pdx-account-card">' +
      '<div class="pdx-account-admin-search">' +
        '<input type="search" class="pdx-input" id="pdx-master-customer-search" placeholder="' + escHtml(t('search_customers', 'Search customers…')) + '" value="' + escHtml(accountState.masterSearch || '') + '" />' +
        '<button type="button" class="pdx-portal-btn pdx-portal-btn--secondary" id="pdx-master-customer-search-btn">' + escHtml(t('search', 'Search')) + '</button>' +
      '</div>';
    if (typeof total === 'number') {
      html += '<div class="pdx-account-admin-count">' + escHtml(String(total)) + ' ' + escHtml(t('customers_total', 'customers')) +
        (totalPages > 1 ? ' · ' + escHtml(t('page', 'Page')) + ' ' + escHtml(String(page)) + ' / ' + escHtml(String(totalPages)) : '') +
      '</div>';
    }
    html += '<div class="pdx-account-admin-table-wrap"><table class="pdx-account-admin-table"><thead><tr>' +
        '<th></th><th>' + escHtml(t('customer', 'Customer')) + '</th><th>' + escHtml(t('email', 'Email')) + '</th><th>' + escHtml(t('level', 'Level')) + '</th><th>' + escHtml(t('status', 'Status')) + '</th><th></th>' +
      '</tr></thead><tbody>';
    if (!rows.length) {
      html += '<tr><td colspan="6">' + escHtml(t('no_customers_found', 'No customers found.')) + '</td></tr>';
    } else {
      rows.forEach(function (row) {
        html += '<tr>' +
          '<td class="pdx-account-admin-table__avatar">' + renderAdminCustomerAvatarHtml(row, 'pdx-account-avatar--menu') + '</td>' +
          '<td><strong>' + escHtml(row.display_name || '—') + '</strong></td>' +
          '<td>' + renderAdminCustomerEmail(row) + '</td>' +
          '<td>' + (row.level_label ? escHtml(row.level_label) : '—') + '</td>' +
          '<td><span class="pdx-account-admin-status pdx-account-admin-status--' + escHtml(row.account_status || 'active') + '">' + escHtml(row.account_status || '') + '</span></td>' +
          '<td><button type="button" class="pdx-portal-btn pdx-portal-btn--secondary pdx-admin-open-customer" data-customer-id="' + escHtml(String(row.id)) + '">' + escHtml(t('manage', 'Manage')) + '</button></td>' +
        '</tr>';
      });
    }
    html += '</tbody></table></div>';
    if (totalPages > 1) {
      html += '<div class="pdx-account-admin-pagination">' +
        '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-admin-page-prev"' + (page <= 1 ? ' disabled' : '') + '>' + escHtml(t('previous', 'Previous')) + '</button>' +
        '<span class="pdx-account-admin-pagination__label">' + escHtml(t('page', 'Page')) + ' ' + escHtml(String(page)) + ' / ' + escHtml(String(totalPages)) + '</span>' +
        '<button type="button" class="pdx-portal-btn pdx-portal-btn--ghost pdx-admin-page-next"' + (page >= totalPages ? ' disabled' : '') + '>' + escHtml(t('next', 'Next')) + '</button>' +
      '</div>';
    }
    html += '</div>';
    return html;
  }

  function loadMasterAdminCustomers(search, page) {
    accountState.masterSearch = search !== undefined ? (search || '') : accountState.masterSearch || '';
    if (page !== undefined) accountState.masterPage = Math.max(1, Number(page) || 1);
    var q = '?page=' + encodeURIComponent(String(accountState.masterPage)) +
      '&per_page=' + encodeURIComponent(String(accountState.masterPerPage || 50)) +
      '&_=' + Date.now();
    if (accountState.masterSearch) q += '&search=' + encodeURIComponent(accountState.masterSearch);
    return customerApiFetch('GET', '/customer/master/customers' + q).then(function (data) {
      if (data && data._ok !== false) {
        accountState.masterCustomers = data;
        if (data.page) accountState.masterPage = Number(data.page);
      }
      return data;
    });
  }

  function loadMasterAdminCustomer(id) {
    return customerApiFetch('GET', '/customer/master/customers/' + encodeURIComponent(String(id))).then(function (data) {
      if (data && data._ok !== false && data.customer) accountState.masterCustomer = data.customer;
      return data;
    });
  }

  function loadMasterAdminLevels() {
    if (accountState.masterLevels && accountState.masterLevels.length) {
      return Promise.resolve(accountState.masterLevels);
    }
    return customerApiFetch('GET', '/customer/master/levels').then(function (data) {
      if (data && data._ok !== false && Array.isArray(data.levels)) {
        accountState.masterLevels = data.levels;
      }
      if (data && data._ok !== false && Array.isArray(data.standard_presets)) {
        accountState.masterStandardPresets = data.standard_presets;
      }
      if (data && data._ok !== false && Array.isArray(data.vip_presets)) {
        accountState.masterVipPresets = data.vip_presets.map(function (preset) {
          if (!preset) return preset;
          preset.url = normalizeAvatarAssetUrl(preset.url || '');
          preset.locked = false;
          return preset;
        });
      }
      return data;
    });
  }

  function bindAccountAdminSection(container) {
    ensureAccountAvatarFallbackHandler();
    bindAccountAvatarFallbacks(container);
    var backBtn = container.querySelector('.pdx-admin-back');
    if (backBtn) {
      backBtn.addEventListener('click', function () {
        accountState.masterCustomer = null;
        loadMasterAdminCustomers().then(function () { renderAccountApp(); });
      });
    }
    container.querySelectorAll('.pdx-admin-open-customer').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-customer-id');
        if (!id) return;
        Promise.all([loadMasterAdminLevels(), loadMasterAdminCustomer(id)]).then(function () {
          renderAccountApp();
        });
      });
    });
    var searchBtn = container.querySelector('#pdx-master-customer-search-btn');
    var searchInput = container.querySelector('#pdx-master-customer-search');
    if (searchBtn && searchInput) {
      var runSearch = function () {
        accountState.masterPage = 1;
        loadMasterAdminCustomers(searchInput.value || '', 1).then(function () { renderAccountApp(); });
      };
      searchBtn.addEventListener('click', runSearch);
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          runSearch();
        }
      });
    }
    var prevPageBtn = container.querySelector('.pdx-admin-page-prev');
    var nextPageBtn = container.querySelector('.pdx-admin-page-next');
    if (prevPageBtn) {
      prevPageBtn.addEventListener('click', function () {
        if (prevPageBtn.disabled) return;
        loadMasterAdminCustomers(undefined, Math.max(1, accountState.masterPage - 1)).then(function () { renderAccountApp(); });
      });
    }
    if (nextPageBtn) {
      nextPageBtn.addEventListener('click', function () {
        if (nextPageBtn.disabled) return;
        loadMasterAdminCustomers(undefined, accountState.masterPage + 1).then(function () { renderAccountApp(); });
      });
    }
    container.querySelectorAll('.pdx-admin-grant-vip').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = accountState.masterCustomer && accountState.masterCustomer.id;
        var vipId = btn.getAttribute('data-vip-id');
        if (!id || !vipId) return;
        btn.disabled = true;
        customerApiFetch('POST', '/customer/master/customers/' + id, { vip_avatar_id: vipId }).then(function (data) {
          if (data && data._ok !== false && data.customer) {
            accountState.masterCustomer = data.customer;
            renderAccountApp();
            notify(t('vip_assigned', 'VIP avatar assigned.'), 'info');
          } else {
            notify((data && data.message) || t('update_failed', 'Update failed.'), 'error');
            btn.disabled = false;
          }
        });
      });
    });
    container.querySelectorAll('.pdx-admin-revoke-vip').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = accountState.masterCustomer && accountState.masterCustomer.id;
        var vipId = btn.getAttribute('data-vip-id');
        if (!id || !vipId) return;
        btn.disabled = true;
        customerApiFetch('POST', '/customer/master/customers/' + id, { revoke_vip_avatar_id: vipId }).then(function (data) {
          if (data && data._ok !== false && data.customer) {
            accountState.masterCustomer = data.customer;
            renderAccountApp();
            notify(t('vip_revoked', 'VIP avatar revoked.'), 'info');
          } else {
            notify((data && data.message) || t('update_failed', 'Update failed.'), 'error');
            btn.disabled = false;
          }
        });
      });
    });
    container.querySelectorAll('.pdx-admin-assign-avatar').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.getAttribute('data-avatar-locked') === '1' || btn.disabled) return;
        var id = accountState.masterCustomer && accountState.masterCustomer.id;
        var presetId = btn.getAttribute('data-avatar-preset');
        if (!id || !presetId || btn.classList.contains('is-selected')) return;
        btn.disabled = true;
        customerApiFetch('POST', '/customer/master/customers/' + id, { vip_avatar_id: presetId }).then(function (data) {
          if (data && data._ok !== false && data.customer) {
            accountState.masterCustomer = data.customer;
            renderAccountApp();
            notify(t('avatar_preset_updated', 'Avatar updated.'), 'info');
          } else {
            notify((data && data.message) || t('update_failed', 'Update failed.'), 'error');
            btn.disabled = false;
          }
        });
      });
    });
    var removeUploadBtn = container.querySelector('.pdx-admin-remove-upload');
    if (removeUploadBtn) {
      removeUploadBtn.addEventListener('click', function () {
        var id = accountState.masterCustomer && accountState.masterCustomer.id;
        if (!id) return;
        removeUploadBtn.disabled = true;
        customerApiFetch('POST', '/customer/master/customers/' + id, { remove_avatar_upload: true }).then(function (data) {
          if (data && data._ok !== false && data.customer) {
            accountState.masterCustomer = data.customer;
            renderAccountApp();
            notify(t('photo_removed', 'Photo removed. Your PAXDesign avatar is restored.'), 'info');
          } else {
            notify((data && data.message) || t('update_failed', 'Update failed.'), 'error');
            removeUploadBtn.disabled = false;
          }
        });
      });
    }
    var form = container.querySelector('#pdx-master-customer-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var id = accountState.masterCustomer && accountState.masterCustomer.id;
        if (!id) return;
        var fd = new FormData(form);
        customerApiFetch('POST', '/customer/master/customers/' + id, {
          display_name: fd.get('display_name'),
          email: fd.get('email'),
          account_status: fd.get('account_status'),
          customer_level: fd.get('customer_level'),
          admin_notes: fd.get('admin_notes'),
        }).then(function (data) {
          if (data && data._ok !== false && data.customer) {
            accountState.masterCustomer = data.customer;
            loadMasterAdminCustomers(undefined, accountState.masterPage).then(function () {
              renderAccountApp();
            });
            notify(t('customer_updated', 'Customer updated.'), 'info');
          } else {
            notify((data && data.message) || t('update_failed', 'Update failed.'), 'error');
          }
        });
      });
      var levelSelect = form.querySelector('#pdx-admin-level-select');
      if (levelSelect) {
        levelSelect.addEventListener('change', function () {
          var val = Number(levelSelect.value || 0);
          var customer = accountState.masterCustomer;
          if (!customer) return;
          var selected = null;
          (accountState.masterLevels || []).forEach(function (lvl) {
            if (Number(lvl.level) === val) selected = lvl;
          });
          customer.customer_level = val;
          customer.has_customer_level = val > 0;
          customer.level_label = selected ? selected.label : '';
          customer.level_title = selected ? selected.title : '';
          customer.level_description = selected ? selected.description : '';
          var levelPanel = container.closest('.pdx-account-admin-editor');
          if (levelPanel) {
            var card = levelPanel.querySelector('.pdx-account-admin-levels');
            if (card && card.parentElement) {
              card.parentElement.innerHTML = renderAdminLevelPanel(customer);
            }
            var previewCard = levelPanel.querySelector('.pdx-account-admin-preview__card');
            if (previewCard) {
              var badgeHost = previewCard.querySelector('.pdx-account-profile-identity-text');
              if (badgeHost) {
                var existingBadge = badgeHost.querySelector('.pdx-account-level-badge');
                if (existingBadge) existingBadge.remove();
                var existingDesc = badgeHost.querySelector('.pdx-account-profile-level-desc');
                if (existingDesc) existingDesc.remove();
                var badgeHtml = renderCustomerLevelBadge(customer);
                if (badgeHtml) {
                  var emailEl = badgeHost.querySelector('.pdx-account-profile-email');
                  if (emailEl) emailEl.insertAdjacentHTML('beforebegin', badgeHtml + (customer.level_description ? '<div class="pdx-account-profile-level-desc">' + escHtml(customer.level_description) + '</div>' : ''));
                }
              }
            }
          }
        });
      }
    }
  }

  function renderAccountMain() {
    if (!accountMainEl || !accountState.dashboard) return;
    var section = accountState.section;
    var head = '<div class="pdx-account-page-head"><h2 class="pdx-account-page-title">' + escHtml(accountSectionTitle(section)) + '</h2>' +
      '<p class="pdx-account-page-lead">' + escHtml(accountSectionLead(section)) + '</p></div>';

    if (section === 'personal') {
      accountMainEl.innerHTML = head + renderAccountPersonalSection(accountProfileData());
      bindAccountPersonalForm(accountMainEl);
      return;
    }
    if (section === 'security') {
      accountMainEl.innerHTML = head + renderAccountSecuritySection();
      bindAccountSecurityForm(accountMainEl);
      return;
    }
    if (section === 'settings') {
      accountMainEl.innerHTML = head + renderAccountSettingsSection();
      return;
    }
    if (section === 'preferences') {
      accountMainEl.innerHTML = head + renderAccountPreferencesSection(accountState.settings);
      bindAccountSettingsForm(accountMainEl);
      return;
    }
    if (section === 'files') {
      accountMainEl.innerHTML = head + renderAccountFilesSection(accountState.files);
      return;
    }
    if (section === 'administration') {
      if (!isMasterAdminUser()) {
        accountMainEl.innerHTML = head + '<p class="pdx-auth-error">' + escHtml(t('access_denied', 'Access denied.')) + '</p>';
        return;
      }
      accountMainEl.innerHTML = head + renderAccountAdminSection();
      bindAccountAdminSection(accountMainEl);
      return;
    }

    portalState.tab = accountSectionToPortalTab(section);
    portalState.detail = accountState.detail;
    portalState.dashboard = accountState.dashboard;
    accountMainEl.innerHTML = head + '<div class="pdx-account-portal-host"></div>';
    var host = accountMainEl.querySelector('.pdx-account-portal-host');
    renderCustomerPortalDashboard(host, accountState.dashboard);
  }

  function loadAccountAppData(force) {
    if (!isAuthPage() || !user.logged_in) return Promise.resolve(false);
    if (accountState.loaded && !force) {
      renderAccountApp();
      return Promise.resolve(true);
    }
    if (accountMainEl) accountMainEl.innerHTML = cxLoading(t('loading_account', 'Loading your account…'));
    return claimGuestSessionIfNeeded().then(function () {
      return refreshAccountAvatarPresets().then(function () {
        return Promise.all([
          customerApiFetch('GET', '/customer/dashboard'),
          customerApiFetch('GET', '/customer/profile?_=' + Date.now()),
          customerApiFetch('GET', '/customer/files'),
          customerApiFetch('GET', '/customer/settings'),
        ]);
      });
    }).then(function (results) {
      var dashboard = normalizeDashboardResponse(results[0]);
      if (!dashboard) {
        if (accountMainEl) accountMainEl.innerHTML = '<p class="pdx-auth-error">' + escHtml(t('sign_in_continue', 'Please sign in to continue.')) + '</p>';
        return false;
      }
      if (dashboard.code === 'pdx_email_unverified') {
        if (accountMainEl) accountMainEl.innerHTML = '<p class="pdx-auth-error">' + escHtml(t('verify_email_dashboard', 'Verify your email to access your account dashboard.')) + '</p>';
        return false;
      }
      return enrichAccountDashboard(dashboard).then(function (enriched) {
        accountState.dashboard = enriched;
        accountState.profile = unwrapProfileResponse(results[1]);
        applyAccountUserFromPayload(accountState.profile);
        accountState.files = (results[2] && Array.isArray(results[2].files)) ? results[2].files : (Array.isArray(results[2]) ? results[2] : []);
        accountState.settings = (results[3] && results[3]._ok !== false) ? results[3] : {};
        accountState.loaded = true;
        portalState.dashboard = enriched;
        renderAccountApp();
        return true;
      });
    }).catch(function () {
      if (accountMainEl) accountMainEl.innerHTML = '<p class="pdx-auth-error">' + escHtml(t('load_account_error', 'Unable to load your account. Please try again.')) + '</p>';
      return false;
    });
  }

  function syncAccountDashboardLayout() {
    if (!accountAppEl || !accountMainEl) return;
    accountAppEl.hidden = false;
    accountAppEl.style.removeProperty('display');
    accountMainEl.hidden = false;
    accountMainEl.style.removeProperty('display');
    accountMainEl.style.removeProperty('visibility');
    accountMainEl.setAttribute('aria-hidden', 'false');
  }

  function renderAccountApp() {
    if (!accountAppEl || !accountSidebarEl || !accountMainEl) return;
    applyAccountLocale();
    ensureAccountMobileChrome();
    bindAccountSidebarEvents();
    bindAccountMainEvents();
    renderAccountSidebar();
    renderAccountMain();
    bindAccountAvatarFallbacks(accountAppEl);
    if (authBar) bindAccountAvatarFallbacks(authBar);
    if (authMenu) bindAccountAvatarFallbacks(authMenu);
    syncAccountMobileOverlayMount();
    syncAccountDashboardLayout();
  }

  function initAccountApp(force) {
    if (!isAuthPage() || !user.logged_in) return;
    document.body.classList.add('pdx-account-dashboard-body');
    loadAccountAppData(force);
  }

  function bindAuthPageControls() {
    if (!authPageEl || authPageEl.dataset.bound === '1') return;
    authPageEl.dataset.bound = '1';
    authPageEl.querySelectorAll('.pdx-auth-page-segment-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setAuthPageView(btn.getAttribute('data-auth-view') || 'login');
      });
    });
  }

  function setAuthPageView(view) {
    currentView = view || 'login';
    if (isAuthPage() && authPageFormEl) {
      renderAuthForm(authPageFormEl);
      syncAuthPageSegment();
      try {
        var url = new URL(window.location.href);
        if (view === 'login') url.searchParams.delete('view');
        else url.searchParams.set('view', view);
        window.history.replaceState({}, '', url.pathname + url.search);
      } catch (e) {}
    } else {
      navigateToAuthPage(view);
    }
  }

  function syncAuthPageSegment() {
    if (!authPageEl) return;
    var activeView = currentView === 'register' ? 'register' : 'login';
    authPageEl.querySelectorAll('.pdx-auth-page-segment-btn').forEach(function (btn) {
      var view = btn.getAttribute('data-auth-view') || 'login';
      var active = view === activeView;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    var pageTitle = document.getElementById('pdx-auth-page-title');
    if (pageTitle) {
      pageTitle.textContent = activeView === 'register' ? t('create_account', 'Konto erstellen') : t('sign_in', 'Anmelden');
    }
  }

  function updateAuthPagePanels() {
    if (!authPageEl) return;
    isolateAuthShell();
    var guestPanel = document.getElementById('pdx-auth-page-guest');
    if (!guestPanel) return;
    if (user.logged_in) {
      guestPanel.hidden = true;
      if (accountAppEl) accountAppEl.hidden = false;
      initAccountApp(false);
    } else {
      guestPanel.hidden = false;
      if (accountAppEl) accountAppEl.hidden = true;
      document.body.classList.remove('pdx-account-dashboard-body');
      accountState.loaded = false;
      if (authPageFormEl) renderAuthForm(authPageFormEl);
      syncAuthPageSegment();
    }
  }

  /* ─── URL handlers ─────────────────────────────────────── */
  function handleUrlParams() {
    var params = new URLSearchParams(window.location.search);
    if (params.get('pdx_reset') === '1' && params.get('token')) {
      currentView = 'reset';
      if (isAuthPage()) {
        renderAuthForm(authPageFormEl);
      } else {
        openOverlay('reset');
      }
    }
    if (params.get('pdx_auth') === 'verified') {
      notify(decodeURIComponent(params.get('pdx_msg') || 'Email verified!'), 'info');
      refreshUser({ reason: 'verification', trigger: 'verification' });
      cleanUrl();
    }
    if (params.get('pdx_auth') === 'verify_failed') {
      notify(decodeURIComponent(params.get('pdx_msg') || 'Verification failed.'), 'warn');
      cleanUrl();
    }
    if (params.get('pdx_account') === '1') {
      if (C.accountPageUrl && !isAuthPage()) {
        window.location.replace(accountPageUrl(user.logged_in ? 'login' : 'login'));
        return;
      }
      if (user.logged_in) {
        if (redirectAfterAuthSuccess()) {
          return;
        }
        openAccountPanel();
      } else if (!isAuthPage()) {
        navigateToAuthPage('login');
      }
      cleanUrl();
    }
    if (isAuthPage() && user.logged_in && getReturnToParam()) {
      redirectAfterAuthSuccess();
    }
  }

  function cleanUrl() {
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', window.location.pathname);
    }
  }

  /* ─── Access gate ──────────────────────────────────────── */
  function renderAuthGate(container, moduleId, reason) {
    var title = reason === 'verify' ? t('verify_email_title', 'E-Mail bestätigen') : t('sign_in_required', 'Anmeldung erforderlich');
    var desc = reason === 'verify'
      ? t('verify_email_desc', 'Bitte bestätigen Sie Ihre E-Mail-Adresse, um fortzufahren.')
      : t('sign_in_required_desc', 'Melden Sie sich an, um auf Ihr Konto zuzugreifen.');
    var gateIcon = reason === 'verify' ? 'mail' : 'shield';
    var actions =
      '<button type="button" class="pdx-btn-pearl pdx-btn-pearl--sm pdx-btn-pearl--inline pdx-auth-gate-login">' +
        '<span class="pdx-btn-pearl__wrap">' + cxIcon(reason === 'verify' ? 'mail' : 'login', 16) +
        '<span>' + escHtml(reason === 'verify' ? t('resend_verification', 'Bestätigung erneut senden') : t('sign_in', 'Anmelden')) + '</span></span></button>';
    if (reason !== 'verify') {
      actions += '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-auth-gate-register">' +
        cxIcon('register', 16) + escHtml(t('create_account', 'Konto erstellen')) + '</button>';
    }
    container.innerHTML =
      '<div class="pdx-auth-gate pdx-cx-shell">' +
        '<div class="pdx-auth-gate-icon">' + cxIcon(gateIcon, 24) + '</div>' +
        '<div class="pdx-auth-gate-title">' + escHtml(title) + '</div>' +
        '<div class="pdx-auth-gate-desc">' + escHtml(desc) + '</div>' +
        '<div class="pdx-auth-gate-actions">' + actions + '</div>' +
      '</div>';
    container.querySelector('.pdx-auth-gate-login').addEventListener('click', function () {
      if (reason === 'verify') {
        apiFetch('POST', '/auth/resend-verification').then(function (data) {
          notify(data.message || 'Verification email sent.', data.success ? 'info' : 'warn');
        });
      } else {
        navigateToAuthPage('login');
      }
    });
    var regBtn = container.querySelector('.pdx-auth-gate-register');
    if (regBtn) {
      regBtn.addEventListener('click', function () { navigateToAuthPage('register'); });
    }
  }

  /* ─── Account dashboard ────────────────────────────────── */
  function renderAccountDashboard(container) {
    container.innerHTML = cxLoading('Loading your account…');
    apiFetch('GET', '/account/dashboard').then(function (data) {
      if (data.error || !data.profile) {
        container.innerHTML = '<div class="pdx-error">Could not load account. Please log in again.</div>';
        return;
      }
      dashboardData = data;
      renderDashboardUI(container, data);
    });
  }

  function renderDashboardUI(container, data) {
    if (data.is_admin) {
      renderAdminDashboardUI(container, data);
    } else {
      renderCustomerDashboardUI(container, data);
    }
  }

  function renderAdminDashboardUI(container, data) {
    var p = data.profile;
    var html =
      '<div class="pdx-account-dash pdx-cx-shell">' +
        '<div class="pdx-account-nav">' +
          navBtn('profile', 'Profile', true, 'user') +
          navBtn('api-keys', 'API Keys', false, 'key') +
          navBtn('integrations', 'Integrations', false, 'settings') +
          navBtn('license', 'License', false, 'shield') +
        '</div>' +
        '<div class="pdx-ph-body">' +
          sectionProfile(p) +
          sectionApiKeys(data.api_keys || []) +
          sectionIntegrations(data.integrations || []) +
          sectionLicense(data.license || {}, true) +
        '</div>' +
      '</div>';
    container.innerHTML = html;
    bindDashboardNav(container);
    bindProfileForm(container);
    bindApiKeyForms(container);
    bindLogout(container);
  }

  function renderCustomerDashboardUI(container, data) {
    var p = data.profile;
    var html =
      '<div class="pdx-account-dash pdx-account-dash--customer pdx-cx-shell">' +
        '<div class="pdx-account-nav">' +
          navBtn('profile', 'Overview', true, 'user') +
          navBtn('purchases', 'Purchases', false, 'package') +
          navBtn('invoices', 'Billing', false, 'receipt') +
          navBtn('subscription', 'Subscription', false, 'subscription') +
        '</div>' +
        '<div class="pdx-ph-body">' +
          sectionProfile(p) +
          sectionPurchases(data.purchases || []) +
          sectionInvoices(data.orders || []) +
          sectionCustomerSubscription(data.subscription || {}) +
        '</div>' +
      '</div>';
    container.innerHTML = html;
    bindDashboardNav(container);
    bindProfileForm(container);
    bindInvoiceActions(container);
    bindLogout(container);
  }

  function bindDashboardNav(container) {
    container.querySelectorAll('.pdx-account-nav-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        container.querySelectorAll('.pdx-account-nav-btn').forEach(function (b) { b.classList.remove('is-active'); });
        container.querySelectorAll('.pdx-account-section').forEach(function (s) { s.classList.remove('is-active'); });
        btn.classList.add('is-active');
        var sec = container.querySelector('#pdx-acc-' + btn.dataset.section);
        if (sec) sec.classList.add('is-active');
      });
    });
  }

  function navBtn(id, label, active, iconName) {
    return '<button type="button" class="pdx-account-nav-btn' + (active ? ' is-active' : '') + '" data-section="' + id + '">' +
      cxIcon(iconName || 'user', 15) + escHtml(label) + '</button>';
  }

  function sectionProfile(p) {
    var statusCls = p.verified ? 'verified' : 'pending';
    var statusLabel = p.verified ? 'Verified' : 'Pending verification';
    return '<div id="pdx-acc-profile" class="pdx-account-section is-active">' +
      '<div class="pdx-account-card">' +
        '<div class="pdx-account-card-title">' + cxIcon('user', 16) + 'Account Overview</div>' +
        '<p class="pdx-cx-card__sub">Your profile, verification status, and account security.</p>' +
        '<div class="pdx-account-profile-head">' + nameWithBadge(p.display_name || 'Account', p.verified, { size: 16, context: 'account' }) + '</div>' +
        '<div class="pdx-account-status-row">' +
          '<span class="pdx-account-status pdx-account-status--' + statusCls + '">' + escHtml(statusLabel) + '</span>' +
          (!p.verified ? '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-resend-verify">' + cxIcon('mail', 16) + 'Resend email</button>' : '') +
        '</div>' +
      '</div>' +
      '<div class="pdx-account-card">' +
        '<div class="pdx-account-card-title">' + cxIcon('settings', 16) + 'Profile & Security</div>' +
        '<form id="pdx-profile-form">' +
          field('display_name', 'Display name', p.display_name) +
          field('email', 'Email', p.email, 'email') +
          field('current_password', 'Current password (to change password)', '', 'password') +
          field('new_password', 'New password', '', 'password') +
          '<div style="margin-top:12px">' + actionBtn('Save changes', { type: 'submit', icon: 'check', small: true, inline: true }) + '</div>' +
        '</form>' +
      '</div>' +
      '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-logout-btn">' + cxIcon('logout', 16) + 'Log out</button>' +
    '</div>';
  }

  function field(name, label, value, type) {
    type = type || 'text';
    return '<div class="pdx-account-field"><label>' + escHtml(label) + '</label>' +
      '<input name="' + name + '" type="' + type + '" value="' + escHtml(value || '') + '" autocomplete="' + (type === 'password' ? 'new-password' : 'off') + '" /></div>';
  }

  function sectionApiKeys(keys) {
    var html = '<div id="pdx-acc-api-keys" class="pdx-account-section"><div class="pdx-account-card"><div class="pdx-account-card-title">Your API Keys</div>';
    keys.forEach(function (k) {
      var st = k.status || 'disconnected';
      html += '<div class="pdx-api-key-row" data-provider="' + escHtml(k.provider) + '">' +
        '<div class="pdx-api-key-header">' +
          '<span class="pdx-api-key-label">' + escHtml(k.label) + '</span>' +
          '<span class="pdx-api-key-status pdx-api-key-status--' + st + '">' + escHtml(st) + '</span>' +
        '</div>' +
        (k.masked ? '<div style="font-size:11px;color:#555;margin-bottom:4px">' + escHtml(k.masked) + '</div>' : '') +
        '<div class="pdx-api-key-actions">' +
          '<input type="password" placeholder="Enter API key" autocomplete="off" />' +
          '<button type="button" class="pdx-account-btn pdx-save-key">Save</button>' +
          '<button type="button" class="pdx-account-btn pdx-account-btn--ghost pdx-validate-key">Validate</button>' +
          '<button type="button" class="pdx-account-btn pdx-account-btn--ghost pdx-clear-key">Clear</button>' +
        '</div>' +
      '</div>';
    });
    return html + '</div></div>';
  }

  function sectionIntegrations(items) {
    var html = '<div id="pdx-acc-integrations" class="pdx-account-section"><div class="pdx-account-card"><div class="pdx-account-card-title">Provider Integrations</div>';
    items.forEach(function (i) {
      html += '<div class="pdx-api-key-row">' +
        '<div class="pdx-api-key-header">' +
          '<span class="pdx-api-key-label">' + escHtml(i.label) + '</span>' +
          '<span class="pdx-api-key-status pdx-api-key-status--' + escHtml(i.status) + '">' + escHtml(i.status.replace('_', ' ')) + '</span>' +
        '</div>' +
        '<div style="font-size:11px;color:#555">Source: ' + escHtml(i.source) + '</div>' +
      '</div>';
    });
    return html + '</div></div>';
  }

  function sectionLicense(lic, isAdmin) {
    return '<div id="pdx-acc-license" class="pdx-account-section">' +
      '<div class="pdx-license-placeholder">' +
        '<strong>License & Subscription</strong>' +
        'Plan: ' + escHtml(lic.plan || 'free') + ' · Status: ' + escHtml(lic.status || 'inactive') + '<br><br>' +
        (isAdmin
          ? 'Subscription management and license keys will be available here. Connect your billing plan to unlock premium modules across the platform.'
          : 'Your subscription and license status is shown in the Subscription tab.') +
      '</div></div>';
  }

  function sectionPurchases(items) {
    var html = '<div id="pdx-acc-purchases" class="pdx-account-section"><div class="pdx-account-card"><div class="pdx-account-card-title">' + cxIcon('package', 16) + 'My Purchases</div>';
    if (!items.length) {
      html += '<p class="pdx-account-empty">No active purchases yet. Premium modules unlock after payment.</p>';
    } else {
      html += '<div class="pdx-order-list">';
      items.forEach(function (item) {
        html += '<div class="pdx-order-row">' +
          '<div class="pdx-order-row-main">' +
            '<div class="pdx-order-product">' + escHtml(item.label || item.module_id) + '</div>' +
            '<div class="pdx-order-meta">Purchased: ' + escHtml(formatDate(item.purchased_at)) + '</div>' +
          '</div>' +
          '<span class="pdx-account-status pdx-account-status--verified">Active</span>' +
        '</div>';
      });
      html += '</div>';
    }
    return html + '</div></div>';
  }

  function sectionInvoices(orders) {
    var html = '<div id="pdx-acc-invoices" class="pdx-account-section"><div class="pdx-account-card"><div class="pdx-account-card-title">' + cxIcon('receipt', 16) + 'Invoices & Payments</div>';
    if (!orders.length) {
      html += '<p class="pdx-account-empty">No payment records yet.</p>';
    } else {
      html += '<div class="pdx-invoice-table-wrap"><table class="pdx-invoice-table"><thead><tr>' +
        '<th>Order</th><th>Date</th><th>Product</th><th>Amount</th><th>Status</th><th></th>' +
        '</tr></thead><tbody>';
      orders.forEach(function (o) {
        html += '<tr data-order-ref="' + escHtml(o.order_id) + '">' +
          '<td>' + escHtml(o.order_id) + '</td>' +
          '<td>' + escHtml(formatDate(o.paid_at)) + '</td>' +
          '<td>' + escHtml(o.product) + '</td>' +
          '<td>' + escHtml(o.currency + ' ' + Number(o.amount || 0).toFixed(2)) + '</td>' +
          '<td><span class="pdx-pay-status pdx-pay-status--' + escHtml(String(o.payment_status || '').toLowerCase()) + '">' + escHtml(o.payment_status) + '</span></td>' +
          '<td class="pdx-invoice-actions">' +
            '<button type="button" class="pdx-account-btn pdx-account-btn--ghost pdx-view-order">Details</button>' +
            (o.invoice_available ? ' <button type="button" class="pdx-account-btn pdx-download-invoice">Invoice</button>' : '') +
          '</td>' +
        '</tr>' +
        '<tr class="pdx-order-detail-row" hidden><td colspan="6"><div class="pdx-order-detail"></div></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    return html + '</div></div>';
  }

  function sectionCustomerSubscription(sub) {
    var modules = sub.active_modules || [];
    var html = '<div id="pdx-acc-subscription" class="pdx-account-section"><div class="pdx-account-card">' +
      '<div class="pdx-account-card-title">' + cxIcon('subscription', 16) + 'Subscription & License</div>' +
      '<p class="pdx-cx-card__sub">Your plan status and licensed modules.</p>' +
      '<div class="pdx-sub-summary">' +
        '<div class="pdx-profile-row"><span class="pdx-profile-label">Plan</span><span class="pdx-profile-value">' + escHtml(sub.plan || 'free') + '</span></div>' +
        '<div class="pdx-profile-row"><span class="pdx-profile-label">Status</span><span class="pdx-profile-value">' + escHtml(sub.status || 'inactive') + '</span></div>' +
        (sub.renewal_at ? '<div class="pdx-profile-row"><span class="pdx-profile-label">Renewal</span><span class="pdx-profile-value">' + escHtml(formatDate(sub.renewal_at)) + '</span></div>' : '') +
      '</div>';
    if (modules.length) {
      html += '<div class="pdx-account-card-title" style="margin-top:16px">Licensed Modules</div><div class="pdx-order-list">';
      modules.forEach(function (m) {
        html += '<div class="pdx-order-row"><div class="pdx-order-product">' + escHtml(m.label || m.module_id) + '</div><span class="pdx-account-status pdx-account-status--verified">Licensed</span></div>';
      });
      html += '</div>';
    } else {
      html += '<p class="pdx-account-empty">No active subscription or license modules. Purchase a module to unlock premium features.</p>';
    }
    return html + '</div></div>';
  }

  function formatDate(value) {
    if (!value) return '—';
    var d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleString();
  }

  function bindInvoiceActions(container) {
    container.querySelectorAll('.pdx-invoice-table tbody tr[data-order-ref]').forEach(function (row) {
      var ref = row.dataset.orderRef;
      var detailRow = row.nextElementSibling;
      var viewBtn = row.querySelector('.pdx-view-order');
      var dlBtn = row.querySelector('.pdx-download-invoice');
      if (viewBtn && detailRow) {
        viewBtn.addEventListener('click', function () {
          var open = !detailRow.hidden;
          container.querySelectorAll('.pdx-order-detail-row').forEach(function (r) { r.hidden = true; });
          if (open) return;
          var order = (dashboardData && dashboardData.orders || []).find(function (o) { return o.order_id === ref; });
          if (!order) return;
          detailRow.querySelector('.pdx-order-detail').innerHTML =
            '<strong>Transaction ID:</strong> ' + escHtml(order.transaction_id || '—') + '<br>' +
            '<strong>Access status:</strong> ' + escHtml(order.access_status || '—') + '<br>' +
            (order.expires_at ? '<strong>Expires:</strong> ' + escHtml(formatDate(order.expires_at)) + '<br>' : '');
          detailRow.hidden = false;
        });
      }
      if (dlBtn) {
        dlBtn.addEventListener('click', function () {
          apiFetch('GET', '/account/invoice/' + encodeURIComponent(ref)).then(function (data) {
            if (!data || !data.success || !data.html) {
              notify((data && data.message) || 'Invoice unavailable.', 'warn');
              return;
            }
            var blob = new Blob([data.html], { type: 'text/html' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = data.filename || 'invoice.html';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
          });
        });
      }
    });
  }

  function bindProfileForm(container) {
    var form = container.querySelector('#pdx-profile-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      apiFetch('POST', '/account/profile', {
        display_name: fd.get('display_name'),
        email: fd.get('email'),
        current_password: fd.get('current_password'),
        new_password: fd.get('new_password'),
      }).then(function (data) {
        notify(data.message || 'Updated.', data.success ? 'info' : 'warn');
        if (data.success) {
          if (data.user) {
            user = data.user;
            C.emailVerified = !!data.user.verified;
          }
          updateAuthBar();
          refreshUser();
          renderAccountDashboard(container);
        }
      });
    });
    var resend = container.querySelector('.pdx-resend-verify');
    if (resend) {
      resend.addEventListener('click', function () {
        apiFetch('POST', '/auth/resend-verification').then(function (data) {
          notify(data.message, data.success ? 'info' : 'warn');
        });
      });
    }
  }

  function bindApiKeyForms(container) {
    container.querySelectorAll('.pdx-api-key-row[data-provider]').forEach(function (row) {
      var provider = row.dataset.provider;
      row.querySelector('.pdx-save-key').addEventListener('click', function () {
        var key = row.querySelector('input').value;
        apiFetch('POST', '/account/api-keys', { provider: provider, key: key }).then(function (data) {
          notify(data.message, data.success ? 'info' : 'warn');
          if (data.success) renderAccountDashboard(container.closest('.pdx-ph-body') || container);
        });
      });
      row.querySelector('.pdx-validate-key').addEventListener('click', function () {
        apiFetch('POST', '/account/api-keys/validate', { provider: provider }).then(function (data) {
          notify(data.message, data.success ? 'info' : 'warn');
        });
      });
      row.querySelector('.pdx-clear-key').addEventListener('click', function () {
        apiFetch('POST', '/account/api-keys', { provider: provider, key: '' }).then(function (data) {
          notify('Key cleared.', 'info');
          renderAccountDashboard(container.closest('.pdx-ph-body') || container);
        });
      });
    });
  }

  function bindLogout(container) {
    var btn = container.querySelector('.pdx-logout-btn');
    if (!btn) return;
    btn.addEventListener('click', doLogout);
  }

  /* ─── Public API ───────────────────────────────────────── */
  function withPortalLang(path) {
    var lang = encodeURIComponent(customerPortalLang());
    if (path.indexOf('/customer/news') === 0 || path.indexOf('/customer/dashboard') === 0 || path.indexOf('/customer/orders') === 0 || path.indexOf('/customer/projects') === 0) {
      var join = path.indexOf('?') >= 0 ? '&' : '?';
      return path + join + 'lang=' + lang;
    }
    return path;
  }

  function customerApiFetch(method, path, body) {
    var base = (C.restUrl || '/wp-json/pdx/v1').replace(/\/$/, '');
    var requestPath = method === 'GET' ? withPortalLang(path) : path;
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': C.nonce || '',
      },
    };
    if (body && method !== 'GET') opts.body = JSON.stringify(body);
    return fetch(base + requestPath, opts).then(function (r) {
      return r.json().then(function (data) {
        data._status = r.status;
        data._ok = r.ok;
        return data;
      });
    });
  }

  var portalOverlay = null;
  var portalState = { tab: 'overview', dashboard: null, detail: null, servicesCatalog: null };

  var GUEST_SESSION_KEY = 'paxdesign-chat-session';
  var GUEST_DEVICE_TOKEN_KEY = 'paxdesign-chat-device-token';

  function readGuestChatStorage(key) {
    try {
      return localStorage.getItem(key) || sessionStorage.getItem(key) || '';
    } catch (e) {
      return '';
    }
  }

  function claimGuestSessionIfNeeded() {
    var guestSession = readGuestChatStorage(GUEST_SESSION_KEY);
    var deviceToken = readGuestChatStorage(GUEST_DEVICE_TOKEN_KEY);
    if (!guestSession || !deviceToken) {
      return Promise.resolve(false);
    }
    return customerApiFetch('POST', '/customer/chat/claim', {
      session_id: guestSession,
      device_token: deviceToken,
    }).then(function (data) {
      if (data && data.success) {
        try {
          localStorage.removeItem(GUEST_SESSION_KEY);
        } catch (e) {}
      }
      return !!(data && data.success);
    }).catch(function () {
      return false;
    });
  }

  function customerApiFormData(path, formData) {
    var base = (C.restUrl || '/wp-json/pdx/v1').replace(/\/$/, '');
    return fetch(base + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': C.nonce || '' },
      body: formData,
    }).then(function (r) {
      return r.json().then(function (data) {
        data._status = r.status;
        data._ok = r.ok;
        return data;
      });
    });
  }

  function portalNavBtn(id, label, iconName) {
    var active = portalState.tab === id ? ' is-active' : '';
    return '<button type="button" class="pdx-portal-nav-btn' + active + '" data-portal-tab="' + id + '">' +
      cxIcon(iconName || 'dashboard', 14) + escHtml(label) + '</button>';
  }

  function renderPortalNav() {
    return '<nav class="pdx-portal-nav" id="pdx-portal-nav">' +
      portalNavBtn('overview', 'Overview', 'dashboard') +
      portalNavBtn('chat', 'Chat', 'message') +
      portalNavBtn('projects', 'Projects', 'folder') +
      portalNavBtn('orders', 'Requests', 'receipt') +
      portalNavBtn('services', 'Services', 'package') +
      portalNavBtn('news', 'News', 'news') +
      portalNavBtn('notifications', 'Alerts', 'bell') +
    '</nav>';
  }

  function portalBackBtn(label) {
    return '<button type="button" class="pdx-portal-back" data-portal-back="1">&larr; ' + escHtml(label || t('back', 'Back')) + '</button>';
  }

  function bindPortalNav(container) {
    container.querySelectorAll('[data-portal-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        portalState.tab = btn.dataset.portalTab;
        portalState.detail = null;
        if (isAuthPage()) {
          setAccountSection(btn.dataset.portalTab === 'chat' ? 'support' : btn.dataset.portalTab);
          return;
        }
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
    });
    var back = container.querySelector('[data-portal-back]');
    if (back) {
      back.addEventListener('click', function () {
        portalState.detail = null;
        accountState.detail = null;
        if (isAuthPage()) {
          renderAccountApp();
          return;
        }
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
    }
  }

  function openCustomerPortal(tab) {
    tab = tab || 'overview';
    if (!user.logged_in) {
      navigateToAuthPage('login');
      return;
    }
    if (!user.verified && !user.is_admin) {
      notify(t('verify_email_access', 'Please verify your email to access your account.'), 'warn');
      navigateToAuthPage('login');
      return;
    }
    var section = tab === 'chat' ? 'support' : tab;
    if (isAuthPage()) {
      setAccountSection(section);
      return;
    }
    if (C.accountPageUrl) {
      window.location.href = accountPageUrl() + '#/' + section;
      return;
    }
    notify(t('account_not_configured', 'Account page is not configured.'), 'warn');
  }

  function closeCustomerPortal() {
    if (!portalOverlay) return;
    var body = portalOverlay.querySelector('.pdx-customer-portal-body');
    if (body && typeof body._pdxChatCleanup === 'function') {
      body._pdxChatCleanup();
      body._pdxChatCleanup = null;
    }
    portalOverlay.classList.remove('is-open');
    document.body.classList.remove('pdx-no-scroll');
  }

  function customerApiStream(path, body, onEvent) {
    var base = (C.restUrl || '/wp-json/pdx/v1').replace(/\/$/, '');
    return fetch(base + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-WP-Nonce': C.nonce || '',
      },
      body: JSON.stringify(body || {}),
    }).then(function (response) {
      if (!response.ok || !response.body) {
        return response.text().then(function (text) {
          var err = { code: 'stream_failed', message: 'Unable to stream AI response.' };
          try { err = JSON.parse(text); } catch (e) {}
          throw err;
        });
      }
      var reader = response.body.getReader();
      var decoder = new TextDecoder();
      var buffer = '';
      function pump() {
        return reader.read().then(function (chunk) {
          if (chunk.done) return;
          buffer += decoder.decode(chunk.value, { stream: true });
          var parts = buffer.split('\n\n');
          buffer = parts.pop() || '';
          parts.forEach(function (block) {
            block.split('\n').forEach(function (line) {
              if (line.indexOf('data: ') !== 0) return;
              var payload = line.slice(6).trim();
              if (!payload || payload === '[DONE]') return;
              try { onEvent(JSON.parse(payload)); } catch (e) {}
            });
          });
          return pump();
        });
      }
      return pump();
    });
  }

  function renderCustomerPortalDashboard(container, data) {
    portalState.dashboard = data;
    if (typeof container._pdxChatCleanup === 'function') {
      container._pdxChatCleanup();
      container._pdxChatCleanup = null;
    }
    var html = renderPortalNav() + '<div class="pdx-portal-content">';
    if (portalState.detail) {
      html += renderPortalDetailView(portalState.detail);
    } else {
      switch (portalState.tab) {
        case 'chat':
          html += renderPortalChatSection(data);
          break;
        case 'projects':
          html += renderPortalProjectsSection(data);
          break;
        case 'orders':
          html += renderPortalOrdersSection(data);
          break;
        case 'records':
          html += renderPortalRecordsSection();
          break;
        case 'services':
          html += renderPortalServicesSection();
          break;
        case 'news':
          html += renderPortalNewsSection(data);
          break;
        case 'notifications':
          html += renderPortalNotificationsSection();
          break;
        default:
          html += renderPortalOverviewSection(data);
      }
    }
    html += '</div>';
    container.innerHTML = html;
    bindPortalNav(container);
    if (portalState.tab === 'chat' && !portalState.detail) {
      initCustomerPortalChat(container, (data.chat || {}).session_id || '');
    }
    if (portalState.tab === 'services' && !portalState.detail) {
      bindPortalServicesSection(container);
    }
    if (portalState.tab === 'notifications' && !portalState.detail) {
      bindPortalNotificationsSection(container);
    }
    if (portalState.tab === 'records' && !portalState.detail) {
      bindPortalRecordsSection(container);
    }
    container.querySelectorAll('[data-portal-open]').forEach(function (el) {
      el.addEventListener('click', function () {
        openPortalDetail(container, el.dataset.portalOpen, el.dataset.portalId || el.dataset.portalSlug || '');
      });
    });
  }

  function openPortalDetail(container, kind, id) {
    portalState.detail = { kind: kind, id: id };
    accountState.detail = portalState.detail;
    var body = container;
    if (kind === 'project') {
      body.innerHTML = renderPortalNav() + '<div class="pdx-portal-content">' + cxLoading('Loading project…') + '</div>';
      customerApiFetch('GET', '/customer/projects/' + encodeURIComponent(id)).then(function (project) {
        if (!project || !project._ok) {
          portalState.detail = null;
          renderCustomerPortalDashboard(container, portalState.dashboard);
          notify('Project could not be loaded.', 'warn');
          return;
        }
        portalState.detail.data = project;
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
      return;
    }
    if (kind === 'order') {
      body.innerHTML = renderPortalNav() + '<div class="pdx-portal-content">' + cxLoading('Loading request…') + '</div>';
      customerApiFetch('GET', '/customer/orders/' + encodeURIComponent(id)).then(function (order) {
        if (!order || !order._ok) {
          portalState.detail = null;
          renderCustomerPortalDashboard(container, portalState.dashboard);
          notify('Request could not be loaded.', 'warn');
          return;
        }
        portalState.detail.data = order;
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
      return;
    }
    if (kind === 'news') {
      body.innerHTML = renderPortalNav() + '<div class="pdx-portal-content">' + cxLoading('Loading article…') + '</div>';
      customerApiFetch('GET', '/customer/news/' + encodeURIComponent(id)).then(function (item) {
        if (!item || !item._ok) {
          portalState.detail = null;
          renderCustomerPortalDashboard(container, portalState.dashboard);
          notify('Article could not be loaded.', 'warn');
          return;
        }
        portalState.detail.data = item;
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
      return;
    }
    if (kind === 'service') {
      body.innerHTML = renderPortalNav() + '<div class="pdx-portal-content">' + cxLoading(t('loading_services', 'Loading services…')) + '</div>';
      resolveServiceBySlug(id).then(function (service) {
        if (!service || !service._ok) {
          portalState.detail = null;
          renderCustomerPortalDashboard(container, portalState.dashboard);
          notify(t('services_load_error', 'Services could not be loaded.'), 'warn');
          return;
        }
        portalState.detail.data = service;
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
      return;
    }
    if (kind === 'cybercrime') {
      body.innerHTML = renderPortalNav() + '<div class="pdx-portal-content">' + cxLoading('Loading record…') + '</div>';
      customerApiFetch('GET', '/customer/cybercrime/reports/' + encodeURIComponent(id)).then(function (data) {
        var report = data && data.report ? data.report : null;
        if (!report || !report.reference_id || !data._ok) {
          portalState.detail = null;
          renderCustomerPortalDashboard(container, portalState.dashboard);
          notify('Record could not be loaded.', 'warn');
          return;
        }
        portalState.detail.data = report;
        renderCustomerPortalDashboard(container, portalState.dashboard);
      });
    }
  }

  function renderPortalDetailView(detail) {
    if (!detail.data) {
      return cxLoading('Loading…');
    }
    if (detail.kind === 'project') {
      return renderPortalProjectDetail(detail.data);
    }
    if (detail.kind === 'order') {
      return renderPortalOrderDetail(detail.data);
    }
    if (detail.kind === 'news') {
      return renderPortalNewsDetail(detail.data);
    }
    if (detail.kind === 'service') {
      return renderPortalServiceDetail(detail.data);
    }
    if (detail.kind === 'cybercrime') {
      return renderPortalCybercrimeDetail(detail.data);
    }
    return '';
  }

  function renderPortalOverviewSection(data) {
    var projects = data.projects_active || [];
    var orders = data.orders_recent || [];
    var news = data.news || [];
    var unread = data.unread_count || 0;
    var chat = data.chat || {};
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('dashboard', 16) + escHtml(t('welcome', 'Welcome')) + '</h3>';
    html += '<p class="pdx-portal-lead">' + escHtml(t('overview_lead', 'Your projects, requests, and conversations in one place.')) + '</p>';
    html += '<div class="pdx-portal-stats">';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="projects"><span>' + projects.length + '</span>' + escHtml(t('active_projects', 'Active projects')) + '</button>';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="orders"><span>' + orders.length + '</span>' + escHtml(t('recent_requests', 'Recent requests')) + '</button>';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="notifications"><span>' + unread + '</span>' + escHtml(t('unread_alerts', 'Unread alerts')) + '</button>';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="chat"><span>' + escHtml(chat.handler || 'ai') + '</span>' + escHtml(t('chat_mode', 'Chat mode')) + '</button>';
    html += '</div></section>';
    html += '<section class="pdx-portal-section"><h3>' + cxIcon('folder', 16) + escHtml(t('active_projects', 'Active projects')) + '</h3>';
    if (projects.length) {
      projects.slice(0, 4).forEach(function (p) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="project" data-portal-id="' + escHtml(String(p.id)) + '">' +
          '<strong>' + escHtml(p.title) + '</strong><span>' + escHtml(String(p.progress || 0)) + '% · ' + escHtml(p.status) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">' + escHtml(t('no_active_projects_overview', 'No active projects yet. Browse services to request work.')) + '</p>';
    }
    html += '</section>';
    html += '<section class="pdx-portal-section"><h3>' + cxIcon('news', 16) + escHtml(t('latest_news', 'Latest news')) + '</h3>';
    if (news.length) {
      news.slice(0, 3).forEach(function (n) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="news" data-portal-slug="' + escHtml(n.slug || '') + '">' +
          '<strong>' + escHtml(n.title) + '</strong></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">' + escHtml(t('no_announcements', 'No announcements right now.')) + '</p>';
    }
    html += '</section>';
    setTimeout(function () {
      document.querySelectorAll('[data-portal-tab-jump]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          portalState.tab = btn.dataset.portalTabJump;
          portalState.detail = null;
          if (isAuthPage()) {
            var jump = btn.dataset.portalTabJump;
            setAccountSection(jump === 'chat' ? 'support' : jump, { keepDetail: false });
            return;
          }
          var body = activePortalContainer();
          if (body) renderCustomerPortalDashboard(body, portalState.dashboard);
        });
      });
    }, 0);
    return html;
  }

  function renderPortalChatSection(data) {
    var sessionId = (data.chat || {}).session_id || '';
    return '<section class="pdx-portal-section pdx-portal-chat">' +
      '<div class="pdx-portal-chat-head">' +
        '<h3>' + cxIcon('message', 16) + escHtml(t('conversation', 'Conversation')) + '</h3>' +
      '</div>' +
      '<div class="pdx-portal-chat-log" id="pdx-portal-chat-log">' + cxLoading(t('loading_messages', 'Loading messages…')) + '</div>' +
      '<div class="pdx-portal-chat-tools">' +
        '<label class="pdx-portal-tool" title="' + escHtml(t('attach_image', 'Attach image')) + '"><input type="file" accept="image/*" id="pdx-portal-image-input" hidden />' + cxIcon('image', 16) + '</label>' +
        '<label class="pdx-portal-tool" title="' + escHtml(t('attach_file', 'Attach file')) + '"><input type="file" id="pdx-portal-file-input" hidden />' + cxIcon('file', 16) + '</label>' +
        '<button type="button" class="pdx-portal-tool" id="pdx-portal-voice-btn" title="' + escHtml(t('voice_message', 'Voice message')) + '">' + cxIcon('mic', 16) + '</button>' +
        '<button type="button" class="pdx-portal-tool" id="pdx-portal-location-btn" title="' + escHtml(t('share_location', 'Share location')) + '">' + cxIcon('location', 16) + '</button>' +
      '</div>' +
      '<form class="pdx-portal-chat-form" id="pdx-portal-chat-form">' +
        '<textarea rows="2" placeholder="' + escHtml(t('write_message', 'Write a message…')) + '" aria-label="' + escHtml(t('message', 'Message')) + '"></textarea>' +
        actionBtn(t('send', 'Send'), { type: 'submit', small: true, inline: true, icon: 'send' }) +
      '</form></section>';
  }

  function renderPortalProjectsSection(data) {
    var projects = data.projects_active || [];
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('folder', 16) + escHtml(t('your_projects', 'Your projects')) + '</h3>';
    if (projects.length) {
      projects.forEach(function (p) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="project" data-portal-id="' + escHtml(String(p.id)) + '">' +
          '<strong>' + escHtml(p.title) + '</strong><span>' + escHtml(String(p.progress || 0)) + '% · ' + escHtml(p.status) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">' + escHtml(t('no_active_projects', 'No active projects yet. Request a service to begin.')) + '</p>';
    }
    return html + '</section>';
  }

  function renderPortalProjectDetail(project) {
    var html = portalBackBtn(t('all_projects', 'All projects'));
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(project.title) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(project.ref) + ' · ' + escHtml(project.status) + ' · ' + escHtml(String(project.progress || 0)) + '%</p>';
    if (project.description) {
      html += '<div class="pdx-portal-body-text">' + escHtml(project.description) + '</div>';
    }
    if ((project.milestones || []).length) {
      html += '<h4>' + escHtml(t('milestones', 'Milestones')) + '</h4><ul class="pdx-portal-list">';
      project.milestones.forEach(function (m) {
        html += '<li><strong>' + escHtml(m.title) + '</strong> — ' + escHtml(m.status) +
          (m.due_date ? ' · ' + escHtml(t('due', 'due')) + ' ' + escHtml(formatDate(m.due_date)) : '') + '</li>';
      });
      html += '</ul>';
    }
    if ((project.notes || []).length) {
      html += '<h4>' + escHtml(t('updates', 'Updates')) + '</h4><ul class="pdx-portal-list">';
      project.notes.forEach(function (n) {
        html += '<li>' + escHtml(n.body) + '</li>';
      });
      html += '</ul>';
    }
    if ((project.files || []).length) {
      html += '<h4>' + escHtml(t('files', 'Files')) + '</h4><ul class="pdx-portal-list">';
      project.files.forEach(function (f) {
        var dl = '/customer/projects/' + project.id + '/files/' + f.id + '/download';
        html += '<li><a href="' + escHtml((C.restUrl || '/wp-json/pdx/v1').replace(/\/$/, '') + dl) + '" target="_blank" rel="noopener">' + escHtml(f.file_name) + '</a></li>';
      });
      html += '</ul>';
    }
    if ((project.assignees || []).length) {
      html += '<h4>' + escHtml(t('your_team', 'Your team')) + '</h4><ul class="pdx-portal-list">';
      project.assignees.forEach(function (a) {
        html += '<li>' + escHtml(a.display_name || (t('staff', 'Staff') + ' #' + a.user_id)) + ' — ' + escHtml(a.role_label || t('staff', 'Staff')) + '</li>';
      });
      html += '</ul>';
    }
    return html + '</article>';
  }

  function renderPortalOrdersSection(data) {
    var orders = data.orders_recent || [];
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('receipt', 16) + escHtml(t('service_requests', 'Service requests')) + '</h3>';
    if (orders.length) {
      orders.forEach(function (o) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="order" data-portal-id="' + escHtml(String(o.id)) + '">' +
          '<strong>' + escHtml(o.service_label || o.ref) + '</strong><span>' + escHtml(o.status) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">' + escHtml(t('no_service_requests', 'No service requests yet.')) + '</p>';
    }
    return html + '</section>';
  }

  function renderPortalOrderDetail(order) {
    var html = portalBackBtn(t('all_requests', 'All requests'));
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(order.service_label || order.ref) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(order.ref) + ' · ' + escHtml(order.status) + '</p>';
    if (order.message) {
      html += '<div class="pdx-portal-body-text">' + escHtml(order.message) + '</div>';
    }
    if (order.notes && order.notes.length) {
      html += '<h4>' + escHtml(t('notes', 'Notes')) + '</h4><ul class="pdx-portal-list">';
      order.notes.forEach(function (n) {
        html += '<li>' + escHtml(n.body) + '</li>';
      });
      html += '</ul>';
    }
    return html + '</article>';
  }

  function portalFormatDate(value) {
    if (!value) {
      return '—';
    }
    var d = new Date(String(value).replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) {
      return String(value);
    }
    try {
      var lang = customerPortalLang();
      var locale = lang === 'ar' ? 'ar' : (lang === 'de' ? 'de-DE' : 'en-US');
      return d.toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' });
    } catch (e) {
      return String(value);
    }
  }

  function cybercrimeReportIsActive(report) {
    if (!report) {
      return false;
    }
    if (report.is_active === false || report.is_active === 0 || report.is_active === '0') {
      return false;
    }
    var status = String(report.status || '');
    return status !== 'closed' && status !== 'resolved';
  }

  function cybercrimeVisibleTimeline(report) {
    return (report && report.timeline ? report.timeline : []).filter(function (entry) {
      if (!entry || entry.author_type === 'ai' || entry.channel === 'chat') {
        return false;
      }
      if (entry.customer_visible === false) {
        return false;
      }
      return !!(entry.body && String(entry.body).trim());
    }).sort(function (a, b) {
      var aTime = String(a.created_at || '') + String(a.id != null ? a.id : '');
      var bTime = String(b.created_at || '') + String(b.id != null ? b.id : '');
      return bTime.localeCompare(aTime);
    });
  }

  function renderPortalRecordsSection() {
    return '<section class="pdx-portal-section pdx-portal-records" id="pdx-portal-records">' + cxLoading(t('loading_records', 'Loading records…')) + '</section>';
  }

  function bindPortalRecordsSection(container) {
    var section = container.querySelector('#pdx-portal-records');
    if (!section) {
      return;
    }
    customerApiFetch('GET', '/customer/cybercrime/reports').then(function (data) {
      if (!data || !data._ok) {
        section.innerHTML = '<p class="pdx-auth-error">' + escHtml(t('records_load_error', 'Records could not be loaded.')) + '</p>';
        return;
      }
      var history = Array.isArray(data.history) ? data.history : (data.reports || []).filter(function (report) {
        return report && !cybercrimeReportIsActive(report);
      });
      var active = data.active || null;
      var html = '<h3>' + cxIcon('file', 16) + escHtml(t('cybercrime_records', 'Cybercrime Support records')) + '</h3>';
      html += '<p class="pdx-portal-lead">' + escHtml(t('records_lead', 'Closed reports stay read-only. Start a new report anytime if you need help again.')) + '</p>';
      if (active && cybercrimeReportIsActive(active)) {
        html += '<p class="pdx-portal-note">' + escHtml(t('open_report_note', 'You currently have an open report')) + ' (<code>' + escHtml(active.reference_id || '') + '</code>). ' +
          '<a href="' + escHtml(homePageUrl().replace(/\/$/, '') + '/cybercrime-support/?ref=' + encodeURIComponent(active.reference_id || '')) + '">' + escHtml(t('view_active_report', 'View active report')) + '</a></p>';
      }
      if (history.length) {
        history.forEach(function (report) {
          html += '<button type="button" class="pdx-portal-row pdx-portal-row--link pdx-portal-row--records' +
            (cybercrimeReportIsActive(report) ? '' : ' is-closed') + '" data-portal-open="cybercrime" data-portal-id="' +
            escHtml(String(report.reference_id || '')) + '">' +
            '<strong>' + escHtml(report.reference_id || t('record', 'Record')) + '</strong>' +
            '<span>' + escHtml(report.status_label || report.status || '') + ' · ' + escHtml(portalFormatDate(report.updated_at || report.created_at)) + '</span>' +
            (report.category_label ? '<span class="pdx-portal-row-sub">' + escHtml(report.category_label) + '</span>' : '') +
            '</button>';
        });
      } else {
        html += '<p class="pdx-portal-empty">' + escHtml(t('no_closed_records', 'No closed Cybercrime Support records yet.')) + '</p>';
      }
      html += '<p class="pdx-portal-records-actions"><a class="pdx-portal-btn pdx-portal-btn--secondary" href="' +
        escHtml(homePageUrl().replace(/\/$/, '') + '/cybercrime-support/') + '">' + escHtml(t('start_new_report', 'Start a new report')) + '</a></p>';
      section.innerHTML = html;
      section.querySelectorAll('[data-portal-open]').forEach(function (el) {
        el.addEventListener('click', function () {
          openPortalDetail(container, el.dataset.portalOpen, el.dataset.portalId || '');
        });
      });
    });
  }

  function renderPortalCybercrimeDetail(report) {
    var html = portalBackBtn(t('all_records', 'All records'));
    var isActive = cybercrimeReportIsActive(report);
    html += '<article class="pdx-portal-detail pdx-portal-detail--cybercrime' + (isActive ? '' : ' is-closed') + '">';
    html += '<h3>' + escHtml(report.reference_id || t('cybercrime_report', 'Cybercrime report')) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(report.status_label || report.status || '') +
      (report.category_label ? ' · ' + escHtml(report.category_label) : '') + '</p>';
    html += '<dl class="pdx-portal-records-meta">';
    html += '<div><dt>' + escHtml(t('submitted', 'Submitted')) + '</dt><dd>' + escHtml(portalFormatDate(report.created_at)) + '</dd></div>';
    html += '<div><dt>' + escHtml(t('updated', 'Updated')) + '</dt><dd>' + escHtml(portalFormatDate(report.updated_at || report.created_at)) + '</dd></div>';
    html += '</dl>';
    if (!isActive) {
      html += '<p class="pdx-portal-note pdx-portal-note--closed">' + escHtml(t('record_closed_note', 'This record is closed and read-only. To request new help, start a new report.')) + '</p>';
    }
    if (report.description) {
      html += '<h4>' + escHtml(t('summary', 'Summary')) + '</h4><div class="pdx-portal-body-text">' + escHtml(report.description) + '</div>';
    }
    if (report.platforms) {
      html += '<h4>' + escHtml(t('platforms', 'Platforms')) + '</h4><div class="pdx-portal-body-text">' + escHtml(report.platforms) + '</div>';
    }
    var timeline = cybercrimeVisibleTimeline(report);
    if (timeline.length) {
      html += '<h4>' + escHtml(t('official_updates', 'Official updates')) + '</h4><ul class="pdx-portal-cybercrime-timeline">';
      timeline.forEach(function (entry) {
        var author = entry.author_type === 'customer' ? t('you', 'You') : t('support_team', 'PAXDesign Support Team');
        html += '<li class="pdx-portal-cybercrime-timeline__item">' +
          '<p class="pdx-portal-cybercrime-timeline__meta"><strong>' + escHtml(author) + '</strong> · ' +
          escHtml(portalFormatDate(entry.created_at)) + '</p>' +
          '<div class="pdx-portal-cybercrime-timeline__body">' + escHtml(entry.body || '') + '</div></li>';
      });
      html += '</ul>';
    }
    if ((report.attachments || []).length) {
      html += '<h4>' + escHtml(t('attachments', 'Attachments')) + '</h4><ul class="pdx-portal-list">';
      report.attachments.forEach(function (file) {
        if (!file) {
          return;
        }
        var name = escHtml(file.name || 'file');
        if (file.url) {
          html += '<li><a href="' + escHtml(file.url) + '" target="_blank" rel="noopener">' + name + '</a></li>';
        } else {
          html += '<li>' + name + '</li>';
        }
      });
      html += '</ul>';
    }
    if (!isActive) {
      html += '<p class="pdx-portal-records-actions"><a class="pdx-portal-btn pdx-portal-btn--secondary" href="' +
        escHtml(homePageUrl().replace(/\/$/, '') + '/cybercrime-support/') + '">' + escHtml(t('start_new_report', 'Start a new report')) + '</a></p>';
    }
    return html + '</article>';
  }

  function activePortalHost() {
    var container = activePortalContainer();
    if (!container) return null;
    return container.querySelector('.pdx-account-portal-host') || container;
  }

  function findCatalogServiceCard(slug) {
    var catalog = portalState.servicesCatalog;
    if (!catalog || !Array.isArray(catalog.cards)) return null;
    var key = String(slug || '').toLowerCase();
    for (var i = 0; i < catalog.cards.length; i++) {
      var card = catalog.cards[i];
      if (!card) continue;
      if (String(card.order_slug || '').toLowerCase() === key || String(card.id || '').toLowerCase() === key) {
        return card;
      }
    }
    return null;
  }

  function catalogCardAsService(card) {
    if (!card) return null;
    return {
      _ok: true,
      slug: card.order_slug || card.id,
      name: card.title || card.name || '',
      description: card.description || '',
      features: card.features || [],
      category: card.category || '',
      details: card.details || [],
    };
  }

  function resolveServiceBySlug(slug) {
    return customerApiFetch('GET', '/customer/services/' + encodeURIComponent(slug)).then(function (service) {
      if (service && service._ok && service.slug) return service;
      var card = findCatalogServiceCard(slug);
      if (card) return catalogCardAsService(card);
      return service;
    }).catch(function () {
      var card = findCatalogServiceCard(slug);
      return card ? catalogCardAsService(card) : null;
    });
  }

  function bindPortalServiceRows(container, scopeEl) {
    if (!scopeEl) return;
    scopeEl.querySelectorAll('[data-portal-open="service"]').forEach(function (el) {
      el.addEventListener('click', function () {
        openPortalDetail(container, 'service', el.dataset.portalSlug || el.dataset.portalId || '');
      });
    });
  }

  function renderPortalServicesCatalogHtml(catalog, services) {
    services = services || [];
    var html = '';
    if (catalog && catalog.title) {
      html += '<p class="pdx-portal-lead">' + escHtml(catalog.subtitle || catalog.statement || '') + '</p>';
    }
    if (services.length) {
      html += '<div class="pdx-portal-services-group"><h4>' + escHtml(t('services_catalog', 'Services catalog')) + '</h4>';
      services.forEach(function (s) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="service" data-portal-slug="' + escHtml(s.slug) + '">' +
          '<strong>' + escHtml(s.name) + '</strong><span>' + escHtml(s.category || '') + '</span></button>';
      });
      html += '</div>';
    }
    if (catalog && Array.isArray(catalog.cards) && catalog.cards.length) {
      html += '<div class="pdx-portal-services-group"><h4>' + escHtml(catalog.title || t('services_catalog', 'Services catalog')) + '</h4>';
      catalog.cards.forEach(function (card) {
        var slug = card.order_slug || card.id || '';
        var badge = '';
        if (card.badge && catalog.badges && catalog.badges[card.badge]) {
          badge = catalog.badges[card.badge];
        } else if (card.badge) {
          badge = card.badge;
        }
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link' + (card.highlighted ? ' pdx-portal-row--featured' : '') + '" data-portal-open="service" data-portal-slug="' + escHtml(slug) + '">' +
          '<strong>' + escHtml(card.title || '') + '</strong>' +
          '<span>' + escHtml(badge || (card.description || '').slice(0, 72)) + '</span></button>';
      });
      html += '</div>';
    }
    if (!html) {
      html = '<p class="pdx-portal-empty">' + escHtml(t('no_services', 'No services available yet.')) + '</p>';
    }
    return html;
  }

  function renderPortalServicesSection() {
    return '<section class="pdx-portal-section pdx-portal-section--services" id="pdx-portal-services">' +
      '<h3>' + cxIcon('package', 16) + escHtml(t('services_catalog', 'Services catalog')) + '</h3>' +
      cxLoading(t('loading_services', 'Loading services…')) +
    '</section>';
  }

  function bindPortalServicesSection(container) {
    var section = container.querySelector('#pdx-portal-services');
    if (!section) return;
    var lang = customerPortalLang();
    Promise.all([
      customerApiFetch('GET', '/customer/services'),
      customerApiFetch('GET', '/content/services-catalog?lang=' + encodeURIComponent(lang)),
    ]).then(function (results) {
      var servicesData = results[0] || {};
      var catalogData = results[1] || {};
      portalState.servicesCatalog = (catalogData && catalogData._ok !== false && Array.isArray(catalogData.cards)) ? catalogData : null;
      var services = (servicesData._ok !== false && Array.isArray(servicesData.services)) ? servicesData.services : [];
      var catalog = portalState.servicesCatalog;
      if (!services.length && !catalog) {
        section.innerHTML = '<h3>' + cxIcon('package', 16) + escHtml(t('services_catalog', 'Services catalog')) + '</h3>' +
          '<p class="pdx-auth-error">' + escHtml(t('services_load_error', 'Services could not be loaded.')) + '</p>';
        return;
      }
      section.innerHTML = '<h3>' + cxIcon('package', 16) + escHtml(t('services_catalog', 'Services catalog')) + '</h3>' +
        renderPortalServicesCatalogHtml(catalog, services);
      bindPortalServiceRows(container, section);
    }).catch(function () {
      section.innerHTML = '<h3>' + cxIcon('package', 16) + escHtml(t('services_catalog', 'Services catalog')) + '</h3>' +
        '<p class="pdx-auth-error">' + escHtml(t('services_load_error', 'Services could not be loaded.')) + '</p>';
    });
  }

  function renderPortalServiceDetail(service) {
    var html = portalBackBtn(t('all_services', 'All services'));
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(service.name) + '</h3>';
    if (service.description) {
      html += '<div class="pdx-portal-body-text">' + escHtml(service.description) + '</div>';
    }
    if (service.features && service.features.length) {
      html += '<ul class="pdx-portal-list">';
      service.features.forEach(function (feature) {
        html += '<li>' + escHtml(feature) + '</li>';
      });
      html += '</ul>';
    }
    html += '<form class="pdx-portal-request-form" id="pdx-portal-request-form">' +
      '<textarea rows="3" placeholder="' + escHtml(t('describe_request', 'Describe your request…')) + '" aria-label="' + escHtml(t('request_message', 'Request message')) + '" required></textarea>' +
      actionBtn(t('submit_request', 'Submit request'), { type: 'submit', small: true, inline: true, icon: 'send' }) +
      '</form></article>';
    setTimeout(function () {
      var form = document.getElementById('pdx-portal-request-form');
      if (!form) return;
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var message = (form.querySelector('textarea').value || '').trim();
        if (!message) return;
        customerApiFetch('POST', '/customer/orders', {
          service_slug: service.slug,
          message: message,
        }).then(function (res) {
          notify(res.message || (res._ok ? t('request_submitted', 'Request submitted.') : t('request_failed', 'Request failed.')), res._ok ? 'info' : 'error');
          if (res._ok) {
            portalState.tab = 'orders';
            portalState.detail = null;
            accountState.section = 'orders';
            accountState.detail = null;
            customerApiFetch('GET', '/customer/dashboard').then(function (dash) {
              dash = normalizeDashboardResponse(dash);
              if (!dash) return;
              enrichAccountDashboard(dash).then(function (enriched) {
                portalState.dashboard = enriched;
                accountState.dashboard = enriched;
                if (isAuthPage()) {
                  renderAccountApp();
                } else {
                  var host = portalOverlay && portalOverlay.querySelector('.pdx-customer-portal-body');
                  if (host) renderCustomerPortalDashboard(host, enriched);
                }
              });
            });
          }
        });
      });
    }, 0);
    return html;
  }

  function renderPortalNewsSection(data) {
    var news = data.news || [];
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('news', 16) + escHtml(t('news_announcements', 'News & announcements')) + '</h3>';
    if (news.length) {
      news.forEach(function (n) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="news" data-portal-slug="' + escHtml(n.slug || '') + '">' +
          '<strong>' + escHtml(n.title) + '</strong><span>' + escHtml(formatDate(n.published_at)) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">' + escHtml(t('no_announcements', 'No announcements right now.')) + '</p>';
    }
    return html + '</section>';
  }

  function renderPortalNewsDetail(item) {
    var html = portalBackBtn(t('all_news', 'All news'));
    html += '<article class="pdx-portal-detail pdx-portal-detail--news">';
    if (item.image_url) {
      html += '<figure class="pdx-portal-news-hero">' +
        '<img class="pdx-portal-news-hero__img" src="' + escHtml(item.image_url) + '" alt="" loading="lazy" decoding="async" />' +
        '</figure>';
    }
    html += '<h3>' + escHtml(item.title) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(formatDate(item.published_at)) + '</p>';
    if (item.excerpt) {
      html += '<p class="pdx-portal-lead">' + escHtml(item.excerpt) + '</p>';
    }
    html += '<div class="pdx-portal-body-text pdx-portal-body-text--html">' + renderTrustedNewsHtml(item.body || '') + '</div></article>';
    return html;
  }

  function renderPortalNotificationsSection() {
    return '<section class="pdx-portal-section" id="pdx-portal-notifications">' + cxLoading(t('loading_notifications', 'Loading notifications…')) + '</section>';
  }

  function bindPortalNotificationsSection(container) {
    var section = container.querySelector('#pdx-portal-notifications');
    if (!section) return;
    customerApiFetch('GET', '/customer/notifications?limit=50').then(function (data) {
      if (!data || !data._ok) {
        section.innerHTML = '<p class="pdx-auth-error">' + escHtml(t('notifications_load_error', 'Notifications could not be loaded.')) + '</p>';
        return;
      }
      var html = '<h3>' + cxIcon('bell', 16) + escHtml(t('notifications', 'Notifications'));
      if (data.unread_count) {
        html += ' <span class="pdx-portal-badge">' + escHtml(String(data.unread_count)) + '</span>';
      }
      html += '</h3>';
      if ((data.items || []).length) {
        html += '<div class="pdx-portal-notify-list">';
        data.items.forEach(function (n) {
          var unread = n.read_at ? '' : ' pdx-portal-notify--unread';
          html += '<div class="pdx-portal-notify' + unread + '" data-notify-id="' + escHtml(String(n.id)) + '">' +
            '<strong>' + escHtml(n.title) + '</strong><p>' + escHtml(n.body) + '</p>' +
            '<span class="pdx-portal-meta">' + escHtml(formatDate(n.created_at)) + '</span></div>';
        });
        html += '</div>';
        if (data.unread_count) {
          html += portalBtn(t('mark_all_read', 'Mark all read'), { type: 'button', variant: 'secondary', small: true, id: 'pdx-portal-mark-read' });
        }
      } else {
        html += '<p class="pdx-portal-empty">' + escHtml(t('no_notifications', 'No notifications yet.')) + '</p>';
      }
      section.innerHTML = html;
      section.querySelectorAll('.pdx-portal-notify--unread').forEach(function (el) {
        el.addEventListener('click', function () {
          var id = el.dataset.notifyId;
          customerApiFetch('POST', '/customer/notifications', { ids: [parseInt(id, 10)] }).then(function () {
            el.classList.remove('pdx-portal-notify--unread');
          });
        });
      });
      var markAll = section.querySelector('#pdx-portal-mark-read');
      if (markAll) {
        markAll.addEventListener('click', function () {
          var ids = (data.items || []).filter(function (n) { return !n.read_at; }).map(function (n) { return n.id; });
          customerApiFetch('POST', '/customer/notifications', { ids: ids }).then(function () {
            bindPortalNotificationsSection(container);
          });
        });
      }
    });
  }

  function formatPortalMessage(m) {
    if (m.image_url) {
      return '<img class="pdx-portal-msg-image" src="' + escHtml(m.image_url) + '" alt="" />' +
        (m.content ? '<div>' + escHtml(m.content) + '</div>' : '');
    }
    if (m.attachment_type === 'voice' && m.audio_url) {
      return '<audio controls preload="none" src="' + escHtml(m.audio_url) + '"></audio>';
    }
    if (m.attachment_type === 'file' && m.file_url) {
      return '<a href="' + escHtml(m.file_url) + '" target="_blank" rel="noopener">' + escHtml(m.file_name || 'Download file') + '</a>';
    }
    if (m.attachment_type === 'location') {
      var lat = m.lat || m.latitude;
      var lng = m.lng || m.longitude;
      if (lat && lng) {
        return '<a href="https://maps.google.com/?q=' + encodeURIComponent(lat + ',' + lng) + '" target="_blank" rel="noopener">' +
          escHtml(m.label || 'Shared location') + '</a>';
      }
    }
    return escHtml(m.content || '');
  }

  function initCustomerPortalChat(container, sessionId) {
    var logEl = container.querySelector('#pdx-portal-chat-log');
    var form = container.querySelector('#pdx-portal-chat-form');
    if (!logEl || !form) return;

    var state = { sessionId: sessionId, handler: 'ai', messages: [], sending: false, recording: null, pollTimer: null, typingTimer: null };

    function isHumanHandler() {
      return state.handler === 'admin' || state.handler === 'live_request';
    }

    function isLifecycleNoise(content) {
      var lower = String(content || '').toLowerCase();
      var blocked = ['closed', 'geschlossen', 'beendet', 'ended', 'conversation ended', 'session closed', 'neues gespräch', 'new chat', 'new conversation', 'start a new', 'inactivity', 'inaktivität', 'مغلق', 'انتهت'];
      return blocked.some(function (needle) { return lower.indexOf(needle) !== -1; });
    }

    function updateChatChrome() {
      /* Persistent conversation — no end-chat control for signed-in customers. */
    }

    function renderMessages() {
      var visible = state.messages.filter(function (m) {
        return !(m.role === 'system' && isLifecycleNoise(m.content));
      });
      if (!visible.length) {
        logEl.innerHTML = '<p class="pdx-portal-empty">No messages yet. Start a conversation with PAXDesign.</p>';
        return;
      }
      var html = visible.map(function (m) {
        var cls = m.role === 'user' ? 'pdx-portal-msg pdx-portal-msg--user' : 'pdx-portal-msg pdx-portal-msg--assistant';
        return '<div class="' + cls + '">' + formatPortalMessage(m) + '</div>';
      }).join('');
      if (state.adminTyping && isHumanHandler()) {
        html += '<div class="pdx-portal-msg pdx-portal-msg--assistant pdx-portal-msg--typing">' +
          '<span class="pdx-portal-typing"><span></span><span></span><span></span></span>' +
          '<span>Support is typing…</span></div>';
      }
      logEl.innerHTML = html;
      logEl.scrollTop = logEl.scrollHeight;
    }

    function loadMessages(full) {
      var path = '/customer/chat/messages?full=' + (full ? '1' : '0');
      if (state.sessionId) path += '&session_id=' + encodeURIComponent(state.sessionId);
      return customerApiFetch('GET', path).then(function (data) {
        if (!data || !data._ok) {
          logEl.innerHTML = '<p class="pdx-auth-error">Unable to load conversation.</p>';
          return;
        }
        state.sessionId = data.session_id || state.sessionId;
        state.handler = data.handler || 'ai';
        if (state.handler === 'closed') {
          return customerApiFetch('POST', '/customer/chat/session', {
            session_id: state.sessionId,
            new_conversation: false,
          }).then(function (renewed) {
            if (renewed && renewed.session_id) state.sessionId = renewed.session_id;
            if (renewed && renewed.handler) state.handler = renewed.handler;
            return loadMessages(full);
          });
        }
        state.adminTyping = !!data.admin_typing;
        state.messages = (data.messages || []).map(function (m) {
          return {
            role: m.role,
            content: m.content || '',
            image_url: m.image_url,
            attachment_type: m.attachment_type,
            audio_url: m.audio_url,
            file_url: m.file_url,
            file_name: m.file_name,
            lat: m.lat,
            lng: m.lng,
            label: m.label,
          };
        });
        updateChatChrome();
        renderMessages();
      }).catch(function () {
        logEl.innerHTML = '<p class="pdx-auth-error">Unable to load conversation.</p>';
      });
    }

    function startPolling() {
      if (state.pollTimer) clearInterval(state.pollTimer);
      state.pollTimer = window.setInterval(function () {
        loadMessages(false);
      }, 2000);
    }

    function stopPolling() {
      if (state.pollTimer) {
        clearInterval(state.pollTimer);
        state.pollTimer = null;
      }
    }

    function sendTypingPing(stop) {
      if (!isHumanHandler()) return;
      customerApiFetch('POST', '/customer/chat/typing', {
        session_id: state.sessionId,
        stop: stop ? 1 : 0,
      });
    }
    function sendHumanMessage(text) {
      return customerApiFetch('POST', '/customer/chat/messages', {
        session_id: state.sessionId,
        message: text,
      }).then(function (data) {
        if (!data || !data._ok) throw data || {};
        if (data.message) appendLocal('user', text);
        return loadMessages(true);
      });
    }

    function sendAiStream(text) {
      appendLocal('user', text);
      var assistantIdx = state.messages.length;
      state.messages.push({ role: 'assistant', content: '' });
      renderMessages();
      return customerApiStream('/customer/chat/stream', {
        session_id: state.sessionId,
        message: text,
      }, function (evt) {
        if (evt.type === 'text' && evt.text) {
          state.messages[assistantIdx].content += evt.text;
          renderMessages();
        }
        if (evt.type === 'done' && evt.message && evt.message.content) {
          state.messages[assistantIdx].content = evt.message.content;
          renderMessages();
        }
        if (evt.type === 'error' && evt.message) {
          notify(evt.message, 'error');
        }
      }).then(loadMessages.bind(null, true));
    }

    function sendText(text) {
      state.sending = true;
      var sendPromise = (state.handler === 'admin' || state.handler === 'live_request')
        ? sendHumanMessage(text)
        : sendAiStream(text);
      return sendPromise.catch(function (err) {
        notify((err && err.message) || 'Message could not be sent.', 'error');
      }).finally(function () {
        state.sending = false;
      });
    }

    function uploadMedia(path, field, file, extra) {
      if (state.sending || !file) return;
      state.sending = true;
      var fd = new FormData();
      fd.append(field, file);
      fd.append('session_id', state.sessionId);
      if (extra) {
        Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
      }
      customerApiFormData(path, fd).then(function (data) {
        if (!data || !data._ok) {
          notify((data && data.message) || 'Upload failed.', 'error');
          return;
        }
        loadMessages(true);
      }).catch(function () {
        notify('Upload failed.', 'error');
      }).finally(function () {
        state.sending = false;
      });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (state.sending) return;
      var input = form.querySelector('textarea');
      var text = (input && input.value || '').trim();
      if (!text) return;
      sendText(text).finally(function () {
        if (input) input.value = '';
        sendTypingPing(true);
      });
    });

    var chatInput = form.querySelector('textarea');
    if (chatInput) {
      chatInput.addEventListener('input', function () {
        if (state.typingTimer) clearTimeout(state.typingTimer);
        var typing = !!(chatInput.value || '').trim();
        sendTypingPing(!typing);
        if (typing) {
          state.typingTimer = window.setTimeout(function () {
            sendTypingPing(true);
          }, 2500);
        }
      });
    }

    var imageInput = container.querySelector('#pdx-portal-image-input');
    if (imageInput) {
      imageInput.addEventListener('change', function () {
        if (imageInput.files && imageInput.files[0]) {
          uploadMedia('/customer/chat/messages/image', 'image', imageInput.files[0]);
          imageInput.value = '';
        }
      });
    }

    var fileInput = container.querySelector('#pdx-portal-file-input');
    if (fileInput) {
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
          uploadMedia('/customer/chat/messages/file', 'file', fileInput.files[0]);
          fileInput.value = '';
        }
      });
    }

    var voiceBtn = container.querySelector('#pdx-portal-voice-btn');
    if (voiceBtn && navigator.mediaDevices && typeof MediaRecorder !== 'undefined') {
      voiceBtn.addEventListener('click', function () {
        if (state.recording) {
          state.recording.stop();
          return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
          var recorder = new MediaRecorder(stream);
          var chunks = [];
          state.recording = recorder;
          voiceBtn.classList.add('is-recording');
          recorder.ondataavailable = function (ev) { if (ev.data.size) chunks.push(ev.data); };
          recorder.onstop = function () {
            voiceBtn.classList.remove('is-recording');
            state.recording = null;
            stream.getTracks().forEach(function (t) { t.stop(); });
            var blob = new Blob(chunks, { type: 'audio/webm' });
            var file = new File([blob], 'voice-message.webm', { type: 'audio/webm' });
            uploadMedia('/customer/chat/messages/voice', 'audio', file, { duration: 0 });
          };
          recorder.start();
          setTimeout(function () { if (state.recording) state.recording.stop(); }, 60000);
        }).catch(function () {
          notify('Microphone access is required for voice messages.', 'warn');
        });
      });
    }

    var locationBtn = container.querySelector('#pdx-portal-location-btn');
    if (locationBtn && navigator.geolocation) {
      locationBtn.addEventListener('click', function () {
        if (state.sending) return;
        locationBtn.disabled = true;
        navigator.geolocation.getCurrentPosition(function (pos) {
          locationBtn.disabled = false;
          state.sending = true;
          customerApiFetch('POST', '/customer/chat/messages/location', {
            session_id: state.sessionId,
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            label: 'My location',
          }).then(function (data) {
            if (!data || !data._ok) {
              notify((data && data.message) || 'Location could not be shared.', 'error');
              return;
            }
            loadMessages(true);
          }).finally(function () { state.sending = false; });
        }, function () {
          locationBtn.disabled = false;
          notify('Location permission denied.', 'warn');
        });
      });
    }

    loadMessages(true).then(startPolling);
    container._pdxChatCleanup = function () {
      stopPolling();
      sendTypingPing(true);
    };
  }

  window.PDXAuth = {
    init: function () {
      ensureAccountAvatarFallbackHandler();
      createAuthBar();
      if (isAuthPage()) {
        initAuthPage();
      } else {
        createOverlay();
      }
      handleUrlParams();
      syncSessionFromServer('init', { cacheBust: true });
      bindSessionAutoSync();
      window.addEventListener('resize', function () {
        updateAuthBar();
        ensureHeaderUtilityCluster();
        stabilizeDesktopHeaderAuthLayout();
        stabilizeMobileHeaderAuthLayout();
      }, { passive: true });
      window.addEventListener('load', scheduleDesktopHeaderAuthLayoutReset);
    },
    isLoggedIn: function () { return !!user.logged_in; },
    isVerified: function () { return !!user.verified || !!user.is_admin; },
    canAccessModule: canAccessModule,
    moduleRequiresAuth: moduleRequiresAuth,
    openLogin: function (moduleId) {
      if (moduleId) returnModule = moduleId;
      navigateToAuthPage(typeof moduleId === 'string' && moduleId === 'register' ? 'register' : 'login');
    },
    openAccountPage: function (view) { navigateToAuthPage(view || 'login'); },
    accountPageUrl: accountPageUrl,
    mountInlineAuth: mountInlineAuth,
    unmountInlineAuth: unmountInlineAuth,
    renderAuthGate: renderAuthGate,
    openCustomerPortal: openCustomerPortal,
    openAccountSection: setAccountSection,
    closeCustomerPortal: closeCustomerPortal,
    customerApiFetch: customerApiFetch,
    customerApiStream: customerApiStream,
    refreshUser: refreshUser,
    syncSession: syncSessionFromServer,
    refreshSessionNonce: refreshSessionNonce,
    applySession: applySession,
    getNonce: function () { return C.nonce || ''; },
    getUser: function () { return user; },
    isRestNonceError: isRestNonceError,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.PDXAuth.init);
  } else {
    window.PDXAuth.init();
  }
})();
