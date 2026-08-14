/**
 * PAXdesign Karriere — secure application form submission.
 */
(function () {
  'use strict';

  var root = document.getElementById('pax-karriere');
  var form = document.getElementById('pax-karriere-form');
  var config = window.paxKarriereIntake || {};

  if (!root || !form) {
    return;
  }

  var submitBtn = document.getElementById('pax-karriere-submit');
  var errorEl = document.getElementById('pax-karriere-form-error');
  var successEl = document.getElementById('pax-karriere-success');
  var refEl = document.getElementById('pax-karriere-ref');
  var maxCvMb = config.maxCvMb || 5;
  var maxOptionalMb = config.maxOptionalMb || 5;
  var maxCertFiles = config.maxCertFiles || 5;

  function bytesToMb(bytes) {
    return bytes / 1048576;
  }

  function showError(msg) {
    if (!errorEl) {
      return;
    }
    errorEl.hidden = false;
    errorEl.textContent = msg;
  }

  function hideError() {
    if (errorEl) {
      errorEl.hidden = true;
      errorEl.textContent = '';
    }
  }

  function markInvalid(field) {
    var wrap = field.closest('.pax-karriere__field') || field.closest('.pax-karriere__check');
    if (wrap) {
      wrap.classList.add('is-invalid');
    }
  }

  function clearInvalid() {
    form.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
  }

  function fileLabel(input) {
    if (!input || !input.files || !input.files.length) {
      return '';
    }
    var names = [];
    for (var i = 0; i < input.files.length; i++) {
      names.push(input.files[i].name);
    }
    return names.join(', ');
  }

  function updateDropzone(input) {
    var zone = input.closest('.pax-karriere__dropzone');
    if (!zone) {
      return;
    }
    var nameEl = zone.querySelector('.pax-karriere__dropzone-name');
    var label = fileLabel(input);
    zone.classList.toggle('has-file', !!label);
    if (nameEl) {
      nameEl.textContent = label;
    }
  }

  function validatePdfFile(file, maxMb, required) {
    if (!file) {
      return required ? 'Bitte lade die erforderliche PDF-Datei hoch.' : '';
    }
    if (file.type && file.type !== 'application/pdf') {
      return 'Nur PDF-Dateien sind erlaubt.';
    }
    if (bytesToMb(file.size) > maxMb) {
      return 'Die Datei ist zu groß (max. ' + maxMb + ' MB).';
    }
    return '';
  }

  function validateForm() {
    clearInvalid();
    hideError();
    var valid = true;

    form.querySelectorAll('input, select, textarea').forEach(function (field) {
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

    var cvInput = form.querySelector('[name="cv"]');
    if (cvInput) {
      var cvErr = validatePdfFile(cvInput.files && cvInput.files[0], maxCvMb, true);
      if (cvErr) {
        valid = false;
        markInvalid(cvInput);
        showError(cvErr);
        return false;
      }
    }

    var coverInput = form.querySelector('[name="cover_letter"]');
    if (coverInput && coverInput.files && coverInput.files[0]) {
      var coverErr = validatePdfFile(coverInput.files[0], maxOptionalMb, false);
      if (coverErr) {
        valid = false;
        markInvalid(coverInput);
        showError(coverErr);
        return false;
      }
    }

    var certInput = form.querySelector('[name="certificates[]"]');
    if (certInput && certInput.files && certInput.files.length) {
      if (certInput.files.length > maxCertFiles) {
        valid = false;
        markInvalid(certInput);
        showError('Maximal ' + maxCertFiles + ' Zertifikatsdateien erlaubt.');
        return false;
      }
      for (var i = 0; i < certInput.files.length; i++) {
        var certErr = validatePdfFile(certInput.files[i], maxOptionalMb, false);
        if (certErr) {
          valid = false;
          markInvalid(certInput);
          showError(certErr);
          return false;
        }
      }
    }

    if (!valid) {
      var firstInvalid = form.querySelector('.is-invalid input, .is-invalid select, .is-invalid textarea');
      if (firstInvalid) {
        firstInvalid.focus();
      }
      showError('Bitte fülle alle Pflichtfelder aus.');
    }
    return valid;
  }

  function setLoading(loading) {
    if (!submitBtn) {
      return;
    }
    submitBtn.disabled = loading;
    submitBtn.classList.toggle('is-loading', loading);
  }

  function showSuccess(reference) {
    root.setAttribute('data-phase', 'success');
    if (successEl) {
      successEl.hidden = false;
    }
    if (refEl && reference) {
      refEl.textContent = reference;
    }
    window.scrollTo({ top: successEl ? successEl.offsetTop - 24 : 0, behavior: 'smooth' });
  }

  form.querySelectorAll('input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
      updateDropzone(input);
    });
  });

  form.querySelectorAll('.pax-karriere__dropzone').forEach(function (zone) {
    var input = zone.querySelector('input[type="file"]');
    if (!input) {
      return;
    }
    ['dragenter', 'dragover'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
      zone.addEventListener(evt, function (e) {
        e.preventDefault();
        zone.classList.remove('is-dragover');
      });
    });
    zone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        updateDropzone(input);
      }
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validateForm()) {
      return;
    }
    if (!config.ajaxUrl || !config.nonce) {
      showError('Konfigurationsfehler. Bitte lade die Seite neu.');
      return;
    }

    setLoading(true);
    hideError();

    var data = new FormData(form);
    data.append('action', config.action || 'paxdesign_career_application');
    data.append('nonce', config.nonce);

    fetch(config.ajaxUrl, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (json) {
          return { ok: res.ok, status: res.status, json: json };
        });
      })
      .then(function (result) {
        var json = result.json;
        if (!json || !json.success) {
          var msg = (json && json.data && json.data.message) || 'Übermittlung fehlgeschlagen. Bitte versuche es erneut.';
          throw new Error(msg);
        }
        showSuccess(json.data && json.data.reference ? json.data.reference : '');
      })
      .catch(function (err) {
        showError(err.message || 'Übermittlung fehlgeschlagen. Bitte versuche es erneut.');
        setLoading(false);
      });
  });
})();
