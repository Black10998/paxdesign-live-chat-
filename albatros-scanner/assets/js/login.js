(function () {
  var cfg = window.ALB_LOGIN || {};
  var i18n = cfg.i18n || {};
  var form = document.getElementById('login-form');
  var resetForm = document.getElementById('reset-form');
  var toggle = document.getElementById('toggle-reset');
  var msg = document.getElementById('login-msg');

  function t(key) {
    return i18n[key] || key;
  }

  function show(text, type) {
    msg.hidden = !text;
    msg.className = 'msg' + (type ? ' msg-' + type : '');
    msg.textContent = text || '';
  }

  document.querySelectorAll('[data-locale]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.cookie = 'alb_locale=' + btn.getAttribute('data-locale') + ';path=/;max-age=31536000';
      window.location.reload();
    });
  });

  toggle.addEventListener('click', function () {
    var reset = resetForm.hidden;
    resetForm.hidden = !reset;
    form.hidden = reset;
    toggle.textContent = reset ? t('login.reset_back') : t('login.forgot');
    show('', '');
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    show('', '');
    fetch(cfg.rest + 'auth/login', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify({
        login: form.login.value,
        password: form.password.value,
        remember: form.remember.checked
      })
    }).then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
      .then(function (res) {
        if (!res.ok) {
          show(res.data.message || t('login.error.invalid'), 'error');
          return;
        }
        window.location.href = cfg.next || '/';
      }).catch(function () {
        show(t('common.error'), 'error');
      });
  });

  resetForm.addEventListener('submit', function (e) {
    e.preventDefault();
    fetch(cfg.rest + 'auth/reset', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify({ login: resetForm.login.value })
    }).then(function (r) { return r.json(); })
      .then(function (data) {
        show(data.message || t('login.reset_sent'), 'ok');
      }).catch(function () {
        show(t('common.error'), 'error');
      });
  });
})();
