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
    <link rel="stylesheet" href="<?php echo esc_url(Alb_Frontend::asset_url('assets/css/app.css')); ?>">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                <strong><?php echo esc_html($config['company']); ?></strong>
                <span>Scanner Management</span>
            </div>
            <nav class="nav" id="app-nav" aria-label=""></nav>
            <div class="nav-people" id="nav-people">
                <div class="nav-person">
                    <?php if (!empty($config['team']['ceo_photo'])) : ?>
                        <img class="nav-avatar" src="<?php echo esc_url($config['team']['ceo_photo']); ?>" alt="<?php echo esc_attr($config['team']['ceo_name'] ?? 'Burak Ünver'); ?>">
                    <?php else : ?>
                        <span class="nav-avatar nav-avatar--initials" aria-hidden="true">BU</span>
                    <?php endif; ?>
                    <span class="nav-person-text">
                        <strong><?php echo esc_html($config['team']['ceo_name'] ?? 'Burak Ünver'); ?></strong>
                        <span class="nav-person-role" data-people-role="ceo"><?php echo esc_html($config['i18n']['help.role.ceo'] ?? 'Geschäftsführer'); ?></span>
                    </span>
                </div>
                <div class="nav-person nav-person--dev">
                    <span class="nav-avatar nav-avatar--initials" aria-hidden="true">AA</span>
                    <span class="nav-person-text">
                        <strong><?php echo esc_html($config['team']['developer_name'] ?? 'Ahmad Al Khalaf'); ?></strong>
                        <span class="nav-person-role" data-people-role="dev"><?php echo esc_html($config['i18n']['help.role.dev'] ?? 'Programmierer / Entwickler'); ?></span>
                    </span>
                </div>
                <div class="nav-person">
                    <?php if (!empty($config['team']['support_photo'])) : ?>
                        <img class="nav-avatar nav-avatar--logo" src="<?php echo esc_url($config['team']['support_photo']); ?>" alt="">
                    <?php else : ?>
                        <span class="nav-avatar nav-avatar--initials" aria-hidden="true">AE</span>
                    <?php endif; ?>
                    <span class="nav-person-text">
                        <strong><?php echo esc_html($config['team']['support_name'] ?? 'Albatros Express'); ?></strong>
                        <span class="nav-person-role" data-people-role="support"><?php echo esc_html($config['i18n']['help.role.support'] ?? 'Technischer Support'); ?></span>
                    </span>
                </div>
            </div>
            <div class="sidebar-foot">
                <div class="sys-version">
                    <span class="sys-version-text"><?php echo esc_html($config['i18n']['settings.version'] ?? 'Version'); ?> <strong id="app-version"><?php echo esc_html($config['version'] ?? ALB_SCANNER_VERSION); ?></strong></span>
                    <button type="button" class="sys-update" id="update-check" title="<?php echo esc_attr($config['i18n']['update.check'] ?? ''); ?>" aria-label="<?php echo esc_attr($config['i18n']['update.check'] ?? ''); ?>">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">
                            <path d="M20 12a8 8 0 1 1-2.2-5.5"></path>
                            <path d="M20 4v6h-6"></path>
                        </svg>
                    </button>
                </div>
                <p class="sys-update-msg" id="update-status" hidden></p>
                <div class="official-block">
                    <span><?php echo esc_html($config['i18n']['official.website']); ?></span>
                    <a href="<?php echo esc_url($config['official_url']); ?>" target="_blank" rel="noopener noreferrer">www.albatros-express.at</a>
                </div>
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
    <script src="<?php echo esc_url(Alb_Frontend::asset_url('assets/js/qrcode.min.js')); ?>"></script>
    <script src="<?php echo esc_url(Alb_Frontend::asset_url('assets/js/app.js')); ?>"></script>
</body>
</html>
