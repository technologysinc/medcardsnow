<?php

namespace Objectiv\Plugins\Checkout\Features;

use Objectiv\Plugins\Checkout\Features\CustomFields\FeeApplicator;
use Objectiv\Plugins\Checkout\Features\CustomFields\OrderData;
use Objectiv\Plugins\Checkout\Features\CustomFields\ValueStore;
use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Model\CustomField;
use WP_Error;

/**
 * Wires merchant-authored checkout fields into the checkout request lifecycle.
 *
 * Rendering itself lives in SlotRenderer. This class owns everything around it: resolving a
 * submitted value back into the markup, validating required fields, and persisting values to the
 * order.
 *
 * Deliberately not plan-gated. Creating a field is a paid feature, but rendering one that already
 * exists is not — gating the render path would mean a lapsed licence silently drops fields a
 * merchant's customers are already filling in.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Features
 */
class CustomFields {

	/**
	 * Parsed `post_data` for this request, or null before it has been read.
	 *
	 * @var array<string, mixed>|null
	 */
	private $parsed_post_data = null;

	/**
	 * Sanitised custom field values for this request, or null before they have been resolved.
	 *
	 * @var array<string, mixed>|null
	 */
	private $submitted_values = null;

	/**
	 * Registers hooks.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function init(): void {
		ValueStore::instance()->init();
		( new FeeApplicator() )->init();
		( new OrderData() )->init();

		add_filter( 'woocommerce_checkout_get_value', [ $this, 'get_checkout_value' ], 10, 2 );
		add_filter( 'woocommerce_form_field_checkbox', [ $this, 'move_checkbox_validation_to_input' ], 210000, 3 );
		add_filter( 'woocommerce_form_field', [ $this, 'fix_radio_group_semantics' ], 210000, 3 );
		add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_fields' ], 10, 2 );
		add_action( 'cfw_checkout_payment_method_tab', [ $this, 'output_mobile_container' ], 38 );
		add_filter( 'cfw_checkout_data', [ $this, 'add_visibility_to_checkout_data' ] );
	}

	/**
	 * Publishes each field's current visibility to the frontend.
	 *
	 * This payload is re-sent with every checkout update, so a field whose display conditions start
	 * or stop matching is revealed or hidden as the cart changes.
	 *
	 * @since 11.4.0
	 *
	 * @param array $data The checkout data payload.
	 *
	 * @return array
	 */
	public function add_visibility_to_checkout_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$manager = CustomFieldManager::instance();

		$data['custom_fields'] = [
			'visibility'       => $manager->get_visibility_map(),
			'revision'         => $manager->get_revision(),
			// Re-sent on every refresh so a required field that starts or stops applying takes the
			// express buttons with it. The initial render is decided server side in
			// cfw_payment_request_buttons().
			'suppress_express' => $manager->express_checkout_is_suppressed(),
		];

		return $data;
	}

	/**
	 * Moves a required checkbox's validation attributes from its label onto the input.
	 *
	 * WooCommerce renders a checkbox as `<label class="checkbox" {custom_attributes}>`, so
	 * `data-parsley-required` lands on the label and only `aria-required` reaches the input. The
	 * frontend validator only binds to inputs, selects and textareas, which means a required
	 * checkbox is never validated at all. Copy the parsley attributes onto the checkbox input so it
	 * behaves like every other required field.
	 *
	 * A class handler is added alongside them. Parsley treats a checkbox as a multi-element field and
	 * so hangs `aria-describedby` on the element's parent, which here is the label wrapping the input
	 * — where it describes nothing. Pointing the handler at the input itself puts the error message
	 * where a screen reader will read it out with the field, as it already does for text inputs.
	 *
	 * Scoped to our own fields on purpose. The same gap affects checkboxes registered by other
	 * plugins, but making those suddenly block checkout is a behaviour change that belongs in its own
	 * change, not here.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field The rendered field HTML.
	 * @param string $key   The field key.
	 * @param array  $args  The field arguments.
	 *
	 * @return string
	 */
	public function move_checkbox_validation_to_input( $field, $key = null, $args = [] ): string {
		if ( ! is_string( $field ) || ! is_string( $key ) || 0 !== strpos( $key, CustomField::KEY_PREFIX ) ) {
			return (string) $field;
		}

		// Idempotent: CheckoutWC re-registers field hooks on cfw_checkout_update_order_review, so
		// this filter can run more than once for the same markup.
		if ( false !== strpos( $field, 'type="checkbox" data-parsley' ) ) {
			return $field;
		}

		$attributes = is_array( $args['custom_attributes'] ?? null ) ? $args['custom_attributes'] : [];
		$inject     = '';

		foreach ( $attributes as $name => $value ) {
			if ( 0 === strpos( (string) $name, 'data-parsley-' ) ) {
				$inject .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
			}
		}

		if ( '' === $inject ) {
			return $field;
		}

		// Field IDs are sanitize_key()'d on save, so this is always a safe selector.
		$inject .= ' data-parsley-class-handler="#' . esc_attr( (string) ( $args['id'] ?? $key ) ) . '"';

		// Target the checkbox specifically — a checkbox field may be preceded by a hidden input
		// carrying its unchecked value.
		return str_replace( '<input type="checkbox"', '<input type="checkbox"' . $inject, $field );
	}

	/**
	 * Makes a radio field's group label behave like a group label.
	 *
	 * WooCommerce points the wrapping label's `for` at the first option — `$label_id .= '_' .
	 * current( array_keys( $args['options'] ) )` — which has two consequences. Clicking the field's
	 * own label selects that first option, so a customer who taps "Gift wrap" silently changes their
	 * choice and any fee attached to it; and assistive technology reads the group's name as the first
	 * option's label, leaving the options ungrouped and the group unnamed.
	 *
	 * Dropping the `for` and naming the group through `aria-labelledby` fixes both. Scoped to custom
	 * fields: every radio rendered by woocommerce_form_field() has this, but re-pointing labels
	 * belonging to other extensions is not this feature's business.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field The complete field markup.
	 * @param string $key   The field key.
	 * @param array  $args  The field arguments.
	 *
	 * @return string
	 */
	public function fix_radio_group_semantics( $field, $key = null, $args = [] ): string {
		if ( ! is_string( $field ) || ! is_string( $key ) || 0 !== strpos( $key, CustomField::KEY_PREFIX ) ) {
			return (string) $field;
		}

		if ( 'radio' !== ( $args['type'] ?? '' ) || '' === (string) ( $args['label'] ?? '' ) ) {
			return $field;
		}

		// Idempotent: CheckoutWC re-registers field hooks on cfw_checkout_update_order_review, so this
		// filter can run more than once over the same markup.
		if ( false !== strpos( $field, 'role="radiogroup"' ) ) {
			return $field;
		}

		$label_id = sanitize_html_class( $key ) . '_label';

		// The group label is the first one in the markup: WooCommerce emits it ahead of the input
		// wrapper, and the guard above means it is always present.
		$field = preg_replace(
			'/<label for="[^"]*"/',
			'<label id="' . esc_attr( $label_id ) . '"',
			$field,
			1
		);

		return str_replace(
			'<span class="woocommerce-input-wrapper">',
			'<span class="woocommerce-input-wrapper" role="radiogroup" aria-labelledby="' . esc_attr( $label_id ) . '">',
			(string) $field
		);
	}

	/**
	 * Rejects a submission that breaks a custom field's own constraints.
	 *
	 * Only fields returned by get_active_fields() are enforced. A field whose slot never rendered
	 * for this cart — the shipping slots on a digital order, for instance — must not be enforced, or
	 * the customer is blocked by a field that is not on their screen and cannot be filled in.
	 *
	 * @since 11.4.0
	 *
	 * @param array    $data   The posted checkout data.
	 * @param WP_Error $errors The error object to add to.
	 *
	 * @return void
	 */
	public function validate_fields( $data, $errors ): void {
		if ( ! $errors instanceof WP_Error ) {
			return;
		}

		$submitted = $this->get_submitted_values();

		// Eligible, not merely active: a field whose display conditions do not match this cart was
		// rendered hidden and disabled, so its value never reaches us and it must not be enforced.
		foreach ( CustomFieldManager::instance()->get_eligible_fields() as $field ) {
			$value = trim( (string) ( $submitted[ $field->get_input_name() ] ?? '' ) );

			if ( '' === $value ) {
				if ( $field->is_required() ) {
					$errors->add(
						'required-field',
						sprintf(
							/* translators: %s: field label */
							esc_html__( '%s is a required field.', 'checkout-wc' ),
							'<strong>' . esc_html( $field->get_label() ) . '</strong>'
						)
					);
				}

				continue;
			}

			$max_length = $field->get_max_length();

			// The maxlength attribute already stops this in the browser, so an over-long value means
			// the input was edited or the request was crafted. Rejected rather than truncated: the
			// value goes on to the order, the customer's emails and whatever the merchant prints
			// from it, and a silently shortened gift message or engraving is worse than an error.
			if ( $max_length > 0 && mb_strlen( $value ) > $max_length ) {
				$errors->add(
					'custom-field-too-long',
					sprintf(
						/* translators: 1: field label, 2: maximum number of characters */
						esc_html__( '%1$s must be %2$s characters or fewer.', 'checkout-wc' ),
						'<strong>' . esc_html( $field->get_label() ) . '</strong>',
						number_format_i18n( $max_length )
					)
				);
			}
		}
	}

	/**
	 * Outputs the mount point custom fields move into on small screens.
	 *
	 * Sits immediately above the terms checkbox, matching where order bumps assigned to
	 * cart-summary slots appear on mobile.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function output_mobile_container(): void {
		echo '<div id="cfw_custom_fields_mobile_output"></div>';
	}

	/**
	 * Resolves a custom field's submitted value for re-render.
	 *
	 * WooCommerce resolves a field value from `$_POST` and then from the customer object, and knows
	 * nothing about our keys in either. On the update_order_review request the value is not a
	 * top-level POST key at all — it arrives inside the serialized `post_data` blob — so without
	 * this filter a custom field renders empty whenever the markup is regenerated.
	 *
	 * @since 11.4.0
	 *
	 * @param mixed  $value The value WooCommerce resolved, if any.
	 * @param string $input The field key being resolved.
	 *
	 * @return mixed
	 */
	public function get_checkout_value( $value, $input ) {
		if ( ! is_string( $input ) || 0 !== strpos( $input, CustomField::KEY_PREFIX ) ) {
			return $value;
		}

		$submitted = $this->get_submitted_values();

		if ( array_key_exists( $input, $submitted ) ) {
			return $submitted[ $input ];
		}

		// Fall back to the session so a field repopulates on a plain page load, where there is no
		// request data at all — after a validation failure, or when the customer navigates back.
		$stored = ValueStore::instance()->get( substr( $input, strlen( CustomField::KEY_PREFIX ) ) );

		return '' !== $stored ? $stored : $value;
	}

	/**
	 * Returns every submitted custom field value in this request, keyed by input name.
	 *
	 * Sanitised through the field itself, so a textarea keeps the line breaks the customer typed.
	 * Memoised because the request data behind it already is: this runs once per field on every
	 * render, and re-deriving it each time would mean re-reading the definitions with it.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_submitted_values(): array {
		if ( null !== $this->submitted_values ) {
			return $this->submitted_values;
		}

		$fields = CustomFieldManager::instance()->get_fields();
		$values = [];

		foreach ( $this->get_request_data() as $key => $value ) {
			if ( ! is_string( $key ) || 0 !== strpos( $key, CustomField::KEY_PREFIX ) ) {
				continue;
			}

			if ( ! is_string( $value ) ) {
				$values[ $key ] = $value;
				continue;
			}

			$field = $fields[ substr( $key, strlen( CustomField::KEY_PREFIX ) ) ] ?? null;

			// A key with no matching definition belongs to a field the merchant has since deleted.
			// Nothing reads it, but clean it as a single line rather than trusting an unknown shape.
			$values[ $key ] = $field instanceof CustomField
				? $field->sanitize_value( (string) wp_unslash( $value ) )
				: (string) wc_clean( wp_unslash( $value ) );
		}

		$this->submitted_values = $values;

		return $this->submitted_values;
	}

	/**
	 * Returns the request payload that may carry custom field values.
	 *
	 * On a normal submit the fields are top-level POST keys. On update_order_review they are inside
	 * `post_data`, which CheckoutWC parses into a local variable without touching `$_POST`, so it
	 * has to be parsed again here.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_request_data(): array {
		if ( null !== $this->parsed_post_data ) {
			return $this->parsed_post_data;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only value resolution; the AJAX and checkout handlers verify their own nonces before acting on this data.
		$data = $_POST;

		if ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$parsed = [];
			// The blob is a serialized query string, so it cannot be sanitized whole without
			// corrupting it. Individual values are sanitized in get_submitted_values().
			parse_str( wp_unslash( $_POST['post_data'] ), $parsed ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			// Top-level POST keys win: on a real submit they are the authoritative values.
			$data = array_merge( $parsed, $data );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->parsed_post_data = $data;

		return $this->parsed_post_data;
	}
}
