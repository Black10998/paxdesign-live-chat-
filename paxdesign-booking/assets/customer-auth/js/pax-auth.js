/**
 * PaxDesign Auth — login/register UI, session handling, account dashboard.
 */
(function () {
  'use strict';

  var C = typeof PAX_AUTH_CONFIG !== 'undefined' ? PAX_AUTH_CONFIG : (typeof PDX_CONFIG !== 'undefined' ? PDX_CONFIG : null);
  if (!C) return;
  var user = {
    logged_in: !!C.isLoggedIn,
    verified: !!C.emailVerified,
    display_name: C.userName || '',
    email: C.userEmail || '',
    id: C.userId || 0,
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
    loaded: false,
  };
  var sessionSyncInFlight = false;
  var sessionSyncTimer = null;
  var SESSION_SYNC_INTERVAL_MS = 45000;
  var authBroadcast = null;

  var SVG_GRADIENT = '<defs><linearGradient id="pdx-gradient-stroke" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse"><stop offset="0%" stop-color="black"></stop><stop offset="100%" stop-color="white"></stop></linearGradient></defs>';
  var SVG_EMAIL = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' + SVG_GRADIENT + '<g stroke="url(#pdx-gradient-stroke)" fill="none" stroke-width="1"><path d="M21.6365 5H3L12.2275 12.3636L21.6365 5Z"></path><path d="M16.5 11.5L22.5 6.5V17L16.5 11.5Z"></path><path d="M8 11.5L2 6.5V17L8 11.5Z"></path><path d="M9.5 12.5L2.81805 18.5002H21.6362L15 12.5L12 15L9.5 12.5Z"></path></g></svg>';
  var SVG_LOCK = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' + SVG_GRADIENT + '<g stroke="url(#pdx-gradient-stroke)" fill="none" stroke-width="1"><path d="M3.5 15.5503L9.20029 9.85L12.3503 13L11.6 13.7503H10.25L9.8 15.1003L8 16.0003L7.55 18.2503L5.5 19.6003H3.5V15.5503Z"></path><path d="M16 3.5H11L8.5 6L16 13.5L21 8.5L16 3.5Z"></path><path d="M16 10.5L18 8.5L15 5.5H13L12 6.5L16 10.5Z"></path></g></svg>';
  var SVG_USER = '<svg aria-hidden="true" width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="m15.626 11.769a6 6 0 1 0 -7.252 0 9.008 9.008 0 0 0 -5.374 8.231 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 9.008 9.008 0 0 0 -5.374-8.231zm-7.626-4.769a4 4 0 1 1 4 4 4 4 0 0 1 -4-4zm10 14h-12a1 1 0 0 1 -1-1 7 7 0 0 1 14 0 1 1 0 0 1 -1 1z"></path></svg>';

  var publicModules = C.publicModules || ['trust', 'create', 'workspace'];
  var authMenuOpen = false;
  var profileOverlay = null;

  function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function stripHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.innerHTML = String(s);
    return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
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
    if (user.is_admin) return 'Administrator';
    return verified ? 'Verified' : 'Pending verification';
  }

  function verifiedBadgeHtml(verified, opts) {
    if (window.PDXVerifiedBadge) return window.PDXVerifiedBadge.render(verified, opts);
    return '';
  }

  function nameWithBadge(name, verified, opts) {
    if (window.PDXVerifiedBadge) return window.PDXVerifiedBadge.nameWithBadge(name, verified, opts);
    return escHtml(name || 'Account');
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

  function cxLoading(label) {
    return '<div class="pdx-cx-loading"><div class="pdx-cx-loading__spinner"></div><span>' + escHtml(label || 'Loading…') + '</span></div>';
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
      C.isLoggedIn = !!u.logged_in;
      C.emailVerified = !!u.verified;
      C.userId = u.id || 0;
      C.userName = u.display_name || '';
      C.userEmail = u.email || '';
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

  function findHeaderMount() {
    var selectors = [
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

  function mountAuthBar() {
    removeLegacyTopbar();
    authBar.classList.remove('pdx-auth-bar--topbar');
    var mount = findHeaderMount();
    if (mount) {
      mount.classList.add('pdx-header-has-auth');
      authBar.classList.add('pdx-auth-bar--header');
      mount.appendChild(authBar);
      return;
    }
    authBar.classList.add('pdx-auth-bar--header');
    document.body.appendChild(authBar);
  }

  function createAuthBar() {
    authBar = document.createElement('div');
    authBar.id = 'pdx-auth-bar';
    authBar.className = 'pdx-cx-shell';
    authBar.innerHTML =
      '<div class="pdx-auth-bar-inner">' +
        '<button type="button" class="pdx-auth-signup-btn pdx-cx-btn pdx-auth-header-btn">Sign Up</button>' +
        '<button type="button" class="pdx-auth-account-btn pdx-cx-btn pdx-cx-btn--ghost pdx-auth-header-btn" aria-haspopup="true" aria-expanded="false" hidden>' +
          '<span class="pdx-auth-account-label">Account</span>' +
        '</button>' +
        '<button type="button" class="pdx-auth-portal-btn pdx-cx-btn pdx-auth-header-btn" hidden>Customer Portal</button>' +
        '<div class="pdx-auth-menu" hidden>' +
          '<div class="pdx-auth-menu-head"></div>' +
          '<div class="pdx-auth-menu-actions">' +
            '<button type="button" class="pdx-auth-menu-item" data-action="portal">' + cxIcon('dashboard', 16) + 'Customer Portal</button>' +
            '<button type="button" class="pdx-auth-menu-item" data-action="profile">' + cxIcon('user', 16) + 'My Profile</button>' +
            '<button type="button" class="pdx-auth-menu-item" data-action="account">' + cxIcon('settings', 16) + 'My Account</button>' +
            '<button type="button" class="pdx-auth-menu-item pdx-auth-menu-item--logout" data-action="logout">' + cxIcon('logout', 16) + 'Logout</button>' +
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
        if (action === 'portal') openCustomerPortal();
        else if (action === 'profile') openProfileOverlay();
        else if (action === 'account') openAccountPanel();
        else if (action === 'logout') doLogout();
      });
    });

    var signupBtn = authBar.querySelector('.pdx-auth-signup-btn');
    var portalBtn = authBar.querySelector('.pdx-auth-portal-btn');
    if (signupBtn) {
      signupBtn.addEventListener('click', function () { navigateToAuthPage('register'); });
    }
    if (portalBtn) {
      portalBtn.addEventListener('click', function () { openCustomerPortal(); });
    }

    document.addEventListener('click', function (e) {
      if (!authBar || !authMenuOpen) return;
      if (!authBar.contains(e.target)) closeAuthMenu();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAuthMenu();
    });

    mountAuthBar();
    updateAuthBar();
  }

  function accountStatusLabel() {
    if (!user.logged_in) return 'Guest';
    if (user.is_admin) return 'Administrator';
    return user.verified ? 'Verified' : 'Pending verification';
  }

  function updateAuthBar() {
    if (!authMenu) return;
    var accountBtn = authBar ? authBar.querySelector('.pdx-auth-account-btn') : null;
    var labelEl = accountBtn ? accountBtn.querySelector('.pdx-auth-account-label') : null;
    var head = authMenu.querySelector('.pdx-auth-menu-head');
    var signupBtn = authBar ? authBar.querySelector('.pdx-auth-signup-btn') : null;
    var portalBtn = authBar ? authBar.querySelector('.pdx-auth-portal-btn') : null;
    var label = user.logged_in ? (user.display_name || 'Account') : 'Account';

    if (signupBtn) signupBtn.hidden = !!user.logged_in;
    if (accountBtn) accountBtn.hidden = !user.logged_in;
    /* Portal is in the account dropdown on desktop; standalone btn is mobile-only. */
    if (portalBtn) portalBtn.hidden = !user.logged_in || !window.matchMedia('(max-width: 768px)').matches;

    if (labelEl) {
      if (user.logged_in) {
        labelEl.innerHTML = nameWithBadge(label, user.verified, { size: 14, inline: true, context: 'account' });
      } else {
        labelEl.textContent = label;
      }
    }
    if (accountBtn) {
      accountBtn.classList.toggle('pdx-auth-account-btn--verified', user.logged_in && user.verified);
      accountBtn.setAttribute('aria-label', user.logged_in ? 'Account menu' : 'Account');
    }

    if (user.logged_in && head) {
      head.innerHTML =
        '<div class="pdx-auth-menu-name">' + nameWithBadge(user.display_name || 'Account', user.verified, { size: 15, context: 'account' }) + '</div>' +
        '<div class="pdx-auth-menu-email">' + escHtml(user.email || '') + '</div>' +
        '<div class="pdx-auth-menu-status">' + escHtml(accountStatusLabel()) + '</div>';
      authMenu.removeAttribute('hidden');
    } else {
      if (head) head.innerHTML = '';
      closeAuthMenu();
      authMenu.setAttribute('hidden', 'hidden');
    }
  }

  function openAuthMenu() {
    if (!user.logged_in || !authMenu || !authBtn) return;
    authMenu.hidden = false;
    authMenu.classList.add('is-open');
    authBtn.setAttribute('aria-expanded', 'true');
    authMenuOpen = true;
  }

  function closeAuthMenu() {
    if (!authMenu || !authBtn) return;
    authMenu.classList.remove('is-open');
    authBtn.setAttribute('aria-expanded', 'false');
    authMenuOpen = false;
  }

  function onAuthBarClick() {
    if (!user.logged_in) {
      navigateToAuthPage('register');
      return;
    }
    if (authMenuOpen) closeAuthMenu();
    else openAuthMenu();
  }

  function openAccountPanel() {
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
      profileOverlay.className = 'pdx-cx-shell';
      profileOverlay.setAttribute('role', 'dialog');
      profileOverlay.setAttribute('aria-modal', 'true');
      profileOverlay.setAttribute('aria-label', 'My Profile');
      profileOverlay.innerHTML =
        '<div class="pdx-profile-card">' +
          '<button type="button" class="pdx-auth-close" aria-label="Close">&times;</button>' +
          '<div class="pdx-profile-card-title">' + cxIcon('user', 18) + 'My Profile</div>' +
          '<div class="pdx-profile-card-body"></div>' +
          '<div class="pdx-profile-card-actions">' +
            '<button type="button" class="pdx-cx-btn pdx-profile-open-account">' + cxIcon('settings', 16) + 'My Account</button>' +
            '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-profile-logout">' + cxIcon('logout', 16) + 'Logout</button>' +
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
      '<div class="pdx-profile-row"><span class="pdx-profile-label">Full Name</span><span class="pdx-profile-value">' + nameWithBadge(user.display_name || '—', user.verified, { size: 15, context: 'account' }) + '</span></div>' +
      '<div class="pdx-profile-row"><span class="pdx-profile-label">Email</span><span class="pdx-profile-value">' + escHtml(user.email || '—') + '</span></div>' +
      '<div class="pdx-profile-row"><span class="pdx-profile-label">Account Status</span><span class="pdx-profile-value pdx-profile-value--status">' + escHtml(accountStatusText(user.verified)) + '</span></div>' +
      '<div class="pdx-profile-row"><span class="pdx-profile-label">Login Status</span><span class="pdx-profile-value">' + (user.logged_in ? 'Signed in' : 'Signed out') + '</span></div>';
    profileOverlay.classList.add('is-open');
    document.body.classList.add('pdx-no-scroll');
  }

  function closeProfileOverlay() {
    if (!profileOverlay) return;
    profileOverlay.classList.remove('is-open');
    document.body.classList.remove('pdx-no-scroll');
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
        broadcastSessionChange();
        try {
          window.dispatchEvent(new CustomEvent('pdx-session-updated', { detail: { reason: 'logout' } }));
        } catch (e) {}
      }
      notify('Logged out.', 'info');
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
    overlay.setAttribute('aria-label', 'Authentication');
    overlay.innerHTML =
      '<div class="pdx-auth-wrapper">' +
        '<button type="button" class="pdx-auth-close" aria-label="Close">&times;</button>' +
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
    var titles = { login: 'Sign In', register: 'Create Account', forgot: 'Forgot Password', reset: 'Reset Password' };
    var subtitles = {
      login: 'Welcome back. Sign in to your PAXDesign account.',
      register: 'Create your account to access modules and billing.',
      forgot: 'Enter your email and we will send a secure reset link.',
      reset: 'Choose a strong new password for your account.',
    };
    var headIcons = { login: 'login', register: 'register', forgot: 'mail', reset: 'lock' };
    var html = '<form class="pdx-auth-form pdx-auth-form--' + currentView + (compact ? ' pdx-auth-form--compact' : '') + '" novalidate>';
    if (!compact) {
      html += '<div class="pdx-cx-auth-head">';
      html += '<div class="pdx-cx-icon-wrap">' + cxIcon(headIcons[currentView] || 'login', 22) + '</div>';
      html += '<span class="pdx-auth-title">' + escHtml(titles[currentView] || 'Sign In') + '</span>';
      html += '<p class="pdx-cx-auth-subtitle">' + escHtml(subtitles[currentView] || '') + '</p>';
      html += '</div>';
    }
    html += '<div class="pdx-auth-msg-slot"></div>';
    html += '<div class="pdx-auth-fields">';

    if (currentView === 'login') {
      html += fieldInput('email', 'email', 'Email', 'mail', 'email', true);
      html += fieldInput('password', 'password', 'Password', 'lock', 'current-password', true);
    } else if (currentView === 'register') {
      html += fieldInput('name', 'text', 'Full name', 'user', 'name', true);
      html += fieldInput('email', 'email', 'Email', 'mail', 'email', true);
      html += fieldInput('password', 'password', 'Password (min 8 characters)', 'lock', 'new-password', true);
    } else if (currentView === 'forgot') {
      html += fieldInput('email', 'email', 'Email', 'mail', 'email', true);
    } else if (currentView === 'reset') {
      html += fieldInput('password', 'password', 'New password', 'lock', 'new-password', true);
      html += fieldInput('password2', 'password', 'Confirm password', 'lock', 'new-password', true);
    }

    html += '</div>';

    if (currentView === 'login') {
      html += submitBtn('Sign In', 'login');
      html += links([
        { view: 'forgot', label: 'Forgot password?' },
        { view: 'register', label: 'Create account' },
      ]);
    } else if (currentView === 'register') {
      html += submitBtn('Create Account', 'register');
      html += links([{ view: 'login', label: 'Already have an account? Sign in' }]);
    } else if (currentView === 'forgot') {
      html += submitBtn('Send Reset Link', 'mail');
      html += links([{ view: 'login', label: 'Back to sign in' }]);
    } else if (currentView === 'reset') {
      html += submitBtn('Reset Password', 'lock');
      html += links([{ view: 'login', label: 'Back to sign in' }]);
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
    return !!(inlineAuthMount && inlineAuthMount.context === 'page' && inlineAuthMount.compact);
  }

  function appleSubmitBtn(label) {
    return '<div class="pdx-auth-submit-wrap">' +
      '<button type="submit" class="pdx-auth-submit">' + escHtml(label) + '</button></div>';
  }

  function submitBtn(label, iconName) {
    if (isAuthPageFormMount()) {
      return appleSubmitBtn(label);
    }
    return '<div class="pdx-auth-submit-wrap">' + pearlBtn(label, { icon: iconName || 'check' }) + '</div>';
  }

  function setFormLoading(loading) {
    var root = activeAuthRoot();
    var btn = root && (root.querySelector('.pdx-auth-submit') || root.querySelector('.pdx-btn-pearl'));
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

  function initAuthPage() {
    authPageEl = document.getElementById('pdx-auth-page');
    if (!authPageEl) return;
    authPageFormEl = document.getElementById('pdx-auth-page-form');
    accountAppEl = document.getElementById('pdx-account-app');
    accountSidebarEl = document.getElementById('pdx-account-sidebar');
    accountMainEl = document.getElementById('pdx-account-main');
    var params = new URLSearchParams(window.location.search);
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
    }
    window.addEventListener('hashchange', parseAccountSectionFromHash);
  }

  function accountNavGroups() {
    return [
      {
        label: 'Account',
        items: [
          { id: 'overview', label: 'Overview', icon: 'dashboard' },
          { id: 'personal', label: 'Personal Information', icon: 'user' },
          { id: 'security', label: 'Security', icon: 'lock' },
        ],
      },
      {
        label: 'Your Work',
        items: [
          { id: 'projects', label: 'Projects', icon: 'folder' },
          { id: 'orders', label: 'Orders & Requests', icon: 'receipt' },
          { id: 'files', label: 'Files & Invoices', icon: 'file' },
        ],
      },
      {
        label: 'Support',
        items: [
          { id: 'support', label: 'Messages', icon: 'message' },
          { id: 'services', label: 'Services', icon: 'package' },
        ],
      },
    ];
  }

  function accountSectionTitle(section) {
    var titles = {
      overview: 'Overview',
      personal: 'Personal Information',
      security: 'Security',
      projects: 'Projects',
      orders: 'Orders & Requests',
      files: 'Files & Invoices',
      support: 'Messages',
      services: 'Services',
    };
    return titles[section] || 'Account';
  }

  function accountSectionLead(section) {
    var leads = {
      overview: 'A snapshot of your projects, requests, and account activity.',
      personal: 'Update your name and contact details.',
      security: 'Manage your password and account security.',
      projects: 'Track active work and deliverables.',
      orders: 'View requests, billing, and payment history.',
      files: 'Download shared files and invoices.',
      support: 'Continue your conversation with PAXDesign.',
      services: 'Browse services and start new requests.',
    };
    return leads[section] || '';
  }

  function accountSectionToPortalTab(section) {
    var map = {
      overview: 'overview',
      projects: 'projects',
      orders: 'orders',
      support: 'chat',
      services: 'services',
    };
    return map[section] || 'overview';
  }

  function parseAccountSectionFromHash() {
    if (!isAuthPage() || !user.logged_in) return;
    var hash = (window.location.hash || '').replace(/^#\/?/, '');
    if (!hash) hash = 'overview';
    if (hash === 'chat') hash = 'support';
    if (hash === 'profile') hash = 'personal';
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
    var html = '<div class="pdx-account-sidebar-user">' +
      '<div class="pdx-account-sidebar-name">' + nameWithBadge(user.display_name || 'Account', user.verified, { size: 15, inline: true, context: 'account' }) + '</div>' +
      '<div class="pdx-account-sidebar-email">' + escHtml(user.email || '') + '</div>' +
      '<div class="pdx-account-sidebar-status">' + escHtml(accountStatusText(user.verified)) + '</div>' +
    '</div>';
    accountNavGroups().forEach(function (group) {
      html += '<div class="pdx-account-nav-group"><div class="pdx-account-nav-label">' + escHtml(group.label) + '</div>';
      group.items.forEach(function (item) {
        var active = accountState.section === item.id ? ' is-active' : '';
        html += '<button type="button" class="pdx-account-nav-btn' + active + '" data-account-section="' + item.id + '">' +
          cxIcon(item.icon, 16) + escHtml(item.label) + '</button>';
      });
      html += '</div>';
    });
    html += '<div class="pdx-account-sidebar-footer">' +
      '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-account-signout">' + cxIcon('logout', 16) + escHtml('Sign Out') + '</button>' +
    '</div>';
    accountSidebarEl.innerHTML = html;
    accountSidebarEl.querySelectorAll('[data-account-section]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setAccountSection(btn.getAttribute('data-account-section'));
      });
    });
    var signOut = accountSidebarEl.querySelector('.pdx-account-signout');
    if (signOut) signOut.addEventListener('click', doLogout);
  }

  function renderAccountPersonalSection(profile) {
    profile = profile || {};
    return '<div class="pdx-account-card">' +
      '<div class="pdx-account-card-title">Personal Information</div>' +
      '<form id="pdx-customer-profile-form">' +
        field('display_name', 'Display name', profile.display_name || user.display_name) +
        field('email', 'Email', profile.email || user.email, 'email') +
        '<div style="margin-top:12px">' + pearlBtn('Save changes', { type: 'submit', icon: 'check', small: true, inline: true }) + '</div>' +
      '</form>' +
    '</div>';
  }

  function renderAccountSecuritySection() {
    return '<div class="pdx-account-card">' +
      '<div class="pdx-account-card-title">Security</div>' +
      '<form id="pdx-customer-security-form">' +
        field('current_password', 'Current password', '', 'password') +
        field('new_password', 'New password', '', 'password') +
        field('confirm_password', 'Confirm new password', '', 'password') +
        '<div style="margin-top:12px">' + pearlBtn('Update password', { type: 'submit', icon: 'lock', small: true, inline: true }) + '</div>' +
      '</form>' +
    '</div>';
  }

  function renderAccountFilesSection(files) {
    files = files || [];
    var html = '<div class="pdx-account-card"><div class="pdx-account-card-title">Files & Invoices</div>';
    if (!files.length) {
      html += '<p class="pdx-portal-empty">No shared files yet. Project deliverables and invoices appear here.</p>';
    } else {
      files.forEach(function (file) {
        var href = file.download_url || file.url || '#';
        html += '<a class="pdx-portal-row pdx-portal-row--link" href="' + escHtml(href) + '" target="_blank" rel="noopener">' +
          '<strong>' + escHtml(file.name || file.filename || 'File') + '</strong>' +
          '<span>' + escHtml(file.project_title || file.type || '') + '</span></a>';
      });
    }
    return html + '</div>';
  }

  function bindAccountPersonalForm(container) {
    var form = container.querySelector('#pdx-customer-profile-form');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      customerApiFetch('POST', '/customer/profile', {
        display_name: fd.get('display_name'),
        email: fd.get('email'),
      }).then(function (data) {
        notify((data && data.message) || 'Profile updated.', data && data._ok ? 'info' : 'warn');
        if (data && data._ok) {
          refreshUser({ trigger: 'profile_update' });
          renderAccountApp();
        }
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
        notify('Password must be at least 8 characters.', 'warn');
        return;
      }
      if (p1 !== p2) {
        notify('Passwords do not match.', 'warn');
        return;
      }
      customerApiFetch('POST', '/customer/profile', {
        current_password: fd.get('current_password'),
        new_password: p1,
      }).then(function (data) {
        notify((data && data.message) || 'Security settings updated.', data && data._ok ? 'info' : 'warn');
        if (data && data._ok) form.reset();
      });
    });
  }

  function renderAccountMain() {
    if (!accountMainEl || !accountState.dashboard) return;
    var section = accountState.section;
    var head = '<div class="pdx-account-page-head"><h2 class="pdx-account-page-title">' + escHtml(accountSectionTitle(section)) + '</h2>' +
      '<p class="pdx-account-page-lead">' + escHtml(accountSectionLead(section)) + '</p></div>';

    if (section === 'personal') {
      accountMainEl.innerHTML = head + renderAccountPersonalSection(accountState.profile);
      bindAccountPersonalForm(accountMainEl);
      return;
    }
    if (section === 'security') {
      accountMainEl.innerHTML = head + renderAccountSecuritySection();
      bindAccountSecurityForm(accountMainEl);
      return;
    }
    if (section === 'files') {
      accountMainEl.innerHTML = head + renderAccountFilesSection(accountState.files);
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
    if (accountMainEl) accountMainEl.innerHTML = cxLoading('Loading your account…');
    return claimGuestSessionIfNeeded().then(function () {
      return Promise.all([
        customerApiFetch('GET', '/customer/dashboard'),
        customerApiFetch('GET', '/customer/profile'),
        customerApiFetch('GET', '/customer/files'),
      ]);
    }).then(function (results) {
      var dashboard = results[0];
      if (!dashboard || dashboard._status === 401) {
        if (accountMainEl) accountMainEl.innerHTML = '<p class="pdx-auth-error">Please sign in to continue.</p>';
        return false;
      }
      if (dashboard.code === 'pdx_email_unverified') {
        if (accountMainEl) accountMainEl.innerHTML = '<p class="pdx-auth-error">Verify your email to access your account dashboard.</p>';
        return false;
      }
      accountState.dashboard = dashboard;
      accountState.profile = (results[1] && results[1]._ok !== false) ? results[1] : {};
      accountState.files = (results[2] && Array.isArray(results[2].files)) ? results[2].files : (Array.isArray(results[2]) ? results[2] : []);
      accountState.loaded = true;
      portalState.dashboard = dashboard;
      renderAccountApp();
      return true;
    }).catch(function () {
      if (accountMainEl) accountMainEl.innerHTML = '<p class="pdx-auth-error">Unable to load your account. Please try again.</p>';
      return false;
    });
  }

  function renderAccountApp() {
    if (!accountAppEl || !accountSidebarEl || !accountMainEl) return;
    renderAccountSidebar();
    renderAccountMain();
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
  }

  function updateAuthPagePanels() {
    if (!authPageEl) return;
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
        openAccountPanel();
      } else if (!isAuthPage()) {
        navigateToAuthPage('login');
      }
      cleanUrl();
    }
  }

  function cleanUrl() {
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, '', window.location.pathname);
    }
  }

  /* ─── Access gate ──────────────────────────────────────── */
  function renderAuthGate(container, moduleId, reason) {
    var title = reason === 'verify' ? 'Verify your email' : 'Sign in required';
    var desc = reason === 'verify'
      ? 'Please verify your email address to continue using protected modules.'
      : 'Sign in to access your account, purchases, and subscription.';
    var gateIcon = reason === 'verify' ? 'mail' : 'shield';
    var actions =
      '<button type="button" class="pdx-btn-pearl pdx-btn-pearl--sm pdx-btn-pearl--inline pdx-auth-gate-login">' +
        '<span class="pdx-btn-pearl__wrap">' + cxIcon(reason === 'verify' ? 'mail' : 'login', 16) +
        '<span>' + escHtml(reason === 'verify' ? 'Resend verification' : 'Sign In') + '</span></span></button>';
    if (reason !== 'verify') {
      actions += '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost pdx-auth-gate-register">' +
        cxIcon('register', 16) + escHtml('Create Account') + '</button>';
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
          '<div style="margin-top:12px">' + pearlBtn('Save changes', { type: 'submit', icon: 'check', small: true, inline: true }) + '</div>' +
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
  function customerApiFetch(method, path, body) {
    var base = (C.restUrl || '/wp-json/pdx/v1').replace(/\/$/, '');
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': C.nonce || '',
      },
    };
    if (body && method !== 'GET') opts.body = JSON.stringify(body);
    return fetch(base + path, opts).then(function (r) {
      return r.json().then(function (data) {
        data._status = r.status;
        data._ok = r.ok;
        return data;
      });
    });
  }

  var portalOverlay = null;
  var portalState = { tab: 'overview', dashboard: null, detail: null };

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
    return '<button type="button" class="pdx-portal-back" data-portal-back="1">&larr; ' + escHtml(label || 'Back') + '</button>';
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
      navigateToAuthPage('register');
      return;
    }
    if (!user.verified && !user.is_admin) {
      notify('Please verify your email to access your account.', 'warn');
      navigateToAuthPage('login');
      return;
    }
    if (C.accountPageUrl || isAuthPage()) {
      if (isAuthPage()) {
        setAccountSection(tab === 'chat' ? 'support' : tab);
        return;
      }
      window.location.href = accountPageUrl() + '#/' + (tab === 'chat' ? 'support' : tab);
      return;
    }
    if (!portalOverlay) {
      portalOverlay = document.createElement('div');
      portalOverlay.id = 'pdx-customer-portal';
      portalOverlay.className = 'pdx-cx-shell';
      portalOverlay.setAttribute('role', 'dialog');
      portalOverlay.setAttribute('aria-modal', 'true');
      portalOverlay.setAttribute('aria-label', 'Customer Portal');
      portalOverlay.innerHTML =
        '<div class="pdx-customer-portal-card pdx-customer-portal-card--full">' +
          '<button type="button" class="pdx-auth-close" aria-label="Close">&times;</button>' +
          '<div class="pdx-customer-portal-head">' + cxIcon('dashboard', 20) + '<span>Customer Portal</span></div>' +
          '<div class="pdx-customer-portal-body">' + cxLoading('Loading your workspace…') + '</div>' +
        '</div>';
      document.body.appendChild(portalOverlay);
      portalOverlay.querySelector('.pdx-auth-close').addEventListener('click', closeCustomerPortal);
      portalOverlay.addEventListener('click', function (e) {
        if (e.target === portalOverlay) closeCustomerPortal();
      });
    }
    portalOverlay.classList.add('is-open');
    document.body.classList.add('pdx-no-scroll');
    portalState.tab = 'overview';
    portalState.detail = null;
    portalState.dashboard = null;
    var body = portalOverlay.querySelector('.pdx-customer-portal-body');
    body.innerHTML = cxLoading('Loading your workspace…');
    claimGuestSessionIfNeeded().finally(function () {
      customerApiFetch('GET', '/customer/dashboard').then(function (data) {
        if (!data || data._status === 401) {
          body.innerHTML = '<p class="pdx-auth-error">Please sign in to continue.</p>';
          return;
        }
        if (data.code === 'pdx_email_unverified') {
          body.innerHTML = '<p class="pdx-auth-error">Verify your email to access your portal.</p>';
          return;
        }
        portalState.dashboard = data;
        renderCustomerPortalDashboard(body, data);
      }).catch(function () {
        body.innerHTML = '<p class="pdx-auth-error">Unable to load your portal. Please try again.</p>';
      });
    });
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
      body.innerHTML = renderPortalNav() + '<div class="pdx-portal-content">' + cxLoading('Loading service…') + '</div>';
      customerApiFetch('GET', '/customer/services/' + encodeURIComponent(id)).then(function (service) {
        if (!service || !service._ok) {
          portalState.detail = null;
          renderCustomerPortalDashboard(container, portalState.dashboard);
          notify('Service could not be loaded.', 'warn');
          return;
        }
        portalState.detail.data = service;
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
    return '';
  }

  function renderPortalOverviewSection(data) {
    var projects = data.projects_active || [];
    var orders = data.orders_recent || [];
    var news = data.news || [];
    var unread = data.unread_count || 0;
    var chat = data.chat || {};
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('dashboard', 16) + 'Welcome</h3>';
    html += '<p class="pdx-portal-lead">Your projects, requests, and conversations in one place.</p>';
    html += '<div class="pdx-portal-stats">';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="projects"><span>' + projects.length + '</span>Active projects</button>';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="orders"><span>' + orders.length + '</span>Recent requests</button>';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="notifications"><span>' + unread + '</span>Unread alerts</button>';
    html += '<button type="button" class="pdx-portal-stat" data-portal-tab-jump="chat"><span>' + escHtml(chat.handler || 'ai') + '</span>Chat mode</button>';
    html += '</div></section>';
    html += '<section class="pdx-portal-section"><h3>' + cxIcon('folder', 16) + 'Active projects</h3>';
    if (projects.length) {
      projects.slice(0, 4).forEach(function (p) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="project" data-portal-id="' + escHtml(String(p.id)) + '">' +
          '<strong>' + escHtml(p.title) + '</strong><span>' + escHtml(String(p.progress || 0)) + '% · ' + escHtml(p.status) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">No active projects yet. Browse services to request work.</p>';
    }
    html += '</section>';
    html += '<section class="pdx-portal-section"><h3>' + cxIcon('news', 16) + 'Latest news</h3>';
    if (news.length) {
      news.slice(0, 3).forEach(function (n) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="news" data-portal-slug="' + escHtml(n.slug || '') + '">' +
          '<strong>' + escHtml(n.title) + '</strong></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">No announcements right now.</p>';
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
        '<h3>' + cxIcon('message', 16) + 'Conversation</h3>' +
      '</div>' +
      '<div class="pdx-portal-chat-log" id="pdx-portal-chat-log">' + cxLoading('Loading messages…') + '</div>' +
      '<div class="pdx-portal-chat-tools">' +
        '<label class="pdx-portal-tool" title="Attach image"><input type="file" accept="image/*" id="pdx-portal-image-input" hidden />' + cxIcon('image', 16) + '</label>' +
        '<label class="pdx-portal-tool" title="Attach file"><input type="file" id="pdx-portal-file-input" hidden />' + cxIcon('file', 16) + '</label>' +
        '<button type="button" class="pdx-portal-tool" id="pdx-portal-voice-btn" title="Voice message">' + cxIcon('mic', 16) + '</button>' +
        '<button type="button" class="pdx-portal-tool" id="pdx-portal-location-btn" title="Share location">' + cxIcon('location', 16) + '</button>' +
      '</div>' +
      '<form class="pdx-portal-chat-form" id="pdx-portal-chat-form">' +
        '<textarea rows="2" placeholder="Write a message…" aria-label="Message"></textarea>' +
        pearlBtn('Send', { type: 'submit', small: true, inline: true, icon: 'send' }) +
      '</form></section>';
  }

  function renderPortalProjectsSection(data) {
    var projects = data.projects_active || [];
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('folder', 16) + 'Your projects</h3>';
    if (projects.length) {
      projects.forEach(function (p) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="project" data-portal-id="' + escHtml(String(p.id)) + '">' +
          '<strong>' + escHtml(p.title) + '</strong><span>' + escHtml(String(p.progress || 0)) + '% · ' + escHtml(p.status) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">No active projects yet. Request a service to begin.</p>';
    }
    return html + '</section>';
  }

  function renderPortalProjectDetail(project) {
    var html = portalBackBtn('All projects');
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(project.title) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(project.ref) + ' · ' + escHtml(project.status) + ' · ' + escHtml(String(project.progress || 0)) + '%</p>';
    if (project.description) {
      html += '<div class="pdx-portal-body-text">' + escHtml(project.description) + '</div>';
    }
    if ((project.milestones || []).length) {
      html += '<h4>Milestones</h4><ul class="pdx-portal-list">';
      project.milestones.forEach(function (m) {
        html += '<li><strong>' + escHtml(m.title) + '</strong> — ' + escHtml(m.status) +
          (m.due_date ? ' · due ' + escHtml(formatDate(m.due_date)) : '') + '</li>';
      });
      html += '</ul>';
    }
    if ((project.notes || []).length) {
      html += '<h4>Updates</h4><ul class="pdx-portal-list">';
      project.notes.forEach(function (n) {
        html += '<li>' + escHtml(n.body) + '</li>';
      });
      html += '</ul>';
    }
    if ((project.files || []).length) {
      html += '<h4>Files</h4><ul class="pdx-portal-list">';
      project.files.forEach(function (f) {
        var dl = '/customer/projects/' + project.id + '/files/' + f.id + '/download';
        html += '<li><a href="' + escHtml((C.restUrl || '/wp-json/pdx/v1').replace(/\/$/, '') + dl) + '" target="_blank" rel="noopener">' + escHtml(f.file_name) + '</a></li>';
      });
      html += '</ul>';
    }
    if ((project.assignees || []).length) {
      html += '<h4>Your team</h4><ul class="pdx-portal-list">';
      project.assignees.forEach(function (a) {
        html += '<li>' + escHtml(a.display_name || ('Staff #' + a.user_id)) + ' — ' + escHtml(a.role_label || 'Staff') + '</li>';
      });
      html += '</ul>';
    }
    return html + '</article>';
  }

  function renderPortalOrdersSection(data) {
    var orders = data.orders_recent || [];
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('receipt', 16) + 'Service requests</h3>';
    if (orders.length) {
      orders.forEach(function (o) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="order" data-portal-id="' + escHtml(String(o.id)) + '">' +
          '<strong>' + escHtml(o.service_label || o.ref) + '</strong><span>' + escHtml(o.status) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">No service requests yet.</p>';
    }
    return html + '</section>';
  }

  function renderPortalOrderDetail(order) {
    var html = portalBackBtn('All requests');
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(order.service_label || order.ref) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(order.ref) + ' · ' + escHtml(order.status) + '</p>';
    if (order.message) {
      html += '<div class="pdx-portal-body-text">' + escHtml(order.message) + '</div>';
    }
    if (order.notes && order.notes.length) {
      html += '<h4>Notes</h4><ul class="pdx-portal-list">';
      order.notes.forEach(function (n) {
        html += '<li>' + escHtml(n.body) + '</li>';
      });
      html += '</ul>';
    }
    return html + '</article>';
  }

  function renderPortalServicesSection() {
    return '<section class="pdx-portal-section" id="pdx-portal-services">' + cxLoading('Loading services…') + '</section>';
  }

  function bindPortalServicesSection(container) {
    var section = container.querySelector('#pdx-portal-services');
    if (!section) return;
    customerApiFetch('GET', '/customer/services').then(function (data) {
      if (!data || !data._ok) {
        section.innerHTML = '<p class="pdx-auth-error">Services could not be loaded.</p>';
        return;
      }
      var html = '<h3>' + cxIcon('package', 16) + 'Services catalog</h3>';
      (data.services || []).forEach(function (s) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="service" data-portal-slug="' + escHtml(s.slug) + '">' +
          '<strong>' + escHtml(s.name) + '</strong><span>' + escHtml(s.category || '') + '</span></button>';
      });
      if (!(data.services || []).length) {
        html += '<p class="pdx-portal-empty">No services available yet.</p>';
      }
      section.innerHTML = html;
      section.querySelectorAll('[data-portal-open]').forEach(function (el) {
        el.addEventListener('click', function () {
          openPortalDetail(container, 'service', el.dataset.portalSlug);
        });
      });
    });
  }

  function renderPortalServiceDetail(service) {
    var html = portalBackBtn('All services');
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(service.name) + '</h3>';
    if (service.description) {
      html += '<div class="pdx-portal-body-text">' + escHtml(service.description) + '</div>';
    }
    html += '<form class="pdx-portal-request-form" id="pdx-portal-request-form">' +
      '<textarea rows="3" placeholder="Describe your request…" aria-label="Request message" required></textarea>' +
      pearlBtn('Submit request', { type: 'submit', small: true, inline: true, icon: 'send' }) +
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
          notify(res.message || (res._ok ? 'Request submitted.' : 'Request failed.'), res._ok ? 'info' : 'error');
          if (res._ok) {
            portalState.tab = 'orders';
            portalState.detail = null;
            customerApiFetch('GET', '/customer/dashboard').then(function (dash) {
              portalState.dashboard = dash;
              var body = portalOverlay && portalOverlay.querySelector('.pdx-customer-portal-body');
              if (body) renderCustomerPortalDashboard(body, dash);
            });
          }
        });
      });
    }, 0);
    return html;
  }

  function renderPortalNewsSection(data) {
    var news = data.news || [];
    var html = '<section class="pdx-portal-section"><h3>' + cxIcon('news', 16) + 'News & announcements</h3>';
    if (news.length) {
      news.forEach(function (n) {
        html += '<button type="button" class="pdx-portal-row pdx-portal-row--link" data-portal-open="news" data-portal-slug="' + escHtml(n.slug || '') + '">' +
          '<strong>' + escHtml(n.title) + '</strong><span>' + escHtml(formatDate(n.published_at)) + '</span></button>';
      });
    } else {
      html += '<p class="pdx-portal-empty">No announcements right now.</p>';
    }
    return html + '</section>';
  }

  function renderPortalNewsDetail(item) {
    var html = portalBackBtn('All news');
    html += '<article class="pdx-portal-detail"><h3>' + escHtml(item.title) + '</h3>';
    html += '<p class="pdx-portal-meta">' + escHtml(formatDate(item.published_at)) + '</p>';
    if (item.excerpt) {
      html += '<p class="pdx-portal-lead">' + escHtml(item.excerpt) + '</p>';
    }
    html += '<div class="pdx-portal-body-text">' + (item.body_html || escHtml(item.body || '')) + '</div></article>';
    return html;
  }

  function renderPortalNotificationsSection() {
    return '<section class="pdx-portal-section" id="pdx-portal-notifications">' + cxLoading('Loading notifications…') + '</section>';
  }

  function bindPortalNotificationsSection(container) {
    var section = container.querySelector('#pdx-portal-notifications');
    if (!section) return;
    customerApiFetch('GET', '/customer/notifications?limit=50').then(function (data) {
      if (!data || !data._ok) {
        section.innerHTML = '<p class="pdx-auth-error">Notifications could not be loaded.</p>';
        return;
      }
      var html = '<h3>' + cxIcon('bell', 16) + 'Notifications';
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
          html += '<button type="button" class="pdx-cx-btn pdx-cx-btn--ghost" id="pdx-portal-mark-read">Mark all read</button>';
        }
      } else {
        html += '<p class="pdx-portal-empty">No notifications yet.</p>';
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
      }, { passive: true });
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
