<?php
if (!defined('ABSPATH')) {
    exit;
}
$staff_list = PAXdesign_Live_Chat_Permissions::list_staff_for_api();
$labels     = PAXdesign_Live_Chat_Permissions::permission_labels();
$updated    = isset($_GET['updated']);
$error      = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
?>
<div class="wrap">
    <h1><?php esc_html_e('Live Chat Team & Berechtigungen', 'paxdesign-booking'); ?></h1>
    <p><?php esc_html_e('Verwalten Sie Mitarbeiter-Zugang zur Live-Chat-App und zum Admin-Panel. Der Hauptadministrator hat immer volle Rechte.', 'paxdesign-booking'); ?></p>

    <?php if ($updated) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Gespeichert.', 'paxdesign-booking'); ?></p></div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <h2><?php esc_html_e('Mitarbeiter hinzufügen / bearbeiten', 'paxdesign-booking'); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="card" style="max-width:720px;padding:16px;">
        <?php wp_nonce_field('paxdesign_live_chat_staff'); ?>
        <input type="hidden" name="action" value="paxdesign_live_chat_save_staff" />
        <table class="form-table">
            <tr>
                <th><label for="user_email"><?php esc_html_e('WordPress E-Mail', 'paxdesign-booking'); ?></label></th>
                <td><input type="email" name="user_email" id="user_email" class="regular-text" required placeholder="mitarbeiter@example.com" /></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Aktiv', 'paxdesign-booking'); ?></th>
                <td><label><input type="checkbox" name="enabled" value="1" checked /> <?php esc_html_e('Zugang erlauben', 'paxdesign-booking'); ?></label></td>
            </tr>
        </table>
        <fieldset>
            <legend><strong><?php esc_html_e('Berechtigungen', 'paxdesign-booking'); ?></strong></legend>
            <?php foreach ($labels as $key => $label) : ?>
                <p><label><input type="checkbox" name="perm_<?php echo esc_attr($key); ?>" value="1" <?php checked($key, PAXdesign_Live_Chat_Permissions::PERM_VIEW_CHATS, false); ?> /> <?php echo esc_html($label); ?></label></p>
            <?php endforeach; ?>
        </fieldset>
        <p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Speichern', 'paxdesign-booking'); ?></button></p>
    </form>

    <h2><?php esc_html_e('Aktuelles Team', 'paxdesign-booking'); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'paxdesign-booking'); ?></th>
                <th><?php esc_html_e('E-Mail', 'paxdesign-booking'); ?></th>
                <th><?php esc_html_e('Status', 'paxdesign-booking'); ?></th>
                <th><?php esc_html_e('Rechte', 'paxdesign-booking'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($staff_list)) : ?>
                <tr><td colspan="5"><?php esc_html_e('Noch keine Mitarbeiter konfiguriert.', 'paxdesign-booking'); ?></td></tr>
            <?php else : ?>
                <?php foreach ($staff_list as $member) : ?>
                    <tr>
                        <td><?php echo esc_html($member['name']); ?></td>
                        <td><?php echo esc_html($member['email']); ?></td>
                        <td><?php echo !empty($member['enabled']) ? esc_html__('Aktiv', 'paxdesign-booking') : esc_html__('Inaktiv', 'paxdesign-booking'); ?></td>
                        <td>
                            <?php
                            $active = array();
                            foreach ($labels as $key => $label) {
                                if (!empty($member['permissions'][$key])) {
                                    $active[] = $label;
                                }
                            }
                            echo esc_html(implode(', ', $active));
                            ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                <?php wp_nonce_field('paxdesign_live_chat_remove_staff'); ?>
                                <input type="hidden" name="action" value="paxdesign_live_chat_remove_staff" />
                                <input type="hidden" name="user_id" value="<?php echo (int) $member['user_id']; ?>" />
                                <button type="submit" class="button-link-delete" onclick="return confirm('<?php echo esc_js(__('Entfernen?', 'paxdesign-booking')); ?>');"><?php esc_html_e('Entfernen', 'paxdesign-booking'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
