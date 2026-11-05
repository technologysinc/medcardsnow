<?php
/**
 * Global settings: Elementor → Kinetic Motion.
 */

defined( 'ABSPATH' ) || exit;

class KMO_Settings {

	const OPTION = 'kmo_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		return array(
			// Smooth scrolling.
			'smooth'          => 0,
			'smooth_strength' => 'medium',  // light | medium | strong | extra
			'smooth_wheel'    => '1',       // wheel multiplier
			'smooth_touch'    => 0,
			'anchor_offset'   => 0,

			// Loading & accessibility.
			'load_mode'       => 'auto',    // auto | all
			'reduced_motion'  => 1,
			'mobile_motion'   => 'all',     // all | no-gl | none

			// Scroll progress bar.
			'progress'        => 'none',    // none | top | bottom
			'progress_color'  => '#F2636F',
			'progress_height' => 3,

			// Page transitions.
			'transition'       => 'none',   // none | fade | slide-up | slide-left | slide-down
			'transition_color' => '#111111',
			'transition_ms'    => 600,

			// Custom cursor.
			'cursor'          => 'none',    // none | dot-ring | dot | ring
			'cursor_color'    => '#111111',
			'cursor_blend'    => 0,
			'cursor_hide'     => 0,

			// Developer.
			'debug'           => 0,
		);
	}

	public static function choices() {
		return array(
			'smooth_strength' => array(
				'light'  => __( 'Light', 'kinetic-motion' ),
				'medium' => __( 'Medium', 'kinetic-motion' ),
				'strong' => __( 'Strong', 'kinetic-motion' ),
				'extra'  => __( 'Extra smooth', 'kinetic-motion' ),
			),
			'smooth_wheel'    => array(
				'0.6' => __( 'Slower (0.6×)', 'kinetic-motion' ),
				'0.8' => __( 'A bit slower (0.8×)', 'kinetic-motion' ),
				'1'   => __( 'Normal (1×)', 'kinetic-motion' ),
				'1.2' => __( 'A bit faster (1.2×)', 'kinetic-motion' ),
				'1.5' => __( 'Faster (1.5×)', 'kinetic-motion' ),
			),
			'load_mode'       => array(
				'auto' => __( 'Only on pages that use Kinetic Motion (recommended)', 'kinetic-motion' ),
				'all'  => __( 'On every page', 'kinetic-motion' ),
			),
			'mobile_motion'   => array(
				'all'   => __( 'All effects', 'kinetic-motion' ),
				'no-gl' => __( 'All effects except WebGL backgrounds (saves battery)', 'kinetic-motion' ),
				'none'  => __( 'No animations on phones', 'kinetic-motion' ),
			),
			'progress'        => array(
				'none'   => __( 'Off', 'kinetic-motion' ),
				'top'    => __( 'Top of the screen', 'kinetic-motion' ),
				'bottom' => __( 'Bottom of the screen', 'kinetic-motion' ),
			),
			'transition'      => array(
				'none'       => __( 'Off', 'kinetic-motion' ),
				'fade'       => __( 'Fade', 'kinetic-motion' ),
				'slide-up'   => __( 'Curtain up', 'kinetic-motion' ),
				'slide-down' => __( 'Curtain down', 'kinetic-motion' ),
				'slide-left' => __( 'Curtain left', 'kinetic-motion' ),
			),
			'cursor'          => array(
				'none'     => __( 'Off (normal cursor)', 'kinetic-motion' ),
				'dot-ring' => __( 'Dot + ring', 'kinetic-motion' ),
				'dot'      => __( 'Dot', 'kinetic-motion' ),
				'ring'     => __( 'Ring', 'kinetic-motion' ),
			),
		);
	}

	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		if ( null === $key ) {
			return self::$cache;
		}
		return isset( self::$cache[ $key ] ) ? self::$cache[ $key ] : null;
	}

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'purge_caches' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'purge_caches' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KMO_FILE ), array( __CLASS__, 'action_link' ) );
	}

	public static function register() {
		register_setting(
			'kmo_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $in ) {
		$d       = self::defaults();
		$choices = self::choices();
		$in      = is_array( $in ) ? $in : array();
		$out     = array();

		foreach ( array( 'smooth', 'smooth_touch', 'reduced_motion', 'cursor_blend', 'cursor_hide', 'debug' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		foreach ( $choices as $k => $allowed ) {
			$v         = isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
			$out[ $k ] = array_key_exists( $v, $allowed ) ? $v : $d[ $k ];
		}
		$out['anchor_offset']   = isset( $in['anchor_offset'] ) ? min( 400, absint( $in['anchor_offset'] ) ) : 0;
		$out['progress_height'] = isset( $in['progress_height'] ) ? max( 1, min( 20, absint( $in['progress_height'] ) ) ) : 3;
		$out['transition_ms']   = isset( $in['transition_ms'] ) ? max( 200, min( 2000, absint( $in['transition_ms'] ) ) ) : 600;

		foreach ( array( 'progress_color', 'transition_color', 'cursor_color' ) as $k ) {
			$c         = isset( $in[ $k ] ) ? sanitize_hex_color( $in[ $k ] ) : '';
			$out[ $k ] = $c ? $c : $d[ $k ];
		}

		self::$cache = null;
		return $out;
	}

	public static function purge_caches() {
		self::$cache = null;
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	public static function page_url() {
		return admin_url( isset( $GLOBALS['admin_page_hooks']['elementor'] ) ? 'admin.php?page=kinetic-motion' : 'options-general.php?page=kinetic-motion' );
	}

	public static function action_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'kinetic-motion' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		$title = __( 'Kinetic Motion', 'kinetic-motion' );
		if ( isset( $GLOBALS['admin_page_hooks']['elementor'] ) ) {
			$hook = add_submenu_page( 'elementor', $title, $title, 'manage_options', 'kinetic-motion', array( __CLASS__, 'render' ) );
		} else {
			$hook = add_options_page( $title, $title, 'manage_options', 'kinetic-motion', array( __CLASS__, 'render' ) );
		}

		add_action(
			'admin_enqueue_scripts',
			function ( $current ) use ( $hook ) {
				if ( $current !== $hook ) {
					return;
				}
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_style( 'kmo-admin', KMO_URL . 'assets/css/kmo-admin.css', array(), KMO_VERSION );
				wp_enqueue_script( 'wp-color-picker' );
				wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".kmo-color").wpColorPicker();});' );
			}
		);
	}

	/* ---------- Field helpers ---------- */

	private static function name( $k ) {
		return self::OPTION . '[' . $k . ']';
	}

	private static function select( $k ) {
		$s = self::get();
		printf( '<select id="kmo-%1$s" name="%2$s">', esc_attr( $k ), esc_attr( self::name( $k ) ) );
		foreach ( self::choices()[ $k ] as $v => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $v ), selected( (string) $s[ $k ], (string) $v, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private static function checkbox( $k, $label ) {
		$s = self::get();
		printf(
			'<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="kmo-%2$s" name="%1$s" value="1"%3$s> %4$s</label>',
			esc_attr( self::name( $k ) ),
			esc_attr( $k ),
			checked( ! empty( $s[ $k ] ), true, false ),
			esc_html( $label )
		);
	}

	private static function number( $k, $min, $max, $unit ) {
		$s = self::get();
		printf(
			'<input type="number" class="small-text" id="kmo-%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d"> %6$s',
			esc_attr( $k ),
			esc_attr( self::name( $k ) ),
			(int) $s[ $k ],
			(int) $min,
			(int) $max,
			esc_html( $unit )
		);
	}

	private static function color( $k ) {
		$s = self::get();
		$d = self::defaults();
		printf(
			'<input type="text" class="kmo-color" name="%1$s" value="%2$s" data-default-color="%3$s">',
			esc_attr( self::name( $k ) ),
			esc_attr( $s[ $k ] ),
			esc_attr( $d[ $k ] )
		);
	}

	private static function row( $label, $callback, $help = '' ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		call_user_func( $callback );
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap kmo-admin">
			<h1><?php esc_html_e( 'Kinetic Motion', 'kinetic-motion' ); ?></h1>
			<p class="kmo-intro"><?php esc_html_e( 'Site-wide options. Animations for individual containers and widgets are set in Elementor: select an element → Advanced tab → the "Kinetic" sections. New widgets are in the "Kinetic Motion" category.', 'kinetic-motion' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'kmo_group' ); ?>

				<div class="kmo-card">
					<h2><?php esc_html_e( 'Smooth scrolling', 'kinetic-motion' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::row( __( 'Status', 'kinetic-motion' ), function () { self::checkbox( 'smooth', __( 'Turn on smooth scrolling for the whole site', 'kinetic-motion' ) ); }, __( 'Makes mouse-wheel scrolling glide. Phones keep their normal, native scrolling unless you turn on the touch option below.', 'kinetic-motion' ) );
						self::row( __( 'Smoothness', 'kinetic-motion' ), function () { self::select( 'smooth_strength' ); } );
						self::row( __( 'Scroll speed', 'kinetic-motion' ), function () { self::select( 'smooth_wheel' ); } );
						self::row( __( 'Touch devices', 'kinetic-motion' ), function () { self::checkbox( 'smooth_touch', __( 'Also smooth touch scrolling (not recommended)', 'kinetic-motion' ) ); } );
						self::row( __( 'Anchor link offset', 'kinetic-motion' ), function () { self::number( 'anchor_offset', 0, 400, 'px' ); }, __( 'Space to leave at the top when jumping to #section links — usually your sticky header height.', 'kinetic-motion' ) );
						?>
					</table>
				</div>

				<div class="kmo-card">
					<h2><?php esc_html_e( 'Scroll progress bar', 'kinetic-motion' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::row( __( 'Position', 'kinetic-motion' ), function () { self::select( 'progress' ); } );
						self::row( __( 'Color', 'kinetic-motion' ), function () { self::color( 'progress_color' ); } );
						self::row( __( 'Thickness', 'kinetic-motion' ), function () { self::number( 'progress_height', 1, 20, 'px' ); } );
						?>
					</table>
				</div>

				<div class="kmo-card">
					<h2><?php esc_html_e( 'Page transitions', 'kinetic-motion' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::row( __( 'Effect', 'kinetic-motion' ), function () { self::select( 'transition' ); }, __( 'Plays when visitors click a link to another page of your site. Links that open new tabs, files, phone numbers, email and #anchors are skipped. Add data-kmo-no-transition to any link to skip it.', 'kinetic-motion' ) );
						self::row( __( 'Color', 'kinetic-motion' ), function () { self::color( 'transition_color' ); } );
						self::row( __( 'Duration', 'kinetic-motion' ), function () { self::number( 'transition_ms', 200, 2000, 'ms' ); } );
						?>
					</table>
				</div>

				<div class="kmo-card">
					<h2><?php esc_html_e( 'Custom cursor', 'kinetic-motion' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::row( __( 'Style', 'kinetic-motion' ), function () { self::select( 'cursor' ); }, __( 'Desktop only. The ring grows over links and buttons.', 'kinetic-motion' ) );
						self::row( __( 'Color', 'kinetic-motion' ), function () { self::color( 'cursor_color' ); } );
						self::row( __( 'Blend', 'kinetic-motion' ), function () { self::checkbox( 'cursor_blend', __( 'Invert colors under the cursor (difference blend)', 'kinetic-motion' ) ); } );
						self::row( __( 'Default cursor', 'kinetic-motion' ), function () { self::checkbox( 'cursor_hide', __( 'Hide the normal arrow cursor', 'kinetic-motion' ) ); } );
						?>
					</table>
				</div>

				<div class="kmo-card">
					<h2><?php esc_html_e( 'Performance & accessibility', 'kinetic-motion' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::row( __( 'Load scripts', 'kinetic-motion' ), function () { self::select( 'load_mode' ); }, __( 'Recommended keeps pages without animations as fast as before. Choose "every page" only if an animation inside a popup or template does not start.', 'kinetic-motion' ) );
						self::row( __( 'On phones', 'kinetic-motion' ), function () { self::select( 'mobile_motion' ); } );
						self::row( __( 'Reduced motion', 'kinetic-motion' ), function () { self::checkbox( 'reduced_motion', __( 'Turn animations off for visitors who ask their device to reduce motion', 'kinetic-motion' ) ); }, __( 'Strongly recommended for accessibility.', 'kinetic-motion' ) );
						self::row( __( 'Debug', 'kinetic-motion' ), function () { self::checkbox( 'debug', __( 'Show scroll trigger markers (only visible to logged-in admins)', 'kinetic-motion' ) ); } );
						?>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
