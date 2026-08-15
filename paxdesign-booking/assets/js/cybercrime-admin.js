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
  var rejectTicketBtn = document.getElementById('pax-cc-reject-ticket');
  var replySection = document.getElementById('pax-cc-reply-section');
  var replyForm = document.getElementById('pax-cc-reply-form');
  var replyFeedback = document.getElementById('pax-cc-reply-feedback');
  var internalNoteForm = document.getElementById('pax-cc-internal-note-form');
  var internalNoteFeedback = document.getElementById('pax-cc-internal-note-feedback');
  var timelineEl = document.getElementById('pax-cc-admin-timeline');
  var statusBadge = document.getElementById('pax-cc-admin-status-badge');
  var workflowEl = document.getElementById('pax-cc-admin-workflow');
  var tabUnreadBadge = document.getElementById('pax-cc-tab-unread-badge');

  var rejectPanel = document.getElementById('pax-cc-reject-panel');
  var rejectReasonSelect = document.getElementById('pax-cc-reject-reason');
  var rejectExplanation = document.getElementById('pax-cc-reject-explanation');
  var rejectExplanationLabel = document.getElementById('pax-cc-reject-explanation-label');
  var rejectSubmit = document.getElementById('pax-cc-reject-submit');
  var rejectCancel = document.getElementById('pax-cc-reject-cancel');
  var rejectionCard = document.getElementById('pax-cc-admin-rejection');

  if (actionsRoot && !referenceId) {
    referenceId = actionsRoot.getAttribute('data-reference') || '';
  }

  var lastSavedStatus = statusSelect ? statusSelect.value : '';
  var statusSaveTimer = null;
  var closedStatuses = ['resolved', 'closed', 'rejected'];

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
    if (typeof window.paxCybercrimeAdminApplyUnread === 'function') {
      window.paxCybercrimeAdminApplyUnread(summary);
    }
  }

  function markStaffRead(refOverride) {
    return postAction('paxdesign_cybercrime_admin_mark_read', {}, refOverride || referenceId || '')
      .then(function (data) {
        if (data.summary) {
          applyUnreadSummary(data.summary);
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

  function clearUnreadForReference(ref) {
    if (!ref) {
      return;
    }
    document.querySelectorAll('.pax-cc-unread-badge--row[data-unread-for="' + ref + '"]').forEach(function (badge) {
      badge.hidden = true;
      badge.textContent = '';
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
    clearUnreadForReference(referenceId);
    markStaffRead(referenceId).then(function () {
      pollUnreadSummary(referenceId);
    });
    detailPollTimer = window.setInterval(function () {
      pollUnreadSummary(referenceId);
    }, POLL_INTERVAL_MS);
  }

  function updateWorkflow(currentStatus) {
    if (!workflowEl) {
      return;
    }
    var steps = workflowEl.querySelectorAll('.pax-cc-workflow__step');
    if (currentStatus === 'rejected') {
      steps.forEach(function (step) {
        step.classList.remove('is-current');
        step.classList.add('is-done');
      });
      return;
    }
    var currentIndex = workflowOrder.indexOf(currentStatus);
    if (currentIndex < 0) {
      currentIndex = currentStatus === 'draft' ? -1 : 0;
    }
    steps.forEach(function (step, index) {
      step.classList.remove('is-current', 'is-done');
      if (index === currentIndex) {
        step.classList.add('is-current');
      } else if (currentIndex >= 0 && index < currentIndex) {
        step.classList.add('is-done');
      }
    });
  }

  function authorLabel(type) {
    return text('author_' + (type || ''), type || '');
  }

  function channelLabel(channel) {
    return text('channel_' + (channel || ''), channel || '');
  }

  function statusIconHtml(status) {
    var icons = cfg.statusIcons || {};
    return icons[status] || icons.submitted || '';
  }

  function fillStatusBadge(el, report) {
    if (!el || !report) {
      return;
    }
    var status = report.status || '';
    var label = report.status_label || status;
    var html = statusIconHtml(status) + '<span class="pax-cc-status__label">' + escapeHtml(label) + '</span>';
    var target = el.classList && el.classList.contains('pax-cc-status')
      ? el
      : (el.querySelector ? el.querySelector('.pax-cc-status') : null);
    if (!target) {
      el.innerHTML = '<span class="pax-cc-status ' + (statusClasses[status] || 'pax-cc-status--submitted') + '">' + html + '</span>';
      return;
    }
    target.className = 'pax-cc-status ' + (statusClasses[status] || 'pax-cc-status--submitted');
    target.innerHTML = html;
  }

  function setRejectPanelOpen(open) {
    if (!rejectPanel) {
      return;
    }
    rejectPanel.hidden = !open;
  }

  function syncRejectExplanationLabel() {
    if (!rejectExplanationLabel || !rejectReasonSelect) {
      return;
    }
    var other = rejectReasonSelect.value === 'other';
    rejectExplanationLabel.textContent = other
      ? text('rejectExplanationOther', text('rejectExplanation', 'Additional explanation (required)'))
      : text('rejectExplanation', 'Additional explanation (optional)');
  }

  function renderRejectionCard(report) {
    if (!rejectionCard) {
      return;
    }
    var rejection = report && report.rejection && typeof report.rejection === 'object' ? report.rejection : null;
    var status = report && report.status ? report.status : '';
    if (status !== 'rejected' && !rejection) {
      rejectionCard.innerHTML = '';
      return;
    }
    var lang = cfg.lang || 'en';
    var reasonI18n = rejection && rejection.reason_i18n ? rejection.reason_i18n : {};
    var reason = (reasonI18n[lang] || (rejection && rejection.reason) || '');
    var explanation = rejection && rejection.explanation ? rejection.explanation : '';
    var html = '<div class="pax-cc-decision">';
    html += '<div class="pax-cc-decision__status">' + statusIconHtml('rejected') + '<strong>' + escapeHtml(report.status_label || text('rejectTicket', 'Rejected')) + '</strong></div>';
    if (reason) {
      html += '<p class="pax-cc-decision__heading">' + escapeHtml(text('rejectReason', 'Rejection reason')) + '</p>';
      html += '<p class="pax-cc-decision__reason">' + escapeHtml(reason) + '</p>';
    }
    if (explanation) {
      html += '<p class="pax-cc-decision__reason">' + escapeHtml(explanation).replace(/\n/g, '<br>') + '</p>';
    }
    var meta = [];
    if (rejection && rejection.admin_name) {
      meta.push(text('detail_rejection_by', 'Administrator decision') + ': ' + rejection.admin_name);
    }
    if (rejection && rejection.decided_at) {
      meta.push(text('detail_rejection_at', 'Date / time') + ': ' + rejection.decided_at);
    }
    if (meta.length) {
      html += '<p class="pax-cc-decision__meta">' + escapeHtml(meta.join(' · ')) + '</p>';
    }
    html += '</div>';
    rejectionCard.innerHTML = html;
  }

  function updateStatusBadge(report) {
    if (!statusBadge || !report) {
      return;
    }
    fillStatusBadge(statusBadge, report);
  }

  function renderTimelineItem(entry) {
    var author = escapeHtml(authorLabel(entry.author_type || ''));
    var channel = escapeHtml(channelLabel(entry.channel || ''));
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
    fillStatusBadge(badge, report);
  }

  function updateClosedUi(report) {
    if (!report) {
      return;
    }
    var closed = isClosedStatus(report.status || '');
    if (closeTicketBtn) {
      closeTicketBtn.hidden = closed;
    }
    if (rejectTicketBtn) {
      rejectTicketBtn.hidden = closed;
    }
    if (replySection) {
      replySection.hidden = closed;
    }
    if (statusSelect) {
      statusSelect.disabled = closed && (report.status || '') !== 'rejected';
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
    renderRejectionCard(report);
    if (report.status === 'rejected') {
      setRejectPanelOpen(false);
    }
    if (statusSelect && report.status) {
      statusSelect.value = report.status;
      lastSavedStatus = report.status;
    }
  }

  function saveStatus(status, extra) {
    extra = extra || {};
    if (!status || (status === lastSavedStatus && status !== 'rejected')) {
      return Promise.resolve();
    }
    if (status === 'rejected' && status === lastSavedStatus && !extra.reason_key) {
      return Promise.resolve();
    }

    setFeedback(statusFeedback, text('saving', 'Saving…'), 'saving');

    var payload = { status: status };
    if (status === 'rejected') {
      payload.reason_key = extra.reason_key || '';
      payload.explanation = extra.explanation || '';
    }

    return postAction('paxdesign_cybercrime_admin_status', payload)
      .then(function (data) {
        applyReport(data.report);
        if (isClosedStatus(status)) {
          clearUnreadForReference(referenceId);
        }
        setFeedback(statusFeedback, data.message || text('statusSaved', 'Status saved.'), 'success');
        window.setTimeout(function () {
          if (statusFeedback && statusFeedback.textContent === (data.message || text('statusSaved', 'Status saved.'))) {
            setFeedback(statusFeedback, '', '');
          }
        }, 2500);
        return markStaffRead(referenceId).then(function () {
          return data;
        });
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
      if (nextStatus === 'rejected') {
        statusSelect.value = lastSavedStatus;
        setRejectPanelOpen(true);
        return;
      }
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

  if (rejectTicketBtn) {
    rejectTicketBtn.addEventListener('click', function () {
      setRejectPanelOpen(true);
    });
  }

  if (rejectCancel) {
    rejectCancel.addEventListener('click', function () {
      setRejectPanelOpen(false);
      if (statusSelect) {
        statusSelect.value = lastSavedStatus;
      }
    });
  }

  if (rejectReasonSelect) {
    rejectReasonSelect.addEventListener('change', syncRejectExplanationLabel);
    syncRejectExplanationLabel();
  }

  if (rejectSubmit) {
    rejectSubmit.addEventListener('click', function () {
      var reasonKey = rejectReasonSelect ? rejectReasonSelect.value : '';
      var explanation = rejectExplanation ? rejectExplanation.value.trim() : '';
      if (!reasonKey) {
        setFeedback(statusFeedback, text('rejectRequired', 'Please choose a rejection reason.'), 'error');
        return;
      }
      if (reasonKey === 'other' && !explanation) {
        setFeedback(statusFeedback, text('rejectExplanationRequired', 'Please enter an additional explanation for this reason.'), 'error');
        return;
      }
      if (statusSelect) {
        statusSelect.value = 'rejected';
      }
      saveStatus('rejected', {
        reason_key: reasonKey,
        explanation: explanation
      });
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
