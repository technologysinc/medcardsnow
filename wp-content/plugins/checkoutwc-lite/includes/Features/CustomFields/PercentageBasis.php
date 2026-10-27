<?php

namespace Objectiv\Plugins\Checkout\Features\CustomFields;

use WC_Cart;

/**
 * Resolves the cart figure a percentage fee is taken from.
 *
 * The choice is three independent questions — items alone or items plus shipping, before or after
 * discounts, and with or without tax — so the basis is stored as a shape (this class's constants)
 * plus a separate include-tax flag rather than one flat list of every combination.
 *
 * WooCommerce calculates a cart in four straight-line steps: items and coupons, then shipping, then
 * fees, then the grand total. Our fee hook runs in step three, which makes the item and shipping
 * figures final and correct — but leaves the totals from step four holding the *previous*
 * calculation's numbers, or zero on a fresh session.
 *
 * So this class must never read `get_total()`, `get_total_tax()` or `get_fee_total()`. Doing so
 * produces a fee that looks right on the second refresh and wrong on the first, which is close to
 * impossible to reproduce on demand.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Features\CustomFields
 */
class PercentageBasis {

	/**
	 * Order items, with coupons applied. The default and the least surprising.
	 *
	 * @var string
	 */
	const ITEMS_AFTER_DISCOUNTS = 'items_after_discounts';

	/**
	 * Order items at full price, before coupons.
	 *
	 * @var string
	 */
	const ITEMS_BEFORE_DISCOUNTS = 'items_before_discounts';

	/**
	 * Order items with coupons applied, plus shipping.
	 *
	 * @var string
	 */
	const ITEMS_SHIPPING_AFTER_DISCOUNTS = 'items_shipping_after_discounts';

	/**
	 * Order items at full price, plus shipping.
	 *
	 * @var string
	 */
	const ITEMS_SHIPPING_BEFORE_DISCOUNTS = 'items_shipping_before_discounts';

	/**
	 * Superseded basis keys mapped to their replacement.
	 *
	 * The original list folded the tax question into the basis itself, which made it asymmetric — one
	 * shipping entry, no tax variant. Mapping rather than ignoring these keeps a fee configured
	 * before the change charging the same amount instead of silently switching basis.
	 *
	 * @var array<string, array{basis: string, include_tax: bool}>
	 */
	const LEGACY_BASES = [
		'items_after_discounts_excl_tax' => [
			'basis'       => self::ITEMS_AFTER_DISCOUNTS,
			'include_tax' => false,
		],
		'items_after_discounts_incl_tax' => [
			'basis'       => self::ITEMS_AFTER_DISCOUNTS,
			'include_tax' => true,
		],
		'subtotal_excl_tax'              => [
			'basis'       => self::ITEMS_BEFORE_DISCOUNTS,
			'include_tax' => false,
		],
		'subtotal_incl_tax'              => [
			'basis'       => self::ITEMS_BEFORE_DISCOUNTS,
			'include_tax' => true,
		],
		'total_incl_shipping'            => [
			'basis'       => self::ITEMS_SHIPPING_AFTER_DISCOUNTS,
			'include_tax' => false,
		],
	];

	/**
	 * Returns every currently supported basis key.
	 *
	 * @since 11.4.0
	 *
	 * @return string[]
	 */
	public static function get_supported(): array {
		return [
			self::ITEMS_AFTER_DISCOUNTS,
			self::ITEMS_BEFORE_DISCOUNTS,
			self::ITEMS_SHIPPING_AFTER_DISCOUNTS,
			self::ITEMS_SHIPPING_BEFORE_DISCOUNTS,
		];
	}

	/**
	 * Normalises a stored basis into its current key and include-tax flag.
	 *
	 * @since 11.4.0
	 *
	 * @param string $basis       The stored basis key, current or superseded.
	 * @param bool   $include_tax The stored include-tax flag.
	 *
	 * @return array{basis: string, include_tax: bool}
	 */
	public static function normalise( string $basis, bool $include_tax = false ): array {
		if ( isset( self::LEGACY_BASES[ $basis ] ) ) {
			return self::LEGACY_BASES[ $basis ];
		}

		return [
			'basis'       => in_array( $basis, self::get_supported(), true ) ? $basis : self::ITEMS_AFTER_DISCOUNTS,
			'include_tax' => $include_tax,
		];
	}

	/**
	 * Resolves a basis to its amount for the given cart.
	 *
	 * @since 11.4.0
	 *
	 * @param string  $basis       One of this class's basis constants.
	 * @param bool    $include_tax Whether to add the tax on the measured amount.
	 * @param WC_Cart $cart
	 *
	 * @return float
	 */
	public static function resolve( string $basis, bool $include_tax, WC_Cart $cart ): float {
		$normalised  = self::normalise( $basis, $include_tax );
		$include_tax = $normalised['include_tax'];

		switch ( $normalised['basis'] ) {
			case self::ITEMS_BEFORE_DISCOUNTS:
				$net = (float) $cart->get_subtotal();
				$tax = (float) $cart->get_subtotal_tax();
				break;

			case self::ITEMS_SHIPPING_BEFORE_DISCOUNTS:
				$net = (float) $cart->get_subtotal() + (float) $cart->get_shipping_total();
				$tax = (float) $cart->get_subtotal_tax() + (float) $cart->get_shipping_tax();
				break;

			case self::ITEMS_SHIPPING_AFTER_DISCOUNTS:
				$net = (float) $cart->get_cart_contents_total() + (float) $cart->get_shipping_total();
				$tax = (float) $cart->get_cart_contents_tax() + (float) $cart->get_shipping_tax();
				break;

			case self::ITEMS_AFTER_DISCOUNTS:
			default:
				$net = (float) $cart->get_cart_contents_total();
				$tax = (float) $cart->get_cart_contents_tax();
				break;
		}

		return max( 0, $include_tax ? $net + $tax : $net );
	}
}
