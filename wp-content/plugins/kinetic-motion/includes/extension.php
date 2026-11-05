<?php
/**
 * Adds the "Kinetic" control sections (Advanced tab) to every container, section, column and widget.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

class KMO_Extension {

	const TARGETS = array( 'container', 'section', 'column', 'common', 'common-optimized' );

	public static function init() {
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'add_sections' ), 10, 3 );
	}

	/**
	 * @param \Elementor\Controls_Stack $element
	 * @param string                    $section_id
	 */
	public static function add_sections( $element, $section_id, $args ) {
		if ( 'section_effects' !== $section_id || ! in_array( $element->get_name(), self::TARGETS, true ) ) {
			return;
		}
		$name      = $element->get_name();
		$is_widget = in_array( $name, array( 'common', 'common-optimized' ), true );
		$is_box    = in_array( $name, array( 'container', 'section' ), true );

		self::entrance( $element, $name );
		self::scroll( $element, $name );
		if ( $is_widget ) {
			self::text( $element );
		}
		self::mouse( $element );
		if ( $is_box ) {
			self::webgl( $element );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Shared option lists                                                 */
	/* ------------------------------------------------------------------ */

	public static function eases() {
		return array(
			'power2.out'        => __( 'Smooth (default)', 'kinetic-motion' ),
			'power1.out'        => __( 'Gentle', 'kinetic-motion' ),
			'power3.out'        => __( 'Strong', 'kinetic-motion' ),
			'power4.out'        => __( 'Very strong', 'kinetic-motion' ),
			'expo.out'          => __( 'Expo (snappy)', 'kinetic-motion' ),
			'circ.out'          => __( 'Circular', 'kinetic-motion' ),
			'back.out(1.7)'     => __( 'Back (overshoot)', 'kinetic-motion' ),
			'elastic.out(1,0.5)' => __( 'Elastic', 'kinetic-motion' ),
			'bounce.out'        => __( 'Bounce', 'kinetic-motion' ),
			'power2.inOut'      => __( 'Ease in-out', 'kinetic-motion' ),
			'sine.inOut'        => __( 'Sine in-out', 'kinetic-motion' ),
			'none'              => __( 'Linear', 'kinetic-motion' ),
			'custom'            => __( 'Custom…', 'kinetic-motion' ),
		);
	}

	public static function modes() {
		return array(
			'once'    => __( 'Play once', 'kinetic-motion' ),
			'replay'  => __( 'Replay each time it enters', 'kinetic-motion' ),
			'reverse' => __( 'Reverse when scrolling back up', 'kinetic-motion' ),
			'inout'   => __( 'Play in and out of view', 'kinetic-motion' ),
		);
	}

	private static function slider( $label, $min, $max, $step, $default, $unit = 'px', $extra = array() ) {
		return array_merge(
			array(
				'label'              => $label,
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( $unit ),
				'range'              => array(
					$unit => array(
						'min'  => $min,
						'max'  => $max,
						'step' => $step,
					),
				),
				'default'            => array(
					'unit' => $unit,
					'size' => $default,
				),
				'frontend_available' => true,
				'render_type'        => 'none',
			),
			$extra
		);
	}

	private static function select( $label, $options, $default, $extra = array() ) {
		return array_merge(
			array(
				'label'              => $label,
				'type'               => Controls_Manager::SELECT,
				'options'            => $options,
				'default'            => $default,
				'frontend_available' => true,
				'render_type'        => 'none',
			),
			$extra
		);
	}

	private static function switcher( $label, $default = '', $extra = array() ) {
		return array_merge(
			array(
				'label'              => $label,
				'type'               => Controls_Manager::SWITCHER,
				'default'            => $default,
				'return_value'       => 'yes',
				'frontend_available' => true,
				'render_type'        => 'none',
			),
			$extra
		);
	}

	private static function color( $label, $default, $extra = array() ) {
		return array_merge(
			array(
				'label'              => $label,
				'type'               => Controls_Manager::COLOR,
				'default'            => $default,
				'alpha'              => false,
				// Global colors are stored as CSS variables, which the WebGL/JS side cannot read.
				'global'             => array( 'active' => false ),
				'frontend_available' => true,
				'render_type'        => 'none',
			),
			$extra
		);
	}

	private static function note( $element, $id, $html, $condition ) {
		$element->add_control(
			$id,
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => $html,
				'content_classes' => 'elementor-descriptor kmo-note',
				'condition'       => $condition,
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* 1. Entrance                                                         */
	/* ------------------------------------------------------------------ */

	private static function entrance( $el, $name ) {
		$el->start_controls_section(
			'kmo_section_in',
			array(
				'label' => esc_html__( 'Kinetic · Entrance', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$el->add_control(
			'kmo_in',
			self::select(
				esc_html__( 'Animation', 'kinetic-motion' ),
				array(),
				'',
				array(
					'groups' => array(
						''       => esc_html__( 'None', 'kinetic-motion' ),
						'fade'   => array(
							'label'   => esc_html__( 'Fade', 'kinetic-motion' ),
							'options' => array(
								'fade'       => esc_html__( 'Fade in', 'kinetic-motion' ),
								'fade-up'    => esc_html__( 'Fade up', 'kinetic-motion' ),
								'fade-down'  => esc_html__( 'Fade down', 'kinetic-motion' ),
								'fade-left'  => esc_html__( 'Fade from right', 'kinetic-motion' ),
								'fade-right' => esc_html__( 'Fade from left', 'kinetic-motion' ),
								'blur-in'    => esc_html__( 'Blur in', 'kinetic-motion' ),
							),
						),
						'zoom'   => array(
							'label'   => esc_html__( 'Zoom', 'kinetic-motion' ),
							'options' => array(
								'zoom-in'     => esc_html__( 'Zoom in', 'kinetic-motion' ),
								'zoom-out'    => esc_html__( 'Zoom out', 'kinetic-motion' ),
								'elastic-pop' => esc_html__( 'Elastic pop', 'kinetic-motion' ),
							),
						),
						'reveal' => array(
							'label'   => esc_html__( 'Reveal (mask)', 'kinetic-motion' ),
							'options' => array(
								'clip-up'     => esc_html__( 'Reveal from bottom', 'kinetic-motion' ),
								'clip-down'   => esc_html__( 'Reveal from top', 'kinetic-motion' ),
								'clip-left'   => esc_html__( 'Reveal from right', 'kinetic-motion' ),
								'clip-right'  => esc_html__( 'Reveal from left', 'kinetic-motion' ),
								'clip-center' => esc_html__( 'Reveal from center', 'kinetic-motion' ),
								'clip-circle' => esc_html__( 'Circle reveal', 'kinetic-motion' ),
							),
						),
						'3d'     => array(
							'label'   => esc_html__( '3D & motion', 'kinetic-motion' ),
							'options' => array(
								'flip-up'   => esc_html__( 'Flip up (3D)', 'kinetic-motion' ),
								'flip-left' => esc_html__( 'Flip sideways (3D)', 'kinetic-motion' ),
								'tilt-3d'   => esc_html__( 'Tilt in (3D)', 'kinetic-motion' ),
								'rotate-in' => esc_html__( 'Rotate in', 'kinetic-motion' ),
								'skew-up'   => esc_html__( 'Skew up', 'kinetic-motion' ),
								'swing'     => esc_html__( 'Swing in', 'kinetic-motion' ),
							),
						),
					),
				)
			)
		);

		$on = array( 'kmo_in!' => '' );

		$el->add_control( 'kmo_in_duration', self::slider( esc_html__( 'Duration', 'kinetic-motion' ), 0.1, 5, 0.05, 1, 's', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_in_delay', self::slider( esc_html__( 'Delay', 'kinetic-motion' ), 0, 5, 0.05, 0, 's', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_in_ease', self::select( esc_html__( 'Easing', 'kinetic-motion' ), self::eases(), 'power3.out', array( 'condition' => $on ) ) );
		$el->add_control(
			'kmo_in_ease_custom',
			array(
				'label'              => esc_html__( 'Custom easing', 'kinetic-motion' ),
				'type'               => Controls_Manager::TEXT,
				'placeholder'        => '0.22, 1, 0.36, 1',
				'description'        => esc_html__( 'A cubic-bezier like 0.22, 1, 0.36, 1 or any GSAP ease, e.g. expo.inOut', 'kinetic-motion' ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'condition'          => array_merge( $on, array( 'kmo_in_ease' => 'custom' ) ),
			)
		);
		$el->add_control(
			'kmo_in_distance',
			self::slider(
				esc_html__( 'Distance', 'kinetic-motion' ),
				0,
				400,
				1,
				60,
				'px',
				array(
					'condition' => array(
						'kmo_in' => array( 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'tilt-3d', 'rotate-in', 'skew-up' ),
					),
				)
			)
		);
		$el->add_control( 'kmo_in_start', self::slider( esc_html__( 'Start when element reaches', 'kinetic-motion' ), 0, 100, 1, 88, '%', array( 'condition' => $on, 'description' => esc_html__( '% from the top of the screen (100 = as soon as it appears at the bottom).', 'kinetic-motion' ) ) ) );
		$el->add_control( 'kmo_in_mode', self::select( esc_html__( 'Playback', 'kinetic-motion' ), self::modes(), 'once', array( 'condition' => $on ) ) );

		$el->add_control(
			'kmo_in_stagger',
			self::switcher(
				'common' === $name || 'common-optimized' === $name ? esc_html__( 'Animate items one by one', 'kinetic-motion' ) : esc_html__( 'Animate inner elements one by one', 'kinetic-motion' ),
				'',
				array(
					'separator' => 'before',
					'condition' => $on,
				)
			)
		);
		$stag = array_merge( $on, array( 'kmo_in_stagger' => 'yes' ) );
		$el->add_control( 'kmo_in_stagger_each', self::slider( esc_html__( 'Time between items', 'kinetic-motion' ), 0.02, 1, 0.01, 0.12, 's', array( 'condition' => $stag ) ) );
		$el->add_control(
			'kmo_in_stagger_from',
			self::select(
				esc_html__( 'Order', 'kinetic-motion' ),
				array(
					'start'  => esc_html__( 'First to last', 'kinetic-motion' ),
					'end'    => esc_html__( 'Last to first', 'kinetic-motion' ),
					'center' => esc_html__( 'From the center', 'kinetic-motion' ),
					'edges'  => esc_html__( 'From the edges', 'kinetic-motion' ),
					'random' => esc_html__( 'Random', 'kinetic-motion' ),
				),
				'start',
				array( 'condition' => $stag )
			)
		);
		$el->add_control(
			'kmo_in_stagger_selector',
			array(
				'label'              => esc_html__( 'Items selector (optional)', 'kinetic-motion' ),
				'type'               => Controls_Manager::TEXT,
				'placeholder'        => 'li, .elementor-icon-box-wrapper',
				'description'        => esc_html__( 'Leave empty to use the direct child elements. Advanced: any CSS selector inside this element.', 'kinetic-motion' ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'condition'          => $stag,
			)
		);

		$el->add_control( 'kmo_in_off_mobile', self::switcher( esc_html__( 'Disable on phones', 'kinetic-motion' ), '', array( 'separator' => 'before', 'condition' => $on ) ) );

		$el->end_controls_section();
	}

	/* ------------------------------------------------------------------ */
	/* 2. Scroll effects, pin, horizontal scroll                           */
	/* ------------------------------------------------------------------ */

	private static function scroll( $el, $name ) {
		$el->start_controls_section(
			'kmo_section_sc',
			array(
				'label' => esc_html__( 'Kinetic · Scroll Effects', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$el->add_control( 'kmo_sc', self::switcher( esc_html__( 'Animate while scrolling', 'kinetic-motion' ) ) );
		$on = array( 'kmo_sc' => 'yes' );

		$effects = array(
			'kmo_sc_y'      => array( esc_html__( 'Vertical parallax', 'kinetic-motion' ), array( 'kmo_sc_y_amount' => array( esc_html__( 'Distance', 'kinetic-motion' ), -600, 600, 5, 150, 'px', esc_html__( 'Positive moves the element up as you scroll down (slower than the page). Negative moves it the other way.', 'kinetic-motion' ) ) ) ),
			'kmo_sc_x'      => array( esc_html__( 'Horizontal move', 'kinetic-motion' ), array( 'kmo_sc_x_amount' => array( esc_html__( 'Distance', 'kinetic-motion' ), -1000, 1000, 5, 200, 'px', '' ) ) ),
			'kmo_sc_rotate' => array(
				esc_html__( 'Rotate', 'kinetic-motion' ),
				array(
					'kmo_sc_rotate_from' => array( esc_html__( 'From', 'kinetic-motion' ), -360, 360, 1, -10, 'deg', '' ),
					'kmo_sc_rotate_to'   => array( esc_html__( 'To', 'kinetic-motion' ), -360, 360, 1, 10, 'deg', '' ),
				),
			),
			'kmo_sc_scale'  => array(
				esc_html__( 'Scale', 'kinetic-motion' ),
				array(
					'kmo_sc_scale_from' => array( esc_html__( 'From', 'kinetic-motion' ), 0, 3, 0.01, 0.8, 'x', '' ),
					'kmo_sc_scale_to'   => array( esc_html__( 'To', 'kinetic-motion' ), 0, 3, 0.01, 1.1, 'x', '' ),
				),
			),
			'kmo_sc_fade'   => array(
				esc_html__( 'Fade', 'kinetic-motion' ),
				array(
					'kmo_sc_fade_from' => array( esc_html__( 'From', 'kinetic-motion' ), 0, 1, 0.01, 0.2, 'x', '' ),
					'kmo_sc_fade_to'   => array( esc_html__( 'To', 'kinetic-motion' ), 0, 1, 0.01, 1, 'x', '' ),
				),
			),
			'kmo_sc_blur'   => array(
				esc_html__( 'Blur', 'kinetic-motion' ),
				array(
					'kmo_sc_blur_from' => array( esc_html__( 'From', 'kinetic-motion' ), 0, 40, 0.5, 12, 'px', '' ),
					'kmo_sc_blur_to'   => array( esc_html__( 'To', 'kinetic-motion' ), 0, 40, 0.5, 0, 'px', '' ),
				),
			),
		);

		foreach ( $effects as $toggle => $def ) {
			$el->add_control(
				$toggle,
				array(
					'label'              => $def[0],
					'type'               => Controls_Manager::POPOVER_TOGGLE,
					'return_value'       => 'yes',
					'frontend_available' => true,
					'render_type'        => 'none',
					'condition'          => $on,
				)
			);
			$el->start_popover();
			foreach ( $def[1] as $id => $c ) {
				$extra = array( 'condition' => array_merge( $on, array( $toggle => 'yes' ) ) );
				if ( $c[6] ) {
					$extra['description'] = $c[6];
				}
				$el->add_control( $id, self::slider( $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $extra ) );
			}
			$el->end_popover();
		}

		$el->add_control(
			'kmo_sc_start',
			self::select(
				esc_html__( 'Starts when', 'kinetic-motion' ),
				array(
					'top bottom'    => esc_html__( 'Element top enters the screen', 'kinetic-motion' ),
					'top 80%'       => esc_html__( 'Element top is near the bottom', 'kinetic-motion' ),
					'top center'    => esc_html__( 'Element top hits the middle', 'kinetic-motion' ),
					'top top'       => esc_html__( 'Element top hits the top', 'kinetic-motion' ),
					'center bottom' => esc_html__( 'Element middle enters', 'kinetic-motion' ),
				),
				'top bottom',
				array(
					'separator' => 'before',
					'condition' => $on,
				)
			)
		);
		$el->add_control(
			'kmo_sc_end',
			self::select(
				esc_html__( 'Ends when', 'kinetic-motion' ),
				array(
					'bottom top'    => esc_html__( 'Element bottom leaves the top', 'kinetic-motion' ),
					'bottom center' => esc_html__( 'Element bottom hits the middle', 'kinetic-motion' ),
					'center center' => esc_html__( 'Element middle hits the middle', 'kinetic-motion' ),
					'top top'       => esc_html__( 'Element top hits the top', 'kinetic-motion' ),
					'bottom bottom' => esc_html__( 'Element bottom hits the bottom', 'kinetic-motion' ),
				),
				'bottom top',
				array( 'condition' => $on )
			)
		);
		$el->add_control( 'kmo_sc_smooth', self::slider( esc_html__( 'Smoothing', 'kinetic-motion' ), 0, 3, 0.1, 0.6, 's', array( 'condition' => $on, 'description' => esc_html__( '0 = follows the scrollbar exactly. Higher = softer catch-up.', 'kinetic-motion' ) ) ) );
		$el->add_control( 'kmo_sc_off_mobile', self::switcher( esc_html__( 'Disable on phones', 'kinetic-motion' ), '', array( 'condition' => $on ) ) );

		/* Pin */
		$el->add_control(
			'kmo_pin',
			self::switcher(
				esc_html__( 'Pin while scrolling', 'kinetic-motion' ),
				'',
				array(
					'separator'   => 'before',
					'description' => esc_html__( 'Holds the element in place while the page scrolls past. Scroll effects above play during the pin.', 'kinetic-motion' ),
				)
			)
		);
		$pin = array( 'kmo_pin' => 'yes' );
		$el->add_control( 'kmo_pin_length', self::slider( esc_html__( 'Pin length', 'kinetic-motion' ), 10, 600, 5, 100, 'vh', array( 'condition' => $pin, 'description' => esc_html__( '100 = one screen height of scrolling.', 'kinetic-motion' ) ) ) );
		$el->add_control( 'kmo_pin_offset', self::slider( esc_html__( 'Distance from top', 'kinetic-motion' ), 0, 400, 1, 0, 'px', array( 'condition' => $pin ) ) );
		$el->add_control( 'kmo_pin_off_mobile', self::switcher( esc_html__( 'Disable on phones', 'kinetic-motion' ), 'yes', array( 'condition' => $pin ) ) );

		/* Horizontal scroll (containers) */
		if ( 'container' === $name ) {
			$el->add_control(
				'kmo_hs',
				self::switcher(
					esc_html__( 'Horizontal scroll section', 'kinetic-motion' ),
					'',
					array(
						'separator'   => 'before',
						'description' => esc_html__( 'Pins this container and slides its inner elements sideways as visitors scroll down — great for card galleries and timelines.', 'kinetic-motion' ),
					)
				)
			);
			$hs = array( 'kmo_hs' => 'yes' );
			$el->add_control( 'kmo_hs_item', self::slider( esc_html__( 'Card width', 'kinetic-motion' ), 0, 100, 1, 40, 'vw', array( 'condition' => $hs, 'description' => esc_html__( 'Width of each inner element. 0 = keep the widths you set yourself.', 'kinetic-motion' ) ) ) );
			$el->add_control( 'kmo_hs_speed', self::slider( esc_html__( 'Scroll length', 'kinetic-motion' ), 0.5, 4, 0.1, 1, 'x', array( 'condition' => $hs, 'description' => esc_html__( 'How much vertical scrolling it takes. 2 = twice as long.', 'kinetic-motion' ) ) ) );
			$el->add_control( 'kmo_hs_off_mobile', self::switcher( esc_html__( 'Stack normally on phones', 'kinetic-motion' ), 'yes', array( 'condition' => $hs ) ) );
		}

		self::note(
			$el,
			'kmo_sc_note',
			esc_html__( 'Pinning and horizontal scroll are skipped inside the editor so you can keep editing. Preview the page to see them.', 'kinetic-motion' ),
			array()
		);

		$el->end_controls_section();
	}

	/* ------------------------------------------------------------------ */
	/* 3. Text animation (widgets)                                         */
	/* ------------------------------------------------------------------ */

	public static function text_effects() {
		return array(
			''      => esc_html__( 'None', 'kinetic-motion' ),
			'chars' => array(
				'label'   => esc_html__( 'Letters', 'kinetic-motion' ),
				'options' => array(
					'chars-rise'    => esc_html__( 'Letters rise (masked)', 'kinetic-motion' ),
					'chars-fade'    => esc_html__( 'Letters fade', 'kinetic-motion' ),
					'chars-blur'    => esc_html__( 'Letters blur in', 'kinetic-motion' ),
					'chars-flip'    => esc_html__( 'Letters flip (3D)', 'kinetic-motion' ),
					'chars-elastic' => esc_html__( 'Letters pop (elastic)', 'kinetic-motion' ),
					'chars-scatter' => esc_html__( 'Letters assemble (scatter)', 'kinetic-motion' ),
					'chars-wave'    => esc_html__( 'Letters wave (looping)', 'kinetic-motion' ),
				),
			),
			'words' => array(
				'label'   => esc_html__( 'Words', 'kinetic-motion' ),
				'options' => array(
					'words-rise'   => esc_html__( 'Words rise (masked)', 'kinetic-motion' ),
					'words-fade'   => esc_html__( 'Words fade', 'kinetic-motion' ),
					'words-blur'   => esc_html__( 'Words blur in', 'kinetic-motion' ),
					'words-slide'  => esc_html__( 'Words slide in', 'kinetic-motion' ),
					'words-rotate' => esc_html__( 'Words rotate up', 'kinetic-motion' ),
				),
			),
			'lines' => array(
				'label'   => esc_html__( 'Lines', 'kinetic-motion' ),
				'options' => array(
					'lines-rise' => esc_html__( 'Lines rise (masked)', 'kinetic-motion' ),
					'lines-fade' => esc_html__( 'Lines fade up', 'kinetic-motion' ),
					'lines-skew' => esc_html__( 'Lines skew up', 'kinetic-motion' ),
				),
			),
			'fx'    => array(
				'label'   => esc_html__( 'Special', 'kinetic-motion' ),
				'options' => array(
					'scramble'   => esc_html__( 'Scramble / decode', 'kinetic-motion' ),
					'typewriter' => esc_html__( 'Typewriter', 'kinetic-motion' ),
					'fill'       => esc_html__( 'Fill in while scrolling', 'kinetic-motion' ),
				),
			),
		);
	}

	private static function text( $el ) {
		$el->start_controls_section(
			'kmo_section_tx',
			array(
				'label' => esc_html__( 'Kinetic · Text Animation', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$el->add_control( 'kmo_tx', self::select( esc_html__( 'Effect', 'kinetic-motion' ), array(), '', array( 'groups' => self::text_effects() ) ) );
		$on = array( 'kmo_tx!' => '' );

		$el->add_control(
			'kmo_tx_trigger',
			self::select(
				esc_html__( 'Start', 'kinetic-motion' ),
				array(
					'scroll' => esc_html__( 'When scrolled into view', 'kinetic-motion' ),
					'load'   => esc_html__( 'On page load', 'kinetic-motion' ),
					'hover'  => esc_html__( 'In view, and again on hover', 'kinetic-motion' ),
					'scrub'  => esc_html__( 'Linked to scrolling', 'kinetic-motion' ),
				),
				'scroll',
				array( 'condition' => array( 'kmo_tx!' => array( '', 'fill', 'chars-wave' ) ) )
			)
		);
		$el->add_control( 'kmo_tx_duration', self::slider( esc_html__( 'Duration (each)', 'kinetic-motion' ), 0.1, 3, 0.05, 0.8, 's', array( 'condition' => array( 'kmo_tx!' => array( '', 'fill' ) ) ) ) );
		$el->add_control( 'kmo_tx_stagger', self::slider( esc_html__( 'Time between pieces', 'kinetic-motion' ), 0, 0.5, 0.005, 0.03, 's', array( 'condition' => array( 'kmo_tx!' => array( '', 'scramble', 'fill' ) ) ) ) );
		$el->add_control( 'kmo_tx_delay', self::slider( esc_html__( 'Delay', 'kinetic-motion' ), 0, 5, 0.05, 0, 's', array( 'condition' => array( 'kmo_tx!' => array( '', 'fill' ) ) ) ) );
		$el->add_control( 'kmo_tx_ease', self::select( esc_html__( 'Easing', 'kinetic-motion' ), self::eases(), 'power3.out', array( 'condition' => array( 'kmo_tx!' => array( '', 'scramble', 'typewriter', 'fill', 'chars-wave' ) ) ) ) );
		$el->add_control(
			'kmo_tx_ease_custom',
			array(
				'label'              => esc_html__( 'Custom easing', 'kinetic-motion' ),
				'type'               => Controls_Manager::TEXT,
				'placeholder'        => '0.22, 1, 0.36, 1',
				'frontend_available' => true,
				'render_type'        => 'none',
				'condition'          => array_merge( $on, array( 'kmo_tx_ease' => 'custom' ) ),
			)
		);
		$el->add_control( 'kmo_tx_mode', self::select( esc_html__( 'Playback', 'kinetic-motion' ), self::modes(), 'once', array( 'condition' => array( 'kmo_tx_trigger' => 'scroll', 'kmo_tx!' => array( '', 'fill', 'chars-wave' ) ) ) ) );
		$el->add_control( 'kmo_tx_start', self::slider( esc_html__( 'Start when text reaches', 'kinetic-motion' ), 0, 100, 1, 85, '%', array( 'condition' => $on ) ) );

		$el->add_control(
			'kmo_tx_chars',
			self::select(
				esc_html__( 'Scramble characters', 'kinetic-motion' ),
				array(
					'upperCase'  => 'ABCDEFG',
					'lowerCase'  => 'abcdefg',
					'upperAndLowerCase' => 'AbCdEf',
					'0123456789' => '0123456789',
					'!<>-_\\/[]{}—=+*^?#' => '!<>-_/[]{}=+*^?#',
					'01'         => '01 (binary)',
				),
				'upperCase',
				array( 'condition' => array( 'kmo_tx' => 'scramble' ) )
			)
		);
		$el->add_control( 'kmo_tx_cursor', self::switcher( esc_html__( 'Blinking cursor', 'kinetic-motion' ), 'yes', array( 'condition' => array( 'kmo_tx' => 'typewriter' ) ) ) );
		$el->add_control( 'kmo_tx_dim', self::slider( esc_html__( 'Unfilled opacity', 'kinetic-motion' ), 0, 1, 0.01, 0.15, 'x', array( 'condition' => array( 'kmo_tx' => 'fill' ) ) ) );
		$el->add_control(
			'kmo_tx_fill_by',
			self::select(
				esc_html__( 'Fill by', 'kinetic-motion' ),
				array(
					'words' => esc_html__( 'Word', 'kinetic-motion' ),
					'chars' => esc_html__( 'Letter', 'kinetic-motion' ),
				),
				'words',
				array( 'condition' => array( 'kmo_tx' => 'fill' ) )
			)
		);
		$el->add_control(
			'kmo_tx_selector',
			array(
				'label'              => esc_html__( 'Text selector (optional)', 'kinetic-motion' ),
				'type'               => Controls_Manager::TEXT,
				'placeholder'        => '.elementor-heading-title',
				'description'        => esc_html__( 'Leave empty to find the text automatically (headings, text editor, buttons).', 'kinetic-motion' ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'separator'          => 'before',
				'condition'          => $on,
			)
		);
		$el->add_control( 'kmo_tx_off_mobile', self::switcher( esc_html__( 'Disable on phones', 'kinetic-motion' ), '', array( 'condition' => $on ) ) );

		$el->end_controls_section();
	}

	/* ------------------------------------------------------------------ */
	/* 4. Mouse effects                                                    */
	/* ------------------------------------------------------------------ */

	private static function mouse( $el ) {
		$el->start_controls_section(
			'kmo_section_mouse',
			array(
				'label' => esc_html__( 'Kinetic · Mouse Effects', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$el->add_control(
			'kmo_mouse',
			self::select(
				esc_html__( 'Effect', 'kinetic-motion' ),
				array(
					''         => esc_html__( 'None', 'kinetic-motion' ),
					'magnetic' => esc_html__( 'Magnetic (pulls toward the cursor)', 'kinetic-motion' ),
					'tilt'     => esc_html__( '3D tilt', 'kinetic-motion' ),
					'parallax' => esc_html__( 'Follow the cursor (depth parallax)', 'kinetic-motion' ),
				),
				'',
				array( 'description' => esc_html__( 'Desktop only — touch screens are skipped automatically.', 'kinetic-motion' ) )
			)
		);
		$on = array( 'kmo_mouse!' => '' );
		$el->add_control( 'kmo_mouse_strength', self::slider( esc_html__( 'Strength', 'kinetic-motion' ), 1, 100, 1, 30, '%', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_mouse_scale', self::slider( esc_html__( 'Scale on hover', 'kinetic-motion' ), 1, 1.3, 0.01, 1.03, 'x', array( 'condition' => array( 'kmo_mouse' => 'tilt' ) ) ) );
		$el->add_control( 'kmo_mouse_glare', self::switcher( esc_html__( 'Light glare', 'kinetic-motion' ), 'yes', array( 'condition' => array( 'kmo_mouse' => 'tilt' ) ) ) );
		$el->add_control( 'kmo_mouse_speed', self::slider( esc_html__( 'Follow speed', 'kinetic-motion' ), 0.1, 2, 0.05, 0.6, 's', array( 'condition' => $on ) ) );

		$el->end_controls_section();
	}

	/* ------------------------------------------------------------------ */
	/* 5. WebGL background (containers & sections)                         */
	/* ------------------------------------------------------------------ */

	private static function webgl( $el ) {
		$el->start_controls_section(
			'kmo_section_gl',
			array(
				'label' => esc_html__( 'Kinetic · WebGL Background', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$el->add_control(
			'kmo_gl',
			self::select(
				esc_html__( 'Background', 'kinetic-motion' ),
				array(
					''          => esc_html__( 'None', 'kinetic-motion' ),
					'gradient'  => esc_html__( 'Flowing gradient', 'kinetic-motion' ),
					'aurora'    => esc_html__( 'Aurora', 'kinetic-motion' ),
					'liquid'    => esc_html__( 'Liquid marble', 'kinetic-motion' ),
					'blobs'     => esc_html__( 'Morphing blobs', 'kinetic-motion' ),
					'particles' => esc_html__( 'Particle field (3D)', 'kinetic-motion' ),
					'waves'     => esc_html__( 'Wave grid (3D)', 'kinetic-motion' ),
				),
				'',
				array( 'description' => esc_html__( 'Rendered with three.js. Loads only when the section comes into view and pauses when off-screen.', 'kinetic-motion' ) )
			)
		);
		$on = array( 'kmo_gl!' => '' );

		$el->add_control( 'kmo_gl_c1', self::color( esc_html__( 'Color 1', 'kinetic-motion' ), '#F2636F', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_c2', self::color( esc_html__( 'Color 2', 'kinetic-motion' ), '#6C5CE7', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_c3', self::color( esc_html__( 'Color 3', 'kinetic-motion' ), '#00C2FF', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_bg', self::color( esc_html__( 'Base color', 'kinetic-motion' ), '#0B0B1A', array( 'condition' => $on ) ) );

		$el->add_control( 'kmo_gl_speed', self::slider( esc_html__( 'Speed', 'kinetic-motion' ), 0, 3, 0.05, 1, 'x', array( 'separator' => 'before', 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_scale', self::slider( esc_html__( 'Scale / zoom', 'kinetic-motion' ), 0.2, 3, 0.05, 1, 'x', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_intensity', self::slider( esc_html__( 'Intensity', 'kinetic-motion' ), 0, 2, 0.05, 1, 'x', array( 'condition' => $on ) ) );
		$el->add_control(
			'kmo_gl_density',
			self::slider(
				esc_html__( 'Density', 'kinetic-motion' ),
				0.2,
				3,
				0.1,
				1,
				'x',
				array( 'condition' => array( 'kmo_gl' => array( 'particles', 'waves' ) ) )
			)
		);
		$el->add_control( 'kmo_gl_size', self::slider( esc_html__( 'Dot size', 'kinetic-motion' ), 0.2, 4, 0.1, 1, 'x', array( 'condition' => array( 'kmo_gl' => array( 'particles', 'waves' ) ) ) ) );
		$el->add_control( 'kmo_gl_grain', self::slider( esc_html__( 'Film grain', 'kinetic-motion' ), 0, 0.3, 0.01, 0.05, 'x', array( 'condition' => array( 'kmo_gl' => array( 'gradient', 'aurora', 'liquid', 'blobs' ) ) ) ) );
		$el->add_control( 'kmo_gl_opacity', self::slider( esc_html__( 'Opacity', 'kinetic-motion' ), 0, 1, 0.01, 1, 'x', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_mouse', self::switcher( esc_html__( 'React to the mouse', 'kinetic-motion' ), 'yes', array( 'condition' => $on ) ) );
		$el->add_control( 'kmo_gl_scroll', self::switcher( esc_html__( 'React to scrolling', 'kinetic-motion' ), 'yes', array( 'condition' => $on ) ) );
		$el->add_control(
			'kmo_gl_quality',
			self::select(
				esc_html__( 'Quality', 'kinetic-motion' ),
				array(
					'low'    => esc_html__( 'Battery saver', 'kinetic-motion' ),
					'medium' => esc_html__( 'Balanced', 'kinetic-motion' ),
					'high'   => esc_html__( 'Sharp (retina)', 'kinetic-motion' ),
				),
				'medium',
				array(
					'separator' => 'before',
					'condition' => $on,
				)
			)
		);
		$el->add_control( 'kmo_gl_mobile', self::switcher( esc_html__( 'Show on phones', 'kinetic-motion' ), 'yes', array( 'condition' => $on, 'description' => esc_html__( 'When off, phones get a still gradient made from your colors.', 'kinetic-motion' ) ) ) );

		$el->end_controls_section();
	}
}
