<?php
/**
 * Plugin Name:       Code Sentinel
 * Plugin URI:        https://example.com/code-sentinel
 * Description:       Deep scanner for WordPress plugins, themes, mu-plugins and scripts. Finds active and passive issues (syntax errors, fatal conflicts, vulnerabilities, deprecations, malware patterns, missing includes, integrity changes) and reports the exact file path and line. Also captures live runtime PHP and JavaScript errors.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Code Sentinel
 * License:           GPL-2.0-or-later
 * Text Domain:       code-sentinel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CS_VERSION', '1.0.0' );
define( 'CS_FILE', __FILE__ );
define( 'CS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CS_URL', plugin_dir_url( __FILE__ ) );

require_once CS_DIR . 'includes/class-cs-util.php';
require_once CS_DIR . 'includes/class-cs-rules.php';
require_once CS_DIR . 'includes/class-cs-scanner.php';
require_once CS_DIR . 'includes/class-cs-checks.php';
require_once CS_DIR . 'includes/class-cs-runtime.php';
require_once CS_DIR . 'includes/class-cs-admin.php';

/**
 * Bootstrap.
 */
final class CS_Plugin {

	public static function boot() {
		CS_Runtime::boot();

		if ( is_admin() ) {
			CS_Admin::boot();
		}

		add_action( 'cs_daily_scan', array( 'CS_Admin', 'cron_scan' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once CS_DIR . 'includes/class-cs-cli.php';
			WP_CLI::add_command( 'code-sentinel', 'CS_CLI' );
		}
	}

	public static function activate() {
		CS_Util::settings(); // Persists defaults and the private log token.
		CS_Util::ensure_log_dir();
		CS_Admin::reschedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'cs_daily_scan' );
	}
}

register_activation_hook( __FILE__, array( 'CS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CS_Plugin', 'deactivate' ) );

CS_Plugin::boot();
