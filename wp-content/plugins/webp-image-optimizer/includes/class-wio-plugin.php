<?php
/**
 * Bootstrap: wires the components together.
 *
 * @package WebP_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
final class WIO_Plugin {

	/**
	 * Hook everything up. Runs on plugins_loaded.
	 */
	public static function init() {
		load_plugin_textdomain( 'webp-image-optimizer', false, dirname( plugin_basename( WIO_FILE ) ) . '/languages' );

		self::maybe_upgrade();

		WIO_Settings::init();
		WIO_Optimizer::init();
		WIO_Picture::init();

		if ( is_admin() && class_exists( 'WIO_Admin' ) ) {
			WIO_Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WIO_CLI' ) ) {
			WP_CLI::add_command( 'webp-optimizer', 'WIO_CLI' );
		}
	}

	/**
	 * Activation: store defaults and apply the delivery mode.
	 */
	public static function activate() {
		add_option( WIO_Settings::OPTION, WIO_Settings::defaults() );
		update_option( 'wio_version', WIO_VERSION, false );
		WIO_Settings::flush_cache();
		WIO_Rewrite::sync();
	}

	/**
	 * Deactivation: remove server rules and scheduled jobs. Settings and WebP
	 * files are kept so re-activating picks up where it left off.
	 */
	public static function deactivate() {
		WIO_Rewrite::remove();
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( 'wio_process_attachment' );
		}
		delete_transient( 'wio_bulk_lock' );
		delete_transient( 'wio_stats' );
	}

	/**
	 * Run upgrade routines when the stored version differs.
	 */
	private static function maybe_upgrade() {
		$stored = get_option( 'wio_version' );
		if ( WIO_VERSION === $stored ) {
			return;
		}
		update_option( 'wio_version', WIO_VERSION, false );
	}
}
