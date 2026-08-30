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
<body class="login-body">
    <div class="login-box">
        <h1><?php echo esc_html($config['company']); ?></h1>
        <div class="login-sub" data-i18n="login.subtitle"><?php echo esc_html($i18n['login.subtitle']); ?></div>
        <div id="login-msg" hidden class="msg"></div>
        <form id="login-form">
            <div class="field">
                <label for="login"><?php echo esc_html($i18n['login.username']); ?></label>
                <input id="login" name="login" type="text" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password"><?php echo esc_html($i18n['login.password']); ?></label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <label class="row-check">
                <input type="checkbox" name="remember" value="1">
                <span><?php echo esc_html($i18n['login.remember']); ?></span>
            </label>
            <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['login.submit']); ?></button>
        </form>
        <form id="reset-form" hidden>
            <div class="field">
                <label for="reset-login"><?php echo esc_html($i18n['login.username']); ?></label>
                <input id="reset-login" name="login" type="text" autocomplete="username" required>
            </div>
            <p class="hint"><?php echo esc_html($i18n['login.reset_hint']); ?></p>
            <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['login.reset_send']); ?></button>
        </form>
        <div class="login-links">
            <button type="button" id="toggle-reset"><?php echo esc_html($i18n['login.forgot']); ?></button>
        </div>
        <div class="lang-inline">
            <button type="button" data-locale="de" class="<?php echo $locale === 'de' ? 'active' : ''; ?>">Deutsch</button>
            <button type="button" data-locale="en" class="<?php echo $locale === 'en' ? 'active' : ''; ?>">English</button>
            <button type="button" data-locale="tr" class="<?php echo $locale === 'tr' ? 'active' : ''; ?>">Türkçe</button>
        </div>
    </div>
    <script>window.ALB_LOGIN = <?php echo wp_json_encode($config); ?>;</script>
    <script src="<?php echo esc_url(ALB_SCANNER_PLUGIN_URL . 'assets/js/login.js?ver=' . ALB_SCANNER_VERSION); ?>"></script>
</body>
</html>
