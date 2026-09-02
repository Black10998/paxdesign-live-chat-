<?php
if (!defined('ABSPATH')) {
    exit;
}
$scanner = $scanner ?? null;
$photo_url = $config['photo_url'] ?? '';
$has_driver = $scanner && !empty($scanner['current_driver_id']);
$driver_name = $has_driver ? ($scanner['driver_name'] ?: $i18n['scanner.no_driver']) : $i18n['scanner.no_driver'];
$driver_phone = $has_driver && $scanner['driver_phone'] !== '' ? $scanner['driver_phone'] : '—';
$driver_branch = $has_driver ? trim((string) ($scanner['driver_branch'] ?? '')) : '';
$standort = $driver_branch !== ''
    ? ($scanner['driver_branch_label'] ?? '')
    : ($scanner['branch_label'] ?? '');
if ($standort === '' || $standort === '—') {
    $standort = $scanner['branch_label'] ?? ($i18n['branch.empty'] ?? '—');
}
$status_label = $scanner ? ($i18n['status.' . $scanner['status']] ?? $scanner['status']) : '';
$handover = $scanner ? ($scanner['handover_at_display'] ?: $scanner['handover_date_display'] ?: '—') : '—';
$title = $scanner
    ? ($has_driver ? ($scanner['driver_name'] . ' / ' . $scanner['scanner_code']) : ($scanner['scanner_code'] . ' / ' . $scanner['serial_number']))
    : $i18n['scan.title'];
?><!doctype html>
<html lang="<?php echo esc_attr($locale); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(Alb_Frontend::asset_url('assets/css/app.css')); ?>">
</head>
<body class="scan-body">
    <div class="public-record">
        <div class="login-brand">
            <img src="<?php echo esc_url($config['logo']); ?>" alt="Albatros" width="200" height="70">
        </div>
        <h1><?php echo esc_html($config['company']); ?></h1>
        <p class="public-kicker"><?php echo esc_html($i18n['scan.readonly']); ?></p>
        <?php if (!$scanner) : ?>
            <div class="msg msg-error"><?php echo esc_html($i18n['scan.not_found']); ?></div>
        <?php else : ?>
            <p class="hint public-hint"><?php echo esc_html($i18n['scan.public_hint']); ?></p>
            <section class="card public-card">
                <div class="public-hero-row">
                    <?php echo Alb_Frontend::device_visual_html($scanner, $i18n); ?>
                    <div class="public-hero">
                        <?php if ($photo_url) : ?>
                            <img class="face-public" src="<?php echo esc_url($photo_url); ?>" alt="">
                        <?php else : ?>
                            <div class="face-public face-public-empty" aria-hidden="true"></div>
                        <?php endif; ?>
                        <div class="public-hero-text">
                            <h2><?php echo esc_html($i18n['scanner.driver']); ?></h2>
                            <strong><?php echo esc_html($driver_name); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="public-kv">
                    <div class="k"><?php echo esc_html($i18n['driver.phone']); ?></div>
                    <div><?php echo esc_html($driver_phone); ?></div>
                    <div class="k"><?php echo esc_html($i18n['branch.label']); ?></div>
                    <div><?php echo esc_html($standort); ?></div>
                </div>
            </section>
            <section class="card public-card">
                <h2><?php echo esc_html($i18n['scan.assigned_scanner']); ?></h2>
                <div class="public-kv">
                    <div class="k"><?php echo esc_html($i18n['scanner.code']); ?></div>
                    <div><?php echo esc_html($scanner['scanner_code']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.brand']); ?></div>
                    <div><?php echo esc_html($scanner['brand']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.model']); ?></div>
                    <div><?php echo esc_html($scanner['model']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.serial']); ?></div>
                    <div><?php echo esc_html($scanner['serial_number']); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.phone']); ?></div>
                    <div><?php echo esc_html($scanner['phone_number'] !== '' ? $scanner['phone_number'] : '—'); ?></div>
                    <div class="k"><?php echo esc_html($i18n['common.status']); ?></div>
                    <div><?php echo esc_html($status_label); ?></div>
                    <div class="k"><?php echo esc_html($i18n['scanner.handover_date']); ?></div>
                    <div><?php echo esc_html($handover); ?></div>
                </div>
            </section>
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
