<?php
/**
 * Plugin Name:       Kinetic Motion for Elementor
 * Description:       Scroll animations, smooth scrolling, kinetic text, marquees and WebGL / three.js effects for Elementor containers and widgets — powered by GSAP. Loads only on pages that use it.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 * Author:            Med Cards Now
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kinetic-motion
 * Elementor tested up to: 4.0
 */

defined( 'ABSPATH' ) || exit;

define( 'KMO_VERSION', '1.0.0' );
define( 'KMO_FILE', __FILE__ );
define( 'KMO_DIR', plugin_dir_path( __FILE__ ) );
define( 'KMO_URL', plugin_dir_url( __FILE__ ) );
define( 'KMO_MIN_ELEMENTOR', '3.18.0' );

require_once KMO_DIR . 'includes/settings.php';
require_once KMO_DIR . 'includes/assets.php';

/**
 * Boot after all plugins are loaded so Elementor is available.
 */
add_action(
	'plugins_loaded',
	function () {
		// Site-wide features (smooth scroll, cursor, progress bar, page transitions) work even without Elementor.
		KMO_Settings::init();
		KMO_Assets::init();

		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', 'kmo_notice_missing_elementor' );
			return;
		}

		if ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, KMO_MIN_ELEMENTOR, '<' ) ) {
			add_action( 'admin_notices', 'kmo_notice_old_elementor' );
			return;
		}

		require_once KMO_DIR . 'includes/extension.php';
		require_once KMO_DIR . 'includes/plugin.php';

		KMO_Plugin::instance();
	}
);

function kmo_notice_missing_elementor() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Kinetic Motion needs the Elementor plugin. Install and activate Elementor to use the animation controls and widgets.', 'kinetic-motion' ) . '</p></div>';
}

function kmo_notice_old_elementor() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}
	/* translators: %s: version number */
	echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'Kinetic Motion needs Elementor %s or newer. Please update Elementor.', 'kinetic-motion' ), KMO_MIN_ELEMENTOR ) ) . '</p></div>';
}
