<?php
/**
 * Plugin Name: Skyboot Custom Icons for Elementor - Elementor Icons library
 * Description: Skyboot custom icons for Elementor is a fantastic custom Elementor icons plugin for the Elementor page builder. Increase Elementor icons library using the plugin. it's a perfect Elementor custom icons plugin for website owners. 
 * Plugin URI:  https://skybootstrap.com/custom-icons-for-elementor
 * Version:     1.2.0
 * Author:      Skybootstrap
 * Author URI:  https://skybootstrap.com
 * License:     GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: skyboot-custom-icons-for-elementor
 * Domain Path: /languages
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Tested up to: 7.0.3
 **/

// Exit if accessed directly
if (!defined('ABSPATH'))
	exit;

// Define Useful Contastant
define('SKB_CIFE_VERSION', '1.2.0');
define('SKB_CIFE_PL_ROOT', __FILE__);
define('SKB_CIFE_PL_URL', plugin_dir_url(SKB_CIFE_PL_ROOT));
define('SKB_CIFE_PL_PATH', plugin_dir_path(SKB_CIFE_PL_ROOT));
define('SKB_CIFE_PLUGIN_BASE', plugin_basename(SKB_CIFE_PL_ROOT));
define('SKB_CIFE_ASSETS', trailingslashit(SKB_CIFE_PL_URL . 'assets'));

// Include Base class and Helper Fuctions
require_once SKB_CIFE_PL_PATH . 'includes/class-base.php';
require_once SKB_CIFE_PL_PATH . 'includes/helper-functions.php';
require_once SKB_CIFE_PL_PATH . 'includes/class-pro.php';

// Admin marketing, onboarding & engagement (loads regardless of Elementor so
// upgrade prompts and onboarding still work).
if (is_admin()) {
	require_once SKB_CIFE_PL_PATH . 'includes/class-admin.php';
	add_action('plugins_loaded', function () {
		new Skb_Cife_Admin();
	});
}

/**
 * Register the activation hook.
 *
 * When the plugin is activated, call the 'activate' method of our base class.
 */
register_activation_hook(SKB_CIFE_PL_ROOT, ['Skb_Cife\Skb_Cife_Base', 'activate']);

/**
 * The main instance of the plugin.
 */
\Skb_Cife\Skb_Cife_Base::instance();