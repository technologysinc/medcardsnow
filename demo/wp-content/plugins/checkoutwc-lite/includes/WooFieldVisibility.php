<?php

namespace Objectiv\Plugins\Checkout;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

/**
 * Resolves the visibility WooCommerce applies to the three address fields it owns.
 *
 * Phone, company and address 2 each have a WooCommerce option holding `hidden`, `optional` or
 * `required`, and `WC_Countries` acts on all three for classic checkout as well as the Checkout
 * block: a hidden field is unset from every address, and a required one is marked required. Our own
 * billing field filter can only ever remove a field, never restore one, so anything that reads these
 * settings has to agree with WooCommerce or we describe a checkout that is not the one rendering.
 *
 * The value is deliberately not read straight off the option. WooCommerce resolves an unset option
 * through a migration that depends on whether the checkout page holds the Checkout block — which it
 * does on most CheckoutWC stores, since we replace the rendering and not the page content — and that
 * migration writes `hidden` for the company field. Reading the raw option would report a field as
 * present that checkout is about to drop.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout
 */
class WooFieldVisibility {

	/**
	 * @var string
	 */
	const PHONE = 'phone';

	/**
	 * @var string
	 */
	const COMPANY = 'company';

	/**
	 * @var string
	 */
	const ADDRESS_2 = 'address_2';

	/**
	 * The CartCheckoutUtils method that resolves each field, keyed by field.
	 *
	 * @var array<string, string>
	 */
	const RESOLVERS = [
		self::PHONE     => 'get_phone_field_visibility',
		self::COMPANY   => 'get_company_field_visibility',
		self::ADDRESS_2 => 'get_address_2_field_visibility',
	];

	/**
	 * Returns the visibility WooCommerce will apply to a field.
	 *
	 * Falls back to the option, defaulted the way WooCommerce defaults it for a store that is not
	 * running the Checkout block. That branch only matters if the Blocks namespace ever goes away,
	 * which is why the helper is called through a guard rather than depended on outright.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field One of this class's field constants.
	 *
	 * @return string One of `hidden`, `optional` or `required`.
	 */
	public static function get( string $field ): string {
		$option   = 'woocommerce_checkout_' . $field . '_field';
		$resolver = self::RESOLVERS[ $field ] ?? '';

		if ( $resolver && class_exists( CartCheckoutUtils::class ) && method_exists( CartCheckoutUtils::class, $resolver ) ) {
			$value = (string) call_user_func( [ CartCheckoutUtils::class, $resolver ] );
		} else {
			$value = (string) get_option( $option, 'optional' );
		}

		return in_array( $value, [ 'hidden', 'optional', 'required' ], true ) ? $value : 'optional';
	}

	/**
	 * Returns whether WooCommerce removes a field from checkout entirely.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field One of this class's field constants.
	 *
	 * @return bool
	 */
	public static function is_hidden( string $field ): bool {
		return 'hidden' === self::get( $field );
	}

	/**
	 * Returns whether WooCommerce marks a field required.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field One of this class's field constants.
	 *
	 * @return bool
	 */
	public static function is_required( string $field ): bool {
		return 'required' === self::get( $field );
	}
}
