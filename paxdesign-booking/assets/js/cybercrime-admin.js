(function () {
  'use strict';

  var cfg = window.paxCybercrimeAdmin || {};
  var i18n = cfg.i18n || {};
  var statusClasses = cfg.statusClasses || {};
  var workflowOrder = ['submitted', 'in_review', 'waiting_for_customer', 'resolved', 'closed'];
  var REQUEST_TIMEOUT_MS = 45000;

  var actionsRoot = document.getElementById('pax-cc-ticket-actions');
  if (!actionsRoot || !cfg.ajaxUrl || !cfg.nonce) {
    return;
  }

  var referenceId = actionsRoot.getAttribute('data-reference') || '';
  var statusSelect = document.getElementById('pax-cc-status');
  var statusFeedback = document.getElementById('pax-cc-status-feedback');
  var replyForm = document.getElementById('pax-cc-reply-form');
  var replyFeedback = document.getElementById('pax-cc-reply-feedback');
  var internalNoteForm = document.getElementById('pax-cc-internal-note-form');
  var internalNoteFeedback = document.getElementById('pax-cc-internal-note-feedback');
  var timelineEl = document.getElementById('pax-cc-admin-timeline');
  var statusBadge = document.getElementById('pax-cc-admin-status-badge');
  var workflowEl = document.getElementById('pax-cc-admin-workflow');

  var lastSavedStatus = statusSelect ? statusSelect.value : '';
  var statusSaveTimer = null;

  function text(key, fallback) {
    return i18n[key] || fallback || '';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setFeedback(el, message, type) {
    if (!el) {
      return;
    }
    el.textContent = message || '';
    el.classList.remove('is-success', 'is-error', 'is-saving');
    if (type) {
      el.classList.add('is-' + type);
    }
  }

  function setSubmitEnabled(submitBtn, enabled) {
    if (submitBtn) {
      submitBtn.disabled = !enabled;
    }
  }

  function parseActionResponse(response) {
    return response.text().then(function (body) {
      var data = null;
      var trimmed = (body || '').trim();

      if (trimmed === '-1' || trimmed === '0') {
        throw new Error(text('error', 'Something went wrong.'));
      }

      try {
        data = trimmed ? JSON.parse(trimmed) : null;
      } catch (error) {
        throw new Error(text('error', 'Something went wrong.'));
      }

      if (!response.ok || !data || data.success !== true) {
        var message = (data && data.data && data.data.message)
          ? data.data.message
          : text('error', 'Something went wrong.');
        throw new Error(message);
      }

      return data.data || {};
    });
  }

  function postAction(action, payload) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce);
    body.set('reference_id', referenceId);
    Object.keys(payload || {}).forEach(function (key) {
      body.set(key, payload[key]);
    });

    var fetchOptions = {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString()
    };

    var request;
    if (typeof AbortController === 'function') {
      var controller = new AbortController();
      var timeoutId = window.setTimeout(function () {
        controller.abort();
      }, REQUEST_TIMEOUT_MS);
      fetchOptions.signal = controller.signal;
      request = fetch(cfg.ajaxUrl, fetchOptions).then(function (response) {
        window.clearTimeout(timeoutId);
        return parseActionResponse(response);
      }, function (error) {
        window.clearTimeout(timeoutId);
        if (error && error.name === 'AbortError') {
          throw new Error(text('error', 'Something went wrong.'));
        }
        throw error;
      });
    } else {
      request = fetch(cfg.ajaxUrl, fetchOptions).then(parseActionResponse);
    }

    return request;
  }

  function updateWorkflow(currentStatus) {
    if (!workflowEl) {
      return;
    }
    var currentIndex = workflowOrder.indexOf(currentStatus);
    if (currentIndex < 0) {
      currentIndex = 0;
    }
    workflowEl.querySelectorAll('.pax-cc-workflow__step').forEach(function (step, index) {
      step.classList.remove('is-current', 'is-done');
      if (index === currentIndex) {
        step.classList.add('is-current');
      } else if (index < currentIndex) {
        step.classList.add('is-done');
      }
    });
  }

  function updateStatusBadge(report) {
    if (!statusBadge || !report) {
      return;
    }
    var status = report.status || '';
    statusBadge.textContent = report.status_label || status;
    statusBadge.className = 'pax-cc-status ' + (statusClasses[status] || 'pax-cc-status--submitted');
  }

  function renderTimelineItem(entry) {
    var author = escapeHtml(entry.author_type || '');
    var channel = escapeHtml(entry.channel || '');
    var createdAt = escapeHtml(entry.created_at || '');
    var body = escapeHtml(entry.body || '').replace(/\n/g, '<br>');
    var meta = entry.meta && typeof entry.meta === 'object' ? entry.meta : {};
    var isInternal = !!meta.internal_only;
    var itemClass = 'pax-cc-timeline__item' + (isInternal ? ' pax-cc-timeline__item--internal' : '');
    var internalTag = isInternal
      ? ' <span class="pax-cc-timeline__internal-tag">' + escapeHtml(text('internal', 'internal')) + '</span>'
      : '';

    return '<li class="' + itemClass + '">'
      + '<p class="pax-cc-timeline__meta"><strong>' + author + '</strong> · ' + channel + ' · ' + createdAt + internalTag + '</p>'
      + '<div class="pax-cc-timeline__body">' + body + '</div>'
      + '</li>';
  }

  function renderTimeline(timeline) {
    if (!timelineEl) {
      return;
    }
    var entries = Array.isArray(timeline) ? timeline : [];
    if (!entries.length) {
      timelineEl.innerHTML = '<li class="pax-cc-timeline__item">' + escapeHtml(text('noTimeline', 'No timeline entries yet.')) + '</li>';
      return;
    }
    timelineEl.innerHTML = entries.map(renderTimelineItem).join('');
  }

  function applyReport(report) {
    if (!report) {
      return;
    }
    updateStatusBadge(report);
    updateWorkflow(report.status || '');
    renderTimeline(report.timeline || []);
    if (statusSelect && report.status) {
      statusSelect.value = report.status;
      lastSavedStatus = report.status;
    }
  }

  function saveStatus(status) {
    if (!status || status === lastSavedStatus) {
      return Promise.resolve();
    }

    setFeedback(statusFeedback, text('saving', 'Saving…'), 'saving');

    return postAction('paxdesign_cybercrime_admin_status', { status: status })
      .then(function (data) {
        applyReport(data.report);
        setFeedback(statusFeedback, data.message || text('statusSaved', 'Status saved.'), 'success');
        window.setTimeout(function () {
          if (statusFeedback && statusFeedback.textContent === (data.message || text('statusSaved', 'Status saved.'))) {
            setFeedback(statusFeedback, '', '');
          }
        }, 2500);
      })
      .catch(function (error) {
        if (statusSelect) {
          statusSelect.value = lastSavedStatus;
        }
        setFeedback(statusFeedback, error.message || text('error', 'Something went wrong.'), 'error');
      });
  }

  if (statusSelect) {
    statusSelect.addEventListener('change', function () {
      var nextStatus = statusSelect.value;
      if (statusSaveTimer) {
        window.clearTimeout(statusSaveTimer);
      }
      statusSaveTimer = window.setTimeout(function () {
        saveStatus(nextStatus);
      }, 150);
    });
  }

  if (replyForm) {
    replyForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var messageEl = document.getElementById('pax-cc-staff-message');
      var statusEl = document.getElementById('pax-cc-reply-status');
      var submitBtn = document.getElementById('pax-cc-reply-submit');
      var message = messageEl ? messageEl.value.trim() : '';
      if (!message) {
        return;
      }

      setSubmitEnabled(submitBtn, false);
      setFeedback(replyFeedback, text('sending', 'Sending…'), 'saving');

      postAction('paxdesign_cybercrime_admin_reply', {
        message: message,
        status: statusEl ? statusEl.value : 'waiting_for_customer'
      })
        .then(function (data) {
          applyReport(data.report);
          if (messageEl) {
            messageEl.value = '';
          }
          setFeedback(replyFeedback, data.message || text('replySent', 'Reply sent to customer.'), 'success');
        })
        .catch(function (error) {
          setFeedback(replyFeedback, error.message || text('error', 'Something went wrong.'), 'error');
        })
        .then(function () {
          setSubmitEnabled(submitBtn, true);
        });
    });
  }

  if (internalNoteForm) {
    internalNoteForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var noteEl = document.getElementById('pax-cc-internal-note');
      var submitBtn = document.getElementById('pax-cc-internal-note-submit');
      var message = noteEl ? noteEl.value.trim() : '';
      if (!message) {
        return;
      }

      setSubmitEnabled(submitBtn, false);
      setFeedback(internalNoteFeedback, text('addingNote', 'Adding note…'), 'saving');

      postAction('paxdesign_cybercrime_admin_internal_note', { message: message })
        .then(function (data) {
          applyReport(data.report);
          if (noteEl) {
            noteEl.value = '';
          }
          setFeedback(internalNoteFeedback, data.message || text('noteAdded', 'Internal note added.'), 'success');
        })
        .catch(function (error) {
          setFeedback(internalNoteFeedback, error.message || text('error', 'Something went wrong.'), 'error');
        })
        .then(function () {
          setSubmitEnabled(submitBtn, true);
        });
    });
  }
})();
