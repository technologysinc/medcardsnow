<?php
/**
 * Plugin Name:       MCN Core
 * Description:       Med Cards Now site features (patient login, profile form, pricing, ID card, checkout) as independent modules. A broken module is switched off on its own instead of taking the whole site down. Manage under Tools > MCN Modules.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Med Cards Now
 * License:           GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCN_CORE_VERSION', '1.2.0');
define('MCN_CORE_DIR', plugin_dir_path(__FILE__));

require_once MCN_CORE_DIR . 'includes/manifest.php';
require_once MCN_CORE_DIR . 'includes/loader.php';

if (is_admin()) {
    require_once MCN_CORE_DIR . 'includes/admin.php';
}

/*
 * Modules load right after the theme, at the same point the old functions.php
 * code ran. Loading here (not earlier) also lets the loader see whether the old
 * functions.php is still active, so the two can never clash.
 */
add_action('after_setup_theme', 'mcn_core_load_modules', PHP_INT_MIN);
