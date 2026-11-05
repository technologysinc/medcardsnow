<?php
/**
 * Kinetic Heading — rotating words, split-text reveals, scroll fill and hand-drawn highlights.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;

class KMO_Widget_Kinetic_Heading extends KMO_Widget_Base {

	public function get_name() {
		return 'kmo-heading';
	}

	public function get_title() {
		return esc_html__( 'Kinetic Heading', 'kinetic-motion' );
	}

	public function get_icon() {
		return 'eicon-animated-headline';
	}

	public function get_keywords() {
		return array( 'heading', 'title', 'animated', 'rotating', 'typewriter', 'highlight', 'split', 'kinetic' );
	}

	protected function register_controls() {
		/* ---------------- Content ---------------- */
		$this->start_controls_section( 'section_content', array( 'label' => esc_html__( 'Heading', 'kinetic-motion' ) ) );

		$this->add_control(
			'mode',
			array(
				'label'   => esc_html__( 'Style', 'kinetic-motion' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'rotate',
				'options' => array(
					'rotate'    => esc_html__( 'Rotating words', 'kinetic-motion' ),
					'reveal'    => esc_html__( 'Split-text reveal', 'kinetic-motion' ),
					'fill'      => esc_html__( 'Fill in while scrolling', 'kinetic-motion' ),
					'highlight' => esc_html__( 'Hand-drawn highlight', 'kinetic-motion' ),
				),
			)
		);
		$this->add_control(
			'before',
			array(
				'label'       => esc_html__( 'Text before', 'kinetic-motion' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Websites that', 'kinetic-motion' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'words',
			array(
				'label'       => esc_html__( 'Animated text', 'kinetic-motion' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => "move\nconvert\nfeel alive",
				'rows'        => 4,
				'description' => esc_html__( 'Rotating words: one per line. Other styles: the words to animate or highlight.', 'kinetic-motion' ),
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'after',
			array(
				'label'       => esc_html__( 'Text after', 'kinetic-motion' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'   => esc_html__( 'HTML tag', 'kinetic-motion' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'p'    => 'p',
					'div'  => 'div',
					'span' => 'span',
				),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'     => esc_html__( 'Alignment', 'kinetic-motion' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => esc_html__( 'Left', 'kinetic-motion' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => esc_html__( 'Center', 'kinetic-motion' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => esc_html__( 'Right', 'kinetic-motion' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .kmo-kh-title' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'link',
			array(
				'label'   => esc_html__( 'Link', 'kinetic-motion' ),
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->end_controls_section();

		/* ---------------- Animation ---------------- */
		$this->start_controls_section( 'section_anim', array( 'label' => esc_html__( 'Animation', 'kinetic-motion' ) ) );

		// Rotate.
		$this->add_control(
			'rot_effect',
			array(
				'label'     => esc_html__( 'Word change effect', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'slide-up',
				'options'   => array(
					'slide-up'   => esc_html__( 'Slide up', 'kinetic-motion' ),
					'slide-down' => esc_html__( 'Slide down', 'kinetic-motion' ),
					'flip'       => esc_html__( 'Flip (3D)', 'kinetic-motion' ),
					'blur'       => esc_html__( 'Blur', 'kinetic-motion' ),
					'letters'    => esc_html__( 'Letters cascade', 'kinetic-motion' ),
					'clip'       => esc_html__( 'Wipe', 'kinetic-motion' ),
					'typewriter' => esc_html__( 'Typewriter', 'kinetic-motion' ),
					'scramble'   => esc_html__( 'Scramble', 'kinetic-motion' ),
				),
				'condition' => array( 'mode' => 'rotate' ),
			)
		);
		$this->add_control(
			'rot_interval',
			array(
				'label'      => esc_html__( 'Time on each word', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0.5, 'max' => 10, 'step' => 0.1 ) ),
				'default'    => array( 'unit' => 's', 'size' => 2.2 ),
				'condition'  => array( 'mode' => 'rotate' ),
			)
		);

		// Reveal.
		$effects = KMO_Extension::text_effects();
		unset( $effects[''], $effects['fx']['options']['fill'] );
		$this->add_control(
			'rv_effect',
			array(
				'label'     => esc_html__( 'Effect', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'chars-rise',
				'groups'    => $effects,
				'condition' => array( 'mode' => 'reveal' ),
			)
		);
		$this->add_control(
			'rv_trigger',
			array(
				'label'     => esc_html__( 'Start', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'scroll',
				'options'   => array(
					'scroll' => esc_html__( 'When scrolled into view', 'kinetic-motion' ),
					'load'   => esc_html__( 'On page load', 'kinetic-motion' ),
					'hover'  => esc_html__( 'In view, and again on hover', 'kinetic-motion' ),
					'scrub'  => esc_html__( 'Linked to scrolling', 'kinetic-motion' ),
				),
				'condition' => array( 'mode' => array( 'reveal', 'highlight' ) ),
			)
		);
		$this->add_control(
			'rv_mode',
			array(
				'label'     => esc_html__( 'Playback', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'once',
				'options'   => KMO_Extension::modes(),
				'condition' => array(
					'mode'       => array( 'reveal', 'highlight' ),
					'rv_trigger' => 'scroll',
				),
			)
		);

		// Shared timing.
		$this->add_control(
			'duration',
			array(
				'label'      => esc_html__( 'Duration', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0.1, 'max' => 4, 'step' => 0.05 ) ),
				'default'    => array( 'unit' => 's', 'size' => 0.8 ),
				'condition'  => array( 'mode!' => 'fill' ),
			)
		);
		$this->add_control(
			'stagger',
			array(
				'label'      => esc_html__( 'Time between letters/words', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0, 'max' => 0.5, 'step' => 0.005 ) ),
				'default'    => array( 'unit' => 's', 'size' => 0.03 ),
				'condition'  => array( 'mode' => array( 'reveal', 'rotate' ) ),
			)
		);
		$this->add_control(
			'delay',
			array(
				'label'      => esc_html__( 'Delay', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0, 'max' => 5, 'step' => 0.05 ) ),
				'default'    => array( 'unit' => 's', 'size' => 0 ),
				'condition'  => array( 'mode' => array( 'reveal', 'highlight' ) ),
			)
		);
		$this->add_control(
			'ease',
			array(
				'label'     => esc_html__( 'Easing', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'power3.out',
				'options'   => array_diff_key( $this->ease_options(), array( 'custom' => 1 ) ),
				'condition' => array( 'mode!' => 'fill' ),
			)
		);

		// Fill.
		$this->add_control(
			'fill_dim',
			array(
				'label'     => esc_html__( 'Unfilled opacity', 'kinetic-motion' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 1, 'step' => 0.01 ) ),
				'default'   => array( 'size' => 0.15 ),
				'condition' => array( 'mode' => 'fill' ),
			)
		);
		$this->add_control(
			'fill_by',
			array(
				'label'     => esc_html__( 'Fill by', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'words',
				'options'   => array(
					'words' => esc_html__( 'Word', 'kinetic-motion' ),
					'chars' => esc_html__( 'Letter', 'kinetic-motion' ),
				),
				'condition' => array( 'mode' => 'fill' ),
			)
		);

		// Highlight.
		$this->add_control(
			'hl_style',
			array(
				'label'     => esc_html__( 'Highlight', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'underline',
				'options'   => array(
					'marker'    => esc_html__( 'Marker sweep', 'kinetic-motion' ),
					'underline' => esc_html__( 'Hand-drawn underline', 'kinetic-motion' ),
					'double'    => esc_html__( 'Double underline', 'kinetic-motion' ),
					'zigzag'    => esc_html__( 'Zigzag', 'kinetic-motion' ),
					'circle'    => esc_html__( 'Circle', 'kinetic-motion' ),
					'strike'    => esc_html__( 'Strike-through', 'kinetic-motion' ),
					'cross'     => esc_html__( 'Cross out', 'kinetic-motion' ),
				),
				'condition' => array( 'mode' => 'highlight' ),
			)
		);
		$this->end_controls_section();

		/* ---------------- Style: heading ---------------- */
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Heading', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'typo', 'selector' => '{{WRAPPER}} .kmo-kh-title' ) );
		$this->add_control( 'color', array( 'label' => esc_html__( 'Color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kmo-kh-title' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Text_Shadow::get_type(), array( 'name' => 'shadow', 'selector' => '{{WRAPPER}} .kmo-kh-title' ) );
		$this->end_controls_section();

		/* ---------------- Style: animated text ---------------- */
		$this->start_controls_section(
			'section_style_anim',
			array(
				'label'     => esc_html__( 'Animated text', 'kinetic-motion' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'mode' => array( 'rotate', 'highlight' ) ),
			)
		);
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'typo_anim', 'selector' => '{{WRAPPER}} .kmo-kh-anim' ) );
		$this->add_control(
			'anim_fill',
			array(
				'label'   => esc_html__( 'Fill', 'kinetic-motion' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'color',
				'options' => array(
					'color'    => esc_html__( 'Color', 'kinetic-motion' ),
					'gradient' => esc_html__( 'Gradient', 'kinetic-motion' ),
					'outline'  => esc_html__( 'Outline only', 'kinetic-motion' ),
				),
			)
		);
		$this->add_control( 'anim_color', array( 'label' => esc_html__( 'Color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'default' => '#F2636F', 'selectors' => array( '{{WRAPPER}} .kmo-kh-anim' => '--kmo-kh-c1: {{VALUE}}; color: {{VALUE}};' ) ) );
		$this->add_control( 'anim_color2', array( 'label' => esc_html__( 'Second color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'default' => '#6C5CE7', 'condition' => array( 'anim_fill' => 'gradient' ), 'selectors' => array( '{{WRAPPER}} .kmo-kh-anim' => '--kmo-kh-c2: {{VALUE}};' ) ) );
		$this->add_control(
			'anim_angle',
			array(
				'label'      => esc_html__( 'Gradient angle', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'range'      => array( 'deg' => array( 'min' => 0, 'max' => 360 ) ),
				'default'    => array( 'unit' => 'deg', 'size' => 90 ),
				'condition'  => array( 'anim_fill' => 'gradient' ),
				'selectors'  => array( '{{WRAPPER}} .kmo-kh-anim' => '--kmo-kh-angle: {{SIZE}}deg;' ),
			)
		);
		$this->add_control(
			'anim_stroke',
			array(
				'label'     => esc_html__( 'Outline width', 'kinetic-motion' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 6, 'step' => 0.5 ) ),
				'default'   => array( 'size' => 1.5 ),
				'condition' => array( 'anim_fill' => 'outline' ),
				'selectors' => array( '{{WRAPPER}} .kmo-kh-anim' => '--kmo-kh-stroke: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'hl_color',
			array(
				'label'     => esc_html__( 'Highlight color', 'kinetic-motion' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#FFD43B',
				'separator' => 'before',
				'condition' => array( 'mode' => 'highlight' ),
				'selectors' => array( '{{WRAPPER}} .kmo-kh-hl' => '--kmo-hl-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'hl_width',
			array(
				'label'     => esc_html__( 'Line thickness', 'kinetic-motion' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 1, 'max' => 30 ) ),
				'default'   => array( 'size' => 8 ),
				'condition' => array(
					'mode'      => 'highlight',
					'hl_style!' => 'marker',
				),
				'selectors' => array( '{{WRAPPER}} .kmo-kh-hl' => '--kmo-hl-width: {{SIZE}};' ),
			)
		);
		$this->add_control(
			'hl_front',
			array(
				'label'     => esc_html__( 'Draw over the text', 'kinetic-motion' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => '',
				'condition' => array( 'mode' => 'highlight' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Hand-drawn SVG paths, drawn in a 500 × 150 box stretched over the words.
	 */
	private function highlight_svg( $style ) {
		$paths = array(
			'underline' => 'M8 132 C 120 118, 330 116, 494 128',
			'double'    => 'M8 124 C 140 112, 340 112, 494 122 M40 144 C 170 134, 330 134, 470 142',
			'zigzag'    => 'M6 134 L 40 118 L 74 134 L 108 118 L 142 134 L 176 118 L 210 134 L 244 118 L 278 134 L 312 118 L 346 134 L 380 118 L 414 134 L 448 118 L 494 134',
			'circle'    => 'M262 12 C 392 8, 496 40, 490 80 C 484 126, 356 146, 236 142 C 104 138, 6 116, 12 72 C 18 32, 128 10, 278 16',
			'strike'    => 'M6 84 C 160 70, 340 92, 494 76',
			'cross'     => 'M10 20 C 160 60, 330 100, 490 140 M490 14 C 330 56, 170 98, 10 136',
		);
		if ( ! isset( $paths[ $style ] ) ) {
			return '';
		}
		return '<svg class="kmo-kh-hl-svg" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="' . esc_attr( $paths[ $style ] ) . '" pathLength="1"/></svg>';
	}

	protected function render() {
		$s     = $this->settings(
			array(
				'mode'       => 'rotate',
				'before'     => '',
				'words'      => '',
				'after'      => '',
				'tag'        => 'h2',
				'link'       => array(),
				'rot_effect' => 'slide-up',
				'rv_effect'  => 'chars-rise',
				'rv_trigger' => 'scroll',
				'rv_mode'    => 'once',
				'ease'       => 'power3.out',
				'fill_by'    => 'words',
				'hl_style'   => 'underline',
				'hl_front'   => '',
				'anim_fill'  => 'color',
			)
		);
		$mode  = in_array( $s['mode'], array( 'rotate', 'reveal', 'fill', 'highlight' ), true ) ? $s['mode'] : 'rotate';
		$tag   = in_array( $s['tag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ), true ) ? $s['tag'] : 'h2';
		$words = array_values( array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $s['words'] ) ), 'strlen' ) );

		if ( ! $words && '' === trim( $s['before'] . $s['after'] ) ) {
			return;
		}

		$data = array(
			'type'     => 'heading',
			'mode'     => $mode,
			'effect'   => 'rotate' === $mode ? $s['rot_effect'] : $s['rv_effect'],
			'interval' => $this->size( $s, 'rot_interval', 2.2 ),
			'duration' => $this->size( $s, 'duration', 0.8 ),
			'stagger'  => $this->size( $s, 'stagger', 0.03 ),
			'delay'    => $this->size( $s, 'delay', 0 ),
			'ease'     => $s['ease'] ? $s['ease'] : 'power3.out',
			'trigger'  => $s['rv_trigger'],
			'playback' => $s['rv_mode'],
			'dim'      => $this->size( $s, 'fill_dim', 0.15 ),
			'fillBy'   => $s['fill_by'],
			'hl'       => $s['hl_style'],
		);

		$classes = array( 'kmo-widget', 'kmo-kh', 'kmo-kh--' . $mode, 'kmo-kh-fill-' . $s['anim_fill'] );
		if ( 'highlight' === $mode && 'yes' === $s['hl_front'] ) {
			$classes[] = 'kmo-kh-hl-front';
		}

		$open  = '';
		$close = '';
		if ( ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'link', $s['link'] );
			$open  = '<a ' . $this->get_render_attribute_string( 'link' ) . '>';
			$close = '</a>';
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $this->data_attr( $data ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<' . $tag . ' class="kmo-kh-title">' . $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' !== trim( $s['before'] ) ) {
			echo '<span class="kmo-kh-before">' . esc_html( $s['before'] ) . ' </span>';
		}

		if ( $words ) {
			if ( 'rotate' === $mode ) {
				echo '<span class="kmo-kh-anim kmo-kh-rotator">';
				echo '<span class="kmo-kh-rot" aria-hidden="true">';
				foreach ( $words as $i => $w ) {
					echo '<span class="kmo-kh-word' . ( 0 === $i ? ' is-active' : '' ) . '">' . esc_html( $w ) . '</span>';
				}
				echo '</span>';
				echo '<span class="kmo-sr">' . esc_html( implode( ', ', $words ) ) . '</span>';
				echo '</span>';
			} elseif ( 'highlight' === $mode ) {
				$style = $s['hl_style'];
				echo '<span class="kmo-kh-anim kmo-kh-hl kmo-kh-hl--' . esc_attr( $style ) . '"><span class="kmo-kh-hl-text">' . esc_html( implode( ' ', $words ) ) . '</span>';
				echo 'marker' === $style ? '<span class="kmo-kh-hl-marker" aria-hidden="true"></span>' : $this->highlight_svg( $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</span>';
			} else {
				echo '<span class="kmo-kh-anim kmo-kh-plain">' . esc_html( implode( ' ', $words ) ) . '</span>';
			}
		}

		if ( '' !== trim( $s['after'] ) ) {
			echo '<span class="kmo-kh-after"> ' . esc_html( $s['after'] ) . '</span>';
		}

		echo $close . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
