(function () {
  'use strict';

  var cfg = window.paxCybercrimeAdmin || {};
  if (!cfg.ajaxUrl || !cfg.nonce) {
    return;
  }

  var i18n = cfg.i18n || {};
  var statusClasses = cfg.statusClasses || {};
  var workflowOrder = ['submitted', 'in_review', 'waiting_for_customer', 'resolved', 'closed', 'rejected'];
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
  var attachmentsEl = document.getElementById('pax-cc-admin-attachments');
  var attachmentsCardEl = document.getElementById('pax-cc-admin-attachments-card');
  var lightboxEl = document.getElementById('pax-cc-lightbox');
  var lightboxImgEl = document.getElementById('pax-cc-lightbox-img');
  var lightboxCloseEl = document.getElementById('pax-cc-lightbox-close');
  var statusBadge = document.getElementById('pax-cc-admin-status-badge');
  var workflowEl = document.getElementById('pax-cc-admin-workflow');
  var tabUnreadBadge = document.getElementById('pax-cc-tab-unread-badge');

  if (actionsRoot && !referenceId) {
    referenceId = actionsRoot.getAttribute('data-reference') || '';
  }

  var lastSavedStatus = statusSelect ? statusSelect.value : '';
  var statusSaveTimer = null;
  var closedStatuses = ['resolved', 'closed', 'rejected'];
  var syncSnapshot = null;
  var mutationDepth = 0;
  var pollRequestSeq = 0;
  var currentReport = null;

  if (cfg.initialSync && typeof cfg.initialSync === 'object') {
    syncSnapshot = {
      updatedAt: String(cfg.initialSync.updated_at || ''),
      timelineMaxId: parseInt(cfg.initialSync.timeline_max_id, 10) || 0,
      timelineCount: parseInt(cfg.initialSync.timeline_count, 10) || 0,
      status: String(cfg.initialSync.status || ''),
      syncRevision: String(cfg.initialSync.sync_revision || '')
    };
  }

  function syncFromReport(report) {
    if (!report || typeof report !== 'object') {
      return null;
    }
    return {
      updatedAt: String(report.updated_at || ''),
      timelineMaxId: parseInt(report.timeline_max_id, 10) || 0,
      timelineCount: parseInt(report.timeline_count, 10) || 0,
      status: String(report.status || ''),
      syncRevision: String(report.sync_revision || '')
    };
  }

  function compareSync(incoming, current) {
    if (!incoming || !current) {
      return 0;
    }
    if (incoming.syncRevision && current.syncRevision && incoming.syncRevision === current.syncRevision) {
      return 0;
    }
    if (incoming.timelineMaxId !== current.timelineMaxId) {
      return incoming.timelineMaxId > current.timelineMaxId ? 1 : -1;
    }
    if (incoming.timelineCount !== current.timelineCount) {
      return incoming.timelineCount > current.timelineCount ? 1 : -1;
    }
    if (incoming.updatedAt !== current.updatedAt) {
      return incoming.updatedAt > current.updatedAt ? 1 : -1;
    }
    if (incoming.status !== current.status) {
      return incoming.updatedAt >= current.updatedAt ? 1 : -1;
    }
    return 0;
  }

  function shouldApplyReport(report, source) {
    var incoming = syncFromReport(report);
    if (!incoming) {
      return false;
    }
    if (!syncSnapshot) {
      return true;
    }
    var cmp = compareSync(incoming, syncSnapshot);
    if (source === 'poll' || source === 'mark_read') {
      if (mutationDepth > 0 && cmp <= 0) {
        return false;
      }
      if (cmp < 0) {
        return false;
      }
      return true;
    }
    if (source === 'mutation') {
      return true;
    }
    return cmp >= 0;
  }

  function rememberSync(report) {
    var incoming = syncFromReport(report);
    if (incoming) {
      syncSnapshot = incoming;
    }
  }

  function beginMutation() {
    mutationDepth += 1;
  }

  function endMutation() {
    mutationDepth = Math.max(0, mutationDepth - 1);
  }

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

  function markStaffRead(refOverride, options) {
    options = options || {};
    return postAction('paxdesign_cybercrime_admin_mark_read', {}, refOverride || referenceId || '')
      .then(function (data) {
        if (data.summary) {
          applyUnreadSummary(data.summary);
        }
        if (view === 'detail' && data.report && options.applyReport !== false) {
          applyReport(data.report, 'mark_read');
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
    var requestSeq = ++pollRequestSeq;
    return postAction('paxdesign_cybercrime_admin_unread', {}, refForDetail || '')
      .then(function (data) {
        if (requestSeq !== pollRequestSeq) {
          return data;
        }
        if (data.summary) {
          applyUnreadSummary(data.summary);
        } else {
          applyUnreadSummary(data);
        }
        if (view === 'detail' && data.report) {
          applyReport(data.report, 'poll');
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

  function renderAttachmentItem(file) {
    if (!file || !file.name) {
      return '';
    }
    var name = escapeHtml(file.name);
    var url = file.url ? escapeHtml(file.url) : '';
    var isImage = !!file.is_image;
    if (!url) {
      return '<div class="pax-cc-attachment"><span class="pax-cc-attachment__name">' + name + '</span></div>';
    }
    if (isImage) {
      return '<a class="pax-cc-attachment pax-cc-attachment--image" href="' + url + '" data-pax-cc-lightbox target="_blank" rel="noopener">'
        + '<img class="pax-cc-attachment__thumb" src="' + url + '" alt="' + name + '" loading="lazy">'
        + '<span class="pax-cc-attachment__name">' + name + '</span></a>';
    }
    return '<a class="pax-cc-attachment pax-cc-attachment--file" href="' + url + '" target="_blank" rel="noopener">'
      + '<span class="pax-cc-attachment__file"><span class="pax-cc-attachment__icon" aria-hidden="true">📄</span></span>'
      + '<span class="pax-cc-attachment__name">' + name + '</span></a>';
  }

  function renderAttachments(attachments) {
    if (!attachmentsEl) {
      return;
    }
    var files = Array.isArray(attachments) ? attachments : [];
    if (attachmentsCardEl) {
      attachmentsCardEl.hidden = files.length === 0;
    }
    if (!files.length) {
      attachmentsEl.innerHTML = '';
      return;
    }
    attachmentsEl.innerHTML = files.map(renderAttachmentItem).join('');
    bindLightboxTriggers(attachmentsEl);
  }

  function renderTimelineAttachments(files) {
    if (!Array.isArray(files) || !files.length) {
      return '';
    }
    var items = files.map(function (file) {
      if (!file || !file.url) {
        return '';
      }
      var name = escapeHtml(file.name || 'file');
      var url = escapeHtml(file.url);
      var thumb = file.is_image
        ? '<img src="' + url + '" alt="' + name + '" loading="lazy">'
        : '';
      return '<a class="pax-cc-timeline__attachment" href="' + url + '"' + (file.is_image ? ' data-pax-cc-lightbox' : '') + ' target="_blank" rel="noopener">'
        + thumb + '<span>' + name + '</span></a>';
    }).join('');
    return items ? '<div class="pax-cc-timeline__attachments">' + items + '</div>' : '';
  }

  function openLightbox(url, alt) {
    if (!lightboxEl || !lightboxImgEl || !url) {
      return;
    }
    lightboxImgEl.src = url;
    lightboxImgEl.alt = alt || '';
    lightboxEl.hidden = false;
    lightboxEl.setAttribute('aria-hidden', 'false');
    lightboxEl.classList.add('is-open');
  }

  function closeLightbox() {
    if (!lightboxEl || !lightboxImgEl) {
      return;
    }
    lightboxEl.hidden = true;
    lightboxEl.setAttribute('aria-hidden', 'true');
    lightboxEl.classList.remove('is-open');
    lightboxImgEl.removeAttribute('src');
    lightboxImgEl.alt = '';
  }

  function bindLightboxTriggers(root) {
    if (!root) {
      return;
    }
    root.querySelectorAll('[data-pax-cc-lightbox]').forEach(function (link) {
      if (link.dataset.paxCcLightboxBound === '1') {
        return;
      }
      link.dataset.paxCcLightboxBound = '1';
      link.addEventListener('click', function (event) {
        event.preventDefault();
        openLightbox(link.getAttribute('href'), link.querySelector('img') ? link.querySelector('img').alt : link.textContent);
      });
    });
  }

  if (lightboxCloseEl) {
    lightboxCloseEl.addEventListener('click', closeLightbox);
  }
  if (lightboxEl) {
    lightboxEl.addEventListener('click', function (event) {
      if (event.target === lightboxEl) {
        closeLightbox();
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeLightbox();
      }
    });
  }

  bindLightboxTriggers(document);

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

  function entryMeta(entry) {
    var meta = entry && entry.meta;
    if (meta && typeof meta === 'object') {
      return meta;
    }
    if (typeof meta === 'string' && meta.trim()) {
      try {
        var parsed = JSON.parse(meta);
        if (parsed && typeof parsed === 'object') {
          return parsed;
        }
      } catch (error) {
        return {};
      }
    }
    return {};
  }

  function timelineKind(entry) {
    if (entry && entry.timeline_kind) {
      return entry.timeline_kind;
    }
    var meta = entryMeta(entry);
    if (meta.internal_only) {
      return 'internal';
    }
    var author = String(entry.author_type || '');
    if (author === 'customer') {
      return 'customer';
    }
    if (author === 'staff') {
      return 'staff';
    }
    return 'system';
  }

  function timelineLabel(entry) {
    if (entry && entry.timeline_label) {
      return entry.timeline_label;
    }
    var labels = {
      customer: text('labelCustomer', 'Customer'),
      staff: text('labelStaff', 'You / Staff'),
      internal: text('labelInternal', 'Internal Note'),
      system: text('labelSystem', 'System')
    };
    return labels[timelineKind(entry)] || labels.system;
  }

  function isDeletableEntry(entry) {
    if (!entry) {
      return false;
    }
    var id = parseInt(entry.id, 10);
    if (!id) {
      return false;
    }
    if (entry.allow_delete === 1 || entry.allow_delete === '1' || entry.allow_delete === true) {
      return true;
    }
    if (entry.can_delete === 1 || entry.can_delete === '1' || entry.can_delete === true) {
      return true;
    }
    var meta = entryMeta(entry);
    if (String(entry.author_type || '') !== 'staff') {
      return false;
    }
    if (meta.internal_only) {
      return false;
    }
    return String(meta.event || '') === 'staff_reply';
  }

  function renderTimelineItem(entry) {
    var kind = timelineKind(entry);
    var label = escapeHtml(timelineLabel(entry));
    var createdAt = escapeHtml(entry.created_at || '');
    var body = escapeHtml(entry.body || '').replace(/\n/g, '<br>');
    var meta = entryMeta(entry);
    var messageId = parseInt(entry.id, 10) || 0;
    var evidenceTag = (meta.request_evidence || entry.request_evidence)
      ? '<span class="pax-cc-convo__tag pax-cc-convo__tag--evidence">' + escapeHtml(text('requestEvidenceTag', 'Evidence Requested')) + '</span>'
      : '';
    var deleteBtn = isDeletableEntry(entry)
      ? '<button type="button" class="pax-cc-convo__delete" data-message-id="' + escapeHtml(String(messageId)) + '">' + escapeHtml(text('deleteMessage', 'Delete message')) + '</button>'
      : '';
    var foot = (evidenceTag || deleteBtn)
      ? '<div class="pax-cc-convo__foot">' + evidenceTag + deleteBtn + '</div>'
      : '';

    return '<li class="pax-cc-convo__item pax-cc-convo__item--' + escapeHtml(kind) + '" data-message-id="' + escapeHtml(String(messageId)) + '">'
      + '<div class="pax-cc-convo__bubble">'
      + '<div class="pax-cc-convo__head">'
      + '<span class="pax-cc-convo__badge pax-cc-convo__badge--' + escapeHtml(kind) + '">' + label + '</span>'
      + '<time class="pax-cc-convo__time">' + createdAt + '</time>'
      + '</div>'
      + '<div class="pax-cc-convo__body">' + body + '</div>'
      + renderTimelineAttachments(entry.attachments || [])
      + foot
      + '</div></li>';
  }

  function renderTimeline(timeline) {
    if (!timelineEl) {
      return;
    }
    var entries = Array.isArray(timeline) ? timeline : [];
    if (!entries.length) {
      timelineEl.innerHTML = '<li class="pax-cc-convo__item pax-cc-convo__item--system"><div class="pax-cc-convo__bubble"><div class="pax-cc-convo__body">' + escapeHtml(text('noConversation', 'No messages yet.')) + '</div></div></li>';
      return;
    }
    timelineEl.innerHTML = entries.map(renderTimelineItem).join('');
    bindLightboxTriggers(timelineEl);
    bindTimelineDeleteActions(timelineEl);
  }

  function bindTimelineDeleteActions(root) {
    if (!root) {
      return;
    }
    root.querySelectorAll('.pax-cc-convo__delete[data-message-id]').forEach(function (btn) {
      if (btn.getAttribute('data-bound') === '1') {
        return;
      }
      btn.setAttribute('data-bound', '1');
      btn.addEventListener('click', function () {
        deleteStaffMessage(btn);
      });
    });
  }

  function deleteStaffMessage(btn) {
    if (!btn || btn.disabled) {
      return;
    }
    var messageId = btn.getAttribute('data-message-id') || '';
    if (!messageId || !referenceId) {
      return;
    }
    if (!window.confirm(text('deleteConfirm', 'Permanently delete this message? This cannot be undone.'))) {
      return;
    }

    btn.disabled = true;
    setFeedback(replyFeedback, text('deleting', 'Deleting…'), 'saving');
    beginMutation();

    postAction('paxdesign_cybercrime_admin_delete_message', { message_id: messageId })
      .then(function (data) {
        applyReport(data.report, 'mutation');
        setFeedback(replyFeedback, data.message || text('deleteSuccess', 'Message deleted.'), 'success');
        window.setTimeout(function () {
          if (replyFeedback && replyFeedback.textContent === (data.message || text('deleteSuccess', 'Message deleted.'))) {
            setFeedback(replyFeedback, '', '');
          }
        }, 2500);
      })
      .catch(function (error) {
        btn.disabled = false;
        setFeedback(replyFeedback, error.message || text('error', 'Something went wrong.'), 'error');
      })
      .then(function () {
        endMutation();
      });
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
    if (rejectTicketBtn) {
      rejectTicketBtn.hidden = closed;
    }
    if (replySection) {
      replySection.hidden = closed;
    }
    if (statusSelect) {
      statusSelect.disabled = closed;
    }
  }

  function applyReport(report, source) {
    source = source || 'poll';
    if (!report) {
      return false;
    }
    if (!shouldApplyReport(report, source)) {
      return false;
    }
    rememberSync(report);
    currentReport = report;
    updateStatusBadge(report);
    updateWorkflow(report.status || '');
    renderTimeline(report.timeline || []);
    renderAttachments(report.attachments || []);
    updateListRowStatus(report);
    updateClosedUi(report);
    if (statusSelect && report.status) {
      statusSelect.value = report.status;
      lastSavedStatus = report.status;
    }
    return true;
  }

  function saveStatus(status) {
    if (!status || status === lastSavedStatus) {
      return Promise.resolve();
    }

    setFeedback(statusFeedback, text('saving', 'Saving…'), 'saving');
    beginMutation();

    return postAction('paxdesign_cybercrime_admin_status', { status: status })
      .then(function (data) {
        applyReport(data.report, 'mutation');
        if (isClosedStatus(status)) {
          clearUnreadForReference(referenceId);
        }
        setFeedback(statusFeedback, data.message || text('statusSaved', 'Status saved.'), 'success');
        window.setTimeout(function () {
          if (statusFeedback && statusFeedback.textContent === (data.message || text('statusSaved', 'Status saved.'))) {
            setFeedback(statusFeedback, '', '');
          }
        }, 2500);
        return markStaffRead(referenceId, { applyReport: false }).then(function () {
          return data;
        });
      })
      .catch(function (error) {
        if (statusSelect) {
          statusSelect.value = lastSavedStatus;
        }
        setFeedback(statusFeedback, error.message || text('error', 'Something went wrong.'), 'error');
      })
      .then(function (result) {
        endMutation();
        return result;
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

  if (rejectTicketBtn) {
    rejectTicketBtn.addEventListener('click', function () {
      if (!window.confirm(text('rejectConfirm', 'Mark this case as مرفوض (Rejected)? The customer will be emailed.'))) {
        return;
      }
      if (statusSelect) {
        statusSelect.value = 'rejected';
      }
      saveStatus('rejected');
    });
  }

  function sendStaffReply() {
    var messageEl = document.getElementById('pax-cc-staff-message');
    var statusEl = document.getElementById('pax-cc-reply-status');
    var submitBtn = document.getElementById('pax-cc-reply-submit');
    var evidenceCheckbox = document.getElementById('pax-cc-request-evidence');
    var requestEvidence = !!(evidenceCheckbox && evidenceCheckbox.checked);
    var message = messageEl ? messageEl.value.trim() : '';
    if (!message) {
      if (messageEl) {
        messageEl.focus();
      }
      return;
    }

    setSubmitEnabled(submitBtn, false);
    setFeedback(replyFeedback, text('sending', 'Sending…'), 'saving');
    beginMutation();

    var payload = {
      message: message,
      status: requestEvidence ? 'waiting_for_customer' : (statusEl ? statusEl.value : 'waiting_for_customer')
    };
    if (requestEvidence) {
      payload.request_evidence = '1';
      if (statusEl) {
        statusEl.value = 'waiting_for_customer';
      }
    }

    return postAction('paxdesign_cybercrime_admin_reply', payload)
      .then(function (data) {
        applyReport(data.report, 'mutation');
        if (messageEl) {
          messageEl.value = '';
        }
        if (evidenceCheckbox) {
          evidenceCheckbox.checked = false;
        }
        var successMsg = requestEvidence
          ? text('requestEvidenceSent', 'Evidence request sent to customer.')
          : (data.message || text('replySent', 'Reply sent to customer.'));
        setFeedback(replyFeedback, successMsg, 'success');
      })
      .catch(function (error) {
        setFeedback(replyFeedback, error.message || text('error', 'Something went wrong.'), 'error');
      })
      .then(function () {
        setSubmitEnabled(submitBtn, true);
        endMutation();
      });
  }

  if (replyForm) {
    replyForm.addEventListener('submit', function (event) {
      event.preventDefault();
      sendStaffReply();
    });
  }

  var evidenceCheckboxEl = document.getElementById('pax-cc-request-evidence');
  var replyStatusEl = document.getElementById('pax-cc-reply-status');
  if (evidenceCheckboxEl && replyStatusEl) {
    evidenceCheckboxEl.addEventListener('change', function () {
      if (evidenceCheckboxEl.checked) {
        replyStatusEl.value = 'waiting_for_customer';
      }
    });
  }

  bindTimelineDeleteActions(timelineEl);

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
      beginMutation();

      postAction('paxdesign_cybercrime_admin_internal_note', { message: message })
        .then(function (data) {
          applyReport(data.report, 'mutation');
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
          endMutation();
        });
    });
  }

  startListPolling();
  startDetailPolling();
})();
