<?php
/**
 * Plugin Name: Albatros Scanner Management
 * Description: Internal scanner, driver, and handover management system for albatros-scanner.shop.
 * Version: 1.1.0
 * Author: Ahmad Al Khalaf
 * License: GPL v2 or later
 * Text Domain: albatros-scanner
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('ALB_SCANNER_VERSION')) {
    return;
}

define('ALB_SCANNER_VERSION', '1.1.0');
define('ALB_SCANNER_DB_VERSION', '1.1.0');
define('ALB_SCANNER_PLUGIN_FILE', __FILE__);
define('ALB_SCANNER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALB_SCANNER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALB_SCANNER_OFFICIAL_URL', 'https://www.albatros-express.at/');
define('ALB_SCANNER_LOGO_FILE', 'assets/img/albatros-logo.jpeg');

require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-i18n.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-capabilities.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-audit.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-install.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-settings.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-auth.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-scanners.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-scan.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-drivers.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-users.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-export.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-rest.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-frontend.php';
require_once ALB_SCANNER_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('Alb_Install', 'activate'));
register_deactivation_hook(__FILE__, array('Alb_Install', 'deactivate'));

Alb_Plugin::init();
