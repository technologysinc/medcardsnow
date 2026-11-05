<?php
/**
 * Elementor integration: widgets, category, controls, asset detection.
 */

defined( 'ABSPATH' ) || exit;

final class KMO_Plugin {

	/** @var KMO_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		KMO_Extension::init();

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/before_render', array( $this, 'detect_usage' ) );

		// Editor preview iframe: always load everything so changes preview live.
		add_action( 'elementor/preview/enqueue_scripts', array( 'KMO_Assets', 'enqueue_motion' ) );
		add_action( 'elementor/preview/enqueue_styles', array( 'KMO_Assets', 'enqueue_motion' ) );

		// Editor panel styling (section headers, widget icons).
		add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'editor_styles' ) );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'kinetic-motion',
			array(
				'title' => esc_html__( 'Kinetic Motion', 'kinetic-motion' ),
				'icon'  => 'eicon-animation',
			)
		);
	}

	public function register_widgets( $widgets_manager ) {
		require_once KMO_DIR . 'includes/widgets/base.php';
		require_once KMO_DIR . 'includes/widgets/marquee.php';
		require_once KMO_DIR . 'includes/widgets/kinetic-heading.php';
		require_once KMO_DIR . 'includes/widgets/webgl-image.php';
		require_once KMO_DIR . 'includes/widgets/webgl-text.php';

		$widgets_manager->register( new KMO_Widget_Marquee() );
		$widgets_manager->register( new KMO_Widget_Kinetic_Heading() );
		$widgets_manager->register( new KMO_Widget_WebGL_Image() );
		$widgets_manager->register( new KMO_Widget_WebGL_Text() );
	}

	/**
	 * Load the animation engine only when an element on the page uses it.
	 *
	 * @param \Elementor\Element_Base $element
	 */
	public function detect_usage( $element ) {
		static $found = false;
		if ( $found ) {
			return;
		}
		$keys = array( 'kmo_in', 'kmo_sc', 'kmo_pin', 'kmo_hs', 'kmo_tx', 'kmo_mouse', 'kmo_gl' );
		$s    = $element->get_settings_for_display();
		foreach ( $keys as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				$found = true;
				KMO_Assets::need_motion();
				return;
			}
		}
	}

	public function editor_styles() {
		wp_enqueue_style( 'kmo-editor', KMO_URL . 'assets/css/kmo-editor.css', array(), KMO_VERSION );
	}
}
