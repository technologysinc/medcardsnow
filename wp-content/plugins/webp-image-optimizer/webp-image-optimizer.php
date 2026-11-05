<?php
/**
 * Plugin Name:       WebP Image Optimizer
 * Description:       Creates smaller WebP copies of your JPEG, PNG and GIF images and serves them to browsers that support WebP. Your original files are never modified.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WebP Image Optimizer
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webp-image-optimizer
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

define( 'WIO_VERSION', '1.0.0' );
define( 'WIO_FILE', __FILE__ );
define( 'WIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WIO_URL', plugin_dir_url( __FILE__ ) );

/*
 * Stop before loading anything that needs a newer PHP. This file itself only
 * uses syntax that parses on very old PHP versions, so the site never breaks.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) || ! function_exists( 'wp_get_upload_dir' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'WebP Image Optimizer needs PHP 7.4+ and WordPress 6.2+. The plugin is inactive until the server is updated.', 'webp-image-optimizer' );
			echo '</p></div>';
		}
	);
	return;
}

require_once WIO_DIR . 'includes/class-wio-settings.php';
require_once WIO_DIR . 'includes/class-wio-converter.php';
require_once WIO_DIR . 'includes/class-wio-optimizer.php';
require_once WIO_DIR . 'includes/class-wio-picture.php';
require_once WIO_DIR . 'includes/class-wio-rewrite.php';
require_once WIO_DIR . 'includes/class-wio-plugin.php';

if ( is_admin() ) {
	require_once WIO_DIR . 'includes/class-wio-admin.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WIO_DIR . 'includes/class-wio-cli.php';
}

register_activation_hook( __FILE__, array( 'WIO_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WIO_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WIO_Plugin', 'init' ) );
