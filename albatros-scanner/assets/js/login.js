(function () {
  var form = document.getElementById('login-form');
  var resetForm = document.getElementById('reset-form');
  var toggle = document.getElementById('toggle-reset');
  var i18n = (window.ALB_LOGIN && window.ALB_LOGIN.i18n) || {};

  document.querySelectorAll('[data-locale]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.cookie = 'alb_locale=' + btn.getAttribute('data-locale') + ';path=/;max-age=31536000';
      window.location.reload();
    });
  });

  if (!toggle || !form || !resetForm) {
    return;
  }

  toggle.addEventListener('click', function () {
    var showReset = resetForm.hidden;
    resetForm.hidden = !showReset;
    form.hidden = showReset;
    toggle.textContent = showReset ? (i18n['login.reset_back'] || '') : (i18n['login.forgot'] || '');
  });
})();
