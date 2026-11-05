<?php
/**
 * Kinetic Marquee — infinite scrolling text / logo band that reacts to scroll speed.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;

class KMO_Widget_Marquee extends KMO_Widget_Base {

	public function get_name() {
		return 'kmo-marquee';
	}

	public function get_title() {
		return esc_html__( 'Kinetic Marquee', 'kinetic-motion' );
	}

	public function get_icon() {
		return 'eicon-animation-text';
	}

	public function get_keywords() {
		return array( 'marquee', 'ticker', 'scrolling text', 'logos', 'band', 'kinetic' );
	}

	protected function register_controls() {
		/* ---------------- Content: items ---------------- */
		$this->start_controls_section( 'section_items', array( 'label' => esc_html__( 'Items', 'kinetic-motion' ) ) );

		$rep = new Repeater();
		$rep->add_control(
			'text',
			array(
				'label'       => esc_html__( 'Text', 'kinetic-motion' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Kinetic', 'kinetic-motion' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'image',
			array(
				'label'   => esc_html__( 'Image or logo (optional)', 'kinetic-motion' ),
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array( 'active' => true ),
			)
		);
		$rep->add_control(
			'link',
			array(
				'label'   => esc_html__( 'Link (optional)', 'kinetic-motion' ),
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->add_control(
			'items',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $rep->get_controls(),
				'title_field' => '{{{ text }}}',
				'default'     => array(
					array( 'text' => esc_html__( 'Smooth', 'kinetic-motion' ) ),
					array( 'text' => esc_html__( 'Kinetic', 'kinetic-motion' ) ),
					array( 'text' => esc_html__( 'Motion', 'kinetic-motion' ) ),
					array( 'text' => esc_html__( 'Design', 'kinetic-motion' ) ),
				),
			)
		);

		$this->add_control(
			'separator',
			array(
				'label'     => esc_html__( 'Separator', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'text',
				'separator' => 'before',
				'options'   => array(
					'none' => esc_html__( 'None', 'kinetic-motion' ),
					'text' => esc_html__( 'Text / symbol', 'kinetic-motion' ),
					'icon' => esc_html__( 'Icon', 'kinetic-motion' ),
				),
			)
		);
		$this->add_control(
			'separator_text',
			array(
				'label'     => esc_html__( 'Symbol', 'kinetic-motion' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '✦',
				'condition' => array( 'separator' => 'text' ),
			)
		);
		$this->add_control(
			'separator_icon',
			array(
				'label'     => esc_html__( 'Icon', 'kinetic-motion' ),
				'type'      => Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'fas fa-star',
					'library' => 'fa-solid',
				),
				'condition' => array( 'separator' => 'icon' ),
			)
		);
		$this->end_controls_section();

		/* ---------------- Content: motion ---------------- */
		$this->start_controls_section( 'section_motion', array( 'label' => esc_html__( 'Motion', 'kinetic-motion' ) ) );

		$this->add_control(
			'direction',
			array(
				'label'   => esc_html__( 'Direction', 'kinetic-motion' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => 'left',
				'toggle'  => false,
				'options' => array(
					'left'  => array(
						'title' => esc_html__( 'Left', 'kinetic-motion' ),
						'icon'  => 'eicon-arrow-left',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'kinetic-motion' ),
						'icon'  => 'eicon-arrow-right',
					),
				),
			)
		);
		$this->add_control(
			'speed',
			array(
				'label'   => esc_html__( 'Speed', 'kinetic-motion' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array( 'px' => array( 'min' => 5, 'max' => 600, 'step' => 5 ) ),
				'default' => array( 'size' => 80 ),
				'description' => esc_html__( 'Pixels per second.', 'kinetic-motion' ),
			)
		);
		$this->add_control( 'pause_hover', array( 'label' => esc_html__( 'Slow down on hover', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'scroll_reverse', array( 'label' => esc_html__( 'Follow scroll direction', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'description' => esc_html__( 'Flips direction when visitors scroll up.', 'kinetic-motion' ) ) );
		$this->add_control(
			'scroll_boost',
			array(
				'label'   => esc_html__( 'Speed boost while scrolling', 'kinetic-motion' ),
				'type'    => Controls_Manager::SLIDER,
				'range'   => array( 'px' => array( 'min' => 0, 'max' => 10, 'step' => 0.5 ) ),
				'default' => array( 'size' => 3 ),
			)
		);
		$this->add_control( 'scroll_skew', array( 'label' => esc_html__( 'Skew with scroll speed', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_control(
			'skew_max',
			array(
				'label'     => esc_html__( 'Max skew', 'kinetic-motion' ),
				'type'      => Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'range'     => array( 'deg' => array( 'min' => 1, 'max' => 30 ) ),
				'default'   => array( 'unit' => 'deg', 'size' => 10 ),
				'condition' => array( 'scroll_skew' => 'yes' ),
			)
		);
		$this->add_control( 'second_row', array( 'label' => esc_html__( 'Second row (opposite direction)', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'separator' => 'before' ) );
		$this->add_control(
			'angle',
			array(
				'label'      => esc_html__( 'Tilt', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'range'      => array( 'deg' => array( 'min' => -20, 'max' => 20, 'step' => 0.5 ) ),
				'default'    => array( 'unit' => 'deg', 'size' => 0 ),
				'selectors'  => array( '{{WRAPPER}} .kmo-marquee' => '--kmo-mq-angle: {{SIZE}}deg;' ),
			)
		);
		$this->add_control( 'fade_edges', array( 'label' => esc_html__( 'Fade the edges', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control(
			'fade_width',
			array(
				'label'      => esc_html__( 'Fade width', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'range'      => array( '%' => array( 'min' => 1, 'max' => 40 ) ),
				'default'    => array( 'unit' => '%', 'size' => 10 ),
				'condition'  => array( 'fade_edges' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .kmo-marquee' => '--kmo-mq-fade: {{SIZE}}%;' ),
			)
		);
		$this->end_controls_section();

		/* ---------------- Style: band ---------------- */
		$this->start_controls_section(
			'section_style_band',
			array(
				'label' => esc_html__( 'Band', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'band_bg', array( 'label' => esc_html__( 'Background', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kmo-mq-row' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'band_bg2', array( 'label' => esc_html__( 'Second row background', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'condition' => array( 'second_row' => 'yes' ), 'selectors' => array( '{{WRAPPER}} .kmo-mq-row + .kmo-mq-row' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'band_padding',
			array(
				'label'      => esc_html__( 'Padding', 'kinetic-motion' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .kmo-mq-row' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => esc_html__( 'Space between rows', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'condition'  => array( 'second_row' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .kmo-marquee' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'      => esc_html__( 'Space between items', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 200 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 40 ),
				'selectors'  => array( '{{WRAPPER}} .kmo-marquee' => '--kmo-mq-gap: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		/* ---------------- Style: text ---------------- */
		$this->start_controls_section(
			'section_style_text',
			array(
				'label' => esc_html__( 'Text', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typo',
				'selector' => '{{WRAPPER}} .kmo-mq-text',
			)
		);
		$this->add_control( 'text_color', array( 'label' => esc_html__( 'Color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kmo-mq-text' => 'color: {{VALUE}};', '{{WRAPPER}} .kmo-marquee' => '--kmo-mq-color: {{VALUE}};' ) ) );
		$this->add_control( 'text_hover', array( 'label' => esc_html__( 'Hover color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kmo-mq-item:hover .kmo-mq-text' => 'color: {{VALUE}};' ) ) );
		$this->add_control(
			'outline',
			array(
				'label'     => esc_html__( 'Outline text', 'kinetic-motion' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'separator' => 'before',
				'options'   => array(
					'none'      => esc_html__( 'Off', 'kinetic-motion' ),
					'all'       => esc_html__( 'All items', 'kinetic-motion' ),
					'alternate' => esc_html__( 'Every other item', 'kinetic-motion' ),
				),
			)
		);
		$this->add_control(
			'stroke_width',
			array(
				'label'     => esc_html__( 'Outline width', 'kinetic-motion' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 6, 'step' => 0.5 ) ),
				'default'   => array( 'size' => 1.5 ),
				'condition' => array( 'outline!' => 'none' ),
				'selectors' => array( '{{WRAPPER}} .kmo-marquee' => '--kmo-mq-stroke: {{SIZE}}px;' ),
			)
		);
		$this->add_control( 'stroke_color', array( 'label' => esc_html__( 'Outline color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'condition' => array( 'outline!' => 'none' ), 'selectors' => array( '{{WRAPPER}} .kmo-marquee' => '--kmo-mq-stroke-color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		/* ---------------- Style: separator & images ---------------- */
		$this->start_controls_section(
			'section_style_sep',
			array(
				'label' => esc_html__( 'Separator & images', 'kinetic-motion' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control( 'sep_color', array( 'label' => esc_html__( 'Separator color', 'kinetic-motion' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kmo-mq-sep' => 'color: {{VALUE}}; fill: {{VALUE}};' ) ) );
		$this->add_responsive_control(
			'sep_size',
			array(
				'label'      => esc_html__( 'Separator size', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 6, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}} .kmo-mq-sep' => 'font-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control( 'sep_spin', array( 'label' => esc_html__( 'Spin separator', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_responsive_control(
			'img_h',
			array(
				'label'      => esc_html__( 'Image height', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 10, 'max' => 400 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 48 ),
				'separator'  => 'before',
				'selectors'  => array( '{{WRAPPER}} .kmo-mq-img' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'img_radius',
			array(
				'label'      => esc_html__( 'Image corner radius', 'kinetic-motion' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 200 ) ),
				'selectors'  => array( '{{WRAPPER}} .kmo-mq-img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control( 'img_gray', array( 'label' => esc_html__( 'Grayscale logos (color on hover)', 'kinetic-motion' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->end_controls_section();
	}

	private function render_group( $s, $outline ) {
		$i = 0;
		foreach ( (array) $s['items'] as $item ) {
			$classes = 'kmo-mq-item';
			if ( 'all' === $outline || ( 'alternate' === $outline && 1 === $i % 2 ) ) {
				$classes .= ' is-outline';
			}
			$has_link = ! empty( $item['link']['url'] );
			$tag      = $has_link ? 'a' : 'span';
			$attrs    = '';
			if ( $has_link ) {
				$attrs .= ' href="' . esc_url( $item['link']['url'] ) . '"';
				$rel    = array();
				if ( ! empty( $item['link']['is_external'] ) ) {
					$attrs .= ' target="_blank"';
					$rel[]  = 'noopener';
				}
				if ( ! empty( $item['link']['nofollow'] ) ) {
					$rel[] = 'nofollow';
				}
				if ( $rel ) {
					$attrs .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
				}
			}

			echo '<' . $tag . ' class="' . esc_attr( $classes ) . '"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( ! empty( $item['image']['url'] ) ) {
				$alt = ! empty( $item['image']['id'] ) ? get_post_meta( $item['image']['id'], '_wp_attachment_image_alt', true ) : '';
				printf( '<img class="kmo-mq-img" src="%1$s" alt="%2$s" loading="lazy" decoding="async">', esc_url( $item['image']['url'] ), esc_attr( $alt ? $alt : $item['text'] ) );
			}
			if ( '' !== trim( (string) $item['text'] ) ) {
				echo '<span class="kmo-mq-text">' . esc_html( $item['text'] ) . '</span>';
			}
			echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			if ( 'none' !== $s['separator'] ) {
				echo '<span class="kmo-mq-sep" aria-hidden="true">';
				if ( 'icon' === $s['separator'] ) {
					Icons_Manager::render_icon( $s['separator_icon'], array( 'aria-hidden' => 'true' ) );
				} else {
					echo esc_html( $s['separator_text'] );
				}
				echo '</span>';
			}
			$i++;
		}
	}

	protected function render() {
		$s = $this->settings(
			array(
				'items'          => array(),
				'separator'      => 'text',
				'separator_text' => '✦',
				'separator_icon' => array(),
				'direction'      => 'left',
				'pause_hover'    => 'yes',
				'scroll_reverse' => 'yes',
				'scroll_skew'    => '',
				'second_row'     => '',
				'fade_edges'     => 'yes',
				'outline'        => 'none',
				'sep_spin'       => '',
				'img_gray'       => '',
			)
		);
		if ( empty( $s['items'] ) ) {
			return;
		}

		$data = array(
			'type'    => 'marquee',
			'dir'     => 'right' === $s['direction'] ? 1 : -1,
			'speed'   => $this->size( $s, 'speed', 80 ),
			'hover'   => 'yes' === $s['pause_hover'],
			'reverse' => 'yes' === $s['scroll_reverse'],
			'boost'   => $this->size( $s, 'scroll_boost', 3 ),
			'skew'    => 'yes' === $s['scroll_skew'] ? $this->size( $s, 'skew_max', 10 ) : 0,
		);

		$classes = array( 'kmo-widget', 'kmo-marquee' );
		if ( 'yes' === $s['fade_edges'] ) {
			$classes[] = 'has-fade';
		}
		if ( 'yes' === $s['sep_spin'] ) {
			$classes[] = 'sep-spin';
		}
		if ( 'yes' === $s['img_gray'] ) {
			$classes[] = 'img-gray';
		}

		$rows = 'yes' === $s['second_row'] ? 2 : 1;

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $this->data_attr( $data ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		for ( $r = 0; $r < $rows; $r++ ) {
			echo '<div class="kmo-mq-row" data-row="' . (int) $r . '"><div class="kmo-mq-track"><div class="kmo-mq-group">';
			$this->render_group( $s, $s['outline'] );
			echo '</div></div></div>';
		}
		echo '</div>';
	}
}
