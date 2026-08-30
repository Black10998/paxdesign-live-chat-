<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html lang="<?php echo esc_attr($locale); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($config['company']); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(ALB_SCANNER_PLUGIN_URL . 'assets/css/app.css?ver=' . ALB_SCANNER_VERSION); ?>">
</head>
<body class="scan-body">
    <div class="scan-page">
        <div class="login-brand">
            <img src="<?php echo esc_url($config['logo']); ?>" alt="Albatros" width="200" height="70">
        </div>
        <h1><?php echo esc_html($config['company']); ?></h1>
        <div class="msg msg-warn"><?php echo esc_html($i18n['access.denied']); ?></div>
        <p class="hint"><?php echo esc_html($i18n['access.denied_hint']); ?></p>
        <?php if (is_user_logged_in()) : ?>
            <a class="btn btn-block btn-sec" href="<?php echo esc_url(wp_logout_url(home_url('/login'))); ?>"><?php echo esc_html($i18n['nav.logout']); ?></a>
        <?php endif; ?>
        <p class="login-official">
            <span><?php echo esc_html($i18n['official.website']); ?></span>
            <a href="<?php echo esc_url($config['official_url']); ?>" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a>
        </p>
    </div>
</body>
</html>
