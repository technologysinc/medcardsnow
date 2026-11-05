<?php
/** Elementor widget implementation. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TableCraft_Elementor_Widget extends \Elementor\Widget_Base {
	public function get_name() {
		return 'tablecraft-table';
	}

	public function get_title() {
		return esc_html__( 'TableCraft Table', 'tablecraft-elementor' );
	}

	public function get_icon() {
		return 'eicon-table';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'table', 'data', 'acf', 'google sheets', 'pricing', 'comparison' );
	}

	public function get_style_depends() {
		return array( 'tablecraft-frontend' );
	}

	public function get_script_depends() {
		return array( 'tablecraft-frontend' );
	}

	protected function register_controls() {
		$this->content_controls();
		$this->functionality_controls();
		$this->style_controls();
	}

	private function content_controls() {
		$this->start_controls_section(
			'tc_section_content',
			array( 'label' => esc_html__( 'Table Content', 'tablecraft-elementor' ), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT )
		);

		$this->add_control(
			'tc_source',
			array(
				'label' => esc_html__( 'Data source', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'static',
				'options' => array(
					'static' => esc_html__( 'Manual / Static data', 'tablecraft-elementor' ),
					'acf_single' => esc_html__( 'ACF Fields (single row)', 'tablecraft-elementor' ),
					'acf' => esc_html__( 'ACF Repeater (multiple rows)', 'tablecraft-elementor' ),
					'google' => esc_html__( 'Google Sheets (public CSV)', 'tablecraft-elementor' ),
				),
			)
		);

		$this->add_control(
			'tc_template',
			array(
				'label' => esc_html__( 'Preloaded table template', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'pricing',
				'options' => array(
					'pricing' => esc_html__( 'Pricing plans', 'tablecraft-elementor' ),
					'comparison' => esc_html__( 'Feature comparison', 'tablecraft-elementor' ),
					'products' => esc_html__( 'Product catalog', 'tablecraft-elementor' ),
					'directory' => esc_html__( 'Team / business directory', 'tablecraft-elementor' ),
					'schedule' => esc_html__( 'Event schedule', 'tablecraft-elementor' ),
					'inventory' => esc_html__( 'Inventory list', 'tablecraft-elementor' ),
					'custom' => esc_html__( 'Custom table (edit columns and rows)', 'tablecraft-elementor' ),
				),
				'condition' => array( 'tc_source' => 'static' ),
				'description' => esc_html__( 'Preloaded templates include starter columns and sample rows. Choose Custom table to edit your own columns and row data. ACF and Google Sheets use the connected source instead.', 'tablecraft-elementor' ),
			)
		);

		$this->add_control(
			'tc_preset',
			array(
				'label' => esc_html__( 'Design preset', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'modern',
				'options' => array(
					'modern' => esc_html__( 'Modern – clean card', 'tablecraft-elementor' ),
					'minimal' => esc_html__( 'Minimal – editorial', 'tablecraft-elementor' ),
					'striped' => esc_html__( 'Striped – data grid', 'tablecraft-elementor' ),
					'pricing' => esc_html__( 'Pricing – highlighted values', 'tablecraft-elementor' ),
					'comparison' => esc_html__( 'Comparison – feature matrix', 'tablecraft-elementor' ),
					'compact' => esc_html__( 'Compact – dense data', 'tablecraft-elementor' ),
					'dark' => esc_html__( 'Dark – dashboard', 'tablecraft-elementor' ),
					'glass' => esc_html__( 'Glass – soft surface', 'tablecraft-elementor' ),
					'enterprise' => esc_html__( 'Enterprise – bordered', 'tablecraft-elementor' ),
				),
			)
		);

		$this->add_control(
			'tc_title',
			array(
				'label' => esc_html__( 'Table title', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'label_block' => true,
			)
		);
		$this->add_control(
			'tc_description',
			array(
				'label' => esc_html__( 'Description', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
				'rows' => 2,
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'label',
			array(
				'label' => esc_html__( 'Column heading', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Column', 'tablecraft-elementor' ),
				'label_block' => true,
			)
		);
		$repeater->add_control(
			'key',
			array(
				'label' => esc_html__( 'Data key / ACF sub-field', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => '',
				'description' => esc_html__( 'For ACF fields, use the field name. For ACF Repeater, use the sub-field name. For manual rows, column order is used.', 'tablecraft-elementor' ),
			)
		);
		$repeater->add_control(
			'align',
			array(
				'label' => esc_html__( 'Alignment', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'left',
				'options' => array( 'left' => esc_html__( 'Left', 'tablecraft-elementor' ), 'center' => esc_html__( 'Center', 'tablecraft-elementor' ), 'right' => esc_html__( 'Right', 'tablecraft-elementor' ) ),
			)
		);
		$repeater->add_control(
			'type',
			array(
				'label' => esc_html__( 'Cell display', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'text',
				'options' => array( 'text' => esc_html__( 'Text', 'tablecraft-elementor' ), 'link' => esc_html__( 'Clickable URL', 'tablecraft-elementor' ), 'badge' => esc_html__( 'Badge / Label', 'tablecraft-elementor' ) ),
			)
		);
		$repeater->add_control(
			'width',
			array(
				'label' => esc_html__( 'Width (optional)', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '20%',
				'description' => esc_html__( 'Examples: 120px, 20%, 10rem.', 'tablecraft-elementor' ),
			)
		);

		$this->add_control(
			'tc_columns_help',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw' => '<div class="elementor-panel-alert elementor-panel-alert-info"><strong>' . esc_html__( 'Editable table headings', 'tablecraft-elementor' ) . '</strong><br>' . esc_html__( 'Expand any column below and edit its heading, data key, alignment, display format, and width. Your changes will update the table preview even when a starter template is selected.', 'tablecraft-elementor' ) . '</div>',
			)
		);

		$this->add_control(
			'tc_columns',
			array(
				'label' => esc_html__( 'Table columns', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => array(
					array( 'label' => 'Product', 'key' => 'product', 'align' => 'left', 'type' => 'text', 'width' => '34%' ),
					array( 'label' => 'SKU', 'key' => 'sku', 'align' => 'center', 'type' => 'text', 'width' => '20%' ),
					array( 'label' => 'Price', 'key' => 'price', 'align' => 'right', 'type' => 'badge', 'width' => '20%' ),
					array( 'label' => 'Availability', 'key' => 'availability', 'align' => 'center', 'type' => 'badge', 'width' => '26%' ),
				),
				'title_field' => '{{{ label }}}',
				'description' => esc_html__( 'Edit a heading, data key, alignment, display type, or width here. Any changes are reflected in the table preview, including when a preloaded template is selected. Leave the starter columns unchanged to use the selected template defaults.', 'tablecraft-elementor' ),
			)
		);

		$this->add_control(
			'tc_static_rows',
			array(
				'label' => esc_html__( 'Editable row content', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
				'rows' => 9,
				'placeholder' => "Wireless keyboard|KB-104|$39.00|In stock\nUSB-C hub|HB-208|$24.50|Low stock\nLaptop stand|ST-310|$45.00|In stock",
				'description' => esc_html__( 'Optional: enter your own table cells to replace the template sample rows. One table row per line; separate cells with | and keep the same order as the columns above. Leave empty to keep the selected template sample data. This works with every preloaded template.', 'tablecraft-elementor' ),
				'condition' => array( 'tc_source' => 'static' ),
			)
		);

		$this->add_control(
			'tc_acf_field',
			array(
				'label' => esc_html__( 'ACF Repeater field name', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'pricing_rows',
				'description' => esc_html__( 'Enter the ACF Repeater field name, not its label. Each column key maps to a sub-field name.', 'tablecraft-elementor' ),
				'condition' => array( 'tc_source' => 'acf' ),
			)
		);
		$this->add_control(
			'tc_acf_post_id',
			array(
				'label' => esc_html__( 'ACF post ID (optional)', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'description' => esc_html__( 'Leave empty to use the current post being viewed.', 'tablecraft-elementor' ),
				'condition' => array( 'tc_source' => 'acf' ),
			)
		);

		$this->add_control(
			'tc_acf_single_post_id',
			array(
				'label' => esc_html__( 'ACF post ID (optional)', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'description' => esc_html__( 'For single-row ACF fields. Leave empty to use the current post.', 'tablecraft-elementor' ),
				'condition' => array( 'tc_source' => 'acf_single' ),
			)
		);

		$this->add_control(
			'tc_google_url',
			array(
				'label' => esc_html__( 'Google Sheets URL', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'input_type' => 'url',
				'placeholder' => 'https://docs.google.com/spreadsheets/d/…/edit',
				'description' => esc_html__( 'Use a sheet shared as “Anyone with the link – Viewer” or published to the web as CSV.', 'tablecraft-elementor' ),
				'label_block' => true,
				'condition' => array( 'tc_source' => 'google' ),
			)
		);
		$this->add_control(
			'tc_google_gid',
			array(
				'label' => esc_html__( 'Sheet tab GID (optional)', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '0',
				'description' => esc_html__( 'Use the gid from the tab URL when the spreadsheet contains multiple tabs.', 'tablecraft-elementor' ),
				'condition' => array( 'tc_source' => 'google' ),
			)
		);
		$this->add_control(
			'tc_google_headers',
			array(
				'label' => esc_html__( 'Use first row as headers', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'tablecraft-elementor' ),
				'label_off' => esc_html__( 'No', 'tablecraft-elementor' ),
				'return_value' => 'yes',
				'default' => 'yes',
				'condition' => array( 'tc_source' => 'google' ),
			)
		);
		$this->add_control(
			'tc_google_use_column_settings',
			array(
				'label' => esc_html__( 'Apply column formatting settings', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Yes', 'tablecraft-elementor' ),
				'label_off' => esc_html__( 'No', 'tablecraft-elementor' ),
				'return_value' => 'yes',
				'default' => '',
				'description' => esc_html__( 'When enabled, alignment, width, badge, and link display types are taken from the Table columns repeater by column position.', 'tablecraft-elementor' ),
				'condition' => array( 'tc_source' => 'google' ),
			)
		);
		$this->add_control(
			'tc_cache_minutes',
			array(
				'label' => esc_html__( 'Google Sheets cache (minutes)', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'min' => 1,
				'max' => 1440,
				'step' => 1,
				'default' => 15,
				'condition' => array( 'tc_source' => 'google' ),
				'description' => esc_html__( 'Cached on the WordPress server to reduce requests and improve page speed.', 'tablecraft-elementor' ),
			)
		);

		$this->end_controls_section();
	}

	private function functionality_controls() {
		$this->start_controls_section(
			'tc_section_functionality',
			array( 'label' => esc_html__( 'Table Features', 'tablecraft-elementor' ), 'tab' => \Elementor\Controls_Manager::TAB_CONTENT )
		);

		$features = array(
			'tc_search' => array( 'Search field', 'yes' ),
			'tc_sort' => array( 'Sortable columns', 'yes' ),
			'tc_pagination' => array( 'Pagination', 'yes' ),
			'tc_count' => array( 'Show row count', 'yes' ),
			'tc_sticky' => array( 'Sticky header', '' ),
		);
		foreach ( $features as $name => $config ) {
			$this->add_control(
				$name,
				array(
					'label' => esc_html__( $config[0], 'tablecraft-elementor' ),
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label_on' => esc_html__( 'On', 'tablecraft-elementor' ),
					'label_off' => esc_html__( 'Off', 'tablecraft-elementor' ),
					'return_value' => 'yes',
				)
			);
		}
		$this->add_control(
			'tc_page_size',
			array(
				'label' => esc_html__( 'Rows per page', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '10',
				'options' => array( '5' => '5', '10' => '10', '15' => '15', '20' => '20', '25' => '25', '50' => '50', '100' => '100' ),
				'condition' => array( 'tc_pagination' => 'yes' ),
			)
		);
		$this->add_control(
			'tc_mobile_mode',
			array(
				'label' => esc_html__( 'Mobile layout', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'scroll',
				'options' => array( 'scroll' => esc_html__( 'Horizontal scroll', 'tablecraft-elementor' ), 'cards' => esc_html__( 'Stacked cards', 'tablecraft-elementor' ) ),
			)
		);
		$this->add_control(
			'tc_empty_text',
			array(
				'label' => esc_html__( 'Empty state text', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'No records found.', 'tablecraft-elementor' ),
			)
		);
		$this->end_controls_section();
	}

	private function style_controls() {
		$this->start_controls_section(
			'tc_section_style',
			array( 'label' => esc_html__( 'Table Styling', 'tablecraft-elementor' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE )
		);

		$color_controls = array(
			'tc_header_bg' => array( 'Header background', '#172554' ),
			'tc_header_color' => array( 'Header text', '#ffffff' ),
			'tc_body_bg' => array( 'Body background', '#ffffff' ),
			'tc_body_color' => array( 'Body text', '#334155' ),
			'tc_accent' => array( 'Accent / active color', '#4f46e5' ),
			'tc_border_color' => array( 'Border color', '#e2e8f0' ),
			'tc_stripe_color' => array( 'Alternate row background', '#f8fafc' ),
			'tc_hover_color' => array( 'Row hover background', '#eef2ff' ),
		);
		foreach ( $color_controls as $name => $config ) {
			$this->add_control(
				$name,
				array(
					'label' => esc_html__( $config[0], 'tablecraft-elementor' ),
					'type' => \Elementor\Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .tc-table-wrap' => '--tc-' . str_replace( 'tc_', '', $name ) . ': {{VALUE}};' ),
				)
			);
		}
		$this->add_control(
			'tc_cell_padding',
			array(
				'label' => esc_html__( 'Cell padding', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 6, 'max' => 36 ) ),
				'selectors' => array( '{{WRAPPER}} .tc-table-wrap' => '--tc-cell-padding: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'tc_font_size',
			array(
				'label' => esc_html__( 'Body font size', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range' => array( 'px' => array( 'min' => 11, 'max' => 22 ), 'rem' => array( 'min' => 0.7, 'max' => 1.5, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .tc-table-wrap' => '--tc-font-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'tc_header_font_size',
			array(
				'label' => esc_html__( 'Header font size', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range' => array( 'px' => array( 'min' => 10, 'max' => 22 ), 'rem' => array( 'min' => 0.7, 'max' => 1.5, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .tc-table-wrap' => '--tc-header-font-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'tc_radius',
			array(
				'label' => esc_html__( 'Corner radius', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors' => array( '{{WRAPPER}} .tc-table-wrap' => '--tc-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'tc_border_width',
			array(
				'label' => esc_html__( 'Border width', 'tablecraft-elementor' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range' => array( 'px' => array( 'min' => 0, 'max' => 3 ) ),
				'selectors' => array( '{{WRAPPER}} .tc-table-wrap' => '--tc-border-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/** Render the widget. */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$columns  = $this->get_columns( $settings );
		$rows     = $this->get_rows( $settings, $columns );
		$source   = isset( $settings['tc_source'] ) ? $settings['tc_source'] : 'static';
		$preset   = isset( $settings['tc_preset'] ) ? sanitize_html_class( $settings['tc_preset'] ) : 'modern';
		$classes  = array( 'tc-table-wrap', 'tc-preset-' . $preset );
		$classes[] = ( isset( $settings['tc_mobile_mode'] ) && 'cards' === $settings['tc_mobile_mode'] ) ? 'tc-mobile-cards' : 'tc-mobile-scroll';
		if ( 'yes' === $this->setting( $settings, 'tc_sticky' ) ) {
			$classes[] = 'tc-sticky-header';
		}

		$attributes = array(
			'data-tc-table' => '1',
			'data-page-size' => (string) max( 1, absint( $this->setting( $settings, 'tc_page_size', 10 ) ) ),
			'data-search' => 'yes' === $this->setting( $settings, 'tc_search' ) ? '1' : '0',
			'data-sort' => 'yes' === $this->setting( $settings, 'tc_sort' ) ? '1' : '0',
			'data-pagination' => 'yes' === $this->setting( $settings, 'tc_pagination' ) ? '1' : '0',
			'data-count' => 'yes' === $this->setting( $settings, 'tc_count' ) ? '1' : '0',
			'data-empty' => isset( $settings['tc_empty_text'] ) ? $settings['tc_empty_text'] : esc_html__( 'No records found.', 'tablecraft-elementor' ),
		);

		$attr_html = '';
		foreach ( $attributes as $key => $value ) {
			$attr_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}

		wp_enqueue_style( 'tablecraft-frontend' );
		wp_enqueue_script( 'tablecraft-frontend' );

		?><div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $attr_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( ! empty( $settings['tc_title'] ) || ! empty( $settings['tc_description'] ) ) : ?>
				<div class="tc-heading">
					<?php if ( ! empty( $settings['tc_title'] ) ) : ?><h3 class="tc-title"><?php echo esc_html( $settings['tc_title'] ); ?></h3><?php endif; ?>
					<?php if ( ! empty( $settings['tc_description'] ) ) : ?><p class="tc-description"><?php echo esc_html( $settings['tc_description'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( 'google' === $source && is_wp_error( $rows ) ) : ?>
				<div class="tc-notice" role="status"><?php echo esc_html( $rows->get_error_message() ); ?></div>
			<?php elseif ( 'acf' === $source && is_wp_error( $rows ) ) : ?>
				<div class="tc-notice" role="status"><?php echo esc_html( $rows->get_error_message() ); ?></div>
			<?php else : ?>
				<?php $rows = is_array( $rows ) ? $rows : array(); ?>
				<?php if ( 'yes' === $this->setting( $settings, 'tc_search' ) ) : ?>
					<div class="tc-toolbar">
						<label class="tc-search-label">
							<span class="screen-reader-text"><?php esc_html_e( 'Search table', 'tablecraft-elementor' ); ?></span>
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
							<input class="tc-search" type="search" placeholder="<?php echo esc_attr__( 'Search records…', 'tablecraft-elementor' ); ?>" autocomplete="off" />
						</label>
					</div>
				<?php endif; ?>
				<div class="tc-scroll-area">
					<table class="tc-table">
						<thead><tr>
							<?php foreach ( $columns as $column ) : ?>
								<?php $width = $this->safe_width( isset( $column['width'] ) ? $column['width'] : '' ); ?>
								<th scope="col" style="text-align:<?php echo esc_attr( $column['align'] ); ?>;<?php echo $width ? 'width:' . esc_attr( $width ) . ';' : ''; ?>" data-sortable="<?php echo 'yes' === $this->setting( $settings, 'tc_sort' ) ? '1' : '0'; ?>">
									<span class="tc-th-inner"><?php echo esc_html( $column['label'] ); ?><?php if ( 'yes' === $this->setting( $settings, 'tc_sort' ) ) : ?><span class="tc-sort-indicator" aria-hidden="true">↕</span><?php endif; ?></span>
								</th>
							<?php endforeach; ?>
						</tr></thead>
						<tbody>
							<?php if ( empty( $rows ) ) : ?>
								<tr class="tc-empty-row"><td colspan="<?php echo esc_attr( max( 1, count( $columns ) ) ); ?>"><?php echo esc_html( $this->setting( $settings, 'tc_empty_text', esc_html__( 'No records found.', 'tablecraft-elementor' ) ) ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $rows as $row_index => $row ) : ?>
									<tr>
										<?php foreach ( $columns as $column_index => $column ) : ?>
											<?php
											$value = isset( $row[ $column_index ] ) ? $row[ $column_index ] : '';
											$display = $this->normalize_cell( $value );
											$type = isset( $column['type'] ) ? $column['type'] : 'text';
											$align = isset( $column['align'] ) ? $column['align'] : 'left';
											?>
											<td data-label="<?php echo esc_attr( $column['label'] ); ?>" style="text-align:<?php echo esc_attr( $align ); ?>">
												<?php if ( 'link' === $type && $display ) : ?>
													<?php $url = esc_url( $display ); ?>
													<?php if ( $url ) : ?><a class="tc-link" href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $this->link_label( $url ) ); ?></a><?php else : ?>—<?php endif; ?>
												<?php elseif ( 'badge' === $type && '' !== $display ) : ?>
													<span class="tc-badge"><?php echo esc_html( $display ); ?></span>
												<?php else : ?>
													<?php echo '' !== $display ? esc_html( $display ) : '—'; ?>
												<?php endif; ?>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<div class="tc-footer">
					<div class="tc-count" aria-live="polite"></div>
					<nav class="tc-pagination" aria-label="<?php echo esc_attr__( 'Table pagination', 'tablecraft-elementor' ); ?>"></nav>
				</div>
			<?php endif; ?>
		</div><?php
	}

	/** Return a setting or fallback. */
	private function setting( $settings, $key, $fallback = '' ) {
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
	}

	/** Prepare column definitions. */
	private function get_columns( $settings ) {
		$source   = $this->setting( $settings, 'tc_source', 'static' );
		$template = $this->setting( $settings, 'tc_template', 'pricing' );
		$columns  = isset( $settings['tc_columns'] ) && is_array( $settings['tc_columns'] ) ? $settings['tc_columns'] : array();

		// Elementor stores repeater defaults in some older widget instances. Treat
		// those untouched defaults as a request to use the selected starter template,
		// but always honor any actual user edits to the repeater.
		if ( 'static' === $source && 'custom' !== $template && $this->is_default_column_set( $columns ) ) {
			$template_data = $this->get_template_data( $template );
			if ( ! empty( $template_data['columns'] ) ) {
				return $template_data['columns'];
			}
		}
		$clean   = array();
		foreach ( array_slice( $columns, 0, 30 ) as $column ) {
			$label = isset( $column['label'] ) ? sanitize_text_field( $column['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$align = isset( $column['align'] ) && in_array( $column['align'], array( 'left', 'center', 'right' ), true ) ? $column['align'] : 'left';
			$type  = isset( $column['type'] ) && in_array( $column['type'], array( 'text', 'link', 'badge' ), true ) ? $column['type'] : 'text';
			$clean[] = array(
				'label' => $label,
				'key' => isset( $column['key'] ) ? sanitize_text_field( $column['key'] ) : '',
				'align' => $align,
				'type' => $type,
				'width' => isset( $column['width'] ) ? trim( (string) $column['width'] ) : '',
			);
		}
		if ( empty( $clean ) ) {
			$clean = array(
				array( 'label' => esc_html__( 'Column 1', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '' ),
				array( 'label' => esc_html__( 'Column 2', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '' ),
			);
		}
		return $clean;
	}

	/** Detect the untouched Elementor starter repeater values. */
	private function is_default_column_set( $columns ) {
		if ( ! is_array( $columns ) || empty( $columns ) ) {
			return true;
		}
		$normalize = static function ( $items ) {
			$normalized = array();
			foreach ( $items as $item ) {
				$normalized[] = array(
					'label' => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
					'key'   => isset( $item['key'] ) ? sanitize_text_field( $item['key'] ) : '',
					'align' => isset( $item['align'] ) ? $item['align'] : 'left',
					'type'  => isset( $item['type'] ) ? $item['type'] : 'text',
					'width' => isset( $item['width'] ) ? trim( (string) $item['width'] ) : '',
				);
			}
			return $normalized;
		};
		$current = $normalize( $columns );
		$known_defaults = array(
			array(
				array( 'label' => 'Product', 'key' => 'product', 'align' => 'left', 'type' => 'text', 'width' => '34%' ),
				array( 'label' => 'SKU', 'key' => 'sku', 'align' => 'center', 'type' => 'text', 'width' => '20%' ),
				array( 'label' => 'Price', 'key' => 'price', 'align' => 'right', 'type' => 'badge', 'width' => '20%' ),
				array( 'label' => 'Availability', 'key' => 'availability', 'align' => 'center', 'type' => 'badge', 'width' => '26%' ),
			),
			// 1.0.0 repeater defaults: preserve starter-template behavior for old widgets.
			array(
				array( 'label' => 'Plan', 'key' => 'plan', 'align' => 'left', 'type' => 'text', 'width' => '30%' ),
				array( 'label' => 'Price', 'key' => 'price', 'align' => 'center', 'type' => 'badge', 'width' => '20%' ),
				array( 'label' => 'Features', 'key' => 'features', 'align' => 'left', 'type' => 'text', 'width' => '35%' ),
				array( 'label' => 'Action', 'key' => 'action', 'align' => 'center', 'type' => 'link', 'width' => '15%' ),
			),
		);
		foreach ( $known_defaults as $default_columns ) {
			if ( $current === $normalize( $default_columns ) ) {
				return true;
			}
		}
		return false;
	}

	/** Build rows from the selected data source. */
	private function get_rows( $settings, &$columns ) {
		$source = $this->setting( $settings, 'tc_source', 'static' );
		if ( 'acf_single' === $source ) {
			if ( ! function_exists( 'get_field' ) ) {
				return new WP_Error( 'tc_acf_missing', esc_html__( 'ACF is not active. Install and activate Advanced Custom Fields to use this data source.', 'tablecraft-elementor' ) );
			}
			$post_id = absint( $this->setting( $settings, 'tc_acf_single_post_id' ) );
			if ( ! $post_id ) {
				$post_id = get_the_ID();
			}
			$line = array();
			foreach ( array_slice( $columns, 0, 30 ) as $column ) {
				$key = isset( $column['key'] ) ? sanitize_key( $column['key'] ) : '';
				$line[] = $key ? get_field( $key, $post_id ) : '';
			}
			return array( $line );
		}
		if ( 'acf' === $source ) {
			if ( ! function_exists( 'get_field' ) ) {
				return new WP_Error( 'tc_acf_missing', esc_html__( 'ACF is not active. Install and activate Advanced Custom Fields to use this data source.', 'tablecraft-elementor' ) );
			}
			$field = sanitize_key( $this->setting( $settings, 'tc_acf_field' ) );
			if ( ! $field ) {
				return new WP_Error( 'tc_acf_field', esc_html__( 'Enter the ACF Repeater field name in the widget settings.', 'tablecraft-elementor' ) );
			}
			$post_id = absint( $this->setting( $settings, 'tc_acf_post_id' ) );
			if ( ! $post_id ) {
				$post_id = get_the_ID();
			}
			$acf_rows = get_field( $field, $post_id );
			if ( ! is_array( $acf_rows ) ) {
				return array();
			}
			$result = array();
			foreach ( array_slice( $acf_rows, 0, 1000 ) as $acf_row ) {
				$line = array();
				foreach ( array_slice( $columns, 0, 30 ) as $column ) {
					$key = isset( $column['key'] ) ? $column['key'] : '';
					$line[] = ( $key && is_array( $acf_row ) && array_key_exists( $key, $acf_row ) ) ? $acf_row[ $key ] : '';
				}
				$result[] = $line;
			}
			return $result;
		}

		if ( 'google' === $source ) {
			$csv_rows = $this->fetch_google_sheet( $settings );
			if ( is_wp_error( $csv_rows ) ) {
				return $csv_rows;
			}
			$use_headers = 'yes' === $this->setting( $settings, 'tc_google_headers', 'yes' );
			if ( $use_headers && ! empty( $csv_rows ) ) {
				$headers = array_slice( array_shift( $csv_rows ), 0, 30 );
				$configured_columns = 'yes' === $this->setting( $settings, 'tc_google_use_column_settings' ) ? $columns : array();
				$columns = array();
				foreach ( $headers as $index => $header ) {
					$label = sanitize_text_field( $header );
					$config = isset( $configured_columns[ $index ] ) ? $configured_columns[ $index ] : array();
					$columns[] = array(
						'label' => $label ? $label : esc_html__( 'Column', 'tablecraft-elementor' ),
						'key' => '',
						'align' => isset( $config['align'] ) ? $config['align'] : 'left',
						'type' => isset( $config['type'] ) ? $config['type'] : 'text',
						'width' => isset( $config['width'] ) ? $config['width'] : '',
					);
				}
			}
			$limit = 1000;
			$csv_rows = array_slice( $csv_rows, 0, $limit );
			$result = array();
			foreach ( $csv_rows as $csv_row ) {
				$result[] = array_slice( array_pad( $csv_row, count( $columns ), '' ), 0, count( $columns ) );
			}
			return $result;
		}

		$template = $this->setting( $settings, 'tc_template', 'pricing' );
		$text     = trim( (string) $this->setting( $settings, 'tc_static_rows' ) );
		$legacy_default_rows = "Starter|$19 / month|Core features|https://example.com/start\nGrowth|$49 / month|Advanced tools|https://example.com/growth\nScale|$99 / month|All features included|https://example.com/scale";

		// Custom rows override all static starter templates. Ignore the old 1.0.0
		// textarea default so existing widgets keep their selected template samples.
		if ( '' !== $text && $legacy_default_rows !== $text ) {
			$lines = preg_split( '/\r\n|\r|\n/', $text );
			$rows  = array();
			foreach ( $lines as $line ) {
				if ( '' === trim( $line ) ) {
					continue;
				}
				$cells = str_getcsv( $line, '|', '"', '\\' );
				$cells = array_slice( array_pad( $cells, count( $columns ), '' ), 0, count( $columns ) );
				$rows[] = array_map( 'sanitize_text_field', $cells );
			}
			return $rows;
		}

		if ( 'static' === $source && 'custom' !== $template ) {
			$template_data = $this->get_template_data( $template );
			return isset( $template_data['rows'] ) ? $template_data['rows'] : array();
		}
		return array();
	}

	/** Return built-in starter templates with editable-style column definitions. */
	private function get_template_data( $template ) {
		$templates = array(
			'pricing' => array(
				'columns' => array(
					array( 'label' => esc_html__( 'Plan', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '24%' ),
					array( 'label' => esc_html__( 'Monthly price', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'badge', 'width' => '22%' ),
					array( 'label' => esc_html__( 'Best for', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '34%' ),
					array( 'label' => esc_html__( 'Get started', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'link', 'width' => '20%' ),
				),
				'rows' => array(
					array( 'Starter', '$19 / month', 'Personal websites', 'https://example.com/starter' ),
					array( 'Growth', '$49 / month', 'Growing teams', 'https://example.com/growth' ),
					array( 'Scale', '$99 / month', 'Established businesses', 'https://example.com/scale' ),
				),
			),
			'comparison' => array(
				'columns' => array(
					array( 'label' => esc_html__( 'Feature', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '34%' ),
					array( 'label' => esc_html__( 'Basic', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'badge', 'width' => '22%' ),
					array( 'label' => esc_html__( 'Pro', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'badge', 'width' => '22%' ),
					array( 'label' => esc_html__( 'Business', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'badge', 'width' => '22%' ),
				),
				'rows' => array(
					array( 'Custom domain', '✓', '✓', '✓' ),
					array( 'Analytics', '—', '✓', '✓' ),
					array( 'Priority support', '—', '—', '✓' ),
					array( 'Team seats', '1', '5', 'Unlimited' ),
				),
			),
			'products' => array(
				'columns' => array(
					array( 'label' => esc_html__( 'Product', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '34%' ),
					array( 'label' => esc_html__( 'SKU', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'text', 'width' => '20%' ),
					array( 'label' => esc_html__( 'Price', 'tablecraft-elementor' ), 'key' => '', 'align' => 'right', 'type' => 'badge', 'width' => '20%' ),
					array( 'label' => esc_html__( 'Availability', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'badge', 'width' => '26%' ),
				),
				'rows' => array(
					array( 'Wireless keyboard', 'KB-104', '$39.00', 'In stock' ),
					array( 'USB-C hub', 'HB-208', '$24.50', 'Low stock' ),
					array( 'Laptop stand', 'ST-310', '$45.00', 'In stock' ),
				),
			),
			'directory' => array(
				'columns' => array(
					array( 'label' => esc_html__( 'Name', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '28%' ),
					array( 'label' => esc_html__( 'Role', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '25%' ),
					array( 'label' => esc_html__( 'Email', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'link', 'width' => '27%' ),
					array( 'label' => esc_html__( 'Website', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'link', 'width' => '20%' ),
				),
				'rows' => array(
					array( 'Alex Morgan', 'Product lead', 'mailto:alex@example.com', 'https://example.com' ),
					array( 'Sam Taylor', 'Design director', 'mailto:sam@example.com', 'https://example.com' ),
					array( 'Jamie Lee', 'Developer', 'mailto:jamie@example.com', 'https://example.com' ),
				),
			),
			'schedule' => array(
				'columns' => array(
					array( 'label' => esc_html__( 'Time', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'badge', 'width' => '18%' ),
					array( 'label' => esc_html__( 'Session', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '36%' ),
					array( 'label' => esc_html__( 'Speaker', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '26%' ),
					array( 'label' => esc_html__( 'Room', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'text', 'width' => '20%' ),
				),
				'rows' => array(
					array( '09:00', 'Opening keynote', 'Alex Morgan', 'Hall A' ),
					array( '10:30', 'Designing better systems', 'Sam Taylor', 'Room 2' ),
					array( '12:00', 'Building for scale', 'Jamie Lee', 'Hall B' ),
				),
			),
			'inventory' => array(
				'columns' => array(
					array( 'label' => esc_html__( 'Item', 'tablecraft-elementor' ), 'key' => '', 'align' => 'left', 'type' => 'text', 'width' => '32%' ),
					array( 'label' => esc_html__( 'SKU', 'tablecraft-elementor' ), 'key' => '', 'align' => 'center', 'type' => 'text', 'width' => '22%' ),
					array( 'label' => esc_html__( 'Quantity', 'tablecraft-elementor' ), 'key' => '', 'align' => 'right', 'type' => 'badge', 'width' => '20%' ),
					array( 'label' => esc_html__( 'Unit cost', 'tablecraft-elementor' ), 'key' => '', 'align' => 'right', 'type' => 'text', 'width' => '26%' ),
				),
				'rows' => array(
					array( 'USB-C cable', 'CB-001', '124', '$4.20' ),
					array( 'Wall charger', 'CH-014', '38', '$8.50' ),
					array( 'Power bank 10K', 'PB-220', '12', '$13.00' ),
				),
			),
		);
		return isset( $templates[ $template ] ) ? $templates[ $template ] : $templates['pricing'];
	}

	/** Fetch and parse a public Google Sheets CSV feed. */
	private function fetch_google_sheet( $settings ) {
		$sheet_url = trim( (string) $this->setting( $settings, 'tc_google_url' ) );
		if ( ! $sheet_url ) {
			return new WP_Error( 'tc_google_url', esc_html__( 'Add a Google Sheets URL in the widget settings.', 'tablecraft-elementor' ) );
		}
		$parts = wp_parse_url( $sheet_url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) || 'docs.google.com' !== strtolower( $parts['host'] ) ) {
			return new WP_Error( 'tc_google_host', esc_html__( 'For security, TableCraft accepts Google Sheets URLs from docs.google.com over HTTPS only.', 'tablecraft-elementor' ) );
		}

		$gid = preg_replace( '/[^0-9]/', '', (string) $this->setting( $settings, 'tc_google_gid' ) );
		if ( ! $gid && ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
			if ( isset( $query_args['gid'] ) ) {
				$gid = preg_replace( '/[^0-9]/', '', (string) $query_args['gid'] );
			}
		}
		if ( ! $gid && ! empty( $parts['fragment'] ) && preg_match( '/gid=([0-9]+)/', $parts['fragment'], $match ) ) {
			$gid = $match[1];
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( false !== strpos( $path, '/spreadsheets/d/e/' ) || ( false !== strpos( $path, '/pub' ) && false !== strpos( $sheet_url, 'output=csv' ) ) ) {
			$fetch_url = $sheet_url;
		} else {
			if ( ! preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $path, $match ) ) {
				return new WP_Error( 'tc_google_id', esc_html__( 'Could not find a spreadsheet ID in that URL. Paste the normal Google Sheets share URL or a published CSV URL.', 'tablecraft-elementor' ) );
			}
			$spreadsheet_id = $match[1];
			$fetch_url = 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $spreadsheet_id ) . '/export?format=csv';
			if ( '' !== $gid ) {
				$fetch_url .= '&gid=' . rawurlencode( $gid );
			}
		}

		$cache_minutes = absint( $this->setting( $settings, 'tc_cache_minutes', 15 ) );
		$cache_minutes = min( 1440, max( 1, $cache_minutes ) );
		$cache_key = 'tc_sheet_' . md5( $fetch_url );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			$fetch_url,
			array(
				'timeout' => 12,
				'redirection' => 3,
				'limit_response_size' => 2 * MB_IN_BYTES,
				'headers' => array( 'Accept' => 'text/csv,text/plain;q=0.9,*/*;q=0.1' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'tc_google_request', esc_html__( 'Google Sheets could not be reached. Check the sheet URL and server outbound HTTPS access.', 'tablecraft-elementor' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== (int) $status || '' === trim( $body ) ) {
			return new WP_Error( 'tc_google_response', esc_html__( 'Google Sheets did not return CSV data. Check sharing permissions and publish/export settings.', 'tablecraft-elementor' ) );
		}
		if ( false !== stripos( substr( ltrim( $body ), 0, 250 ), '<!DOCTYPE html' ) || false !== stripos( substr( ltrim( $body ), 0, 250 ), '<html' ) ) {
			return new WP_Error( 'tc_google_private', esc_html__( 'Google returned a web page instead of CSV. Make the sheet viewable by anyone with the link or publish it as CSV.', 'tablecraft-elementor' ) );
		}

		$rows = $this->parse_csv( $body );
		if ( empty( $rows ) ) {
			return new WP_Error( 'tc_google_empty', esc_html__( 'The Google Sheet is empty or could not be parsed.', 'tablecraft-elementor' ) );
		}
		set_transient( $cache_key, $rows, $cache_minutes * MINUTE_IN_SECONDS );
		return $rows;
	}

	/** Parse CSV while supporting quoted commas and multiline cells. */
	private function parse_csv( $csv ) {
		$stream = fopen( 'php://temp', 'r+' );
		if ( ! $stream ) {
			return array();
		}
		fwrite( $stream, $csv );
		rewind( $stream );
		$rows = array();
		while ( ( $row = fgetcsv( $stream, 0, ',', '"', '\\' ) ) !== false ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row = array_map(
				static function ( $value ) {
					$value = is_string( $value ) ? preg_replace( '/^\xEF\xBB\xBF/', '', $value ) : $value;
					return is_string( $value ) ? trim( $value ) : '';
				},
				$row
			);
			if ( count( $row ) === 1 && '' === $row[0] ) {
				continue;
			}
			$rows[] = $row;
			if ( count( $rows ) >= 1001 ) {
				break;
			}
		}
		fclose( $stream );
		return $rows;
	}

	/** Normalize ACF and static values to safe display text. */
	private function normalize_cell( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? esc_html__( 'Yes', 'tablecraft-elementor' ) : esc_html__( 'No', 'tablecraft-elementor' );
		}
		if ( is_numeric( $value ) || is_string( $value ) ) {
			return trim( (string) $value );
		}
		if ( $value instanceof WP_Post ) {
			return get_the_title( $value );
		}
		if ( $value instanceof WP_Term ) {
			return $value->name;
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
				return $value['url'];
			}
			if ( isset( $value['title'] ) && is_string( $value['title'] ) ) {
				return $value['title'];
			}
			if ( isset( $value['name'] ) && is_string( $value['name'] ) ) {
				return $value['name'];
			}
			if ( isset( $value['ID'] ) ) {
				return get_the_title( absint( $value['ID'] ) );
			}
			$values = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$values[] = (string) $item;
				} elseif ( $item instanceof WP_Post ) {
					$values[] = get_the_title( $item );
				} elseif ( $item instanceof WP_Term ) {
					$values[] = $item->name;
				}
			}
			return implode( ', ', $values );
		}
		return '';
	}

	/** Validate a CSS width value. */
	private function safe_width( $width ) {
		$width = trim( (string) $width );
		if ( preg_match( '/^(\d{1,3}(?:\.\d+)?)(px|%|rem|em)$/', $width, $match ) ) {
			$value = (float) $match[1];
			return ( $value > 0 && $value <= 1000 ) ? $width : '';
		}
		return '';
	}

	/** Make a short link label from a URL. */
	private function link_label( $url ) {
		if ( 0 === stripos( $url, 'mailto:' ) ) {
			return sanitize_email( substr( $url, 7 ) );
		}
		if ( 0 === stripos( $url, 'tel:' ) ) {
			return sanitize_text_field( substr( $url, 4 ) );
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host ? preg_replace( '/^www\./', '', $host ) : esc_html__( 'Open link', 'tablecraft-elementor' );
	}
}
