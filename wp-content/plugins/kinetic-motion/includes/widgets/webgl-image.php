<?php
/**
 * WebGL Image — three.js hover distortion, RGB shift, image swap and scroll reveal.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Utils;

class KMO_Widget_WebGL_Image extends KMO_Widget_Base {

	public function get_name() {
		return 'kmo-glimage';
	}

	public function get_title() {
		return esc_html__( 'WebGL Image', 'kinetic-motion' );
	}

	public function get_icon() {
		return 'eicon-image-hotspot';
	}

	public function get_keywords() {
		return array( 'webgl', 'three', 'distortion', 'image', 'hover', 'ripple', 'liquid', 'kinetic' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_image', array( 'label' => esc_html__( 'Image', 'kinetic-motion' ) ) );
		$this->add_control(
			'image',
			array(
				'label'   => esc_html__( 'Image', 'kinetic-motion' ),
				'type'    => Controls_Manager::MEDIA,
				'default' => array( 'url' => Utils::get_placeholder_image_src() ),
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'image',
				'default' => 'large',
			)
		);
		$this->add_control(
			'image2',
			array(
				'label'       => esc_html__( 'Hover image (for "Image swap")', 'kinetic-motion' ),
				'type'        => Controls_Manager::MEDIA,
				'dynamic'     => array( 'active' => true ),
				'condition'   => array( 'effect' => 'swap' ),
			)
		);
		$this->add_control(
			'ratio',
			array(
				'label'     => esc_html__( 'Aspect ratio', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'auto',
				'options'   => array(
					'auto' => esc_html__( 'Same as image', 'kinetic-motion' ),
					'1/1'  => '1 : 1',
					'4/3'  => '4 : 3',
					'3/2'  => '3 : 2',
					'16/9' => '16 : 9',
					'21/9' => '21 : 9',
					'3/4'  => '3 : 4',
					'2/3'  => '2 : 3',
					'9/16' => '9 : 16',
				),
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

		$this->start_controls_section( 'section_fx', array( 'label' => esc_html__( 'WebGL effect', 'kinetic-motion' ) ) );
		$this->add_control(
			'effect',
			array(
				'label'   => esc_html__( 'On hover', 'kinetic-motion' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'ripple',
				'options' => array(
					'ripple'  => esc_html__( 'Water ripple', 'kinetic-motion' ),
					'liquid'  => esc_html__( 'Liquid distortion', 'kinetic-motion' ),
					'rgb'     => esc_html__( 'RGB shift', 'kinetic-motion' ),
					'bulge'   => esc_html__( 'Magnify bulge', 'kinetic-motion' ),
					'pixel'   => esc_html__( 'Pixelate', 'kinetic-motion' ),
					'swap'    => esc_html__( 'Image swap (liquid transition)', 'kinetic-motion' ),
					'none'    => esc_html__( 'None', 'kinetic-motion' ),
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
		$this->add_control( 'idle', array( 'label' => esc_html__( 'Gentle wave when idle', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_control( 'scroll_wave', array( 'label' => esc_html__( 'Bend with scroll speed', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control(
			'reveal',
			array(
				'label'     => esc_html__( 'Reveal when scrolled into view', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'noise',
				'separator' => 'before',
				'options'   => array(
					'none'  => esc_html__( 'No reveal', 'kinetic-motion' ),
					'noise' => esc_html__( 'Noise dissolve', 'kinetic-motion' ),
					'wipe'  => esc_html__( 'Liquid wipe up', 'kinetic-motion' ),
					'pixel' => esc_html__( 'Pixels sharpen', 'kinetic-motion' ),
					'zoom'  => esc_html__( 'Zoom & warp', 'kinetic-motion' ),
				),
			)
		);
		$this->add_control(
			'reveal_duration',
			array(
				'label'      => esc_html__( 'Reveal duration', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's' ),
				'range'      => array( 's' => array( 'min' => 0.3, 'max' => 4, 'step' => 0.1 ) ),
				'default'    => array( 'unit' => 's', 'size' => 1.6 ),
				'condition'  => array( 'reveal!' => 'none' ),
			)
		);
		$this->add_control( 'mobile', array( 'label' => esc_html__( 'Use WebGL on phones', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Image', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'label'      => esc_html__( 'Width', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px', 'vw' ),
				'range'      => array(
					'%'  => array( 'min' => 10, 'max' => 100 ),
					'px' => array( 'min' => 50, 'max' => 2000 ),
				),
				'selectors'  => array( '{{WRAPPER}} .kmo-glimg' => 'width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'radius',
			array(
				'label'      => esc_html__( 'Corner radius', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 300 ) ),
				'selectors'  => array( '{{WRAPPER}} .kmo-glimg' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'shadow', 'selector' => '{{WRAPPER}} .kmo-glimg' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->settings(
			array(
				'image'       => array(),
				'image2'      => array(),
				'ratio'       => 'auto',
				'effect'      => 'ripple',
				'idle'        => '',
				'scroll_wave' => 'yes',
				'reveal'      => 'noise',
				'mobile'      => 'yes',
				'link'        => array(),
			)
		);
		if ( empty( $s['image']['url'] ) ) {
			return;
		}

		$src = $s['image']['url'];
		if ( ! empty( $s['image']['id'] ) ) {
			$sized = Group_Control_Image_Size::get_attachment_image_src( $s['image']['id'], 'image', $s );
			if ( $sized ) {
				$src = $sized;
			}
		}
		$src2 = ( 'swap' === $s['effect'] && ! empty( $s['image2']['url'] ) ) ? $s['image2']['url'] : '';
		$alt  = ! empty( $s['image']['id'] ) ? get_post_meta( $s['image']['id'], '_wp_attachment_image_alt', true ) : '';

		$w = 0;
		$h = 0;
		if ( ! empty( $s['image']['id'] ) ) {
			$meta = wp_get_attachment_metadata( $s['image']['id'] );
			if ( ! empty( $meta['width'] ) ) {
				$w = (int) $meta['width'];
				$h = (int) $meta['height'];
			}
		}

		$data = array(
			'type'      => 'glimage',
			'effect'    => $s['effect'],
			'src'       => $src,
			'src2'      => $src2,
			'intensity' => $this->size( $s, 'intensity', 1 ),
			'speed'     => $this->size( $s, 'speed', 1 ),
			'idle'      => 'yes' === $s['idle'],
			'scroll'    => 'yes' === $s['scroll_wave'],
			'reveal'    => $s['reveal'],
			'revealDur' => $this->size( $s, 'reveal_duration', 1.6 ),
			'mobile'    => 'yes' === $s['mobile'],
		);

		$ratios = array( '1/1', '4/3', '3/2', '16/9', '21/9', '3/4', '2/3', '9/16' );
		$style  = '';
		if ( in_array( $s['ratio'], $ratios, true ) ) {
			$style = ' style="aspect-ratio:' . esc_attr( $s['ratio'] ) . '"';
		} elseif ( $w && $h ) {
			$style = ' style="aspect-ratio:' . (int) $w . '/' . (int) $h . '"';
		}

		echo '<div class="kmo-widget kmo-glimg"' . $this->data_attr( $data ) . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( ! empty( $s['link']['url'] ) ) {
			$this->add_link_attributes( 'link', $s['link'] );
			echo '<a class="kmo-glimg-link" ' . $this->get_render_attribute_string( 'link' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		printf(
			'<img class="kmo-glimg-img" src="%1$s" alt="%2$s"%3$s loading="lazy" decoding="async">',
			esc_url( $src ),
			esc_attr( $alt ),
			$w && $h ? ' width="' . (int) $w . '" height="' . (int) $h . '"' : ''
		);
		if ( ! empty( $s['link']['url'] ) ) {
			echo '</a>';
		}
		echo '</div>';
	}
}
