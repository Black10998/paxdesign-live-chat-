(function () {
  var A = window.ALB || {};
  var i18n = A.i18n || {};
  var user = A.user || {};
  var root = document.getElementById('app-root');
  var nav = document.getElementById('app-nav');
  var state = { drivers: [], settings: null, perms: null };

  function t(key, vars) {
    var text = i18n[key] || key;
    if (vars) {
      Object.keys(vars).forEach(function (k) {
        text = text.replace('{' + k + '}', vars[k]);
      });
    }
    return text;
  }
  function can(perm) {
    return !!(user.permissions && user.permissions[perm]);
  }
  function setPageTitle(name) {
    document.title = (name ? name + ' — ' : '') + (A.company || 'Albatros');
  }
  function deviceMark(size) {
    if (!A.device_mark) return '';
    return '<img class="device-mark' + (size === 'sm' ? ' device-mark-sm' : '') + '" src="' + esc(A.device_mark) + '" alt="' + esc(t('scanner.device')) + '">';
  }
  function face(url, cls) {
    return url ? '<img class="' + (cls || 'face-thumb') + '" src="' + esc(url) + '" alt="">' : '';
  }
  function apiUpload(path, file) {
    var fd = new FormData();
    fd.append('photo', file);
    return fetch(A.rest + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': A.nonce },
      body: fd
    }).then(function (r) {
      return r.json().then(function (data) { return { ok: r.ok, data: data }; });
    }).then(function (res) {
      if (!res.ok) throw new Error(res.data && res.data.message ? res.data.message : t('common.error'));
      return res.data;
    });
  }
  function maybeUpload(path, form) {
    var input = form.querySelector('input[name="photo"]');
    if (!input || !input.files || !input.files[0]) return Promise.resolve();
    return apiUpload(path, input.files[0]);
  }
  function photoField(currentUrl) {
    return '<div class="field wide"><label>' + esc(t('users.photo')) + '</label>' +
      (currentUrl ? '<div class="person-row">' + face(currentUrl) + '</div>' : '') +
      '<input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/*">' +
      '<p class="hint">' + esc(t('users.photo_hint')) + '</p></div>';
  }
  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function statusLabel(status) {
    return t('status.' + status);
  }
  function badge(status) {
    return '<span class="badge badge-' + esc(status) + '">' + esc(statusLabel(status)) + '</span>';
  }
  function qs(params) {
    return Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] != null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
  }
  function api(path, options) {
    options = options || {};
    return fetch(A.rest + path, {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': A.nonce
      },
      body: options.body ? JSON.stringify(options.body) : undefined
    }).then(function (r) {
      var type = r.headers.get('content-type') || '';
      if (type.indexOf('application/json') === -1) {
        return r.text().then(function (text) { return { ok: r.ok, data: text, status: r.status }; });
      }
      return r.json().then(function (data) { return { ok: r.ok, data: data, status: r.status }; });
    }).then(function (res) {
      if (res.status === 401) {
        window.location.href = '/login';
      }
      if (!res.ok) {
        throw new Error(res.data && res.data.message ? res.data.message : t('common.error'));
      }
      if (res.data && res.data.nonce) {
        A.nonce = res.data.nonce;
      }
      return res.data;
    });
  }
  function path() {
    return window.location.pathname.replace(/\/+$/, '') || '/';
  }
  function go(href) {
    if (href !== path()) {
      history.pushState({}, '', href);
    }
    render();
  }
  function parseRoute() {
    var p = path();
    var parts = p.replace(/^\//, '').split('/').filter(Boolean);
    return { parts: parts, raw: p };
  }
  function pager(meta) {
    if (!meta || !meta.total) return '';
    var pages = Math.max(1, Math.ceil(meta.total / meta.per_page));
    return '<div class="pager"><span>' + esc(meta.total) + ' ' + esc(t('common.results')) + '</span>' +
      '<span>' + esc(t('common.page')) + ' ' + meta.page + ' ' + esc(t('common.of')) + ' ' + pages +
      (meta.page > 1 ? ' <a href="#" data-page="' + (meta.page - 1) + '">‹</a>' : '') +
      (meta.page < pages ? ' <a href="#" data-page="' + (meta.page + 1) + '">›</a>' : '') +
      '</span></div>';
  }
  function emptyRow(cols) {
    return '<tr><td colspan="' + cols + '">' + esc(t('common.empty')) + '</td></tr>';
  }
  function icon(name) {
    var paths = {
      dashboard: '<path d="M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z"/>',
      scanners: '<rect x="6" y="3" width="12" height="18" rx="1"/><path d="M9 7h6M9 10h6M9 13h4M10 17h4"/>',
      drivers: '<circle cx="9" cy="8" r="2.5"/><path d="M4.5 18c.4-3 2.3-4.5 4.5-4.5S13.1 15 13.5 18"/><circle cx="16" cy="8.5" r="2"/><path d="M15 13.6c1.8.2 3.3 1.5 3.6 4.4"/>',
      audit: '<path d="M7 3h8l3 3v15H7z"/><path d="M15 3v4h4M9 12h6M9 15h6"/>',
      reports: '<path d="M5 19V9M10 19V5M15 19v-7M20 19H4"/>',
      users: '<circle cx="12" cy="8" r="3"/><path d="M5.5 19c.7-3.4 3-5 6.5-5s5.8 1.6 6.5 5"/>',
      settings: '<circle cx="12" cy="12" r="3"/><path d="M12 4v2.2M12 17.8V20M4 12h2.2M17.8 12H20M6.4 6.4l1.6 1.6M16 16l1.6 1.6M17.6 6.4 16 8M8 16l-1.6 1.6"/>',
      help: '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.2a2.4 2.4 0 1 1 3.3 2.2c-.8.4-1.3 1-1.3 1.8V14M12 17h.01"/>'
    };
    return '<svg class="nav-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">' +
      (paths[name] || paths.help) + '</svg>';
  }
  function navItems() {
    var items = [];
    if (can('dashboard.view')) items.push(['/', 'nav.dashboard', 'dashboard']);
    if (can('scanners.view')) items.push(['/scanners', 'nav.scanners', 'scanners']);
    if (can('drivers.view')) items.push(['/drivers', 'nav.drivers', 'drivers']);
    if (can('audit.view')) items.push(['/audit', 'nav.audit', 'audit']);
    if (can('reports.export')) items.push(['/reports', 'nav.reports', 'reports']);
    if (can('users.view') || can('users.manage')) items.push(['/users', 'nav.users', 'users']);
    if (can('settings.view') || can('roles.manage')) items.push(['/settings', 'nav.settings', 'settings']);
    items.push(['/help', 'nav.help', 'help']);
    return items;
  }
  function contextLabel() {
    var p = parseRoute().parts;
    if (!p.length) return t('nav.dashboard');
    if (p[0] === 'scanners') return t('nav.scanners');
    if (p[0] === 'drivers') return t('nav.drivers');
    if (p[0] === 'audit') return t('nav.audit');
    if (p[0] === 'reports') return t('nav.reports');
    if (p[0] === 'users') return t('nav.users');
    if (p[0] === 'settings') return t('nav.settings');
    if (p[0] === 'help') return t('nav.help');
    return t('nav.dashboard');
  }
  function renderNav() {
    var p = path();
    if (nav) {
      nav.setAttribute('aria-label', t('nav.menu'));
      nav.innerHTML = navItems().map(function (item) {
        var active = item[0] === '/' ? p === '/' : p.indexOf(item[0]) === 0;
        return '<a href="' + item[0] + '" class="' + (active ? 'active' : '') + '"' +
          (active ? ' aria-current="page"' : '') + '>' +
          icon(item[2]) + '<span class="nav-label">' + esc(t(item[1])) + '</span></a>';
      }).join('');
    }
  }
  function header() {
    var search = document.getElementById('global-search');
    search.placeholder = t('header.search');
    search.setAttribute('aria-label', t('header.search'));
    var ctx = document.getElementById('page-context');
    if (ctx) ctx.textContent = contextLabel();
    var helpBtn = document.getElementById('help-btn');
    var helpLabel = document.getElementById('help-btn-label');
    if (helpLabel) helpLabel.textContent = t('header.help');
    if (helpBtn) helpBtn.setAttribute('aria-label', t('header.help'));
    var who = document.getElementById('current-user');
    who.textContent = user.name || user.username || '';
    who.title = user.last_login_display ? (t('users.last_login') + ': ' + user.last_login_display) : '';
    document.getElementById('logout-btn').textContent = t('nav.logout');
    var langLabel = document.querySelector('label[for="lang-switch"]');
    if (langLabel) langLabel.textContent = t('login.language');
    var sel = document.getElementById('lang-switch');
    sel.setAttribute('aria-label', t('login.language'));
    sel.innerHTML = (A.locales || ['de', 'en', 'tr']).map(function (loc) {
      return '<option value="' + loc + '"' + (A.locale === loc ? ' selected' : '') + '>' + esc(t('lang.' + loc)) + '</option>';
    }).join('');
  }

  function dashView() {
    return new URLSearchParams(window.location.search).get('view') || '';
  }
  function dashQuery(view) {
    if (view === 'assigned') return { assigned: 1 };
    if (view === 'active' || view === 'lost' || view === 'defective' || view === 'returned' || view === 'inactive') {
      return { status: view };
    }
    return {};
  }
  function scannerTable(items, options) {
    options = options || {};
    var rows = (items || []).map(function (s) {
      return '<tr class="click" data-go="/scanners/' + s.id + '">' +
        '<td>' + deviceMark('sm') + esc(s.scanner_code) + '</td><td>' + esc(s.brand) + '</td><td>' + esc(s.model) + '</td>' +
        '<td>' + esc(s.serial_number) + '</td><td>' + esc(s.phone_number) + '</td>' +
        '<td class="person-cell">' + face(s.driver_photo_url) + ' ' + esc(s.driver_name || t('scanner.no_driver')) + '</td>' +
        '<td>' + esc(s.handover_date_display) + '</td><td>' + badge(s.status) + '</td>' +
        (options.actions ? '<td class="row-actions" data-stop="1">' + scannerRowActions(s) + '</td>' : '') +
        '</tr>';
    }).join('') || emptyRow(options.actions ? 9 : 8);
    return '<table class="data"><thead><tr>' +
      '<th>' + esc(t('scanner.code')) + '</th><th>' + esc(t('scanner.brand')) + '</th><th>' + esc(t('scanner.model')) + '</th>' +
      '<th>' + esc(t('scanner.serial')) + '</th><th>' + esc(t('scanner.phone')) + '</th><th>' + esc(t('scanner.driver')) + '</th>' +
      '<th>' + esc(t('scanner.handover_date')) + '</th><th>' + esc(t('common.status')) + '</th>' +
      (options.actions ? '<th>' + esc(t('common.actions')) + '</th>' : '') +
      '</tr></thead><tbody>' + rows + '</tbody></table>';
  }
  function scannerRowActions(s) {
    var html = '';
    if (s.deleted_at) {
      if (can('scanners.delete')) {
        html += '<button type="button" class="btn btn-sec" data-act="restore" data-id="' + s.id + '">' + esc(t('scanner.restore')) + '</button>';
      }
      return html;
    }
    html += '<button type="button" class="btn btn-sec" data-go="/scanners/' + s.id + '">' + esc(t('common.details')) + '</button>';
    if (can('scanners.status')) {
      if (s.status !== 'lost') html += '<button type="button" class="btn btn-sec" data-act="lost" data-id="' + s.id + '">' + esc(t('scanner.mark_lost')) + '</button>';
      if (s.status !== 'defective') html += '<button type="button" class="btn btn-sec" data-act="defective" data-id="' + s.id + '">' + esc(t('scanner.mark_defective')) + '</button>';
      if (s.status !== 'returned') html += '<button type="button" class="btn btn-sec" data-act="returned" data-id="' + s.id + '">' + esc(t('scanner.mark_returned')) + '</button>';
      if (s.status !== 'inactive') html += '<button type="button" class="btn btn-sec" data-act="deactivate" data-id="' + s.id + '">' + esc(t('scanner.deactivate')) + '</button>';
      if (s.status !== 'active') html += '<button type="button" class="btn btn-sec" data-act="restore" data-id="' + s.id + '">' + esc(t('scanner.reactivate')) + '</button>';
    }
    if (can('scanners.delete')) {
      html += '<button type="button" class="btn btn-danger" data-act="delete" data-id="' + s.id + '">' + esc(t('scanner.delete')) + '</button>';
    }
    return html;
  }
  function runScannerAction(act, id, extra) {
    extra = extra || {};
    if (act === 'delete' && !window.confirm(t('scanner.delete_confirm'))) return Promise.resolve();
    if (act === 'deactivate' && !window.confirm(t('scanner.deactivate'))) return Promise.resolve();
    if (act === 'delete') return api('scanners/' + id + '/delete', { method: 'POST', body: extra });
    if (act === 'restore') return api('scanners/' + id + '/restore', { method: 'POST', body: extra });
    if (act === 'take_over') return api('scanners/' + id + '/take-over', { method: 'POST', body: extra });
    var statusMap = { lost: 'lost', defective: 'defective', returned: 'returned', deactivate: 'inactive', activate: 'active' };
    if (statusMap[act]) {
      return api('scanners/' + id + '/status', { method: 'POST', body: { status: statusMap[act], notes: extra.notes || '' } });
    }
    return Promise.reject(new Error(t('common.error')));
  }

  function renderDashboard() {
    var view = dashView();
    root.innerHTML = '<div class="page-head"><h1>' + esc(t('dash.title')) + '</h1></div><p>' + esc(t('common.loading')) + '</p>';
    api('dashboard').then(function (data) {
      var c = data.counts || {};
      var kpis = [
        ['total', 'dash.total', c.total || 0],
        ['active', 'dash.active', c.active || 0],
        ['assigned', 'dash.assigned', c.assigned || 0],
        ['lost', 'dash.lost', c.lost || 0],
        ['defective', 'dash.defective', c.defective || 0],
        ['returned', 'dash.returned', c.returned || 0]
      ];
      var handovers = (data.recent_handovers || []).map(function (h) {
        var key = h.action === 'return' ? 'handover.returned' : 'handover.received';
        return '<li>' + esc(t(key, { date: h.at_display, driver: h.driver_name || '-', serial: h.serial_number })) + '</li>';
      }).join('') || '<li>' + esc(t('dash.no_handovers')) + '</li>';
      var activity = (data.recent_activity || []).map(function (a) {
        return '<li>' + esc(a.created_at_display) + ' — ' + esc(a.actor_name) + ': ' + esc(a.action === 'scanner_scan' ? t('scan.action.' + a.new_value) : (a.field + ' ' + (a.old_value || '') + ' → ' + (a.new_value || ''))) + '</li>';
      }).join('') || '<li>' + esc(t('dash.no_activity')) + '</li>';
      root.innerHTML =
        '<div class="page-head"><h1>' + esc(t('dash.title')) + '</h1></div>' +
        '<p class="hint">' + esc(t('dash.click_hint')) + '</p>' +
        '<div class="kpis">' + kpis.map(function (k) {
          return '<button type="button" class="kpi' + (view === k[0] ? ' is-on' : '') + '" data-view="' + k[0] + '"><div class="label">' + esc(t(k[1])) + '</div><div class="value">' + esc(k[2]) + '</div></button>';
        }).join('') + '</div>' +
        '<div class="card" id="dash-list"><h2>' + esc(t('dash.list')) + '</h2><div class="body" style="padding:12px">' + esc(view ? t('common.loading') : t('dash.click_hint')) + '</div></div>' +
        '<div class="grid-2" style="margin-top:12px">' +
        '<div class="card"><h2>' + esc(t('dash.recent_handovers')) + '</h2><ul class="history">' + handovers + '</ul></div>' +
        '<div class="card"><h2>' + esc(t('dash.recent_activity')) + '</h2><ul class="history">' + activity + '</ul></div>' +
        '</div>';
      root.querySelectorAll('[data-view]').forEach(function (btn) {
        btn.onclick = function () {
          go('/?view=' + btn.getAttribute('data-view'));
        };
      });
      if (view) {
        loadDashList(view);
      }
    }).catch(showError);
  }
  function loadDashList(view) {
    var box = document.getElementById('dash-list');
    if (!box) return;
    var title = {
      total: 'dash.total', active: 'dash.active', assigned: 'dash.assigned',
      lost: 'dash.lost', defective: 'dash.defective', returned: 'dash.returned'
    }[view] || 'dash.list';
    api('scanners?' + qs(Object.assign({ per_page: 100, sort: 'id', dir: 'desc' }, dashQuery(view)))).then(function (data) {
      box.innerHTML = '<h2>' + esc(t(title)) + ' (' + esc((data.total || 0)) + ')</h2>' + scannerTable(data.items || [], { actions: true });
    }).catch(function (err) {
      box.innerHTML = '<h2>' + esc(t('dash.list')) + '</h2><div class="msg msg-error">' + esc(err.message) + '</div>';
    });
  }

  function scannerFilters(q) {
    return '<div class="toolbar">' +
      '<div class="field grow"><label>' + esc(t('common.search')) + '</label><input id="f-q" value="' + esc(q.q || '') + '"></div>' +
      '<div class="field"><label>' + esc(t('common.status')) + '</label><select id="f-status">' +
      '<option value="">' + esc(t('common.all')) + '</option>' +
      (A.statuses || []).map(function (s) {
        return '<option value="' + s + '"' + (q.status === s ? ' selected' : '') + '>' + esc(statusLabel(s)) + '</option>';
      }).join('') + '</select></div>' +
      '<div class="field"><label>' + esc(t('dash.assigned')) + '</label><select id="f-assigned">' +
      '<option value="">' + esc(t('common.all')) + '</option>' +
      '<option value="1"' + (String(q.assigned) === '1' ? ' selected' : '') + '>' + esc(t('dash.assigned')) + '</option>' +
      '</select></div>' +
      (can('scanners.delete') ? '<div class="field"><label>' + esc(t('scanner.removed')) + '</label><select id="f-removed"><option value="">' + esc(t('common.no')) + '</option><option value="1"' + (String(q.removed) === '1' ? ' selected' : '') + '>' + esc(t('scanner.show_removed')) + '</option></select></div>' : '') +
      '<div class="field"><label>' + esc(t('scanner.brand')) + '</label><input id="f-brand" value="' + esc(q.brand || '') + '"></div>' +
      '<div class="field"><label>' + esc(t('scanner.model')) + '</label><input id="f-model" value="' + esc(q.model || '') + '"></div>' +
      '<button class="btn" id="f-apply">' + esc(t('common.filter')) + '</button>' +
      '</div>';
  }

  function renderScanners(query) {
    query = query || {};
    root.innerHTML = '<div class="page-head"><h1>' + esc(t('scanner.title')) + '</h1>' +
      (can('scanners.create') ? '<div class="actions"><button class="btn" data-go="/scanners/new">' + esc(t('scanner.new')) + '</button></div>' : '') +
      '</div>' + scannerFilters(query) + '<div class="card"><p class="body" style="padding:12px">' + esc(t('common.loading')) + '</p></div>';
    api('scanners?' + qs({
      q: query.q || '', status: query.status || '', brand: query.brand || '', model: query.model || '',
      assigned: query.assigned || '', removed: query.removed || '',
      sort: query.sort || 'id', dir: query.dir || 'desc', page: query.page || 1
    })).then(function (data) {
      root.innerHTML = '<div class="page-head"><h1>' + esc(t('scanner.title')) + '</h1>' +
        (can('scanners.create') ? '<div class="actions"><button class="btn" data-go="/scanners/new">' + esc(t('scanner.new')) + '</button></div>' : '') +
        '</div>' + scannerFilters(query) +
        '<div class="card">' + scannerTable(data.items || [], { actions: true }) + pager(data) + '</div>';
      bindPager(function (page) { renderScanners(Object.assign({}, query, { page: page })); });
      document.getElementById('f-apply').onclick = function () {
        renderScanners({
          q: document.getElementById('f-q').value,
          status: document.getElementById('f-status').value,
          assigned: document.getElementById('f-assigned').value,
          removed: document.getElementById('f-removed') ? document.getElementById('f-removed').value : '',
          brand: document.getElementById('f-brand').value,
          model: document.getElementById('f-model').value
        });
      };
    }).catch(showError);
  }

  function driverSelect(selected, includeEmpty) {
    var html = includeEmpty !== false ? '<option value="">' + esc(t('scanner.no_driver')) + '</option>' : '';
    return html + state.drivers.map(function (d) {
      return '<option value="' + d.id + '"' + (String(selected) === String(d.id) ? ' selected' : '') + (d.status !== 'active' ? ' disabled' : '') + '>' + esc(d.name) + '</option>';
    }).join('');
  }

  function renderScannerForm() {
    root.innerHTML = '<div class="page-head"><h1>' + deviceMark('sm') + ' ' + esc(t('scanner.new')) + '</h1><button class="btn btn-sec" data-go="/scanners">' + esc(t('common.back')) + '</button></div>' +
      '<form id="scanner-form" class="card form-grid">' +
      field('brand', t('scanner.brand'), 'text', '', false) +
      field('model', t('scanner.model'), 'text', '', false) +
      field('serial_number', t('scanner.serial'), 'text', '', false) +
      field('phone_number', t('scanner.phone'), 'text', '', false) +
      '<div class="field"><label>' + esc(t('scanner.driver')) + '</label><select name="driver_id">' + driverSelect('') + '</select></div>' +
      field('handover_date', t('scanner.handover_date'), 'date', '', false) +
      '<div class="field"><label>' + esc(t('common.status')) + '</label><select name="status">' +
      (A.statuses || []).map(function (s) { return '<option value="' + s + '">' + esc(statusLabel(s)) + '</option>'; }).join('') +
      '</select></div>' +
      '<div class="field wide"><label>' + esc(t('common.notes')) + '</label><textarea name="notes"></textarea></div>' +
      '<div class="wide"><p class="hint">' + esc(t('scanner.immutable')) + '</p><button class="btn" type="submit">' + esc(t('common.create')) + '</button></div>' +
      '</form>';
    document.getElementById('scanner-form').onsubmit = function (e) {
      e.preventDefault();
      var fd = new FormData(e.target);
      var body = {};
      fd.forEach(function (v, k) { body[k] = v; });
      api('scanners', { method: 'POST', body: body }).then(function (item) {
        go('/scanners/' + item.id);
      }).catch(showError);
    };
  }

  function field(name, label, type, value, readonly) {
    return '<div class="field"><label>' + esc(label) + '</label><input name="' + name + '" type="' + type + '" value="' + esc(value || '') + '"' + (readonly ? ' readonly class="readonly"' : '') + '></div>';
  }

  function renderScannerDetail(id) {
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    api('scanners/' + id).then(function (s) {
      setPageTitle(s.scanner_code + ' / ' + s.serial_number);
      var lost = '';
      if (s.status === 'lost') {
        if (s.last_assigned) {
          lost = '<div class="msg msg-warn">' + esc(t('scanner.lost_banner', { driver: s.last_assigned.driver_name, date: s.last_assigned.at_display })) + '</div>';
        } else {
          lost = '<div class="msg msg-warn">' + esc(t('scanner.lost_unknown')) + '</div>';
        }
      }
      var history = (s.history || []).map(function (h) {
        if (h.type === 'scan') {
          return '<li>' + esc(h.at_display) + ' — ' + esc(h.actor_name) + ': ' + esc(t('scan.action.' + h.action)) + (h.notes ? ' — ' + esc(h.notes) : '') + '</li>';
        }
        if (h.type === 'handover') {
          var key = h.action === 'return' ? 'handover.returned' : 'handover.received';
          var face = h.driver_photo_url ? '<img class="face-thumb" src="' + esc(h.driver_photo_url) + '" alt=""> ' : '';
          return '<li>' + face + esc(t(key, { date: h.at_display, driver: h.driver_name || '-', serial: s.serial_number })) +
            (h.driver_phone ? ' — ' + esc(h.driver_phone) : '') +
            (h.notes ? ' — ' + esc(h.notes) : '') + '</li>';
        }
        return '<li>' + esc(h.at_display) + ' — ' + esc(statusLabel(h.old_status)) + ' → ' + esc(statusLabel(h.new_status)) + '</li>';
      }).join('') || '<li>' + esc(t('common.empty')) + '</li>';
      var deleted = s.deleted_at ? '<div class="msg msg-warn">' + esc(t('scanner.deleted_banner')) + '</div>' : '';
      root.innerHTML =
        '<div class="page-head"><h1>' + esc(s.scanner_code) + ' / ' + esc(s.serial_number) + '</h1><button class="btn btn-sec" data-go="/scanners">' + esc(t('common.back')) + '</button></div>' +
        deleted + lost +
        '<div class="detail">' +
        '<div class="card">' +
        '<h2>' + esc(t('scanner.detail')) + '</h2>' +
        '<div class="device-head">' + deviceMark() + '<div class="kv">' +
        kv(t('scanner.code'), s.scanner_code) +
        kv(t('scanner.brand'), s.brand) +
        kv(t('scanner.model'), s.model) +
        kv(t('scanner.serial'), s.serial_number) +
        kv(t('scanner.phone'), s.phone_number) +
        kv(t('common.status'), statusLabel(s.status)) +
        kv(t('common.notes'), s.notes || '') +
        '</div></div>' +
        '<div class="holder-box"><h2>' + esc(t('scanner.current_holder')) + '</h2>' +
        '<div class="person-row">' + face(s.driver_photo_url, 'face-lg') +
        '<div><strong>' + esc(s.driver_name || t('scanner.no_driver')) + '</strong><br>' +
        esc(t('handover.verified_phone')) + ': ' + esc(s.driver_phone || '—') + '<br>' +
        esc(t('scanner.handover_date')) + ': ' + esc(s.handover_at_display || s.handover_date_display || '—') +
        '</div></div></div>' +
        (can('scanners.edit') || can('scanners.identity') || can('scanners.assign') || can('scanners.status') || can('scanners.delete') ? renderScannerActions(s) : '') +
        '</div>' +
        '<div>' +
        (can('qr.view') ? '<div class="card"><h2>' + esc(t('scanner.qr')) + '</h2><div class="qr-box"><div id="qr"></div><div class="hint">' + esc(t('scanner.qr_hint')) + '</div><div id="qr-url">' + esc(s.qr_url) + '</div>' +
          '<div class="action-bar" style="border:0;justify-content:center"><button type="button" class="btn" id="copy-qr">' + esc(t('scanner.copy_qr')) + '</button>' +
          '<a class="btn btn-sec" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=' + encodeURIComponent(t('scanner.whatsapp_text') + ' ' + s.qr_url) + '">' + esc(t('scanner.whatsapp')) + '</a></div></div></div>' : '') +
        '</div></div>' +
        '<div class="card" style="margin-top:12px"><h2>' + esc(t('scanner.history')) + '</h2><ul class="history">' + history + '</ul></div>';
      if (can('qr.view') && window.QRCode) {
        new QRCode(document.getElementById('qr'), { text: s.qr_url, width: 140, height: 140 });
      }
      var copy = document.getElementById('copy-qr');
      if (copy) {
        copy.onclick = function () {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(s.qr_url).then(function () { copy.textContent = t('scanner.link_copied'); });
          }
        };
      }
      bindScannerActions(s);
    }).catch(showError);
  }

  function kv(k, v) {
    return '<div class="k">' + esc(k) + '</div><div>' + esc(v) + '</div>';
  }

  function renderScannerActions(s) {
    var html = '<div class="action-bar">';
    if (s.deleted_at) {
      if (can('scanners.delete')) {
        html += '<button type="button" class="btn" data-act="restore" data-id="' + s.id + '">' + esc(t('scanner.restore')) + '</button>';
      }
      html += '</div>';
      return html;
    }
    if (can('scanners.assign')) {
      html += '<button type="button" class="btn" data-act="take_over" data-id="' + s.id + '">' + esc(t('scanner.take_over')) + '</button>';
    }
    if (can('scanners.status')) {
      html += '<button type="button" class="btn btn-sec" data-act="returned" data-id="' + s.id + '">' + esc(t('scanner.return_device')) + '</button>';
      html += '<button type="button" class="btn btn-sec" data-act="defective" data-id="' + s.id + '">' + esc(t('scanner.mark_defective')) + '</button>';
      html += '<button type="button" class="btn btn-sec" data-act="lost" data-id="' + s.id + '">' + esc(t('scanner.mark_lost')) + '</button>';
      if (s.status !== 'inactive') {
        html += '<button type="button" class="btn btn-sec" data-act="deactivate" data-id="' + s.id + '">' + esc(t('scanner.deactivate')) + '</button>';
      }
      if (s.status !== 'active') {
        html += '<button type="button" class="btn btn-sec" data-act="restore" data-id="' + s.id + '">' + esc(t('scanner.reactivate')) + '</button>';
      }
    }
    if (can('scanners.delete')) {
      html += '<button type="button" class="btn btn-danger" data-act="delete" data-id="' + s.id + '">' + esc(t('scanner.delete')) + '</button>';
    }
    html += '</div>';
    html += '<form id="scanner-edit" class="form-grid">';
    if (can('scanners.edit') || can('scanners.identity')) {
      if (can('scanners.identity')) {
        html += field('brand', t('scanner.brand'), 'text', s.brand, false);
        html += field('model', t('scanner.model'), 'text', s.model, false);
        html += field('serial_number', t('scanner.serial'), 'text', s.serial_number, false);
        html += field('phone_number', t('scanner.phone'), 'text', s.phone_number, false);
      }
      if (can('scanners.edit')) {
        html += '<div class="field wide"><label>' + esc(t('common.notes')) + '</label><textarea name="notes">' + esc(s.notes || '') + '</textarea></div>';
      }
      html += '<div class="wide"><button class="btn" type="submit">' + esc(t('common.save')) + '</button></div>';
    }
    html += '</form>';
    if (can('scanners.assign')) {
      html += '<form id="assign-form" class="form-grid"><div class="field"><label>' + esc(t('scanner.driver')) + '</label><select name="driver_id">' + driverSelect(s.current_driver_id) + '</select></div>' +
        field('handover_date', t('scanner.handover_date'), 'date', s.handover_date || '', false) +
        '<div class="field wide"><label>' + esc(t('common.notes')) + '</label><input name="notes"></div>' +
        '<div class="wide"><button class="btn" type="submit">' + esc(t('scanner.reassign')) + '</button></div></form>';
    }
    if (can('scanners.status')) {
      html += '<form id="status-form" class="form-grid"><div class="field"><label>' + esc(t('common.status')) + '</label><select name="status">' +
        (A.statuses || []).map(function (st) { return '<option value="' + st + '"' + (s.status === st ? ' selected' : '') + '>' + esc(statusLabel(st)) + '</option>'; }).join('') +
        '</select></div><div class="field"><label>' + esc(t('common.notes')) + '</label><input name="notes"></div>' +
        '<div class="wide"><button class="btn" type="submit">' + esc(t('scanner.change_status')) + '</button></div></form>';
    }
    return html;
  }

  function bindScannerActions(s) {
    var edit = document.getElementById('scanner-edit');
    if (edit) {
      edit.onsubmit = function (e) {
        e.preventDefault();
        var fd = new FormData(edit);
        var body = { notes: fd.get('notes') };
        if (can('scanners.identity')) {
          body.brand = fd.get('brand');
          body.model = fd.get('model');
          body.serial_number = fd.get('serial_number');
          body.phone_number = fd.get('phone_number');
        }
        api('scanners/' + s.id, { method: 'POST', body: body })
          .then(function () { renderScannerDetail(s.id); }).catch(showError);
      };
    }
    var assign = document.getElementById('assign-form');
    if (assign) {
      assign.onsubmit = function (e) {
        e.preventDefault();
        var fd = new FormData(assign);
        api('scanners/' + s.id + '/assign', { method: 'POST', body: { driver_id: fd.get('driver_id'), handover_date: fd.get('handover_date'), notes: fd.get('notes') } })
          .then(function () { renderScannerDetail(s.id); }).catch(showError);
      };
    }
      var status = document.getElementById('status-form');
      if (status) {
        status.onsubmit = function (e) {
          e.preventDefault();
          var fd = new FormData(status);
          api('scanners/' + s.id + '/status', { method: 'POST', body: { status: fd.get('status'), notes: fd.get('notes') } })
            .then(function () { renderScannerDetail(s.id); }).catch(showError);
        };
      }
    }

  function renderDrivers(query) {
    query = query || {};
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    api('drivers?' + qs({ q: query.q || '', status: query.status || '', page: query.page || 1 })).then(function (data) {
      var rows = (data.items || []).map(function (d) {
        return '<tr class="click" data-go="/drivers/' + d.id + '"><td class="person-cell">' + face(d.photo_url) + ' ' + esc(d.name) + '</td><td>' + esc(d.phone) + '</td><td>' + esc(d.email) + '</td><td>' + esc(d.employee_code) + '</td><td>' + badge(d.status) + '</td></tr>';
      }).join('') || emptyRow(5);
      root.innerHTML = '<div class="page-head"><h1>' + esc(t('driver.title')) + '</h1>' +
        (can('drivers.create') ? '<button class="btn" data-go="/drivers/new">' + esc(t('driver.new')) + '</button>' : '') + '</div>' +
        '<div class="toolbar"><div class="field grow"><label>' + esc(t('common.search')) + '</label><input id="d-q" value="' + esc(query.q || '') + '"></div>' +
        '<div class="field"><label>' + esc(t('common.status')) + '</label><select id="d-status"><option value="">' + esc(t('common.all')) + '</option><option value="active"' + (query.status === 'active' ? ' selected' : '') + '>' + esc(t('common.active')) + '</option><option value="inactive"' + (query.status === 'inactive' ? ' selected' : '') + '>' + esc(t('common.inactive')) + '</option></select></div>' +
        '<button class="btn" id="d-apply">' + esc(t('common.filter')) + '</button></div>' +
        '<div class="card"><table class="data"><thead><tr><th>' + esc(t('users.name')) + '</th><th>' + esc(t('driver.phone')) + '</th><th>' + esc(t('driver.email')) + '</th><th>' + esc(t('driver.employee_code')) + '</th><th>' + esc(t('common.status')) + '</th></tr></thead><tbody>' + rows + '</tbody></table>' + pager(data) + '</div>';
      document.getElementById('d-apply').onclick = function () {
        renderDrivers({ q: document.getElementById('d-q').value, status: document.getElementById('d-status').value });
      };
      bindPager(function (page) { renderDrivers(Object.assign({}, query, { page: page })); });
    }).catch(showError);
  }

  function renderDriverForm() {
    root.innerHTML = '<div class="page-head"><h1>' + esc(t('driver.new')) + '</h1><button class="btn btn-sec" data-go="/drivers">' + esc(t('common.back')) + '</button></div>' +
      '<form id="driver-form" class="card form-grid">' +
      field('first_name', t('driver.first_name'), 'text', '') +
      field('last_name', t('driver.last_name'), 'text', '') +
      field('phone', t('driver.phone'), 'text', '') +
      field('email', t('driver.email'), 'email', '') +
      field('employee_code', t('driver.employee_code'), 'text', '') +
      photoField('') +
      '<div class="field wide"><label>' + esc(t('common.notes')) + '</label><textarea name="notes"></textarea></div>' +
      '<div class="wide"><button class="btn" type="submit">' + esc(t('common.create')) + '</button></div></form>';
    document.getElementById('driver-form').onsubmit = function (e) {
      e.preventDefault();
      var body = {};
      new FormData(e.target).forEach(function (v, k) { if (k !== 'photo') body[k] = v; });
      api('drivers', { method: 'POST', body: body }).then(function (d) {
        return maybeUpload('drivers/' + d.id + '/photo', e.target).then(function () { go('/drivers/' + d.id); });
      }).catch(showError);
    };
  }

  function renderDriverDetail(id) {
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    api('drivers/' + id).then(function (d) {
      var assigned = (d.assigned_scanners || []).map(function (s) {
        return '<tr class="click" data-go="/scanners/' + s.id + '"><td>' + esc(s.scanner_code) + '</td><td>' + esc(s.serial_number) + '</td><td>' + badge(s.status) + '</td></tr>';
      }).join('') || emptyRow(3);
      var history = (d.history || []).map(function (h) {
        return '<li>' + esc(h.at_display) + ' — ' + esc(h.scanner_code) + ' / ' + esc(h.serial_number) + ' (' + esc(h.action) + ')</li>';
      }).join('') || '<li>' + esc(t('common.empty')) + '</li>';
      root.innerHTML = '<div class="page-head"><h1>' + esc(d.name) + '</h1><button class="btn btn-sec" data-go="/drivers">' + esc(t('common.back')) + '</button></div>' +
        '<div class="detail"><div class="card"><h2>' + esc(t('driver.detail')) + '</h2>' +
        '<div class="person-row">' + face(d.photo_url, 'face-lg') + '<div><strong>' + esc(d.name) + '</strong><br>' + esc(d.phone || '—') + '</div></div>' +
        (can('drivers.edit') ? '<form id="driver-edit" class="form-grid">' +
          field('first_name', t('driver.first_name'), 'text', d.first_name) +
          field('last_name', t('driver.last_name'), 'text', d.last_name) +
          field('phone', t('driver.phone'), 'text', d.phone) +
          field('email', t('driver.email'), 'email', d.email) +
          field('employee_code', t('driver.employee_code'), 'text', d.employee_code) +
          photoField(d.photo_url) +
          '<div class="field wide"><label>' + esc(t('common.notes')) + '</label><textarea name="notes">' + esc(d.notes || '') + '</textarea></div>' +
          '<div class="wide"><button class="btn" type="submit">' + esc(t('common.save')) + '</button>' +
          (can('drivers.deactivate') ? ' <button type="button" class="btn btn-danger" id="toggle-driver">' + esc(d.status === 'active' ? t('driver.deactivate') : t('driver.activate')) + '</button>' : '') +
          '</div></form>' : '<div class="kv">' + kv(t('driver.phone'), d.phone) + kv(t('driver.email'), d.email) + '</div>') +
        '</div><div class="card"><h2>' + esc(t('driver.assigned')) + '</h2><table class="data"><thead><tr><th>' + esc(t('scanner.code')) + '</th><th>' + esc(t('scanner.serial')) + '</th><th>' + esc(t('common.status')) + '</th></tr></thead><tbody>' + assigned + '</tbody></table></div></div>' +
        '<div class="card" style="margin-top:12px"><h2>' + esc(t('driver.history')) + '</h2><ul class="history">' + history + '</ul></div>';
      var form = document.getElementById('driver-edit');
      if (form) {
        form.onsubmit = function (e) {
          e.preventDefault();
          var body = {};
          new FormData(form).forEach(function (v, k) { if (k !== 'photo') body[k] = v; });
          api('drivers/' + d.id, { method: 'POST', body: body }).then(function () {
            return maybeUpload('drivers/' + d.id + '/photo', form);
          }).then(function () { renderDriverDetail(d.id); }).catch(showError);
        };
      }
      var tog = document.getElementById('toggle-driver');
      if (tog) {
        tog.onclick = function () {
          api('drivers/' + d.id, { method: 'POST', body: { status: d.status === 'active' ? 'inactive' : 'active' } })
            .then(function () { renderDriverDetail(d.id); }).catch(showError);
        };
      }
    }).catch(showError);
  }

  function renderAudit(query) {
    query = query || {};
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    api('audit?' + qs({ q: query.q || '', page: query.page || 1 })).then(function (data) {
      var rows = (data.items || []).map(function (a) {
        return '<tr><td>' + esc(a.created_at_display) + '</td><td>' + esc(a.actor_name) + '</td><td>' + esc(a.action) + '</td><td>' + esc(a.field) + '</td><td>' + esc(a.old_value) + '</td><td>' + esc(a.new_value) + '</td><td>' + esc(a.scanner_id || '') + '</td><td>' + esc(a.driver_id || '') + '</td></tr>';
      }).join('') || emptyRow(8);
      root.innerHTML = '<div class="page-head"><h1>' + esc(t('audit.title')) + '</h1></div><p class="hint">' + esc(t('audit.immutable_note')) + '</p>' +
        '<div class="toolbar"><div class="field grow"><input id="a-q" value="' + esc(query.q || '') + '"><button class="btn" id="a-apply">' + esc(t('common.search')) + '</button></div></div>' +
        '<div class="card"><table class="data"><thead><tr><th>' + esc(t('common.date')) + '</th><th>' + esc(t('audit.actor')) + '</th><th>' + esc(t('common.action')) + '</th><th>' + esc(t('audit.field')) + '</th><th>' + esc(t('audit.old')) + '</th><th>' + esc(t('audit.new')) + '</th><th>' + esc(t('scanner.code')) + '</th><th>' + esc(t('scanner.driver')) + '</th></tr></thead><tbody>' + rows + '</tbody></table>' + pager(data) + '</div>';
      document.getElementById('a-apply').onclick = function () { renderAudit({ q: document.getElementById('a-q').value }); };
      bindPager(function (page) { renderAudit(Object.assign({}, query, { page: page })); });
    }).catch(showError);
  }

  function isPrimary() {
    return !!(user.is_primary || A.is_primary);
  }
  function roleCards(selected) {
    var roles = A.assignable_roles || A.roles || [];
    return '<div class="wide"><label>' + esc(t('users.role')) + '</label><p class="hint">' + esc(t('users.role_hint')) + '</p><div class="role-pick">' +
      roles.map(function (r) {
        return '<label class="role-card"><input type="radio" name="role" value="' + r + '"' + (selected === r ? ' checked' : '') + '>' +
          '<span><strong>' + esc(t('role.' + r)) + '</strong><small>' + esc(t('role.hint.' + r)) + '</small></span></label>';
      }).join('') + '</div></div>';
  }
  function extraPermBoxes(selected) {
    if (!A.can_assign_permissions) return '';
    selected = selected || {};
    var keys = A.extra_permission_keys || ['scanners.identity'];
    return '<details class="wide extras"><summary>' + esc(t('users.extras')) + '</summary><p class="hint">' + esc(t('users.extras_hint')) + '</p>' +
      keys.map(function (key) {
        return '<label class="row-check"><input type="checkbox" name="perm_' + key + '" value="1"' + (selected[key] ? ' checked' : '') + '><span>' + esc(t('perm.' + key)) + '</span></label>';
      }).join('') + '</details>';
  }
  function collectUserBody(form, includeExtras) {
    var body = {};
    var perms = {};
    new FormData(form).forEach(function (v, k) {
      if (k === 'photo') return;
      if (k.indexOf('perm_') === 0) {
        perms[k.slice(5)] = true;
      } else if (k === 'create_as_employee') {
        body[k] = true;
      } else {
        body[k] = v;
      }
    });
    if (includeExtras && A.can_assign_permissions) {
      body.permissions = perms;
    }
    return body;
  }
  function renderUsers() {
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    api('users').then(function (data) {
      var rows = (data.items || []).map(function (u) {
        var role = (u.is_primary ? t('users.primary') + ' · ' : '') + t('role.' + u.role);
        return '<tr class="click" data-go="/users/' + u.id + '"><td class="person-cell">' + face(u.photo_url) + ' ' + esc(u.name) + '</td><td>' + esc(u.username) + '</td><td>' + esc(u.email) + '</td><td>' + esc(role) + '</td><td>' + esc(t('users.status.' + (u.status || 'active'))) + '</td><td>' + esc(u.last_login_display || '—') + '</td></tr>';
      }).join('') || emptyRow(6);
      var form = can('users.manage') ? '<form id="user-form" class="card form-grid"><div class="wide"><h2>' + esc(t('users.new')) + '</h2></div>' +
        field('name', t('users.name'), 'text', '') +
        field('username', t('users.username'), 'text', '') +
        field('email', t('driver.email'), 'email', '') +
        field('phone', t('users.phone'), 'tel', '') +
        field('password', t('users.password'), 'password', '') +
        roleCards('staff') +
        photoField('') +
        '<label class="row-check wide"><input type="checkbox" name="create_as_employee" value="1" checked><span>' + esc(t('users.create_employee')) + '</span></label>' +
        '<div class="wide"><button class="btn" type="submit">' + esc(t('common.create')) + '</button></div></form>' : '';
      root.innerHTML = '<div class="page-head"><h1>' + esc(t('users.title')) + '</h1></div>' + form +
        '<div class="card"><table class="data"><thead><tr><th>' + esc(t('users.name')) + '</th><th>' + esc(t('users.username')) + '</th><th>' + esc(t('driver.email')) + '</th><th>' + esc(t('users.role')) + '</th><th>' + esc(t('users.status')) + '</th><th>' + esc(t('users.last_login')) + '</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
      var uf = document.getElementById('user-form');
      if (uf) {
        uf.onsubmit = function (e) {
          e.preventDefault();
          api('users', { method: 'POST', body: collectUserBody(uf, false) }).then(function (created) {
            return maybeUpload('users/' + created.id + '/photo', uf);
          }).then(function () { renderUsers(); }).catch(showError);
        };
      }
    }).catch(showError);
  }
  function renderUserDetail(id) {
    if (!can('users.manage') && !can('users.view')) {
      root.innerHTML = '<div class="msg msg-error">' + esc(t('error.forbidden')) + '</div>';
      return;
    }
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    api('users/' + id).then(function (u) {
      var locked = u.is_primary && !isPrimary();
      var canEdit = can('users.manage') && !locked;
      var form = canEdit ? '<form id="user-edit" class="card form-grid"><div class="wide"><h2>' + esc(t('users.edit')) + '</h2></div>' +
        '<div class="person-row">' + face(u.photo_url, 'face-lg') + '<div><strong>' + esc(u.name) + '</strong><br>' + esc((u.is_primary ? t('users.primary') + ' · ' : '') + t('role.' + u.role)) + '</div></div>' +
        field('name', t('users.name'), 'text', u.name) +
        field('email', t('driver.email'), 'email', u.email) +
        field('phone', t('users.phone'), 'tel', u.phone || '') +
        field('password', t('users.password'), 'password', '') +
        roleCards(u.role) +
        '<div class="field"><label>' + esc(t('users.status')) + '</label><select name="status"' + (u.is_primary ? ' disabled' : '') + '>' +
          '<option value="active"' + (u.status !== 'inactive' ? ' selected' : '') + '>' + esc(t('users.status.active')) + '</option>' +
          '<option value="inactive"' + (u.status === 'inactive' ? ' selected' : '') + '>' + esc(t('users.status.inactive')) + '</option>' +
        '</select></div>' +
        '<div class="field"><label>' + esc(t('users.last_login')) + '</label><input type="text" value="' + esc(u.last_login_display || '—') + '" readonly class="readonly"></div>' +
        photoField(u.photo_url) +
        '<label class="row-check wide"><input type="checkbox" name="create_as_employee" value="1"' + (u.driver_id ? ' checked' : '') + '><span>' + esc(t('users.create_employee')) + '</span></label>' +
        extraPermBoxes(u.permissions || {}) +
        '<div class="wide"><button class="btn" type="submit">' + esc(t('common.save')) + '</button></div></form>' :
        '<div class="card"><div class="person-row">' + face(u.photo_url, 'face-lg') + '<div><strong>' + esc(u.name) + '</strong></div></div><div class="kv">' + kv(t('users.name'), u.name) + kv(t('users.username'), u.username) + kv(t('driver.email'), u.email) + kv(t('users.phone'), u.phone || '—') + kv(t('users.role'), (u.is_primary ? t('users.primary') + ' · ' : '') + t('role.' + u.role)) + kv(t('users.status'), t('users.status.' + (u.status || 'active'))) + kv(t('users.last_login'), u.last_login_display || '—') + '</div></div>';
      root.innerHTML = '<div class="page-head"><h1>' + esc(u.name || u.username) + '</h1><button class="btn btn-sec" data-go="/users">' + esc(t('common.back')) + '</button></div>' + form;
      var uf = document.getElementById('user-edit');
      if (uf) {
        uf.onsubmit = function (e) {
          e.preventDefault();
          api('users/' + id, { method: 'POST', body: collectUserBody(uf, true) }).then(function () {
            return maybeUpload('users/' + id + '/photo', uf);
          }).then(function () { renderUserDetail(id); }).catch(showError);
        };
      }
    }).catch(showError);
  }

  function renderSettings() {
    root.innerHTML = '<p>' + esc(t('common.loading')) + '</p>';
    var needSettings = can('settings.view') || can('settings.manage');
    var needPerms = can('roles.manage');
    Promise.all([
      needSettings ? api('settings') : Promise.resolve(null),
      needPerms ? api('permissions') : Promise.resolve(null)
    ]).then(function (pack) {
      var s = pack[0] || {};
      var p = pack[1];
      var tabs = '<div class="settings-nav">' +
        (needSettings ? '<button type="button" class="active" data-tab="general">' + esc(t('settings.general')) + '</button>' : '') +
        (needPerms ? '<button type="button" data-tab="roles">' + esc(t('settings.roles')) + '</button>' : '') +
        '</div>';
      var ownerFields = isPrimary()
        ? field('company_name', t('settings.company_name'), 'text', s.company_name) + field('owner_name', t('settings.owner_name'), 'text', s.owner_name)
        : '<div class="field"><label>' + esc(t('settings.company_name')) + '</label><input type="text" value="' + esc(s.company_name || '') + '" readonly class="readonly"></div>' +
          '<div class="field"><label>' + esc(t('settings.owner_name')) + '</label><input type="text" value="' + esc(s.owner_name || '') + '" readonly class="readonly"></div>' +
          '<p class="hint wide">' + esc(t('settings.owner_locked')) + '</p>';
      var general = needSettings ? '<form id="settings-form" class="card form-grid">' +
        ownerFields +
        '<div class="field wide"><label>' + esc(t('official.website')) + '</label>' +
        '<a href="' + esc(A.official_url || 'https://www.albatros-express.at/') + '" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a></div>' +
        '<div class="field"><label>' + esc(t('settings.default_language')) + '</label><select name="default_language">' +
        (A.locales || []).map(function (l) { return '<option value="' + l + '"' + (s.default_language === l ? ' selected' : '') + '>' + esc(t('lang.' + l)) + '</option>'; }).join('') +
        '</select></div>' +
        field('timezone', t('settings.timezone'), 'text', s.timezone) +
        field('date_format', t('settings.date_format'), 'text', s.date_format) +
        field('items_per_page', t('settings.items_per_page'), 'number', s.items_per_page) +
        field('min_password_length', t('settings.min_password_length'), 'number', s.min_password_length) +
        field('remember_days', t('settings.remember_days'), 'number', s.remember_days) +
        field('audit_retention_days', t('settings.audit_retention_days'), 'number', s.audit_retention_days) +
        '<div class="wide"><h2>' + esc(t('settings.sms')) + '</h2><p class="hint">' + esc(t('settings.sms_hint')) + '</p></div>' +
        field('twilio_sid', t('settings.twilio_sid'), 'text', s.twilio_sid || '') +
        field('twilio_token', t('settings.twilio_token'), 'password', s.twilio_token || '') +
        field('twilio_from', t('settings.twilio_from'), 'text', s.twilio_from || '') +
        (can('settings.manage') ? '<div class="wide"><button class="btn" type="submit">' + esc(t('common.save')) + '</button></div>' : '') +
        '</form>' : '';
      var roles = '';
      if (p) {
        roles = '<form id="perm-form" class="card" hidden><table class="data perm-table"><thead><tr><th></th>' +
          p.roles.map(function (r) { return '<th>' + esc(t('role.' + r)) + '</th>'; }).join('') + '</tr></thead><tbody>' +
          p.keys.map(function (key) {
            return '<tr><td>' + esc(t('perm.' + key)) + '</td>' + p.roles.map(function (r) {
              var privileged = (A.extra_permission_keys || []).indexOf(key) !== -1 && key !== 'audit.view';
              var locked = r === 'super_admin' || (privileged && r !== 'super_admin');
              return '<td><input type="checkbox" data-role="' + r + '" data-key="' + key + '"' + (p.map[r][key] ? ' checked' : '') + (locked ? ' disabled' : '') + '></td>';
            }).join('') + '</tr>';
          }).join('') + '</tbody></table><div style="padding:12px"><button class="btn" type="submit">' + esc(t('common.save')) + '</button></div></form>';
      }
      root.innerHTML = '<div class="page-head"><h1>' + esc(t('settings.title')) + '</h1></div>' + tabs + '<div id="tab-general">' + general + '</div><div id="tab-roles">' + roles + '</div>';
      document.querySelectorAll('.settings-nav button').forEach(function (btn) {
        btn.onclick = function () {
          document.querySelectorAll('.settings-nav button').forEach(function (b) { b.classList.remove('active'); });
          btn.classList.add('active');
          var generalEl = document.getElementById('settings-form');
          var permEl = document.getElementById('perm-form');
          if (generalEl) generalEl.hidden = btn.getAttribute('data-tab') !== 'general';
          if (permEl) permEl.hidden = btn.getAttribute('data-tab') !== 'roles';
        };
      });
      var sf = document.getElementById('settings-form');
      if (sf && can('settings.manage')) {
        sf.onsubmit = function (e) {
          e.preventDefault();
          var body = {};
          new FormData(sf).forEach(function (v, k) { body[k] = v; });
          if (!isPrimary()) {
            delete body.company_name;
            delete body.owner_name;
          }
          api('settings', { method: 'POST', body: body }).then(function () { root.insertAdjacentHTML('afterbegin', '<div class="msg msg-ok">' + esc(t('settings.saved')) + '</div>'); }).catch(showError);
        };
      }
      var pf = document.getElementById('perm-form');
      if (pf) {
        pf.onsubmit = function (e) {
          e.preventDefault();
          var map = {};
          pf.querySelectorAll('input[type=checkbox]').forEach(function (box) {
            var role = box.getAttribute('data-role');
            var key = box.getAttribute('data-key');
            map[role] = map[role] || {};
            map[role][key] = box.checked || role === 'super_admin';
          });
          api('permissions', { method: 'POST', body: { map: map } }).then(function () { root.insertAdjacentHTML('afterbegin', '<div class="msg msg-ok">' + esc(t('settings.saved')) + '</div>'); }).catch(showError);
        };
      }
    }).catch(showError);
  }

  function renderReports() {
    var types = ['scanners', 'drivers', 'handovers', 'lost'];
    root.innerHTML = '<div class="page-head"><h1>' + esc(t('reports.title')) + '</h1></div><div class="card"><table class="data"><thead><tr><th></th><th>CSV</th><th>Excel</th><th>PDF</th></tr></thead><tbody>' +
      types.map(function (type) {
        var base = A.rest + 'export/' + type + '?_wpnonce=' + encodeURIComponent(A.nonce);
        return '<tr><td>' + esc(t('reports.' + type)) + '</td>' +
          '<td><a href="' + base + '&format=csv">CSV</a></td>' +
          '<td><a href="' + base + '&format=xlsx">Excel</a></td>' +
          '<td><a href="' + base + '&format=pdf" target="_blank">' + esc(t('reports.pdf')) + '</a></td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function bindPager(fn) {
    root.querySelectorAll('[data-page]').forEach(function (a) {
      a.onclick = function (e) {
        e.preventDefault();
        fn(parseInt(a.getAttribute('data-page'), 10));
      };
    });
  }

  function showError(err) {
    root.innerHTML = '<div class="msg msg-error">' + esc(err.message || t('common.error')) + '</div>' + root.innerHTML;
  }

  function render() {
    header();
    renderNav();
    var route = parseRoute();
    var p = route.parts;
    if (p.length === 0) { setPageTitle(t('dash.title')); return renderDashboard(); }
    if (p[0] === 'scanners' && p[1] === 'new') { setPageTitle(t('scanner.new')); return renderScannerForm(); }
    if (p[0] === 'scanners' && p[1]) { setPageTitle(t('scanner.detail')); return renderScannerDetail(p[1]); }
    if (p[0] === 'scanners') { setPageTitle(t('scanner.title')); return renderScanners(Object.fromEntries(new URLSearchParams(window.location.search))); }
    if (p[0] === 'drivers' && p[1] === 'new') { setPageTitle(t('driver.new')); return renderDriverForm(); }
    if (p[0] === 'drivers' && p[1]) { setPageTitle(t('driver.detail')); return renderDriverDetail(p[1]); }
    if (p[0] === 'drivers') { setPageTitle(t('driver.title')); return renderDrivers({}); }
    if (p[0] === 'audit') { setPageTitle(t('audit.title')); return renderAudit({}); }
    if (p[0] === 'users' && p[1]) { setPageTitle(t('users.edit')); return renderUserDetail(p[1]); }
    if (p[0] === 'users') { setPageTitle(t('users.title')); return renderUsers(); }
    if (p[0] === 'settings') { setPageTitle(t('settings.title')); return renderSettings(); }
    if (p[0] === 'reports') { setPageTitle(t('reports.title')); return renderReports(); }
    if (p[0] === 'help') { setPageTitle(t('help.title')); return renderHelp(); }
    setPageTitle(t('dash.title'));
    renderDashboard();
  }

  function helpBlock(titleKey, bodyKey) {
    return '<section class="help-block"><h2>' + esc(t(titleKey)) + '</h2><p>' + esc(t(bodyKey)) + '</p></section>';
  }
  function renderHelp() {
    var aboutUrl = A.developer_url || 'https://paxdesign.at/';
    root.innerHTML = '<div class="page-head"><h1>' + esc(t('help.title')) + '</h1></div>' +
      '<div class="card">' +
      helpBlock('help.using', 'help.using_body') +
      helpBlock('help.scanners', 'help.scanners_body') +
      helpBlock('help.handover', 'help.handover_body') +
      helpBlock('help.qr', 'help.qr_body') +
      helpBlock('help.drivers', 'help.drivers_body') +
      helpBlock('help.permissions', 'help.permissions_body') +
      helpBlock('help.reports', 'help.reports_body') +
      '<section class="help-block"><h2>' + esc(t('help.faq')) + '</h2><dl class="help-faq">' +
        '<dt>' + esc(t('help.faq_q1')) + '</dt><dd>' + esc(t('help.faq_a1')) + '</dd>' +
        '<dt>' + esc(t('help.faq_q2')) + '</dt><dd>' + esc(t('help.faq_a2')) + '</dd>' +
      '</dl></section>' +
      helpBlock('help.support', 'help.support_body') +
      '<section class="help-block about-block"><h2>' + esc(t('about.title')) + '</h2>' +
        '<div class="kv">' +
          kv(t('about.developer'), A.developer_name || 'Ahmad Al Khalaf') +
          kv(t('about.role'), A.developer_role || 'IT / Software Development') +
          '<div class="k">' + esc(t('about.website')) + '</div><div><a href="' + esc(aboutUrl) + '" target="_blank" rel="noopener noreferrer">paxdesign.at</a></div>' +
        '</div>' +
        '<p class="hint">' + esc(t('about.note')) + '</p>' +
      '</section>' +
      '</div>';
  }

  document.addEventListener('click', function (e) {
    var actEl = e.target.closest('[data-act]');
    if (actEl && !e.target.closest('#scanner-edit, #assign-form, #status-form')) {
      e.preventDefault();
      e.stopPropagation();
      var id = actEl.getAttribute('data-id');
      var act = actEl.getAttribute('data-act');
      var extra = {};
      if (act === 'take_over') {
        var sel = document.querySelector('#assign-form select[name="driver_id"]');
        extra.driver_id = sel ? sel.value : '';
        if (!extra.driver_id) {
          showError(new Error(t('driver.error.not_found')));
          return;
        }
      }
      runScannerAction(act, id, extra).then(function () {
        if (path() === '/' || path() === '') {
          renderDashboard();
        } else if (path().indexOf('/scanners/') === 0) {
          renderScannerDetail(id);
        } else {
          renderScanners(Object.fromEntries(new URLSearchParams(window.location.search)));
        }
      }).catch(showError);
      return;
    }
    if (e.target.closest('[data-stop]')) {
      if (!e.target.closest('[data-go]')) {
        e.stopPropagation();
      }
    }
    var goEl = e.target.closest('[data-go]');
    if (goEl) {
      e.preventDefault();
      go(goEl.getAttribute('data-go'));
      return;
    }
    var a = e.target.closest('a');
    if (a && a.getAttribute('href') && a.getAttribute('href').charAt(0) === '/' && !a.target) {
      e.preventDefault();
      go(a.getAttribute('href'));
    }
  });
  window.addEventListener('popstate', render);
  document.getElementById('logout-btn').onclick = function () {
    api('auth/logout', { method: 'POST' }).finally(function () { window.location.href = '/login'; });
  };
  document.getElementById('lang-switch').onchange = function () {
    api('me', { method: 'POST', body: { locale: this.value } }).then(function () { window.location.reload(); });
  };
  document.getElementById('global-search').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      go('/scanners');
      renderScanners({ q: e.target.value });
    }
  });

  api('bootstrap').then(function (boot) {
    if (boot.user) user = boot.user;
    if (boot.i18n) i18n = boot.i18n;
    A.locale = boot.locale || A.locale;
    if (boot.assignable_roles) A.assignable_roles = boot.assignable_roles;
    if (typeof boot.can_assign_permissions !== 'undefined') A.can_assign_permissions = boot.can_assign_permissions;
    if (boot.extra_permission_keys) A.extra_permission_keys = boot.extra_permission_keys;
    if (typeof boot.is_primary !== 'undefined') A.is_primary = boot.is_primary;
    if (boot.permission_keys) A.permission_keys = boot.permission_keys;
    state.drivers = boot.driver_options || [];
    render();
  }).catch(function () {
    render();
  });
})();
