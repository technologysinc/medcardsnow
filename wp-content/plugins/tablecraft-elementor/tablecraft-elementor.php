<?php
/**
 * Plugin Name: TableCraft – Advanced Tables for Elementor
 * Description: Build editable responsive Elementor tables with preloaded templates, custom columns and rows, ACF data, and public Google Sheets CSV connections.
 * Version:     1.0.1
 * Author:      Amanullah Khan
 * Text Domain: tablecraft-elementor
 * Requires at least: 6.3
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TABLECRAFT_VERSION', '1.0.1' );
define( 'TABLECRAFT_FILE', __FILE__ );
define( 'TABLECRAFT_PATH', plugin_dir_path( __FILE__ ) );
define( 'TABLECRAFT_URL', plugin_dir_url( __FILE__ ) );

require_once TABLECRAFT_PATH . 'includes/class-tablecraft-plugin.php';

add_action( 'plugins_loaded', array( 'TableCraft_Plugin', 'instance' ) );
