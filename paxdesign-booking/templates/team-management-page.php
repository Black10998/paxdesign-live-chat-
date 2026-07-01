<?php
/**
 * Team Management — PAXdesign Booking v2.6.0
 */
if (!defined('ABSPATH')) exit;

$members  = PAXdesign_Booking::get_instance()->get_team_members(true);
$total    = count($members);
$active   = count(array_filter($members, function($m){ return $m['enabled']; }));
$inactive = $total - $active;
$services = count(array_filter($members, function($m){ return $m['has_services']; }));
?>
<div class="pax-admin">

  <!-- Header -->
  <div class="pa-header">
    <div class="pa-header-left">
      <div class="pa-logo">
        <svg width="24" height="24" viewBox="0 0 32 32" fill="none">
          <path d="M8 24L16 8L24 24H8Z" fill="#000"/>
        </svg>
      </div>
      <div>
        <span class="pa-title">Team Management</span>
        <span class="pa-subtitle">Sichtbarkeit, Reihenfolge und Verfügbarkeit</span>
      </div>
    </div>
    <span class="pa-badge pa-badge-green">
      <span class="pa-badge-dot"></span><?php echo $active; ?> aktiv
    </span>
  </div>

  <!-- Layout -->
  <div class="pa-layout">

    <!-- MAIN -->
    <div class="pa-main">

      <!-- How-to -->
      <div class="pa-howto">
        <div class="pa-howto-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="9" cy="19" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
          Zeilen ziehen zum Sortieren
        </div>
        <div class="pa-howto-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="5" width="22" height="14" rx="7"/><circle cx="16" cy="12" r="3" fill="currentColor" stroke="none"/></svg>
          Toggle zum Aktivieren / Deaktivieren
        </div>
        <div class="pa-howto-item">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Ctrl+S zum Speichern
        </div>
      </div>

      <!-- Team card -->
      <div class="pa-card">
        <div class="pa-card-head">
          <div class="pa-card-icon pa-icon-purple">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="pa-card-head-info">
            <h2>Team-Mitglieder</h2>
            <p><?php echo $total; ?> Mitglieder &middot; <?php echo $active; ?> aktiv</p>
          </div>
        </div>
        <div class="pa-card-body-flush">
          <form id="paxdesignTeamManagementForm">
            <div class="pa-table-wrap">
              <table class="pa-table">
                <thead>
                  <tr>
                    <th class="tc-drag"></th>
                    <th class="tc-num">#</th>
                    <th class="tc-member">Mitglied</th>
                    <th class="tc-role">Rolle</th>
                    <th class="tc-services">Services</th>
                    <th class="tc-avail">Verfügbarkeit</th>
                    <th class="tc-active">Aktiv</th>
                  </tr>
                </thead>
                <tbody id="paxdesignTeamTableBody">
                  <?php $i = 1; foreach ($members as $key => $m): ?>
                  <tr class="<?php echo $m['enabled'] ? '' : 'pa-row-off'; ?>" data-member-id="<?php echo esc_attr($key); ?>">

                    <td class="tc-drag">
                      <span class="pa-drag">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="9" cy="19" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="5" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
                      </span>
                    </td>

                    <td class="tc-num">
                      <span class="pa-num"><?php echo $i++; ?></span>
                    </td>

                    <td class="tc-member">
                      <div class="pa-member-cell">
                        <div class="pa-avatar-wrap">
                          <img src="<?php echo esc_url($m['image']); ?>"
                               alt="<?php echo esc_attr($m['name']); ?>"
                               class="pa-avatar" width="42" height="42" loading="lazy">
                          <span class="pa-online <?php echo $m['enabled'] ? 'on' : 'off'; ?>"></span>
                        </div>
                        <div class="pa-cell-text">
                          <span class="pa-cell-name"><?php echo esc_html($m['name']); ?></span>
                          <span class="pa-cell-email"><?php echo esc_html($m['email']); ?></span>
                        </div>
                      </div>
                    </td>

                    <td class="tc-role">
                      <span class="pa-role"><?php echo esc_html($m['role']); ?></span>
                    </td>

                    <td class="tc-services">
                      <?php if ($m['has_services']): ?>
                        <span class="pa-pill pa-pill-green">
                          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>Ja
                        </span>
                      <?php else: ?>
                        <span class="pa-pill pa-pill-gray">
                          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/></svg>Nein
                        </span>
                      <?php endif; ?>
                    </td>

                    <td class="tc-avail">
                      <select class="pa-avail paxdesign-availability-select" data-member-id="<?php echo esc_attr($key); ?>">
                        <option value="available"   <?php selected($m['availability'],'available'); ?>>Verfügbar</option>
                        <option value="busy"        <?php selected($m['availability'],'busy'); ?>>Beschäftigt</option>
                        <option value="vacation"    <?php selected($m['availability'],'vacation'); ?>>Im Urlaub</option>
                        <option value="unavailable" <?php selected($m['availability'],'unavailable'); ?>>Nicht verfügbar</option>
                      </select>
                    </td>

                    <td class="tc-active">
                      <label class="pa-toggle">
                        <input type="checkbox" class="paxdesign-enabled-toggle"
                               data-member-id="<?php echo esc_attr($key); ?>"
                               <?php checked($m['enabled'], true); ?>>
                        <span class="pa-toggle-track"></span>
                      </label>
                    </td>

                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="pa-table-foot">
              <button type="submit" class="pa-btn pa-btn-primary" id="paxdesignSaveTeamSettings">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Änderungen speichern
              </button>
              <span class="pa-save-msg" id="paxdesignSaveStatus"></span>
            </div>
          </form>
        </div>
      </div>

    </div><!-- /pa-main -->

    <!-- SIDEBAR -->
    <div class="pa-sidebar">

      <!-- Stats -->
      <div class="pa-card">
        <div class="pa-card-head">
          <div class="pa-card-icon pa-icon-lime">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          </div>
          <div class="pa-card-head-info"><h2>Übersicht</h2></div>
        </div>
        <div class="pa-card-body">
          <div class="pa-stats">
            <div class="pa-stat">
              <div class="pa-stat-val" id="paxdesignStatTotal"><?php echo $total; ?></div>
              <div class="pa-stat-lbl">Gesamt</div>
            </div>
            <div class="pa-stat">
              <div class="pa-stat-val c-green" id="paxdesignStatEnabled"><?php echo $active; ?></div>
              <div class="pa-stat-lbl">Aktiv</div>
            </div>
            <div class="pa-stat">
              <div class="pa-stat-val c-red" id="paxdesignStatDisabled"><?php echo $inactive; ?></div>
              <div class="pa-stat-lbl">Inaktiv</div>
            </div>
            <div class="pa-stat">
              <div class="pa-stat-val c-blue" id="paxdesignStatServices"><?php echo $services; ?></div>
              <div class="pa-stat-lbl">Services</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Info -->
      <div class="pa-card">
        <div class="pa-card-head">
          <div class="pa-card-icon pa-icon-slate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
          <div class="pa-card-head-info"><h2>Hinweise</h2></div>
        </div>
        <div class="pa-card-body">
          <ul class="pa-flow-list">
            <li>
              <span class="pa-flow-dot pa-dot-blue"></span>
              <div><strong>Reihenfolge</strong><p>Drag &amp; Drop — spiegelt sich im Buchungs-Widget wider.</p></div>
            </li>
            <li>
              <span class="pa-flow-dot pa-dot-green"></span>
              <div><strong>Deaktivieren</strong><p>Deaktivierte Mitglieder erscheinen nicht im Widget.</p></div>
            </li>
            <li>
              <span class="pa-flow-dot pa-dot-amber"></span>
              <div><strong>E-Mail-Adressen</strong><p>Werden in den <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-booking-settings')); ?>">Einstellungen</a> konfiguriert.</p></div>
            </li>
          </ul>
        </div>
      </div>

      <!-- Quick links -->
      <div class="pa-card">
        <div class="pa-card-body">
          <span class="pa-links-title">Schnellzugriff</span>
          <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-booking')); ?>" class="pa-qlink">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Buchungsübersicht
          </a>
          <a href="<?php echo esc_url(admin_url('admin.php?page=paxdesign-booking-settings')); ?>" class="pa-qlink">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Einstellungen
          </a>
        </div>
      </div>

    </div><!-- /pa-sidebar -->
  </div><!-- /pa-layout -->
</div><!-- /pax-admin -->
