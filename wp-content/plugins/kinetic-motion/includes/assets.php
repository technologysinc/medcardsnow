<?php
/**
 * Script/style registration and "load only where used" logic.
 */

defined( 'ABSPATH' ) || exit;

class KMO_Assets {

	const GSAP_VERSION  = '3.15.0';
	const LENIS_VERSION = '1.3.27';

	/** @var bool */
	private static $motion_needed = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_site' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head_snippet' ), 2 );
		add_action( 'wp_footer', array( __CLASS__, 'footer_snippet' ), 99 );
	}

	/**
	 * True inside the Elementor editor preview iframe.
	 */
	public static function is_elementor_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['elementor-preview'] ) ) {
			return true;
		}
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode();
	}

	public static function register() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$v = self::GSAP_VERSION;
		$u = KMO_URL . 'assets/';

		wp_register_script( 'kmo-gsap', $u . 'vendor/gsap.min.js', array(), $v, true );
		wp_register_script( 'kmo-scrolltrigger', $u . 'vendor/ScrollTrigger.min.js', array( 'kmo-gsap' ), $v, true );
		wp_register_script( 'kmo-splittext', $u . 'vendor/SplitText.min.js', array( 'kmo-gsap' ), $v, true );
		wp_register_script( 'kmo-scrambletext', $u . 'vendor/ScrambleTextPlugin.min.js', array( 'kmo-gsap' ), $v, true );
		wp_register_script( 'kmo-customease', $u . 'vendor/CustomEase.min.js', array( 'kmo-gsap' ), $v, true );
		wp_register_script( 'kmo-lenis', $u . 'vendor/lenis.min.js', array(), self::LENIS_VERSION, true );

		// Inline-only handle that carries the configuration for both scripts.
		wp_register_script( 'kmo-config', false, array(), KMO_VERSION, true );
		wp_add_inline_script( 'kmo-config', 'window.KMO_CONFIG=' . wp_json_encode( self::config() ) . ';' );

		$site_deps = array( 'kmo-config' );
		if ( self::smooth_enabled() ) {
			$site_deps[] = 'kmo-lenis';
		}
		wp_register_script( 'kmo-site', $u . 'js/kmo-site.js', $site_deps, KMO_VERSION, true );

		wp_register_script(
			'kmo-motion',
			$u . 'js/kmo-motion.js',
			array( 'kmo-config', 'kmo-gsap', 'kmo-scrolltrigger', 'kmo-splittext', 'kmo-scrambletext', 'kmo-customease' ),
			KMO_VERSION,
			true
		);

		wp_register_style( 'kmo-frontend', $u . 'css/kmo.css', array(), KMO_VERSION );
		wp_register_style( 'kmo-lenis', $u . 'vendor/lenis.css', array(), self::LENIS_VERSION );
	}

	public static function smooth_enabled() {
		return ! empty( KMO_Settings::get( 'smooth' ) ) && ! self::is_elementor_preview();
	}

	public static function site_features_enabled() {
		$s = KMO_Settings::get();
		return self::smooth_enabled()
			|| 'none' !== $s['progress']
			|| 'none' !== $s['cursor']
			|| 'none' !== $s['transition'];
	}

	public static function config() {
		$s        = KMO_Settings::get();
		$strength = array(
			'light'  => 0.16,
			'medium' => 0.1,
			'strong' => 0.075,
			'extra'  => 0.05,
		);
		$preview  = self::is_elementor_preview();

		return array(
			'v'          => KMO_VERSION,
			'edit'       => $preview,
			'reduce'     => (bool) $s['reduced_motion'],
			'mobile'     => $s['mobile_motion'],
			'debug'      => ! empty( $s['debug'] ) && current_user_can( 'manage_options' ),
			'glUrl'      => KMO_URL . 'assets/js/kmo-webgl.js?ver=' . KMO_VERSION,
			'smooth'     => self::smooth_enabled() ? array(
				'lerp'   => isset( $strength[ $s['smooth_strength'] ] ) ? $strength[ $s['smooth_strength'] ] : 0.1,
				'wheel'  => (float) $s['smooth_wheel'],
				'touch'  => (bool) $s['smooth_touch'],
				'offset' => (int) $s['anchor_offset'],
			) : false,
			'progress'   => $preview || 'none' === $s['progress'] ? false : array(
				'pos'    => $s['progress'],
				'color'  => $s['progress_color'],
				'height' => (int) $s['progress_height'],
			),
			'transition' => $preview || 'none' === $s['transition'] ? false : array(
				'type' => $s['transition'],
				'ms'   => (int) $s['transition_ms'],
			),
			'cursor'     => $preview || 'none' === $s['cursor'] ? false : array(
				'type'  => $s['cursor'],
				'color' => $s['cursor_color'],
				'blend' => (bool) $s['cursor_blend'],
				'hide'  => (bool) $s['cursor_hide'],
			),
		);
	}

	/**
	 * Site-wide features + "load everywhere" mode.
	 */
	public static function enqueue_site() {
		if ( is_admin() ) {
			return;
		}

		if ( self::site_features_enabled() && ! self::is_elementor_preview() ) {
			wp_enqueue_script( 'kmo-site' );
			if ( self::smooth_enabled() ) {
				wp_enqueue_style( 'kmo-lenis' );
			}
		}

		// Load the stylesheet in <head> when this page uses Kinetic Motion, so nothing flashes unstyled.
		// (Templates such as headers/footers are still covered: the stylesheet is added when they render.)
		if ( 'all' === KMO_Settings::get( 'load_mode' ) || self::page_uses_kmo() ) {
			wp_enqueue_style( 'kmo-frontend' );
		}

		if ( 'all' === KMO_Settings::get( 'load_mode' ) ) {
			self::need_motion();
		}
	}

	/**
	 * Quick check of the current page's Elementor data for Kinetic settings or widgets.
	 */
	private static function page_uses_kmo() {
		if ( ! is_singular() ) {
			return false;
		}
		$raw = get_post_meta( get_queried_object_id(), '_elementor_data', true );
		if ( is_array( $raw ) ) {
			$raw = wp_json_encode( $raw );
		}
		return is_string( $raw ) && ( false !== strpos( $raw, '"kmo_' ) || false !== strpos( $raw, '"kmo-' ) );
	}

	/**
	 * Called when an element or widget on the page uses Kinetic Motion.
	 */
	public static function need_motion() {
		if ( self::$motion_needed ) {
			return;
		}
		self::$motion_needed = true;

		if ( ! did_action( 'wp_enqueue_scripts' ) && ! doing_action( 'wp_enqueue_scripts' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_motion' ), 30 );
			return;
		}
		self::enqueue_motion();
	}

	public static function enqueue_motion() {
		self::register();
		wp_enqueue_style( 'kmo-frontend' );
		wp_enqueue_script( 'kmo-motion' );
	}

	/**
	 * Tiny, render-blocking-safe snippet:
	 * - marks <html> so elements waiting for an entrance animation start hidden (no flash),
	 * - reveals everything after 3 s if scripts never run (blocked, delayed or failed),
	 * - starts the page-transition reveal.
	 */
	public static function head_snippet() {
		if ( is_admin() || is_feed() ) {
			return;
		}
		$s        = KMO_Settings::get();
		$preview  = self::is_elementor_preview();
		$hide     = ".kmo-js [data-settings*='\"kmo_in\":']:not(.kmo-init),.kmo-js [data-settings*='\"kmo_tx\":']:not(.kmo-init){opacity:0}"
			. ".kmo-js.kmo-failsafe [data-settings*='\"kmo_in\":']:not(.kmo-init),.kmo-js.kmo-failsafe [data-settings*='\"kmo_tx\":']:not(.kmo-init){opacity:1}";
		$css      = $hide;
		if ( ! empty( $s['reduced_motion'] ) ) {
			$css .= "@media (prefers-reduced-motion:reduce){.kmo-js [data-settings*='\"kmo_in\":']:not(.kmo-init),.kmo-js [data-settings*='\"kmo_tx\":']:not(.kmo-init){opacity:1}}";
		}
		if ( 'none' === $s['mobile_motion'] ) {
			$css .= "@media (max-width:767px){.kmo-js [data-settings*='\"kmo_in\":']:not(.kmo-init),.kmo-js [data-settings*='\"kmo_tx\":']:not(.kmo-init){opacity:1}}";
		}

		$pt = ( ! $preview && 'none' !== $s['transition'] ) ? $s['transition'] : '';
		if ( $pt ) {
			$css .= self::transition_css( $pt, $s['transition_color'], (int) $s['transition_ms'] );
		}

		echo '<style id="kmo-head-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, color sanitized on save.

		$js = "!function(d,w){var h=d.documentElement;h.classList.add('kmo-js');w.KMO_FS=setTimeout(function(){h.classList.add('kmo-failsafe')},3000);";
		if ( $pt ) {
			$js .= "try{var r=" . ( empty( $s['reduced_motion'] ) ? 'false' : "w.matchMedia('(prefers-reduced-motion: reduce)').matches" ) . ";if(!r&&w.sessionStorage&&sessionStorage.getItem('kmo-pt')){h.classList.add('kmo-pt-enter');setTimeout(function(){h.classList.add('kmo-pt-go')},2500)}sessionStorage.removeItem('kmo-pt')}catch(e){}";
		}
		$js .= '}(document,window);';

		echo '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" data-pagespeed-no-defer nowprocket>' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static JS.
	}

	public static function footer_snippet() {
		if ( is_admin() || self::is_elementor_preview() || 'none' === KMO_Settings::get( 'transition' ) ) {
			return;
		}
		echo '<script data-no-optimize="1" data-no-defer="1" data-cfasync="false" nowprocket>document.documentElement.classList.add("kmo-pt-go");</script>' . "\n";
	}

	private static function transition_css( $type, $color, $ms ) {
		$frames = array(
			'fade'       => array( 'opacity:1', 'opacity:0' ),
			'slide-up'   => array( 'transform:translateY(0)', 'transform:translateY(-100%)' ),
			'slide-down' => array( 'transform:translateY(0)', 'transform:translateY(100%)' ),
			'slide-left' => array( 'transform:translateX(0)', 'transform:translateX(-100%)' ),
		);
		$enter_from = array(
			'fade'       => 'opacity:0',
			'slide-up'   => 'transform:translateY(100%)',
			'slide-down' => 'transform:translateY(-100%)',
			'slide-left' => 'transform:translateX(100%)',
		);
		if ( ! isset( $frames[ $type ] ) ) {
			return '';
		}
		$ease = 'cubic-bezier(.76,0,.24,1)';
		$css  = 'html.kmo-pt-enter::after,html.kmo-pt-exit::after{content:"";position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483000;background:' . $color . ';pointer-events:none;will-change:transform,opacity}';
		$css .= 'html.kmo-pt-enter::after{animation:kmo-pt-reveal ' . $ms . 'ms ' . $ease . ' 60ms both;animation-play-state:paused}';
		$css .= 'html.kmo-pt-enter.kmo-pt-go::after{animation-play-state:running}';
		$css .= 'html.kmo-pt-exit::after{animation:kmo-pt-cover ' . $ms . 'ms ' . $ease . ' both;pointer-events:all}';
		$css .= '@keyframes kmo-pt-reveal{from{' . $frames[ $type ][0] . '}to{' . $frames[ $type ][1] . '}}';
		$css .= '@keyframes kmo-pt-cover{from{' . $enter_from[ $type ] . '}to{' . $frames[ $type ][0] . '}}';
		return $css;
	}
}
