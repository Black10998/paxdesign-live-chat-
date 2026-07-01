<?php
if (!defined('ABSPATH')) {
    exit;
}

$search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$service   = isset($_GET['service']) ? sanitize_text_field(wp_unslash($_GET['service'])) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
$date_to   = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
$deleted   = isset($_GET['deleted']) ? absint($_GET['deleted']) : 0;

$log       = PAXdesign_Chat_Log::get_instance();
$logs      = $log->get_logs(compact('search', 'service', 'date_from', 'date_to'));
$total     = $log->count_logs(compact('search', 'service', 'date_from', 'date_to'));
$services  = $log->get_distinct_services();

$export_base = wp_nonce_url(
    admin_url('admin-post.php?action=paxdesign_chat_export'),
    'paxdesign_chat_export'
);
$export_query = http_build_query(array_filter(array(
    's'         => $search,
    'service'   => $service,
    'date_from' => $date_from,
    'date_to'   => $date_to,
)));
?>

<div class="wrap pax-admin">
  <?php if ($deleted > 0) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf('%d Konversation(en) wurden gelöscht.', $deleted)); ?></p></div>
  <?php endif; ?>

  <div class="pa-header">
    <div class="pa-header-left">
      <div class="pa-logo" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0a0a0a" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div>
        <span class="pa-title">KI-Chat-Verlauf</span>
        <span class="pa-subtitle">PAXdesign Booking · <?php echo esc_html(PAXDESIGN_BOOKING_VERSION); ?></span>
      </div>
    </div>
    <span class="pa-badge pa-badge-green"><span class="pa-badge-dot"></span><?php echo (int) $total; ?> Gespräche</span>
  </div>

  <div class="pa-card" style="margin-bottom:20px;">
    <form method="get" action="">
      <input type="hidden" name="page" value="paxdesign-chat-history">
      <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Suche</label>
          <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Session-ID oder Inhalt" style="min-width:200px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Dienstleistung</label>
          <select name="service" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;">
            <option value="">Alle</option>
            <?php foreach ($services as $svc) : ?>
              <option value="<?php echo esc_attr($svc); ?>" <?php selected($service, $svc); ?>><?php echo esc_html($svc); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Von</label>
          <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Bis</label>
          <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;">
        </div>
        <button type="submit" class="button button-primary">Filtern</button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-chat-history')); ?>" class="button">Zurücksetzen</a>
        <a href="<?php echo esc_url($export_base . '&format=csv&' . $export_query); ?>" class="button">CSV Export</a>
        <a href="<?php echo esc_url($export_base . '&format=json&' . $export_query); ?>" class="button">JSON Export</a>
      </div>
    </form>
    <p style="margin:12px 0 0;font-size:12px;color:#6b7280;">Datenschutz: Es werden nur anonyme Session-IDs und Chat-Inhalte gespeichert (keine IP, max. <?php echo (int) PAXdesign_Chat_Log::RETENTION_DAYS; ?> Tage).</p>
  </div>

  <div class="pa-card">
    <?php if (empty($logs)) : ?>
      <p style="padding:16px;color:#6b7280;">Noch keine KI-Chats protokolliert.</p>
    <?php else : ?>
      <form id="paxChatHistoryDeleteForm">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:12px;">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
            <input type="checkbox" id="paxChatSelectAll"> Alle auswählen
          </label>
          <button type="button" class="button" id="paxChatDeleteSelected">Ausgewählte löschen</button>
          <button type="button" class="button button-link-delete" id="paxChatDeleteAll" style="color:#b91c1c;margin-left:auto;">Alle löschen …</button>
        </div>
        <table class="wp-list-table widefat fixed striped" style="border:0;">
          <thead>
            <tr>
              <th style="width:36px;"></th>
              <th style="width:140px;">Aktualisiert</th>
              <th style="width:120px;">Session</th>
              <th style="width:140px;">Dienstleistung</th>
              <th style="width:70px;">Booking</th>
              <th style="width:70px;">Beratung</th>
              <th>Verlauf</th>
              <th style="width:80px;">Aktion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $row) :
              $preview = isset($row->last_preview) ? trim((string) $row->last_preview) : '';
              if ($preview === '') {
                $messages  = json_decode($row->messages, true);
                if (is_array($messages)) {
                  $last = end($messages);
                  if (is_array($last) && !empty($last['content'])) {
                    $preview = wp_html_excerpt($last['content'], 120, '…');
                  }
                }
              }
            ?>
            <tr>
              <td><input type="checkbox" name="ids[]" value="<?php echo (int) $row->id; ?>"></td>
              <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($row->updated_at))); ?></td>
              <td><code style="font-size:11px;"><?php echo esc_html(substr($row->session_id, 0, 18)); ?>…</code></td>
              <td><?php echo esc_html($row->detected_service ?: '—'); ?></td>
              <td><?php echo $row->booking_triggered ? '✓' : '—'; ?></td>
              <td><?php echo $row->consultation_started ? '✓' : '—'; ?></td>
              <td>
                <details class="pax-chat-log-details" data-log-id="<?php echo (int) $row->id; ?>">
                  <summary style="cursor:pointer;font-size:13px;"><?php echo esc_html($preview ?: 'Gespräch anzeigen'); ?> (<?php echo (int) $row->message_count; ?>)</summary>
                  <div class="pax-chat-log-detail-body" style="margin-top:8px;padding:10px;background:#f9fafb;border-radius:8px;max-height:220px;overflow:auto;font-size:12px;line-height:1.5;">
                    <p style="margin:0;color:#6b7280;">Verlauf wird geladen …</p>
                  </div>
                </details>
              </td>
              <td>
                <button type="button" class="button button-link-delete pax-chat-delete-one" data-id="<?php echo (int) $row->id; ?>" style="color:#b91c1c;">Löschen</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </form>
    <?php endif; ?>
  </div>
</div>
