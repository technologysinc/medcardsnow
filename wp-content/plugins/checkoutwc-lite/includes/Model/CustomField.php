<?php

namespace Objectiv\Plugins\Checkout\Model;

use WC_Product;
use WC_Tax;

/**
 * A single merchant-authored checkout field.
 *
 * Value object over one entry of the `_cfw_checkout_custom_fields` option. Custom fields are
 * deliberately self-owned rather than registered through WooCommerce's Additional Checkout Fields
 * API: that API renders in the Checkout block only, and its registry lives in the Blocks namespace
 * with no stability promise. We own the definition, the markup, validation and persistence.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Model
 */
class CustomField {

	/**
	 * Prefix for the field's form input name and its order meta key.
	 *
	 * @var string
	 */
	const KEY_PREFIX = 'cfw_cf_';

	/**
	 * Field types a merchant may create.
	 *
	 * @var string[]
	 */
	const SUPPORTED_TYPES = [ 'text', 'textarea', 'select', 'radio', 'checkbox' ];

	/**
	 * Field types every plan may use, including the lite build.
	 *
	 * Every other supported type requires the plan named by PLAN_LOCKED_TYPES_MIN_PLAN.
	 * CustomFieldManager::type_is_available() decides whether a type renders on the storefront, and
	 * CustomFieldManager::type_is_editable() whether the editor can save it; nothing else should
	 * compare against this constant directly.
	 *
	 * @var string[]
	 */
	const FREE_TYPES = [ 'text' ];

	/**
	 * The plan required for any type outside FREE_TYPES.
	 *
	 * @var string
	 */
	const PLAN_LOCKED_TYPES_MIN_PLAN = 'plus';

	/**
	 * Types that may carry a character limit.
	 *
	 * Free-text types only. A select, radio or checkbox value is authored by the merchant, so its
	 * length is already settled when the option is written.
	 *
	 * @var string[]
	 */
	const LENGTH_LIMITED_TYPES = [ 'text', 'textarea' ];

	/**
	 * Default row count for a textarea field.
	 *
	 * @var int
	 */
	const TEXTAREA_ROWS = 4;

	/**
	 * Column count for a textarea field, matching WooCommerce's own default.
	 *
	 * @var int
	 */
	const TEXTAREA_COLS = 5;

	/**
	 * The raw definition.
	 *
	 * @var array<string, mixed>
	 */
	protected $definition;

	/**
	 * @param array<string, mixed> $definition
	 */
	public function __construct( array $definition ) {
		$this->definition = $definition;
	}

	/**
	 * Returns the immutable field identifier.
	 *
	 * @since 11.4.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return (string) ( $this->definition['id'] ?? '' );
	}

	/**
	 * Returns the field type, falling back to text for an unrecognised value.
	 *
	 * @since 11.4.0
	 *
	 * @return string
	 */
	public function get_type(): string {
		$type = (string) ( $this->definition['type'] ?? 'text' );

		return in_array( $type, self::SUPPORTED_TYPES, true ) ? $type : 'text';
	}

	/**
	 * Returns the form input name.
	 *
	 * @since 11.4.0
	 *
	 * @return string
	 */
	public function get_input_name(): string {
		return self::KEY_PREFIX . $this->get_id();
	}

	/**
	 * Returns the order meta key the submitted value is stored under.
	 *
	 * @since 11.4.0
	 *
	 * @return string
	 */
	public function get_meta_key(): string {
		return '_' . $this->get_input_name();
	}

	/**
	 * Returns the merchant-facing label.
	 *
	 * @since 11.4.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return (string) ( $this->definition['label'] ?? '' );
	}

	/**
	 * Returns whether the customer must supply a value.
	 *
	 * @since 11.4.0
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return ! empty( $this->definition['required'] );
	}

	/**
	 * Returns the maximum number of characters the customer may enter, or 0 for no limit.
	 *
	 * @since 11.4.0
	 *
	 * @return int
	 */
	public function get_max_length(): int {
		if ( ! in_array( $this->get_type(), self::LENGTH_LIMITED_TYPES, true ) ) {
			return 0;
		}

		return max( 0, (int) ( $this->definition['max_length'] ?? 0 ) );
	}

	/**
	 * Sanitises a value submitted for this field.
	 *
	 * Type-aware because wc_clean() runs sanitize_text_field(), which collapses every run of
	 * whitespace — newlines included — into a single space. That is right for a one-line input and
	 * wrong for a textarea, where the line breaks in a gift message or a set of delivery
	 * instructions are part of what the customer wrote and are what reaches the order, the emails
	 * and whatever the merchant prints. wc_sanitize_textarea() cleans each line and keeps the breaks.
	 *
	 * @since 11.4.0
	 *
	 * @param string $value The raw, unslashed submitted value.
	 *
	 * @return string
	 */
	public function sanitize_value( string $value ): string {
		return 'textarea' === $this->get_type()
			? (string) wc_sanitize_textarea( $value )
			: (string) wc_clean( $value );
	}

	/**
	 * Returns the display rules in RulesProcessor format.
	 *
	 * @since 11.4.0
	 *
	 * @return array<mixed>
	 */
	public function get_rules(): array {
		$rules = $this->definition['rules'] ?? [];

		return is_array( $rules ) ? $rules : [];
	}

	/**
	 * Returns the configured options, for select and radio fields.
	 *
	 * @since 11.4.0
	 *
	 * @return array<int, array{value: string, label: string, fee_amount: float}>
	 */
	public function get_options(): array {
		$options = $this->definition['options'] ?? [];

		if ( ! is_array( $options ) ) {
			return [];
		}

		$clean = [];

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || '' === (string) ( $option['value'] ?? '' ) ) {
				continue;
			}

			$clean[] = [
				'value'      => (string) $option['value'],
				'label'      => (string) ( $option['label'] ?? $option['value'] ),
				'fee_amount' => (float) ( $option['fee_amount'] ?? 0 ),
			];
		}

		return $clean;
	}

	/**
	 * Returns whether this field carries a price adjustment.
	 *
	 * Every type may carry one. A select or radio prices per option; every other type carries a
	 * single field-level amount, charged once the customer has answered — a ticked checkbox, or any
	 * text at all in a text or textarea field.
	 *
	 * @since 11.4.0
	 *
	 * @return bool
	 */
	public function has_fee(): bool {
		return ! empty( $this->definition['fee']['enabled'] );
	}

	/**
	 * Returns the raw price adjustment configuration.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_fee_config(): array {
		$fee = $this->definition['fee'] ?? [];

		return is_array( $fee ) ? $fee : [];
	}

	/**
	 * Builds the argument array for woocommerce_form_field().
	 *
	 * The `hidden` class must land on WooCommerce's own `.form-row` wrapper rather than an outer
	 * element: the frontend decides whether to skip validation for an invisible field by checking
	 * `parents( '.form-row' ).hasClass( 'hidden' )`, and that same flag suppresses the "required
	 * field" alert. Disabling the input as well keeps its value out of both the serialized AJAX
	 * payload and the native form POST.
	 *
	 * @since 11.4.0
	 *
	 * @param bool   $eligible      Whether the field currently applies to this cart and customer.
	 * @param string $parsley_group Validation group to assign, or an empty string for none.
	 *
	 * @return array<string, mixed>
	 */
	public function get_form_field_args( bool $eligible = true, string $parsley_group = '' ): array {
		$type = $this->get_type();

		$classes = [ 'cfw-custom-field', 'cfw-custom-field--' . $this->get_id() ];

		if ( ! $eligible ) {
			$classes[] = 'hidden';
		}

		if ( $this->has_fee() ) {
			// Picked up by the frontend change listener so the totals refresh when the fee applies.
			$classes[] = 'update_totals_on_change';
		}

		$custom_attributes = [];

		if ( ! $eligible ) {
			$custom_attributes['disabled'] = 'disabled';
		}

		if ( '' !== $parsley_group ) {
			$custom_attributes['data-parsley-group'] = $parsley_group;
		}

		// WooCommerce hardcodes rows="2" unless a rows attribute is supplied, which is too short for
		// the gift messages and delivery instructions a textarea is normally used for here.
		//
		// Pass cols as well even though the value matches WooCommerce's own default: it emits
		// `cols="5"` with no trailing space directly before imploding custom attributes, so
		// supplying only rows produces `cols="5"rows="4"`. Supplying both suppresses its output and
		// keeps the attributes properly separated.
		if ( 'textarea' === $type ) {
			$custom_attributes['rows'] = self::TEXTAREA_ROWS;
			$custom_attributes['cols'] = self::TEXTAREA_COLS;
		}

		$args = [
			'type'              => $type,
			'label'             => $this->get_label(),
			'required'          => $this->is_required(),
			'class'             => $classes,
			'custom_attributes' => $custom_attributes,
			// Custom fields are always full width. Per-field width was removed as a setting: it added
			// a control for something merchants did not need, and a full-width field is correct in
			// every slot.
			'columns'           => 12,
		];

		$placeholder = (string) ( $this->definition['placeholder'] ?? '' );

		if ( '' !== $placeholder ) {
			$args['placeholder'] = $placeholder;
		}

		$max_length = $this->get_max_length();

		if ( $max_length > 0 ) {
			// woocommerce_form_field() turns this into a maxlength attribute, on the text branch and
			// the textarea branch alike. It is a courtesy to the customer rather than the limit
			// itself: the same cap is applied again during checkout validation.
			$args['maxlength'] = $max_length;
		}

		if ( in_array( $type, [ 'select', 'radio' ], true ) ) {
			$options = [];

			foreach ( $this->get_options() as $option ) {
				$options[ $option['value'] ] = $this->append_fee_to_label( $option['label'], (float) $option['fee_amount'] );
			}

			$args['options'] = $options;
		}

		// Every type that is not priced per option carries one field-level amount, so the label the
		// customer reads is where that amount belongs — the checkbox they tick, or the text field
		// they type in.
		if ( ! in_array( $type, [ 'select', 'radio' ], true ) ) {
			$args['label'] = $this->append_fee_to_label( $args['label'], (float) ( $this->get_fee_config()['amount'] ?? 0 ) );
		}

		return $args;
	}

	/**
	 * Returns whether the fee should be shown alongside the label the customer reads.
	 *
	 * Defaults to on: a customer needs to see what a choice costs before they make it — the priced
	 * option they pick, the box they tick, or the text field that charges them for filling it in.
	 * A merchant who words the amount into the label themselves can switch it off.
	 *
	 * @since 11.4.0
	 *
	 * @return bool
	 */
	public function shows_fee_in_label(): bool {
		$fee = $this->get_fee_config();

		return ! array_key_exists( 'show_in_label', $fee ) || ! empty( $fee['show_in_label'] );
	}

	/**
	 * Appends a fee to a customer-facing label.
	 *
	 * @since 11.4.0
	 *
	 * @param string $label
	 * @param float  $amount The configured amount, or the percentage when the fee is a percentage.
	 *
	 * @return string
	 */
	protected function append_fee_to_label( string $label, float $amount ): string {
		if ( ! $this->has_fee() || ! $this->shows_fee_in_label() || $amount <= 0 ) {
			return $label;
		}

		$fee = $this->get_fee_config();

		// A percentage is not money, so it is shown as typed and never tax-adjusted.
		if ( 'percent' === ( $fee['mode'] ?? 'flat' ) ) {
			return sprintf( '%1$s (+%2$s%%)', $label, (string) round( $amount, 2 ) );
		}

		// wc_price() returns markup, and these labels are escaped by WooCommerce when rendered, so
		// reduce it to the plain text a shopper should read.
		$price = html_entity_decode( wp_strip_all_tags( wc_price( $this->to_display_amount( $this->to_effective_amount( $amount ) ) ) ) );

		return sprintf( '%1$s (+%2$s)', $label, $price );
	}

	/**
	 * Resolves a configured fee amount into the amount that actually applies.
	 *
	 * Two steps. A flat amount is converted into the active currency through
	 * `woocommerce_product_get_price` on a throwaway product carrying the amount as its price — the
	 * route an order bump already uses for a flat discount and the side cart for a free shipping
	 * threshold, so every multi-currency plugin converts a fee with no integration written for it.
	 * The product is a dummy rather than null because those plugins call `get_id()` on it unguarded.
	 * A percentage is left alone, having resolved from cart totals the currency plugin has already
	 * converted. The result is then filtered, which is the seam for everything else.
	 *
	 * The charge and the label the customer reads before choosing both call this and diverge only
	 * afterwards, so neither step can reach one without the other.
	 *
	 * @since 11.4.0
	 *
	 * @param float $amount
	 *
	 * @return float
	 */
	public function to_effective_amount( float $amount ): float {
		if ( 'flat' === ( $this->get_fee_config()['mode'] ?? 'flat' ) ) {
			$dummy = new WC_Product();

			$dummy->set_price( $amount );
			$dummy->set_regular_price( $amount );

			$amount = (float) cfw_apply_filters( 'woocommerce_product_get_price', $amount, $dummy );
		}

		/**
		 * Filters a custom field's fee amount.
		 *
		 * The fee API exposes no amount filter of its own. Both the charge and the label the customer
		 * reads before choosing pass through here, so an integration cannot adjust one and leave the
		 * other showing a different number.
		 *
		 * @since 11.4.0
		 *
		 * @param float       $amount
		 * @param CustomField $field
		 */
		return (float) apply_filters( 'cfw_custom_field_fee_amount', $amount, $this );
	}

	/**
	 * Converts a configured fee amount into the figure a customer should be shown.
	 *
	 * Mirrors how WooCommerce displays product prices: the entered amount is gross or net depending
	 * on how the store enters prices, and the cart's tax display setting decides which of those the
	 * shopper sees. Only converts when the two disagree.
	 *
	 * @since 11.4.0
	 *
	 * @param float $amount
	 *
	 * @return float
	 */
	protected function to_display_amount( float $amount ): float {
		$fee = $this->get_fee_config();

		if ( ! wc_tax_enabled() || empty( $fee['taxable'] ) ) {
			return $amount;
		}

		$display_includes_tax = 'incl' === get_option( 'woocommerce_tax_display_cart' );
		$entered_includes_tax = wc_prices_include_tax();

		if ( $display_includes_tax === $entered_includes_tax ) {
			return $amount;
		}

		$customer = function_exists( 'WC' ) && WC()->customer ? WC()->customer : null;
		$rates    = WC_Tax::get_rates( (string) ( $fee['tax_class'] ?? '' ), $customer );

		if ( $display_includes_tax ) {
			return $amount + array_sum( WC_Tax::calc_tax( $amount, $rates, false ) );
		}

		return $amount - array_sum( WC_Tax::calc_tax( $amount, $rates, true ) );
	}

	/**
	 * Returns the raw definition, for persistence.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_definition(): array {
		return $this->definition;
	}
}
