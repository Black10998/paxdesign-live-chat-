<?php
if (!defined('ABSPATH')) {
    exit;
}
$scanner = $scanner ?? null;
$scan_error = $scan_error ?? '';
$is_manager = !empty($config['is_manager']);
$employee = $config['employee'] ?? null;
$otp = $config['otp'] ?? null;
$accepted = !empty($config['accepted']);
$sms_ready = !empty($config['sms_ready']);
$perms = $config['permissions'] ?? array();
$public_status = $scanner ? ($i18n['status.' . $scanner['status']] ?? $scanner['status']) : '';
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
        <div class="login-sub"><?php echo esc_html($i18n['handover.title']); ?></div>
        <?php if ($scan_error) : ?>
            <div class="msg msg-error"><?php echo esc_html($scan_error); ?></div>
        <?php endif; ?>
        <?php if (!$scanner) : ?>
            <div class="msg msg-error"><?php echo esc_html($i18n['scan.not_found']); ?></div>
        <?php elseif ($is_manager) : ?>
            <div class="card">
                <h2><?php echo esc_html($scanner['scanner_code'] . ' / ' . $scanner['serial_number']); ?></h2>
                <div class="kv">
                    <div class="k"><?php echo esc_html($i18n['scanner.brand']); ?></div><div><?php echo esc_html($scanner['brand']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.model']); ?></div><div><?php echo esc_html($scanner['model']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.serial']); ?></div><div><?php echo esc_html($scanner['serial_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.phone']); ?></div><div><?php echo esc_html($scanner['phone_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['common.status']); ?></div><div><?php echo esc_html($public_status); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.driver']); ?></div>
                    <div>
                        <?php if (!empty($scanner['driver_photo_url'])) : ?>
                            <img class="face-thumb" src="<?php echo esc_url($scanner['driver_photo_url']); ?>" alt="">
                        <?php endif; ?>
                        <?php echo esc_html($scanner['driver_name'] ?: $i18n['scanner.no_driver']); ?>
                    </div>
                    <div class="k"><?php echo esc_html($i18n['handover.verified_phone']); ?></div><div><?php echo esc_html($scanner['driver_phone'] ?: '—'); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.handover_date']); ?></div><div><?php echo esc_html($scanner['handover_at_display'] ?: $scanner['handover_date_display']); ?></div>
                </div>
                <div class="scan-action-pad">
                    <button type="button" class="btn btn-block" id="copy-qr" data-link="<?php echo esc_attr($scanner['qr_url']); ?>"><?php echo esc_html($i18n['scanner.copy_qr']); ?></button>
                    <a class="btn btn-block btn-sec" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url('https://wa.me/?text=' . rawurlencode($i18n['scanner.whatsapp_text'] . ' ' . $scanner['qr_url'])); ?>"><?php echo esc_html($i18n['scanner.whatsapp']); ?></a>
                    <?php if (!empty($perms['view_record'])) : ?>
                        <a class="btn btn-block btn-sec" href="<?php echo esc_url(home_url('/scanners/' . $scanner['id'])); ?>"><?php echo esc_html($i18n['scan.open_record']); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($accepted && $employee) : ?>
            <div class="msg msg-ok"><?php echo esc_html($i18n['handover.accepted']); ?></div>
            <div class="card">
                <h2><?php echo esc_html($scanner['scanner_code'] . ' / ' . $scanner['serial_number']); ?></h2>
                <div class="kv">
                    <div class="k"><?php echo esc_html($i18n['scanner.brand']); ?></div><div><?php echo esc_html($scanner['brand']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.model']); ?></div><div><?php echo esc_html($scanner['model']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.serial']); ?></div><div><?php echo esc_html($scanner['serial_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['handover.employee']); ?></div>
                    <div>
                        <img class="face-thumb" src="<?php echo esc_url(Alb_Photos::selfie_url($scanner['qr_token'])); ?>" alt="">
                        <?php echo esc_html($employee['name']); ?>
                    </div>
                    <div class="k"><?php echo esc_html($i18n['handover.verified_phone']); ?></div><div><?php echo esc_html($employee['phone']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['handover.taken_at']); ?></div><div><?php echo esc_html($scanner['handover_at_display']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['common.status']); ?></div><div><?php echo esc_html($public_status); ?></div>
                </div>
            </div>
        <?php elseif ($employee) : ?>
            <div class="card">
                <h2><?php echo esc_html($scanner['scanner_code'] . ' / ' . $scanner['serial_number']); ?></h2>
                <div class="kv">
                    <div class="k"><?php echo esc_html($i18n['scanner.brand']); ?></div><div><?php echo esc_html($scanner['brand']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.model']); ?></div><div><?php echo esc_html($scanner['model']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.serial']); ?></div><div><?php echo esc_html($scanner['serial_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.phone']); ?></div><div><?php echo esc_html($scanner['phone_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['common.status']); ?></div><div><?php echo esc_html($public_status); ?></div>
                </div>
            </div>
            <form method="post" class="card form-grid">
                <?php wp_nonce_field('alb_scan'); ?>
                <input type="hidden" name="alb_action" value="accept">
                <div class="wide person-row">
                    <img class="face-thumb" src="<?php echo esc_url(Alb_Photos::selfie_url($scanner['qr_token'])); ?>" alt="">
                    <div>
                        <strong><?php echo esc_html($employee['name']); ?></strong><br>
                        <?php echo esc_html($employee['phone']); ?>
                    </div>
                </div>
                <p class="hint wide"><?php echo esc_html($i18n['handover.confirm_hint']); ?></p>
                <div class="wide">
                    <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['scanner.take_over']); ?></button>
                </div>
            </form>
        <?php elseif ($otp) : ?>
            <form method="post" class="card form-grid">
                <?php wp_nonce_field('alb_scan'); ?>
                <input type="hidden" name="alb_action" value="verify">
                <input type="hidden" name="phone" value="<?php echo esc_attr($otp['phone']); ?>">
                <p class="hint wide"><?php echo esc_html($i18n['otp.sent_to']); ?> <?php echo esc_html($otp['phone_masked']); ?></p>
                <div class="field wide">
                    <label for="otp_code"><?php echo esc_html($i18n['otp.code']); ?></label>
                    <input id="otp_code" name="otp_code" type="text" inputmode="numeric" autocomplete="one-time-code" required minlength="6" maxlength="6">
                </div>
                <div class="wide">
                    <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['otp.verify']); ?></button>
                </div>
            </form>
        <?php else : ?>
            <div class="card">
                <h2><?php echo esc_html($scanner['scanner_code'] . ' / ' . $scanner['serial_number']); ?></h2>
                <div class="kv">
                    <div class="k"><?php echo esc_html($i18n['scanner.brand']); ?></div><div><?php echo esc_html($scanner['brand']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.model']); ?></div><div><?php echo esc_html($scanner['model']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.serial']); ?></div><div><?php echo esc_html($scanner['serial_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.phone']); ?></div><div><?php echo esc_html($scanner['phone_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['common.status']); ?></div><div><?php echo esc_html($public_status); ?></div>
                </div>
            </div>
            <?php if (!$sms_ready) : ?>
                <div class="msg msg-warn"><?php echo esc_html($i18n['otp.error.not_configured']); ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="card form-grid">
                <?php wp_nonce_field('alb_scan'); ?>
                <input type="hidden" name="alb_action" value="register">
                <div class="field wide">
                    <label for="full_name"><?php echo esc_html($i18n['scan.full_name']); ?></label>
                    <input id="full_name" name="full_name" type="text" autocomplete="name" required minlength="3" maxlength="80">
                </div>
                <div class="field wide">
                    <label for="phone"><?php echo esc_html($i18n['handover.phone']); ?></label>
                    <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required>
                </div>
                <div class="field wide">
                    <label for="selfie"><?php echo esc_html($i18n['handover.selfie']); ?></label>
                    <input id="selfie" name="selfie" type="file" accept="image/jpeg,image/png,image/webp,image/*" capture="user" required>
                </div>
                <p class="hint wide"><?php echo esc_html($i18n['handover.privacy_notice']); ?></p>
                <p class="hint wide"><?php echo esc_html($i18n['handover.phone_notice']); ?></p>
                <div class="wide">
                    <button class="btn btn-block" type="submit" <?php disabled(!$sms_ready); ?>><?php echo esc_html($i18n['otp.send']); ?></button>
                </div>
            </form>
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
    <script>
    (function () {
      var btn = document.getElementById('copy-qr');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var link = btn.getAttribute('data-link') || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(function () { btn.textContent = <?php echo wp_json_encode($i18n['scanner.link_copied']); ?>; });
        }
      });
    })();
    </script>
</body>
</html>
