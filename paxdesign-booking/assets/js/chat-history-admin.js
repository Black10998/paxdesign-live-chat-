/**
 * PAXdesign KI-Chat-Verlauf — Delete actions + lazy conversation load
 * Version: 3.37.0
 */
(function ($) {
  'use strict';

  var cfg = window.paxdesignAdmin;
  if (!cfg || !cfg.ajaxUrl) return;

  var $form = $('#paxChatHistoryDeleteForm');
  var loadedDetails = {};

  function deleteLogs(data, confirmMsg) {
    if (confirmMsg && !window.confirm(confirmMsg)) return;
    data.action = 'paxdesign_chat_delete_logs';
    data.nonce = cfg.nonce;
    $.post(cfg.ajaxUrl, data)
      .done(function (res) {
        if (!res.success) {
          alert(res.data && res.data.message ? res.data.message : 'Löschen fehlgeschlagen.');
          return;
        }
        window.location.href = 'admin.php?page=paxdesign-chat-history&deleted=' + (res.data.deleted || 1);
      })
      .fail(function () {
        alert('Netzwerkfehler beim Löschen.');
      });
  }

  function renderDetailMessages($body, messages) {
    if (!messages || !messages.length) {
      $body.html('<p style="margin:0;color:#6b7280;">Kein Verlauf vorhanden.</p>');
      return;
    }
    var html = '';
    messages.forEach(function (msg) {
      html += '<p style="margin:0 0 8px;"><strong>' +
        $('<div>').text(msg.label || msg.role || 'KI').html() + ':</strong> ' +
        $('<div>').text(msg.content || '').html() + '</p>';
    });
    $body.html(html);
  }

  function loadLogDetail($details) {
    var id = parseInt($details.attr('data-log-id'), 10);
    if (!id || loadedDetails[id]) return;

    var $body = $details.find('.pax-chat-log-detail-body');
    loadedDetails[id] = 'loading';

    $.post(cfg.ajaxUrl, {
      action: 'paxdesign_chat_log_detail',
      nonce: cfg.nonce,
      id: id,
    })
      .done(function (res) {
        if (!res || !res.success || !res.data) {
          $body.html('<p style="margin:0;color:#b91c1c;">Verlauf konnte nicht geladen werden.</p>');
          loadedDetails[id] = 'error';
          return;
        }
        renderDetailMessages($body, res.data.messages || []);
        loadedDetails[id] = 'done';
      })
      .fail(function () {
        $body.html('<p style="margin:0;color:#b91c1c;">Netzwerkfehler beim Laden.</p>');
        loadedDetails[id] = 'error';
      });
  }

  if ($form.length) {
    $('#paxChatDeleteSelected').on('click', function () {
      var ids = [];
      $form.find('input[name="ids[]"]:checked').each(function () {
        ids.push($(this).val());
      });
      if (!ids.length) {
        alert('Bitte mindestens eine Konversation auswählen.');
        return;
      }
      deleteLogs({ ids: ids }, 'Ausgewählte Konversation(en) wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');
    });

    $('#paxChatDeleteAll').on('click', function () {
      var confirmText = window.prompt(
        'Alle Konversationen unwiderruflich löschen?\n\nGeben Sie zur Bestätigung exakt ein: ALLE LOESCHEN'
      );
      if (confirmText !== 'ALLE LOESCHEN') {
        if (confirmText !== null) alert('Bestätigung fehlgeschlagen. Es wurde nichts gelöscht.');
        return;
      }
      deleteLogs({ delete_all: 1, confirm: 'ALLE LOESCHEN' });
    });

    $form.on('click', '.pax-chat-delete-one', function (e) {
      e.preventDefault();
      var id = $(this).data('id');
      deleteLogs({ ids: [id] }, 'Diese Konversation wirklich löschen?');
    });

    $('#paxChatSelectAll').on('change', function () {
      $form.find('input[name="ids[]"]').prop('checked', $(this).is(':checked'));
    });
  }

  $(document).on('toggle', '.pax-chat-log-details', function (e) {
    var el = e.target;
    if (!el.open) return;
    loadLogDetail($(el));
  }, true);
})(jQuery);
