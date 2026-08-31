<?php
if (!defined('ABSPATH')) {
    exit;
}
$login_error = $login_error ?? '';
$login_notice = $login_notice ?? '';
$login_notice_ok = $login_notice_ok ?? false;
$show_reset = isset($_POST['alb_action']) && $_POST['alb_action'] === 'reset';
?><!doctype html>
<html lang="<?php echo esc_attr($locale); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($config['company']); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(Alb_Frontend::asset_url('assets/css/app.css')); ?>">
</head>
<body class="login-body">
    <div class="login-box">
        <div class="login-brand">
            <img src="<?php echo esc_url($config['logo']); ?>" alt="Albatros" width="200" height="70">
        </div>
        <h1><?php echo esc_html($config['company']); ?></h1>
        <div class="login-sub"><?php echo esc_html($i18n['login.subtitle']); ?></div>
        <?php if ($login_error) : ?>
            <div class="msg msg-error"><?php echo esc_html($login_error); ?></div>
        <?php elseif ($login_notice) : ?>
            <div class="msg <?php echo $login_notice_ok ? 'msg-ok' : 'msg-error'; ?>"><?php echo esc_html($login_notice); ?></div>
        <?php endif; ?>
        <form id="login-form" method="post" action="" <?php echo $show_reset ? 'hidden' : ''; ?>>
            <input type="hidden" name="alb_action" value="login">
            <?php wp_nonce_field('alb_login'); ?>
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
        <form id="reset-form" method="post" action="" <?php echo $show_reset ? '' : 'hidden'; ?>>
            <input type="hidden" name="alb_action" value="reset">
            <?php wp_nonce_field('alb_reset'); ?>
            <div class="field">
                <label for="reset-login"><?php echo esc_html($i18n['login.username']); ?></label>
                <input id="reset-login" name="login" type="text" autocomplete="username" required>
            </div>
            <p class="hint"><?php echo esc_html($i18n['login.reset_hint']); ?></p>
            <button class="btn btn-block" type="submit"><?php echo esc_html($i18n['login.reset_send']); ?></button>
        </form>
        <div class="login-links">
            <button type="button" id="toggle-reset"><?php echo esc_html($show_reset ? $i18n['login.reset_back'] : $i18n['login.forgot']); ?></button>
        </div>
        <div class="lang-inline">
            <button type="button" data-locale="de" class="<?php echo $locale === 'de' ? 'active' : ''; ?>">Deutsch</button>
            <button type="button" data-locale="en" class="<?php echo $locale === 'en' ? 'active' : ''; ?>">English</button>
            <button type="button" data-locale="tr" class="<?php echo $locale === 'tr' ? 'active' : ''; ?>">Türkçe</button>
        </div>
        <p class="login-official">
            <span><?php echo esc_html($i18n['official.website']); ?></span>
            <a href="<?php echo esc_url($config['official_url']); ?>" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a>
        </p>
    </div>
    <script>window.ALB_LOGIN = <?php echo wp_json_encode($config); ?>;</script>
    <script src="<?php echo esc_url(Alb_Frontend::asset_url('assets/js/login.js')); ?>"></script>
</body>
</html>
