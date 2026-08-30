<?php
if (!defined('ABSPATH')) {
    exit;
}
$scanner = $scanner ?? null;
$identity = $identity ?? array('identified' => false);
$scan_error = $scan_error ?? '';
$history = $history ?? array();
$drivers = $drivers ?? array();
$perms = $config['permissions'] ?? array();
?><!doctype html>
<html lang="<?php echo esc_attr($locale); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($scanner ? ($scanner['scanner_code'] . ' / ' . $scanner['serial_number']) : $i18n['scan.title']); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(ALB_SCANNER_PLUGIN_URL . 'assets/css/app.css?ver=' . ALB_SCANNER_VERSION); ?>">
</head>
<body class="scan-body">
    <div class="scan-page">
        <div class="login-brand">
            <img src="<?php echo esc_url($config['logo']); ?>" alt="Albatros" width="200" height="70">
        </div>
        <h1><?php echo esc_html($config['company']); ?></h1>
        <div class="login-sub"><?php echo esc_html($i18n['scan.title']); ?></div>
        <?php if ($scan_error) : ?>
            <div class="msg msg-error"><?php echo esc_html($scan_error); ?></div>
        <?php endif; ?>
        <?php if (!$scanner) : ?>
            <div class="msg msg-error"><?php echo esc_html($i18n['scan.not_found']); ?></div>
        <?php elseif (empty($identity['identified'])) : ?>
            <form method="post" class="card form-grid scan-identify">
                <?php wp_nonce_field('alb_scan'); ?>
                <input type="hidden" name="alb_action" value="identify">
                <div class="field wide">
                    <label for="full_name"><?php echo esc_html($i18n['scan.full_name']); ?></label>
                    <input id="full_name" name="full_name" type="text" autocomplete="name" required minlength="3" maxlength="80">
                </div>
                <p class="hint wide"><?php echo esc_html($i18n['scan.identify_hint']); ?></p>
                <div class="wide">
                    <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['scan.continue']); ?></button>
                </div>
            </form>
        <?php else : ?>
            <?php if (!empty($scanner['deleted_at'])) : ?>
                <div class="msg msg-warn"><?php echo esc_html($i18n['scanner.deleted_banner']); ?></div>
            <?php endif; ?>
            <div class="msg msg-ok">
                <?php echo esc_html($i18n['scan.recorded']); ?><br>
                <?php echo esc_html($scanner['scanner_code'] . ' / ' . $scanner['serial_number']); ?><br>
                <?php echo esc_html(($identity['kind'] === 'user' ? $i18n['scan.signed_in_as'] : $i18n['scan.guest_as']) . ' ' . $identity['actor_name']); ?>
            </div>
            <div class="card">
                <h2><?php echo esc_html($scanner['scanner_code'] . ' / ' . $scanner['serial_number']); ?></h2>
                <div class="kv">
                    <div class="k"><?php echo esc_html($i18n['scanner.brand']); ?></div><div><?php echo esc_html($scanner['brand']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.model']); ?></div><div><?php echo esc_html($scanner['model']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.serial']); ?></div><div><?php echo esc_html($scanner['serial_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.phone']); ?></div><div><?php echo esc_html($scanner['phone_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.driver']); ?></div><div><?php echo esc_html($scanner['driver_name'] ?: $i18n['scanner.no_driver']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.handover_date']); ?></div><div><?php echo esc_html($scanner['handover_date_display']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['common.status']); ?></div><div><?php echo esc_html($i18n['status.' . $scanner['status']] ?? $scanner['status']); ?></div>
                    <?php if (!empty($scanner['notes'])) : ?>
                        <div class="k"><?php echo esc_html($i18n['common.notes']); ?></div><div><?php echo esc_html($scanner['notes']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (empty($scanner['deleted_at']) && (!empty($perms['assign']) || !empty($perms['status']))) : ?>
                <div class="card scan-actions">
                    <h2><?php echo esc_html($i18n['common.actions']); ?></h2>
                    <div class="body scan-action-pad">
                        <?php if (!empty($perms['assign'])) : ?>
                            <form method="post">
                                <?php wp_nonce_field('alb_scan'); ?>
                                <input type="hidden" name="alb_action" value="take_over">
                                <div class="field">
                                    <label><?php echo esc_html($i18n['scanner.driver']); ?></label>
                                    <select name="driver_id" required>
                                        <option value=""><?php echo esc_html($i18n['scanner.no_driver']); ?></option>
                                        <?php foreach ($drivers as $driver) : ?>
                                            <?php if ($driver['status'] !== 'active') { continue; } ?>
                                            <option value="<?php echo (int) $driver['id']; ?>" <?php selected((int) $scanner['current_driver_id'], (int) $driver['id']); ?>>
                                                <?php echo esc_html($driver['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['scanner.take_over']); ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($perms['status'])) : ?>
                            <form method="post">
                                <?php wp_nonce_field('alb_scan'); ?>
                                <input type="hidden" name="alb_action" value="return">
                                <button class="btn btn-block btn-sec" type="submit"><?php echo esc_html($i18n['scanner.return_device']); ?></button>
                            </form>
                            <form method="post">
                                <?php wp_nonce_field('alb_scan'); ?>
                                <input type="hidden" name="alb_action" value="mark_defective">
                                <button class="btn btn-block btn-sec" type="submit"><?php echo esc_html($i18n['scanner.mark_defective']); ?></button>
                            </form>
                            <form method="post">
                                <?php wp_nonce_field('alb_scan'); ?>
                                <input type="hidden" name="alb_action" value="mark_lost">
                                <button class="btn btn-block btn-danger" type="submit"><?php echo esc_html($i18n['scanner.mark_lost']); ?></button>
                            </form>
                            <form method="post">
                                <?php wp_nonce_field('alb_scan'); ?>
                                <input type="hidden" name="alb_action" value="status">
                                <div class="field">
                                    <label><?php echo esc_html($i18n['scanner.change_status']); ?></label>
                                    <select name="status">
                                        <?php foreach (Alb_Scanners::statuses() as $status) : ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected($scanner['status'], $status); ?>>
                                                <?php echo esc_html($i18n['status.' . $status] ?? $status); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-block btn-sec" type="submit"><?php echo esc_html($i18n['scanner.change_status']); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (!is_user_logged_in()) : ?>
                <p class="hint"><?php echo esc_html($i18n['scan.login_for_actions']); ?></p>
                <a class="btn btn-block btn-sec" href="<?php echo esc_url(home_url('/login?next=' . rawurlencode('/s/' . $scanner['qr_token']))); ?>"><?php echo esc_html($i18n['login.submit']); ?></a>
            <?php endif; ?>
            <?php if (!empty($perms['view_record'])) : ?>
                <a class="btn btn-block" href="<?php echo esc_url(home_url('/scanners/' . $scanner['id'])); ?>"><?php echo esc_html($i18n['scan.open_record']); ?></a>
            <?php endif; ?>
            <?php if ($history) : ?>
                <div class="card">
                    <h2><?php echo esc_html($i18n['scan.history']); ?></h2>
                    <ul class="history">
                        <?php foreach ($history as $event) : ?>
                            <li>
                                <?php echo esc_html($event['at_display']); ?> —
                                <?php echo esc_html($event['actor_name']); ?>:
                                <?php echo esc_html($i18n['scan.action.' . $event['action']] ?? $event['action']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="lang-inline">
            <a href="<?php echo esc_url(add_query_arg('alb_lang', 'de')); ?>" class="<?php echo $locale === 'de' ? 'active' : ''; ?>">Deutsch</a>
            <a href="<?php echo esc_url(add_query_arg('alb_lang', 'en')); ?>" class="<?php echo $locale === 'en' ? 'active' : ''; ?>">English</a>
            <a href="<?php echo esc_url(add_query_arg('alb_lang', 'tr')); ?>" class="<?php echo $locale === 'tr' ? 'active' : ''; ?>">Türkçe</a>
        </div>
        <p class="login-official">
            <span><?php echo esc_html($i18n['official.website']); ?></span>
            <a href="<?php echo esc_url($config['official_url']); ?>" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a>
        </p>
    </div>
</body>
</html>
