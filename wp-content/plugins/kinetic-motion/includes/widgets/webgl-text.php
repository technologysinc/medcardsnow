<?php
/**
 * WebGL Text — real text rendered through a three.js shader: liquid waves, cursor ripples, RGB glitch.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

class KMO_Widget_WebGL_Text extends KMO_Widget_Base {

	public function get_name() {
		return 'kmo-gltext';
	}

	public function get_title() {
		return esc_html__( 'WebGL Text', 'kinetic-motion' );
	}

	public function get_icon() {
		return 'eicon-t-letter';
	}

	public function get_keywords() {
		return array( 'webgl', 'three', 'text', 'liquid', 'glitch', 'distortion', 'kinetic' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_text', array( 'label' => esc_html__( 'Text', 'kinetic-motion' ) ) );
		$this->add_control(
			'text',
			array(
				'label'   => esc_html__( 'Text', 'kinetic-motion' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'Kinetic', 'kinetic-motion' ),
				'rows'    => 2,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_control(
			'tag',
			array(
				'label'   => esc_html__( 'HTML tag', 'kinetic-motion' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'p'   => 'p',
					'div' => 'div',
				),
			)
		);
		$this->add_responsive_control(
			'align',
			array(
				'label'     => esc_html__( 'Alignment', 'kinetic-motion' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => array(
					'left'   => array( 'title' => esc_html__( 'Left', 'kinetic-motion' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => esc_html__( 'Center', 'kinetic-motion' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => esc_html__( 'Right', 'kinetic-motion' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .kmo-gltext-src' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_fx', array( 'label' => esc_html__( 'WebGL effect', 'kinetic-motion' ) ) );
		$this->add_control(
			'effect',
			array(
				'label'   => esc_html__( 'Effect', 'kinetic-motion' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'wave',
				'options' => array(
					'wave'   => esc_html__( 'Liquid wave (always moving)', 'kinetic-motion' ),
					'ripple' => esc_html__( 'Ripple under the cursor', 'kinetic-motion' ),
					'rgb'    => esc_html__( 'RGB glitch', 'kinetic-motion' ),
					'heat'   => esc_html__( 'Heat haze', 'kinetic-motion' ),
					'stretch' => esc_html__( 'Stretch with scroll speed', 'kinetic-motion' ),
				),
			)
		);
		$this->add_control(
			'intensity',
			array(
				'label'   => esc_html__( 'Intensity', 'kinetic-motion' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array( 'px' => array( 'min' => 0.1, 'max' => 3, 'step' => 0.05 ) ),
				'default' => array( 'size' => 1 ),
			)
		);
		$this->add_control(
			'speed',
			array(
				'label'   => esc_html__( 'Speed', 'kinetic-motion' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array( 'px' => array( 'min' => 0.1, 'max' => 3, 'step' => 0.05 ) ),
				'default' => array( 'size' => 1 ),
			)
		);
		$this->add_control( 'reveal', array( 'label' => esc_html__( 'Dissolve in when scrolled into view', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'mobile', array( 'label' => esc_html__( 'Use WebGL on phones', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control(
			'note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'The real text stays in the page for search engines and screen readers; WebGL draws an animated copy on top. Best for short, large headlines.', 'kinetic-motion' ),
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Text', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'typo',
				'selector'       => '{{WRAPPER}} .kmo-gltext-src',
				'fields_options' => array(
					'typography'  => array( 'default' => 'yes' ),
					'font_size'   => array(
						'default'        => array( 'unit' => 'px', 'size' => 120 ),
						'tablet_default' => array( 'unit' => 'px', 'size' => 84 ),
						'mobile_default' => array( 'unit' => 'px', 'size' => 56 ),
					),
					'font_weight' => array( 'default' => '800' ),
					'line_height' => array( 'default' => array( 'unit' => 'em', 'size' => 1 ) ),
				),
			)
		);
		$this->add_control( 'color', array( 'label' => esc_html__( 'Color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'default' => '#111111', 'selectors' => array( '{{WRAPPER}} .kmo-gltext' => '--kmo-glt-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->settings(
			array(
				'text'   => '',
				'tag'    => 'h2',
				'effect' => 'wave',
				'reveal' => 'yes',
				'mobile' => 'yes',
			)
		);
		$text = trim( (string) $s['text'] );
		if ( '' === $text ) {
			return;
		}
		$tag  = in_array( $s['tag'], array( 'h1', 'h2', 'h3', 'h4', 'p', 'div' ), true ) ? $s['tag'] : 'h2';
		$data = array(
			'type'      => 'gltext',
			'effect'    => $s['effect'],
			'intensity' => $this->size( $s, 'intensity', 1 ),
			'speed'     => $this->size( $s, 'speed', 1 ),
			'reveal'    => 'yes' === $s['reveal'],
			'mobile'    => 'yes' === $s['mobile'],
		);

		echo '<div class="kmo-widget kmo-gltext"' . $this->data_attr( $data ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<' . $tag . ' class="kmo-gltext-src">' . nl2br( esc_html( $text ) ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}
}
