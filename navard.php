<?php

/**
 * Plugin Name: نورد
 * Description: درون‌ریزی و بروزرسانی خودکار قیمت محصولات فولادی از API
 * Version: 1.0.0
 * Author: Navard
 * Text Domain: navard
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('NAVARD_VERSION', '1.0.0');
define('NAVARD_FILE', __FILE__);
define('NAVARD_DIR', plugin_dir_path(__FILE__));
define('NAVARD_URL', plugin_dir_url(__FILE__));

/*
 * ---------------------------------------------------------
 *  API ENDPOINTS  —  edit these two constants to change servers
 * ---------------------------------------------------------
 *  Normalize: no trailing slash needed, will be trimmed.
 */
define('NAVARD_API_MAIN',      'http://localhost/ahan2/v2');
define('NAVARD_API_EMERGENCY', ''); // fill in later, e.g. 'https://backup.example.com/v2'

require_once NAVARD_DIR . 'includes/Autoloader.php';
\Navard\Autoloader::register();

register_activation_hook(__FILE__, ['\Navard\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\Navard\Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
    \Navard\Plugin::instance()->boot();
}, 5);
