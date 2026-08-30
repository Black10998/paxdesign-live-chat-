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
            <nav class="nav" id="app-nav"></nav>
            <div class="sidebar-foot">
                <span><?php echo esc_html($config['i18n']['official.website']); ?></span>
                <a href="<?php echo esc_url($config['official_url']); ?>" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a>
            </div>
        </aside>
        <section class="main">
            <header class="topbar">
                <input class="search" id="global-search" type="search" placeholder="">
                <div class="top-user">
                    <img class="header-logo" src="<?php echo esc_url($config['logo']); ?>" alt="Albatros" width="200" height="70">
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
