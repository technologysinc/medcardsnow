<?php
/**
 * Plugin Name: MCN Timeline Steps
 * Description: A highly customizable horizontal / vertical timeline & process-steps widget for Elementor. Numbers, icons or images, per-step colors, connector styling, full typography and responsive controls.
 * Version:     1.0.0
 * Author:      Med Cards Now
 * Text Domain: mcn-timeline-steps
 * Requires Plugins: elementor
 * Elementor tested up to: 3.25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MCN_TS_VERSION', '1.0.0' );
define( 'MCN_TS_URL', plugin_dir_url( __FILE__ ) );
define( 'MCN_TS_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Register the stylesheet so the widget can declare it as a dependency.
 */
function mcn_ts_register_assets() {
	wp_register_style(
		'mcn-timeline-steps',
		MCN_TS_URL . 'assets/css/mcn-timeline-steps.css',
		array(),
		MCN_TS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'mcn_ts_register_assets' );
add_action( 'elementor/frontend/after_register_styles', 'mcn_ts_register_assets' );

// Make sure the style is present inside the Elementor editor preview too.
add_action( 'elementor/editor/after_enqueue_styles', function () {
	wp_enqueue_style( 'mcn-timeline-steps' );
} );

/**
 * Register the widget with Elementor.
 */
function mcn_ts_register_widget( $widgets_manager ) {
	require_once MCN_TS_PATH . 'widgets/class-mcn-timeline-steps-widget.php';
	$widgets_manager->register( new \MCN_Timeline_Steps_Widget() );
}
add_action( 'elementor/widgets/register', 'mcn_ts_register_widget' );

/**
 * Admin notice if Elementor is missing.
 */
add_action( 'plugins_loaded', function () {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p><strong>MCN Timeline Steps</strong> requires <strong>Elementor</strong> to be installed and active.</p></div>';
		} );
	}
} );
