<?php

namespace Objectiv\Plugins\Checkout\Features\CustomFields;

use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Model\CustomField;
use Objectiv\Plugins\Checkout\SingletonAbstract;

/**
 * Holds submitted custom field values in the WooCommerce session.
 *
 * Fees have to be recalculated in requests that carry no POST at all — every page load through the
 * checkout data payload, twice per checkout refresh, during order creation, on the cart page and in
 * the side cart. WooCommerce's own fee API states outright that fees do not persist, so the value a
 * fee is derived from cannot be read from the request; it has to live somewhere the next
 * recalculation can reach.
 *
 * Writes are an authoritative replace, never a merge: every currently defined field is written on
 * each update, and a field absent from the payload is *cleared*. That single rule removes the whole
 * phantom-fee class — an unchecked checkbox never serialises, a field whose slot did not render is
 * absent, and a conditionally hidden field is disabled and therefore absent. A merge would leave a
 * stale value charging the customer indefinitely.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Features\CustomFields
 */
class ValueStore extends SingletonAbstract {

	/**
	 * WooCommerce session key holding the values, keyed by field ID.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'cfw_custom_field_values';

	/**
	 * Registers hooks.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Runs before the customer is saved and before totals are recalculated on a checkout refresh.
		add_action( 'cfw_checkout_update_order_review', [ $this, 'capture_from_post_data' ], 5 );

		// The last point before WooCommerce recalculates the totals that become the order total.
		add_action( 'woocommerce_checkout_process', [ $this, 'capture_from_post' ], 5 );

		add_action( 'woocommerce_cart_emptied', [ $this, 'clear' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'clear' ] );
	}

	/**
	 * Captures values from the serialized payload sent with a checkout refresh.
	 *
	 * @since 11.4.0
	 *
	 * @param string $post_data The raw `post_data` query string.
	 *
	 * @return void
	 */
	public function capture_from_post_data( $post_data = '' ): void {
		$parsed = [];

		if ( is_string( $post_data ) && '' !== $post_data ) {
			parse_str( $post_data, $parsed );
		}

		$this->replace( $parsed );
	}

	/**
	 * Captures values from a checkout submission.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function capture_from_post(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout verifies the checkout nonce before this hook fires; values are sanitized in replace().
		$this->replace( $_POST );
	}

	/**
	 * Replaces the stored values, treating an absent field as cleared.
	 *
	 * Iterates the field definitions rather than the payload, so a field deleted by the merchant
	 * simply stops being stored instead of lingering in the session.
	 *
	 * @since 11.4.0
	 *
	 * @param array $payload Raw request data.
	 *
	 * @return void
	 */
	private function replace( array $payload ): void {
		if ( ! WC()->session ) {
			return;
		}

		$values = [];

		foreach ( CustomFieldManager::instance()->get_fields() as $field_id => $field ) {
			$key = $field->get_input_name();

			if ( ! isset( $payload[ $key ] ) ) {
				continue;
			}

			$raw = $payload[ $key ];

			if ( is_array( $raw ) ) {
				continue;
			}

			// Trimmed after sanitising rather than before: a textarea keeps its internal line breaks,
			// but a submission that is nothing but whitespace is still no answer at all.
			$value = trim( $field->sanitize_value( (string) wp_unslash( (string) $raw ) ) );

			if ( '' === $value ) {
				continue;
			}

			$values[ $field_id ] = $this->constrain( $field, $value );
		}

		WC()->session->set( self::SESSION_KEY, array_filter( $values, 'strlen' ) );
	}

	/**
	 * Rejects a value that is not one of the field's own options.
	 *
	 * A select or radio value has to match a currently configured option. Anything else — a stale
	 * selection whose option the merchant has since removed, or a hand-crafted request — must not be
	 * priced, and must never silently fall back to the field-level amount.
	 *
	 * @since 11.4.0
	 *
	 * @param CustomField $field
	 * @param string      $value
	 *
	 * @return string
	 */
	private function constrain( CustomField $field, string $value ): string {
		if ( ! in_array( $field->get_type(), [ 'select', 'radio' ], true ) ) {
			return $value;
		}

		foreach ( $field->get_options() as $option ) {
			if ( $option['value'] === $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Returns every stored value, keyed by field ID.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, string>
	 */
	public function all(): array {
		if ( ! WC()->session ) {
			return [];
		}

		$values = WC()->session->get( self::SESSION_KEY, [] );

		return is_array( $values ) ? $values : [];
	}

	/**
	 * Returns a single stored value.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field_id
	 *
	 * @return string
	 */
	public function get( string $field_id ): string {
		return (string) ( $this->all()[ $field_id ] ?? '' );
	}

	/**
	 * Discards all stored values.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function clear(): void {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY, [] );
		}
	}
}
