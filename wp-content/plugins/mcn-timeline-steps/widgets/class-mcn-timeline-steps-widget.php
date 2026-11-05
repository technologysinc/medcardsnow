<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Icons_Manager;

class MCN_Timeline_Steps_Widget extends Widget_Base {

	public function get_name() {
		return 'mcn_timeline_steps';
	}

	public function get_title() {
		return esc_html__( 'MCN Timeline Steps', 'mcn-timeline-steps' );
	}

	public function get_icon() {
		return 'eicon-time-line';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'timeline', 'steps', 'process', 'how it works', 'number', 'roadmap', 'mcn' );
	}

	public function get_style_depends() {
		return array( 'mcn-timeline-steps' );
	}

	/* ===================================================================
	 *  CONTROLS
	 * ================================================================= */
	protected function register_controls() {

		/* -------------------------------------------------- CONTENT: STEPS */
		$this->start_controls_section( 'section_steps', array(
			'label' => esc_html__( 'Steps', 'mcn-timeline-steps' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$repeater = new Repeater();

		$repeater->add_control( 'marker_type', array(
			'label'   => esc_html__( 'Marker', 'mcn-timeline-steps' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'number',
			'options' => array(
				'number' => esc_html__( 'Number', 'mcn-timeline-steps' ),
				'icon'   => esc_html__( 'Icon', 'mcn-timeline-steps' ),
				'image'  => esc_html__( 'Image', 'mcn-timeline-steps' ),
			),
		) );

		$repeater->add_control( 'number', array(
			'label'       => esc_html__( 'Custom Number / Text', 'mcn-timeline-steps' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => esc_html__( 'Auto (1, 2, 3…)', 'mcn-timeline-steps' ),
			'condition'   => array( 'marker_type' => 'number' ),
		) );

		$repeater->add_control( 'icon', array(
			'label'     => esc_html__( 'Icon', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::ICONS,
			'condition' => array( 'marker_type' => 'icon' ),
		) );

		$repeater->add_control( 'image', array(
			'label'     => esc_html__( 'Image', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::MEDIA,
			'condition' => array( 'marker_type' => 'image' ),
		) );

		$repeater->add_control( 'title', array(
			'label'   => esc_html__( 'Title', 'mcn-timeline-steps' ),
			'type'    => Controls_Manager::TEXT,
			'default' => esc_html__( 'Step title', 'mcn-timeline-steps' ),
			'label_block' => true,
		) );

		$repeater->add_control( 'description', array(
			'label'   => esc_html__( 'Description', 'mcn-timeline-steps' ),
			'type'    => Controls_Manager::TEXTAREA,
			'default' => esc_html__( 'Describe this step in a sentence or two.', 'mcn-timeline-steps' ),
			'rows'    => 4,
		) );

		$repeater->add_control( 'link', array(
			'label'       => esc_html__( 'Title Link', 'mcn-timeline-steps' ),
			'type'        => Controls_Manager::URL,
			'placeholder' => 'https://your-link.com',
		) );

		$repeater->add_control( 'custom_colors', array(
			'label'        => esc_html__( 'Custom marker colors', 'mcn-timeline-steps' ),
			'type'         => Controls_Manager::SWITCHER,
			'separator'    => 'before',
			'return_value' => 'yes',
		) );

		$repeater->add_control( 'marker_bg', array(
			'label'     => esc_html__( 'Marker Background', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'condition' => array( 'custom_colors' => 'yes' ),
		) );

		$repeater->add_control( 'marker_color', array(
			'label'     => esc_html__( 'Marker Number / Icon Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'condition' => array( 'custom_colors' => 'yes' ),
		) );

		$this->add_control( 'steps', array(
			'label'       => esc_html__( 'Steps', 'mcn-timeline-steps' ),
			'type'        => Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ title }}}',
			'default'     => array(
				array( 'marker_type' => 'number', 'title' => esc_html__( 'Get a patient number', 'mcn-timeline-steps' ), 'description' => esc_html__( 'Apply to your state registry and receive a patient number.', 'mcn-timeline-steps' ) ),
				array( 'marker_type' => 'number', 'title' => esc_html__( 'Sign up', 'mcn-timeline-steps' ), 'description' => esc_html__( 'Register on any device and consult a licensed doctor in minutes.', 'mcn-timeline-steps' ) ),
				array( 'marker_type' => 'number', 'title' => esc_html__( 'Submit your application', 'mcn-timeline-steps' ), 'description' => esc_html__( 'Once approved, log in to submit your application and state fee.', 'mcn-timeline-steps' ) ),
				array( 'marker_type' => 'number', 'title' => esc_html__( 'Get your card', 'mcn-timeline-steps' ), 'description' => esc_html__( 'Receive your medical card shortly after approval.', 'mcn-timeline-steps' ) ),
			),
		) );

		$this->end_controls_section();

		/* -------------------------------------------------- CONTENT: LAYOUT */
		$this->start_controls_section( 'section_layout', array(
			'label' => esc_html__( 'Layout', 'mcn-timeline-steps' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'orientation', array(
			'label'   => esc_html__( 'Orientation', 'mcn-timeline-steps' ),
			'type'    => Controls_Manager::SELECT,
			'default' => 'horizontal',
			'options' => array(
				'horizontal' => esc_html__( 'Horizontal', 'mcn-timeline-steps' ),
				'vertical'   => esc_html__( 'Vertical', 'mcn-timeline-steps' ),
			),
			'prefix_class' => 'mcn-ts-o-',
		) );

		$this->add_control( 'mobile_stack', array(
			'label'        => esc_html__( 'Stack on mobile', 'mcn-timeline-steps' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'description'  => esc_html__( 'Turns a horizontal timeline vertical on phones.', 'mcn-timeline-steps' ),
			'condition'    => array( 'orientation' => 'horizontal' ),
		) );

		$this->add_control( 'show_connector', array(
			'label'        => esc_html__( 'Show connector line', 'mcn-timeline-steps' ),
			'type'         => Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_responsive_control( 'content_align', array(
			'label'     => esc_html__( 'Content Alignment', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::CHOOSE,
			'options'   => array(
				'left'   => array( 'title' => esc_html__( 'Left', 'mcn-timeline-steps' ), 'icon' => 'eicon-text-align-left' ),
				'center' => array( 'title' => esc_html__( 'Center', 'mcn-timeline-steps' ), 'icon' => 'eicon-text-align-center' ),
				'right'  => array( 'title' => esc_html__( 'Right', 'mcn-timeline-steps' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors' => array( '{{WRAPPER}} .mcn-ts--horizontal .mcn-ts__item' => 'text-align: {{VALUE}};' ),
			'condition' => array( 'orientation' => 'horizontal' ),
		) );

		$this->end_controls_section();

		/* -------------------------------------------------- STYLE: MARKER */
		$this->start_controls_section( 'section_style_marker', array(
			'label' => esc_html__( 'Marker', 'mcn-timeline-steps' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'marker_size', array(
			'label'      => esc_html__( 'Size', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 28, 'max' => 140 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 56 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts' => '--mcn-marker-size: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'marker_radius', array(
			'label'      => esc_html__( 'Corner Radius', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 70 ), '%' => array( 'min' => 0, 'max' => 50 ) ),
			'default'    => array( 'unit' => '%', 'size' => 50 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts__marker' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'num_heading', array( 'label' => esc_html__( 'Number', 'mcn-timeline-steps' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'num_typography',
			'selector' => '{{WRAPPER}} .mcn-ts__num',
		) );

		$this->add_responsive_control( 'icon_size', array(
			'label'      => esc_html__( 'Icon Size', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 10, 'max' => 72 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 24 ),
			'selectors'  => array(
				'{{WRAPPER}} .mcn-ts__marker i'   => 'font-size: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .mcn-ts__marker svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
			),
			'separator'  => 'before',
		) );

		// Normal / Hover color tabs.
		$this->start_controls_tabs( 'marker_color_tabs' );

		$this->start_controls_tab( 'marker_tab_normal', array( 'label' => esc_html__( 'Normal', 'mcn-timeline-steps' ) ) );
		$this->add_control( 'marker_bg', array(
			'label'     => esc_html__( 'Background', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#1D6FD6',
			'selectors' => array( '{{WRAPPER}} .mcn-ts' => '--mcn-marker-bg: {{VALUE}};' ),
		) );
		$this->add_control( 'marker_fg', array(
			'label'     => esc_html__( 'Number / Icon Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => array( '{{WRAPPER}} .mcn-ts' => '--mcn-marker-color: {{VALUE}};' ),
		) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'marker_tab_hover', array( 'label' => esc_html__( 'Hover', 'mcn-timeline-steps' ) ) );
		$this->add_control( 'marker_bg_hover', array(
			'label'     => esc_html__( 'Background', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .mcn-ts' => '--mcn-marker-bg-hover: {{VALUE}};' ),
		) );
		$this->add_control( 'marker_fg_hover', array(
			'label'     => esc_html__( 'Number / Icon Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .mcn-ts' => '--mcn-marker-color-hover: {{VALUE}};' ),
		) );
		$this->end_controls_tab();
		$this->end_controls_tabs();

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'      => 'marker_border',
			'selector'  => '{{WRAPPER}} .mcn-ts__marker',
			'separator' => 'before',
		) );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
			'name'     => 'marker_shadow',
			'selector' => '{{WRAPPER}} .mcn-ts__marker',
		) );

		$this->end_controls_section();

		/* -------------------------------------------------- STYLE: CONNECTOR */
		$this->start_controls_section( 'section_style_connector', array(
			'label'     => esc_html__( 'Connector Line', 'mcn-timeline-steps' ),
			'tab'       => Controls_Manager::TAB_STYLE,
			'condition' => array( 'show_connector' => 'yes' ),
		) );

		$this->add_control( 'line_color', array(
			'label'     => esc_html__( 'Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#E4EBF3',
			'selectors' => array( '{{WRAPPER}} .mcn-ts' => '--mcn-line-color: {{VALUE}};' ),
		) );

		$this->add_control( 'line_thickness', array(
			'label'      => esc_html__( 'Thickness', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 1, 'max' => 16 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 3 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts' => '--mcn-line-thickness: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'line_style', array(
			'label'     => esc_html__( 'Style', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'solid',
			'options'   => array(
				'solid'  => esc_html__( 'Solid', 'mcn-timeline-steps' ),
				'dashed' => esc_html__( 'Dashed', 'mcn-timeline-steps' ),
				'dotted' => esc_html__( 'Dotted', 'mcn-timeline-steps' ),
				'double' => esc_html__( 'Double', 'mcn-timeline-steps' ),
			),
			'selectors' => array( '{{WRAPPER}} .mcn-ts' => '--mcn-line-style: {{VALUE}};' ),
		) );

		$this->end_controls_section();

		/* -------------------------------------------------- STYLE: TITLE */
		$this->start_controls_section( 'section_style_title', array(
			'label' => esc_html__( 'Title', 'mcn-timeline-steps' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'title_typography',
			'selector' => '{{WRAPPER}} .mcn-ts__title',
		) );

		$this->add_control( 'title_color', array(
			'label'     => esc_html__( 'Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#0F2545',
			'selectors' => array( '{{WRAPPER}} .mcn-ts__title, {{WRAPPER}} .mcn-ts__title a' => 'color: {{VALUE}};' ),
		) );

		$this->add_control( 'title_color_hover', array(
			'label'     => esc_html__( 'Hover Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .mcn-ts__item:hover .mcn-ts__title, {{WRAPPER}} .mcn-ts__title a:hover' => 'color: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'title_spacing', array(
			'label'      => esc_html__( 'Spacing Below', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 8 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts__title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
		) );

		$this->end_controls_section();

		/* -------------------------------------------------- STYLE: DESCRIPTION */
		$this->start_controls_section( 'section_style_desc', array(
			'label' => esc_html__( 'Description', 'mcn-timeline-steps' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_group_control( Group_Control_Typography::get_type(), array(
			'name'     => 'desc_typography',
			'selector' => '{{WRAPPER}} .mcn-ts__desc',
		) );

		$this->add_control( 'desc_color', array(
			'label'     => esc_html__( 'Color', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '#51637A',
			'selectors' => array( '{{WRAPPER}} .mcn-ts__desc' => 'color: {{VALUE}};' ),
		) );

		$this->add_responsive_control( 'desc_max_width', array(
			'label'      => esc_html__( 'Max Width', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px', '%' ),
			'range'      => array( 'px' => array( 'min' => 120, 'max' => 600 ), '%' => array( 'min' => 20, 'max' => 100 ) ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts--horizontal .mcn-ts__content' => 'max-width: {{SIZE}}{{UNIT}}; margin-left:auto; margin-right:auto;' ),
			'condition'  => array( 'orientation' => 'horizontal' ),
		) );

		$this->end_controls_section();

		/* -------------------------------------------------- STYLE: ITEMS / BOX */
		$this->start_controls_section( 'section_style_items', array(
			'label' => esc_html__( 'Items & Spacing', 'mcn-timeline-steps' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'item_hspacing', array(
			'label'      => esc_html__( 'Horizontal Spacing', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 14 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts' => '--mcn-item-pad: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'item_vgap', array(
			'label'      => esc_html__( 'Vertical Gap', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 8, 'max' => 90 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 28 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts' => '--mcn-v-gap: {{SIZE}}{{UNIT}};' ),
			'description' => esc_html__( 'Used for vertical layout and the mobile-stacked view.', 'mcn-timeline-steps' ),
		) );

		$this->add_responsive_control( 'marker_content_gap', array(
			'label'      => esc_html__( 'Marker ↔ Content Gap', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 18 ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts' => '--mcn-mc-gap: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'item_bg', array(
			'label'     => esc_html__( 'Item Background', 'mcn-timeline-steps' ),
			'type'      => Controls_Manager::COLOR,
			'separator' => 'before',
			'selectors' => array( '{{WRAPPER}} .mcn-ts__item' => 'background: {{VALUE}};' ),
		) );

		$this->add_group_control( Group_Control_Border::get_type(), array(
			'name'     => 'item_border',
			'selector' => '{{WRAPPER}} .mcn-ts__item',
		) );

		$this->add_control( 'item_radius', array(
			'label'      => esc_html__( 'Item Radius', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts__item' => 'border-radius: {{SIZE}}{{UNIT}};' ),
		) );

		$this->add_responsive_control( 'content_padding', array(
			'label'      => esc_html__( 'Content Padding', 'mcn-timeline-steps' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => array( 'px', 'em' ),
			'selectors'  => array( '{{WRAPPER}} .mcn-ts__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		) );

		$this->end_controls_section();
	}

	/* ===================================================================
	 *  RENDER
	 * ================================================================= */
	protected function render() {
		$settings    = $this->get_settings_for_display();
		$steps       = isset( $settings['steps'] ) ? $settings['steps'] : array();
		$orientation = ! empty( $settings['orientation'] ) ? $settings['orientation'] : 'horizontal';

		$classes = array( 'mcn-ts', 'mcn-ts--' . $orientation );
		if ( 'horizontal' === $orientation && ! empty( $settings['mobile_stack'] ) && 'yes' === $settings['mobile_stack'] ) {
			$classes[] = 'mcn-ts--stack';
		}
		if ( empty( $settings['show_connector'] ) || 'yes' !== $settings['show_connector'] ) {
			$classes[] = 'mcn-ts--no-line';
		}
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php foreach ( $steps as $index => $step ) :

				$num       = ( isset( $step['number'] ) && '' !== $step['number'] ) ? $step['number'] : ( $index + 1 );
				$has_link  = ! empty( $step['link']['url'] );
				$item_vars = '';
				if ( ! empty( $step['custom_colors'] ) && 'yes' === $step['custom_colors'] ) {
					if ( ! empty( $step['marker_bg'] ) ) {
						$item_vars .= '--mcn-marker-bg:' . $step['marker_bg'] . ';';
					}
					if ( ! empty( $step['marker_color'] ) ) {
						$item_vars .= '--mcn-marker-color:' . $step['marker_color'] . ';';
					}
				}
				?>
				<div class="mcn-ts__item"<?php echo $item_vars ? ' style="' . esc_attr( $item_vars ) . '"' : ''; ?>>
					<div class="mcn-ts__marker">
						<?php
						if ( 'icon' === $step['marker_type'] && ! empty( $step['icon']['value'] ) ) {
							Icons_Manager::render_icon( $step['icon'], array( 'aria-hidden' => 'true' ) );
						} elseif ( 'image' === $step['marker_type'] && ! empty( $step['image']['url'] ) ) {
							printf( '<img src="%s" alt="%s">', esc_url( $step['image']['url'] ), esc_attr( $step['title'] ) );
						} else {
							printf( '<span class="mcn-ts__num">%s</span>', esc_html( $num ) );
						}
						?>
					</div>
					<div class="mcn-ts__content">
						<?php if ( ! empty( $step['title'] ) ) : ?>
							<h3 class="mcn-ts__title">
								<?php
								if ( $has_link ) {
									$target = ! empty( $step['link']['is_external'] ) ? ' target="_blank"' : '';
									$rel    = ! empty( $step['link']['nofollow'] ) ? ' rel="nofollow"' : '';
									printf( '<a href="%s"%s%s>%s</a>', esc_url( $step['link']['url'] ), $target, $rel, esc_html( $step['title'] ) );
								} else {
									echo esc_html( $step['title'] );
								}
								?>
							</h3>
						<?php endif; ?>
						<?php if ( ! empty( $step['description'] ) ) : ?>
							<p class="mcn-ts__desc"><?php echo wp_kses_post( nl2br( $step['description'] ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
