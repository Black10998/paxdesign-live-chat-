(function () {
  'use strict';

  var cfg = window.paxCybercrimeAdmin || {};
  if (!cfg.ajaxUrl || !cfg.nonce) {
    return;
  }

  var i18n = cfg.i18n || {};
  var statusClasses = cfg.statusClasses || {};
  var workflowOrder = ['submitted', 'in_review', 'waiting_for_customer', 'resolved', 'closed'];
  var REQUEST_TIMEOUT_MS = 45000;
  var POLL_INTERVAL_MS = 15000;

  var view = cfg.view || (cfg.reference ? 'detail' : 'list');
  var referenceId = cfg.reference || '';
  var actionsRoot = document.getElementById('pax-cc-ticket-actions');
  var listPollTimer = null;
  var detailPollTimer = null;

  var statusSelect = document.getElementById('pax-cc-status');
  var statusFeedback = document.getElementById('pax-cc-status-feedback');
  var closeTicketBtn = document.getElementById('pax-cc-close-ticket');
  var replySection = document.getElementById('pax-cc-reply-section');
  var replyForm = document.getElementById('pax-cc-reply-form');
  var replyFeedback = document.getElementById('pax-cc-reply-feedback');
  var internalNoteForm = document.getElementById('pax-cc-internal-note-form');
  var internalNoteFeedback = document.getElementById('pax-cc-internal-note-feedback');
  var timelineEl = document.getElementById('pax-cc-admin-timeline');
  var statusBadge = document.getElementById('pax-cc-admin-status-badge');
  var workflowEl = document.getElementById('pax-cc-admin-workflow');
  var tabUnreadBadge = document.getElementById('pax-cc-tab-unread-badge');

  if (actionsRoot && !referenceId) {
    referenceId = actionsRoot.getAttribute('data-reference') || '';
  }

  var lastSavedStatus = statusSelect ? statusSelect.value : '';
  var statusSaveTimer = null;
  var closedStatuses = ['resolved', 'closed'];

  function isClosedStatus(status) {
    return closedStatuses.indexOf(status || '') !== -1;
  }

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

  function postAction(action, payload, refOverride) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', cfg.nonce);
    body.set('reference_id', refOverride || referenceId || '');
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

  function updateTabUnreadBadge(total) {
    if (!tabUnreadBadge) {
      return;
    }
    var count = parseInt(total, 10) || 0;
    if (count <= 0) {
      tabUnreadBadge.hidden = true;
      tabUnreadBadge.textContent = '';
      return;
    }
    tabUnreadBadge.hidden = false;
    tabUnreadBadge.textContent = count > 99 ? '99+' : String(count);
  }

  function applyUnreadSummary(summary) {
    if (!summary || typeof summary !== 'object') {
      return;
    }
    updateTabUnreadBadge(summary.total || 0);
    var reports = Array.isArray(summary.reports) ? summary.reports : [];
    var unreadMap = {};
    reports.forEach(function (item) {
      if (item && item.reference_id) {
        unreadMap[item.reference_id] = parseInt(item.unread_count, 10) || 0;
      }
    });
    document.querySelectorAll('.pax-cc-unread-badge--row').forEach(function (badge) {
      var ref = badge.getAttribute('data-unread-for') || '';
      var count = unreadMap[ref] || 0;
      if (count > 0) {
        badge.hidden = false;
        badge.textContent = count > 99 ? '99+' : String(count);
      } else {
        badge.hidden = true;
        badge.textContent = '';
      }
    });
  }

  function pollUnreadSummary(refForDetail) {
    return postAction('paxdesign_cybercrime_admin_unread', {}, refForDetail || '')
      .then(function (data) {
        if (data.summary) {
          applyUnreadSummary(data.summary);
        } else {
          applyUnreadSummary(data);
        }
        if (view === 'detail' && data.report) {
          applyReport(data.report);
        }
        return data;
      })
      .catch(function () {
        return null;
      });
  }

  function startListPolling() {
    if (view !== 'list') {
      return;
    }
    pollUnreadSummary('');
    listPollTimer = window.setInterval(function () {
      pollUnreadSummary('');
    }, POLL_INTERVAL_MS);
  }

  function startDetailPolling() {
    if (view !== 'detail' || !referenceId) {
      return;
    }
    pollUnreadSummary(referenceId);
    detailPollTimer = window.setInterval(function () {
      pollUnreadSummary(referenceId);
    }, POLL_INTERVAL_MS);
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

  function updateListRowStatus(report) {
    if (!report || !report.reference_id) {
      return;
    }
    var row = document.querySelector('tr[data-reference="' + report.reference_id + '"]');
    if (!row) {
      return;
    }
    var badge = row.querySelector('.pax-cc-status');
    if (!badge) {
      return;
    }
    var status = report.status || '';
    badge.textContent = report.status_label || status;
    badge.className = 'pax-cc-status ' + (statusClasses[status] || 'pax-cc-status--submitted');
  }

  function updateClosedUi(report) {
    if (!report) {
      return;
    }
    var closed = isClosedStatus(report.status || '');
    if (closeTicketBtn) {
      closeTicketBtn.hidden = closed;
    }
    if (replySection) {
      replySection.hidden = closed;
    }
    if (statusSelect) {
      statusSelect.disabled = closed;
    }
  }

  function applyReport(report) {
    if (!report) {
      return;
    }
    updateStatusBadge(report);
    updateWorkflow(report.status || '');
    renderTimeline(report.timeline || []);
    updateListRowStatus(report);
    updateClosedUi(report);
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
      var delay = isClosedStatus(nextStatus) ? 0 : 150;
      if (delay === 0) {
        saveStatus(nextStatus);
        return;
      }
      statusSaveTimer = window.setTimeout(function () {
        saveStatus(nextStatus);
      }, delay);
    });
  }

  if (closeTicketBtn) {
    closeTicketBtn.addEventListener('click', function () {
      if (!window.confirm(text('closeConfirm', 'Close this ticket? The customer can start a new report.'))) {
        return;
      }
      if (statusSelect) {
        statusSelect.value = 'closed';
      }
      saveStatus('closed');
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

  startListPolling();
  startDetailPolling();
})();
