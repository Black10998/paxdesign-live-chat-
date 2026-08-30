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
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <strong><?php echo esc_html($config['company']); ?></strong>
                <span>Scanner Management</span>
            </div>
            <nav class="nav" id="app-nav" aria-label=""></nav>
            <div class="sidebar-foot">
                <span><?php echo esc_html($config['i18n']['official.website']); ?></span>
                <a href="<?php echo esc_url($config['official_url']); ?>" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a>
            </div>
        </aside>
        <section class="main">
            <header class="topbar">
                <img class="header-logo" src="<?php echo esc_url($config['logo']); ?>" alt="Albatros" width="200" height="70">
                <div class="page-context" id="page-context"></div>
                <input class="search" id="global-search" type="search" placeholder="" aria-label="">
                <a href="/help" class="btn btn-sec header-help" id="help-btn">
                    <svg class="ui-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M9.6 9.2a2.4 2.4 0 1 1 3.3 2.2c-.8.4-1.3 1-1.3 1.8V14"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                    <span id="help-btn-label"></span>
                </a>
                <div class="top-user">
                    <label class="visually-hidden" for="lang-switch"></label>
                    <select id="lang-switch"></select>
                    <span id="current-user"></span>
                    <button type="button" class="btn btn-sec" id="logout-btn"></button>
                </div>
            </header>
            <div class="content" id="app-root"></div>
        </section>
    </div>
    <script>window.ALB = <?php echo wp_json_encode($config); ?>;</script>
    <script src="<?php echo esc_url(ALB_SCANNER_PLUGIN_URL . 'assets/js/qrcode.min.js?ver=' . ALB_SCANNER_VERSION); ?>"></script>
    <script src="<?php echo esc_url(ALB_SCANNER_PLUGIN_URL . 'assets/js/app.js?ver=' . ALB_SCANNER_VERSION); ?>"></script>
</body>
</html>
