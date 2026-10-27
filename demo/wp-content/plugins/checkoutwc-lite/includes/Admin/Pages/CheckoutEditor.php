<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Admin\Pages\Premium\OrderBumps;
use Objectiv\Plugins\Checkout\EditorPreviewCart;
use Objectiv\Plugins\Checkout\Managers\BadgeIconRegistry;
use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\SlotManager;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Model\Template;
use Objectiv\Plugins\Checkout\WooFieldVisibility;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use WC_Tax;
use function WordpressEnqueueChunksPlugin\get as cfwChunkedScriptsConfigGet;

class CheckoutEditor extends PageAbstract {

	public function __construct() {
		parent::__construct( __( 'Checkout Editor', 'checkout-wc' ), 'cfw_manage_pages', 'checkout-editor' );
	}

	public function init() {
		parent::init();

		add_action( 'current_screen', [ $this, 'set_admin_page_title' ] );
		add_filter( 'admin_body_class', [ $this, 'add_body_class' ] );
		add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar' ] );
		add_filter( 'admin_title', [ $this, 'filter_admin_title' ], 10, 2 );
	}

	/**
	 * Sets the admin page title global for the editor screen.
	 *
	 * The editor is registered as a submenu page with no parent, so WordPress cannot resolve its title and leaves the global unset, which makes admin-header.php pass null to strip_tags().
	 *
	 * @since 11.3.1
	 *
	 * @return void
	 */
	public function set_admin_page_title(): void {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$GLOBALS['title'] = $this->title; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function add_body_class( $classes ) {
		if ( ! $this->is_current_page() ) {
			return $classes;
		}

		return $classes . ' cfw-checkout-editor-page';
	}

	public function hide_admin_bar( $show ) {
		if ( $this->is_current_page() ) {
			return false;
		}

		return $show;
	}

	public function filter_admin_title( $admin_title, $title ) {
		if ( ! $this->is_current_page() ) {
			return $admin_title;
		}

		$site_name = get_bloginfo( 'name', 'display' );

		return sprintf(
			/* translators: 1: Admin page title, 2: Network or site name. */
			__( '%1$s ‹ %2$s — WordPress' ),
			$this->title,
			$site_name
		);
	}

	public function setup_menu() {
		add_submenu_page( '', $this->title, $this->title, $this->capability, $this->slug, [ $this, 'output_with_wrap' ], $this->priority );
	}

	public function add_admin_bar_menu_node( \WP_Admin_Bar $admin_bar ) {
		// No admin bar node for the editor.
	}

	public function output_with_wrap() {
		cfw_do_action( 'cfw_admin_output_page', $this->get_slug() );
		?>
		<div id="cfw-checkout-editor"></div>
		<?php
	}

	public function output() {
		// Handled by output_with_wrap.
	}

	public function enqueue_scripts() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		wp_enqueue_media();

		cfw_register_scripts( [ 'admin-checkout-editor' ] );

		wp_localize_script(
			'cfw-admin-checkout-editor',
			'cfwAdminPagesData',
			$this->get_script_data()
		);

		wp_enqueue_script( 'cfw-admin-checkout-editor' );

		$front    = CFW_PATH_ASSETS;
		$manifest = cfwChunkedScriptsConfigGet( 'manifest' );

		if ( isset( $manifest['chunks']['admin-checkout-editor-styles']['file'] ) ) {
			wp_enqueue_style(
				'objectiv-cfw-admin-checkout-editor-styles',
				"{$front}/{$manifest['chunks']['admin-checkout-editor-styles']['file']}",
				[],
				$manifest['chunks']['admin-checkout-editor-styles']['hash']
			);
		}
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		$settings_manager = SettingsManager::instance();
		$saved_slug       = cfw_get_active_template( true )->get_slug();

		// Optional: edit a specific template in the editor without changing the saved option (template only updates on Save).
		$requested_slug = isset( $_GET['cfw_editor_template'] ) ? sanitize_text_field( wp_unslash( $_GET['cfw_editor_template'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all_slugs      = array_keys( Template::get_all_available() );
		$template_slug  = ( $requested_slug && in_array( $requested_slug, $all_slugs, true ) ) ? $requested_slug : $saved_slug;

		// Editor-only settings: only the keys used by the editor sections (Template, Logo, Typography, Colors, Steps, Addresses, Cart Summary, Other).
		$editor_settings = [];

		// When the editor is previewing a template other than the saved active one, always show that
		// template's defined defaults (consistent with the "Changing templates will update colors..." dialog).
		// This also fixes the Lite→Pro upgrade bug where Lite wrote standard fallback defaults for Pro
		// templates that didn't have their defaults.php, and add_option() won't overwrite those values.
		$template           = new Template( $template_slug );
		$template_defaults  = $template->get_default_settings();
		$is_active_template = ( $template_slug === $saved_slug );

		// Logo (template-scoped). An unset logo reads as false, and the editor's save formatter
		// turns a boolean into the string 'no' - which is truthy, so the UI then behaves as though
		// a logo were set. Normalize to an ID string or an empty string so that cannot happen.
		$logo_attachment_id = (int) $settings_manager->get_setting( 'logo_attachment_id', [ $template_slug ] );

		$editor_settings[ $settings_manager->add_suffix( 'logo_attachment_id', [ $template_slug ] ) ] = $logo_attachment_id > 0 ? (string) $logo_attachment_id : '';
		$editor_settings[ $settings_manager->add_suffix( 'logo_size', [ $template_slug ] ) ]          = $settings_manager->get_setting( 'logo_size', [ $template_slug ] ) ?: '';
		$editor_settings[ $settings_manager->add_suffix( 'logo_margin', [ $template_slug ] ) ]        = $settings_manager->get_setting( 'logo_margin', [ $template_slug ] ) ?: '';

		// Typography (template-scoped).
		if ( $is_active_template ) {
			$body_font_saved    = $settings_manager->get_setting( 'body_font', [ $template_slug ] );
			$heading_font_saved = $settings_manager->get_setting( 'heading_font', [ $template_slug ] );
			$editor_settings[ $settings_manager->add_suffix( 'body_font', [ $template_slug ] ) ]    = false !== $body_font_saved ? $body_font_saved : ( $template_defaults['body_font'] ?? '' );
			$editor_settings[ $settings_manager->add_suffix( 'heading_font', [ $template_slug ] ) ] = false !== $heading_font_saved ? $heading_font_saved : ( $template_defaults['heading_font'] ?? '' );
		} else {
			$editor_settings[ $settings_manager->add_suffix( 'body_font', [ $template_slug ] ) ]    = $template_defaults['body_font'] ?? '';
			$editor_settings[ $settings_manager->add_suffix( 'heading_font', [ $template_slug ] ) ] = $template_defaults['heading_font'] ?? '';
		}

		// Field label style (template-scoped, shown in the Typography section). Templates don't define a default, so the saved value always applies.
		$editor_settings[ $settings_manager->add_suffix( 'label_style', [ $template_slug ] ) ] = $settings_manager->get_setting( 'label_style', [ $template_slug ] );

		// Colors (template-scoped). active_theme_colors holds per-template settings (e.g. Glass accent color).
		$color_section_ids  = [ 'body', 'buttons', 'breadcrumbs', 'cart_summary', 'header', 'footer', 'active_theme_colors' ];
		$raw_color_settings = Appearance::get_theme_color_settings( $template_slug );
		foreach ( $color_section_ids as $section_id ) {
			if ( isset( $raw_color_settings[ $section_id ]['settings'] ) ) {
				foreach ( array_keys( $raw_color_settings[ $section_id ]['settings'] ) as $key ) {
					if ( $is_active_template ) {
						$saved = $settings_manager->get_setting( $key, [ $template_slug ] );
						$value = false !== $saved ? $saved : ( $template_defaults[ $key ] ?? '' );
					} else {
						$value = $template_defaults[ $key ] ?? '';
					}
					$editor_settings[ $settings_manager->add_suffix( $key, [ $template_slug ] ) ] = $value;
				}
			}
		}

		// Steps.
		$editor_settings['skip_shipping_step']       = $settings_manager->get_setting( 'skip_shipping_step' ) === 'yes';
		$editor_settings['enable_order_review_step'] = $settings_manager->get_setting( 'enable_order_review_step' ) === 'yes';
		$editor_settings['enable_one_page_checkout'] = $settings_manager->get_setting( 'enable_one_page_checkout' ) === 'yes';

		// Addresses.
		$editor_settings['use_fullname_field']               = $settings_manager->get_setting( 'use_fullname_field' ) === 'yes';
		$editor_settings['enable_discreet_address_1_fields'] = $settings_manager->get_setting( 'enable_discreet_address_1_fields' ) === 'yes';
		$editor_settings['discreet_address_1_fields_order']  = $settings_manager->get_setting( 'discreet_address_1_fields_order' );
		$editor_settings['enable_highlighted_countries']     = $settings_manager->get_setting( 'enable_highlighted_countries' ) === 'yes';
		$editor_settings['highlighted_countries']            = $settings_manager->get_setting( 'highlighted_countries' );
		// WooCommerce owns the visibility of these three and drops them from billing and shipping alike
		// when one is hidden. Surfaced here so the merchant can see and change the setting that decides
		// whether the matching billing toggle means anything.
		$editor_settings['wp_option/woocommerce_checkout_phone_field']     = WooFieldVisibility::get( WooFieldVisibility::PHONE );
		$editor_settings['wp_option/woocommerce_checkout_company_field']   = WooFieldVisibility::get( WooFieldVisibility::COMPANY );
		$editor_settings['wp_option/woocommerce_checkout_address_2_field'] = WooFieldVisibility::get( WooFieldVisibility::ADDRESS_2 );
		$editor_settings['hide_optional_address_fields_behind_link']       = $settings_manager->get_setting( 'hide_optional_address_fields_behind_link' ) === 'yes';
		$editor_settings['force_different_billing_address']                = $settings_manager->get_setting( 'force_different_billing_address' ) === 'yes';
		$editor_settings['enabled_billing_address_fields']                 = $settings_manager->get_setting( 'enabled_billing_address_fields' );

		// Cart Summary.
		$editor_settings['enable_coupon_code_link'] = $settings_manager->get_setting( 'enable_coupon_code_link' ) === 'yes';
		$editor_settings['enable_cart_editing']     = $settings_manager->get_setting( 'enable_cart_editing' ) === 'yes';
		$editor_settings['allow_checkout_cart_item_variation_changes'] = $settings_manager->get_setting( 'allow_checkout_cart_item_variation_changes' ) === 'yes';
		$editor_settings['show_item_remove_button']                    = $settings_manager->get_setting( 'show_item_remove_button' ) === 'yes';
		$editor_settings['cart_edit_empty_cart_redirect']              = $settings_manager->get_setting( 'cart_edit_empty_cart_redirect' );
		$editor_settings['enable_sticky_cart_summary']                 = $settings_manager->get_setting( 'enable_sticky_cart_summary' ) === 'yes';
		$editor_settings['show_cart_item_discount']                    = $settings_manager->get_setting( 'show_cart_item_discount' ) === 'yes';
		$editor_settings['cart_item_link']         = $settings_manager->get_setting( 'cart_item_link' );
		$editor_settings['cart_item_data_display'] = $settings_manager->get_setting( 'cart_item_data_display' );

		// Order Bumps.
		$editor_settings['enable_order_bumps']       = $settings_manager->get_setting( 'enable_order_bumps' ) === 'yes';
		$editor_settings['order_bumps_settings_url'] = add_query_arg( 'page', 'cfw-settings-order_bumps', admin_url( 'admin.php' ) );
		$editor_settings['max_bumps']                = (int) ( $settings_manager->get_setting( 'max_bumps' ) ?? 10 );

		// Badges.
		$editor_settings['enable_trust_badges']         = $settings_manager->get_setting( 'enable_trust_badges' ) === 'yes';
		$editor_settings['trust_badge_position']        = $settings_manager->get_setting( 'trust_badge_position' );
		$editor_settings['trust_badges_title']          = $settings_manager->get_setting( 'trust_badges_title' );
		$editor_settings['trust_badges']                = self::get_editor_trust_badges();
		$editor_settings['trust_badge_columns']         = max( 1, (int) ( $settings_manager->get_setting( 'trust_badge_columns' ) ?: 3 ) );
		$editor_settings['trust_badge_cart_columns']    = max( 1, (int) ( $settings_manager->get_setting( 'trust_badge_cart_columns' ) ?: 2 ) );
		$editor_settings['trust_badge_mobile_columns']  = max( 1, (int) ( $settings_manager->get_setting( 'trust_badge_mobile_columns' ) ?: 1 ) );
		$editor_settings['enable_wc_review_badges']     = $settings_manager->get_setting( 'enable_wc_review_badges' ) === 'yes';
		$editor_settings['wc_review_source']            = $settings_manager->get_setting( 'wc_review_source' );
		$editor_settings['wc_review_min_rating']        = $settings_manager->get_setting( 'wc_review_min_rating' );
		$editor_settings['wc_review_limit']             = (int) $settings_manager->get_setting( 'wc_review_limit' );
		$editor_settings['review_badge_columns']        = max( 1, (int) ( $settings_manager->get_setting( 'review_badge_columns' ) ?: 3 ) );
		$editor_settings['review_badge_cart_columns']   = max( 1, (int) ( $settings_manager->get_setting( 'review_badge_cart_columns' ) ?: 2 ) );
		$editor_settings['review_badge_mobile_columns'] = max( 1, (int) ( $settings_manager->get_setting( 'review_badge_mobile_columns' ) ?: 1 ) );

		// Other (footer text is template-scoped).
		$editor_settings['enable_order_notes'] = $settings_manager->get_setting( 'enable_order_notes' ) === 'yes';
		$editor_settings[ $settings_manager->add_suffix( 'footer_text', [ $template_slug ] ) ] = $settings_manager->get_setting( 'footer_text', [ $template_slug ] );
		$editor_settings['footer_text_editor_mode'] = $settings_manager->get_setting( 'footer_text_editor_mode' );

		// Go Live (activation toggle, same setting as CheckoutWC > Start Here).
		$editor_settings['enable'] = $settings_manager->get_setting( 'enable' ) === 'yes';

		// Express Checkout (same as CheckoutWC > Express Checkout page).
		$editor_settings['disable_express_checkout']              = $settings_manager->get_setting( 'disable_express_checkout' ) === 'yes';
		$editor_settings['allow_express_without_required_custom_fields'] = $settings_manager->get_setting( 'allow_express_without_required_custom_fields' ) === 'yes';

		// Color defaults for reset/preview.
		$all_color_defaults      = Appearance::get_theme_color_settings_defaults( $template_slug );
		$color_settings_defaults = [];
		foreach ( $color_section_ids as $section_id ) {
			if ( isset( $raw_color_settings[ $section_id ]['settings'] ) ) {
				foreach ( array_keys( $raw_color_settings[ $section_id ]['settings'] ) as $key ) {
					if ( isset( $all_color_defaults[ $key ] ) ) {
						$color_settings_defaults[ $key ] = $all_color_defaults[ $key ];
					}
				}
			}
		}

		// Always seed the preview transient on page load so the preview iframe never starts
		// from a stale transient left behind by a previous editing session. Slot assignments
		// are written in every case; non-active templates additionally inject their
		// template-specific color/font defaults (before FormObserver fires).
		SlotManager::instance()->maybe_migrate();
		$slot_manager_early = SlotManager::instance();

		$preview_transient = [
			SlotManager::SLOTS_OPTION         => $slot_manager_early->get_slots(),
			SlotManager::HTML_BLOCKS_OPTION   => $slot_manager_early->get_custom_html_blocks(),
			CustomFieldManager::FIELDS_OPTION => CustomFieldManager::instance()->get_definitions(),
		];

		if ( ! $is_active_template ) {
			foreach ( $color_section_ids as $section_id ) {
				if ( isset( $raw_color_settings[ $section_id ]['settings'] ) ) {
					foreach ( array_keys( $raw_color_settings[ $section_id ]['settings'] ) as $key ) {
						$preview_transient[ $settings_manager->add_suffix( $key, [ $template_slug ] ) ] = $template_defaults[ $key ] ?? '';
					}
				}
			}
			$preview_transient[ $settings_manager->add_suffix( 'body_font', [ $template_slug ] ) ]    = $template_defaults['body_font'] ?? '';
			$preview_transient[ $settings_manager->add_suffix( 'heading_font', [ $template_slug ] ) ] = $template_defaults['heading_font'] ?? '';
		}

		set_transient( '_cfw_editor_preview_' . get_current_user_id(), $preview_transient, 30 * MINUTE_IN_SECONDS );

		$appearance_instance = new Appearance();

		// Determine where the Close button should send the user.
		$default_close_url = add_query_arg(
			[
				'page'    => 'cfw-settings-checkout',
				'subpage' => 'checkout',
			],
			admin_url( 'admin.php' )
		);

		$editor_base_url = add_query_arg(
			[
				'page' => $this->get_slug(),
			],
			admin_url( 'admin.php' )
		);

		// Preserve the original admin page the user came from across editor reloads.
		$return_param_key = 'cfw_editor_return';
		$encoded_return   = isset( $_GET[ $return_param_key ] ) ? wp_unslash( $_GET[ $return_param_key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$original_return  = $encoded_return ? esc_url_raw( rawurldecode( $encoded_return ) ) : '';

		if ( $original_return && false !== strpos( $original_return, admin_url() ) && false === strpos( $original_return, $editor_base_url ) ) {
			$close_url = $original_return;
		} else {
			$referer = wp_get_referer();

			if ( $referer && false !== strpos( $referer, admin_url() ) && false === strpos( $referer, $editor_base_url ) ) {
				// First load from another admin page: use that as the return URL.
				$close_url = $referer;
			} else {
				// Direct access or referer is the editor itself: fall back to Checkout settings.
				$close_url = $default_close_url;
			}
		}

		// The preview cart itself is built on the preview request — see EditorPreviewCart. All the editor needs to know
		// is whether the store has anything to show, and what the merchant has chosen.
		$preview_url       = wc_get_checkout_url();
		$preview_selection = EditorPreviewCart::get_resolved_mirror();
		$has_products      = ! empty( $preview_selection ) || null !== EditorPreviewCart::get_auto_selected_product();

		$preview_nonce = wp_create_nonce( 'cfw-editor-preview' );
		$preview_url   = add_query_arg(
			[
				'cfw-editor-preview'  => '1',
				'_cfw_preview_nonce'  => $preview_nonce,
				'cfw-preview'         => $template_slug,
			],
			$preview_url
		);

		$countries = WC()->countries->countries;
		asort( $countries );

		// Templates data for the editor (for switching templates from the Design tab).
		$templates        = Template::get_all_available();
		$editor_templates = [];
		$has_premium_plan = PlanManager::has_admin_plan_or_higher();

		foreach ( $templates as $template ) {
			$slug = $template->get_slug();

			$editor_templates[] = [
				'slug'   => $slug,
				'name'   => $template->get_name(),
				'active' => $slug === $template_slug,
				'locked' => ! $has_premium_plan && $slug !== $template_slug,
			];
		}

		// Ensure template switches keep sending the user back to the same place.
		$editor_url = add_query_arg(
			[
				'page'             => $this->get_slug(),
				$return_param_key  => rawurlencode( $close_url ),
			],
			admin_url( 'admin.php' )
		);

		// Run slot migration before building editor data so the editor reflects migrated assignments.
		SlotManager::instance()->maybe_migrate();

		$slot_manager = SlotManager::instance();

		$this->set_script_data(
			[
				'editor_settings' => [
					'settings' => $editor_settings,
					'params'   => [
						'font_options'              => $appearance_instance->get_font_settings(),
						'template_path'             => $template_slug,
						'color_settings'            => Appearance::get_theme_color_settings( $template_slug ),
						'color_settings_defaults'   => $color_settings_defaults,
						'logo_preview_url'          => wp_get_attachment_url( $settings_manager->get_setting( 'logo_attachment_id', [ $template_slug ] ) ),
						'countries'                => $countries,
						'conditional_settings'      => [
							'order_notes_enable'            => ! has_filter( 'woocommerce_enable_order_notes_field' ) || ( $settings_manager->get_setting( 'enable_order_notes' ) === 'yes' && 1 === cfw_count_filters( 'woocommerce_enable_order_notes_field' ) ),
							// Both providers write the selected address into address_1, which separate address fields hide and overwrite.
							'address_autocomplete_enabled'  => PlanManager::can_access_feature( 'enable_address_autocomplete' ),
							'fetchify_autocomplete_enabled' => PlanManager::can_access_feature( 'enable_fetchify_address_autocomplete' ),
						],
						'tax_classes'               => self::get_tax_class_options(),
						// The editor hides every tax control when tax is off in WooCommerce, and needs
						// to know how prices are entered to describe what a taxable fee will charge.
						'tax_enabled'               => wc_tax_enabled(),
						'prices_include_tax'        => wc_prices_include_tax(),
						'currency_symbol'           => html_entity_decode( get_woocommerce_currency_symbol() ),
						'price_decimals'            => wc_get_price_decimals(),
						'express_checkout_gateways' => apply_filters( 'cfw_detected_gateways', [] ),
						'requires_license'          => defined( 'CFW_PREMIUM_PLAN_IDS' ),
					],
				],
				'slots_data'           => [
					'slot_definitions'   => SlotManager::get_slot_hook_map(),
					'assignments'        => $slot_manager->get_slots(),
					'custom_html_blocks' => $slot_manager->get_custom_html_blocks(),
					'custom_fields'      => CustomFieldManager::instance()->get_definitions(),
					'available_items'  => $slot_manager->get_available_items(),
				],
				'preview_url'          => $preview_url,
				'has_products'         => $has_products,
				'preview_cart'         => [
					'selection' => $preview_selection,
					'max_items' => EditorPreviewCart::MAX_ITEMS,
				],
				'close_url'            => $close_url,
				'editor_url'           => $editor_url,
				'saved_active_template' => $saved_slug,
				'admin_url'            => admin_url( 'admin.php' ),
				'new_order_bump_url'   => admin_url( 'post-new.php?post_type=cfw_order_bumps' ),
				'order_edit_url'       => self::get_order_edit_url_template(),
				'bump_editor'          => self::get_bump_editor_data(),
				'editor_logo_url' => CFW_PATH_URL_BASE . 'assets/images/cfw.svg',
				'badge_icons'     => BadgeIconRegistry::get_picker_icons(),
				'plan'            => $this->get_plan_data(),
				'templates'       => $editor_templates,
			]
		);
	}

	/**
	 * Data the editor needs to open the order bump editor in a modal.
	 *
	 * `available` is false in the lite build and on plans without order bumps, in which
	 * case the editor falls back to its existing plan-locked treatment.
	 *
	 * Also served by SlotsAPI so the editor can refresh `can_create` after a bump is
	 * created or trashed in the modal, without a page reload.
	 *
	 * @return array{available: bool, edit_url_template: string, new_url: string, can_create: bool, allowed_count: int, used_count: int}
	 */
	public static function get_bump_editor_data(): array {
		$available = class_exists( OrderBumps::class ) && PlanManager::has_admin_plan_or_higher( 'plus' );

		return [
			'available'         => $available,
			'edit_url_template' => admin_url( 'post.php?post=%d&action=edit&cfw_modal=1' ),
			'new_url'           => admin_url( 'post-new.php?post_type=cfw_order_bumps&cfw_modal=1' ),
			'can_create'        => $available && OrderBumps::can_create_bump(),
			'allowed_count'     => $available ? OrderBumps::get_allowed_bump_count() : 0,
			'used_count'        => $available ? OrderBumps::get_bumps_count() : 0,
		];
	}

	/**
	 * Returns the admin URL for editing an order, with `%d` standing in for the order ID.
	 *
	 * The editor links to an order placed from inside its preview, and only learns which order that was
	 * from the URL the preview landed on, so it builds the link itself. Which screen edits an order
	 * depends on whether the store keeps orders in their own tables or as posts.
	 *
	 * @since 11.4.0
	 *
	 * @return string
	 */
	private static function get_order_edit_url_template(): string {
		$hpos = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();

		return $hpos
			? admin_url( 'admin.php?page=wc-orders&action=edit&id=%d' )
			: admin_url( 'post.php?post=%d&action=edit' );
	}

	/**
	 * Returns the trust badges for the editor's form state.
	 *
	 * The editor edits badges through the same settings save as every other setting, so this array
	 * is posted straight back to `_cfw_trust_badges`. The settings page's transient `slot` key is
	 * dropped on the way in: the editor places badges through the slot registry, and leaving the key
	 * on would have SlotManager::reconcile_trust_badge_slots() rewrite those same assignments.
	 *
	 * @since 11.4.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_editor_trust_badges(): array {
		$badges = [];

		foreach ( cfw_get_trust_badges( false ) as $badge ) {
			unset( $badge['slot'] );

			$badges[] = $badge;
		}

		return $badges;
	}

	/**
	 * Returns the WooCommerce tax classes as slug/name pairs for the custom field editor.
	 *
	 * Slugs are what WooCommerce validates a fee's tax class against, and an unrecognised slug
	 * silently bills at the standard rate — so the picker only ever offers slugs that exist.
	 *
	 * @since 11.4.0
	 *
	 * @return array<int, array{slug: string, name: string}>
	 */
	public static function get_tax_class_options(): array {
		$options = [
			[
				'slug' => '',
				'name' => __( 'Standard rate', 'checkout-wc' ),
			],
		];

		foreach ( WC_Tax::get_tax_classes() as $name ) {
			$slug = sanitize_title( $name );

			if ( '' === $slug ) {
				continue;
			}

			$options[] = [
				'slug' => $slug,
				'name' => $name,
			];
		}

		return $options;
	}
}
