<?php
/** Main plugin controller. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TableCraft_Plugin {
	/** @var TableCraft_Plugin|null */
	private static $instance = null;

	/** @return TableCraft_Plugin */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'elementor/init', array( $this, 'register_elementor_widget' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_notices', array( $this, 'elementor_missing_notice' ) );
	}

	/** Register the Elementor widget only after Elementor has initialized. */
	public function register_elementor_widget() {
		add_action( 'elementor/widgets/register', array( $this, 'add_widget' ) );
	}

	/** @param \Elementor\Widgets_Manager $widgets_manager Elementor manager. */
	public function add_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once TABLECRAFT_PATH . 'includes/class-tablecraft-widget.php';
		$widgets_manager->register( new TableCraft_Elementor_Widget() );
	}

	/** Register frontend assets. */
	public function register_assets() {
		wp_register_style(
			'tablecraft-frontend',
			TABLECRAFT_URL . 'assets/css/tablecraft-frontend.css',
			array(),
			TABLECRAFT_VERSION
		);
		wp_register_script(
			'tablecraft-frontend',
			TABLECRAFT_URL . 'assets/js/tablecraft-frontend.js',
			array(),
			TABLECRAFT_VERSION,
			true
		);
	}

	/** Add a small setup page under Settings. */
	public function register_admin_page() {
		add_options_page(
			esc_html__( 'TableCraft', 'tablecraft-elementor' ),
			esc_html__( 'TableCraft', 'tablecraft-elementor' ),
			'manage_options',
			'tablecraft-elementor',
			array( $this, 'render_admin_page' )
		);
	}

	/** Render plugin setup notes. */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TableCraft for Elementor', 'tablecraft-elementor' ); ?></h1>
			<p><?php esc_html_e( 'Create polished, responsive data tables directly inside the Elementor editor.', 'tablecraft-elementor' ); ?></p>
			<div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:22px;margin-top:18px">
				<h2><?php esc_html_e( 'Getting started', 'tablecraft-elementor' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Open a page with Elementor and search the widget panel for “TableCraft”.', 'tablecraft-elementor' ); ?></li>
					<li><?php esc_html_e( 'Choose a preloaded table template or Custom table, then configure columns, rows, or a connected data source.', 'tablecraft-elementor' ); ?></li>
					<li><?php esc_html_e( 'Use the Style tab to adjust colors, typography, spacing, borders, and responsive behavior.', 'tablecraft-elementor' ); ?></li>
				</ol>
				<hr />
				<h2><?php esc_html_e( 'ACF connection', 'tablecraft-elementor' ); ?></h2>
				<p><?php esc_html_e( 'Install and activate Advanced Custom Fields. For ordinary ACF fields, choose ACF Fields (single row) and map each column to its field name. For repeated records, choose ACF Repeater, enter the repeater field name, and map columns to sub-field names.', 'tablecraft-elementor' ); ?></p>
				<hr />
				<h2><?php esc_html_e( 'Google Sheets connection', 'tablecraft-elementor' ); ?></h2>
				<p><?php esc_html_e( 'Set the sheet so anyone with the link can view it, or publish it to the web as CSV. Paste the Google Sheets URL in the widget. The first row is used as column headings when “Use first row as headers” is enabled. Data is cached on the server for the duration selected in the widget.', 'tablecraft-elementor' ); ?></p>
				<p><strong><?php esc_html_e( 'Privacy note:', 'tablecraft-elementor' ); ?></strong> <?php esc_html_e( 'This version supports public/viewable Google Sheets CSV feeds. It does not provide OAuth access to private spreadsheets. Do not paste confidential data into a public sheet.', 'tablecraft-elementor' ); ?></p>
				<hr />
				<h2><?php esc_html_e( 'Included design presets', 'tablecraft-elementor' ); ?></h2>
				<p><?php esc_html_e( 'Content templates: Pricing plans, Feature comparison, Product catalog, Team / business directory, Event schedule, and Inventory list. Design presets: Modern, Minimal, Striped, Pricing, Comparison, Compact, Dark, Glass, and Enterprise.', 'tablecraft-elementor' ); ?></p>
				<p><small><?php echo esc_html( 'TableCraft ' . TABLECRAFT_VERSION ); ?></small></p>
			</div>
		</div>
		<?php
	}

	/** Warn administrators when Elementor is not active. */
	public function elementor_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || did_action( 'elementor/loaded' ) ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) || 'tablecraft-elementor' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'TableCraft needs Elementor to be installed and active. The setup page is available, but the widget will appear once Elementor is enabled.', 'tablecraft-elementor' ); ?></p></div>
		<?php
	}
}
