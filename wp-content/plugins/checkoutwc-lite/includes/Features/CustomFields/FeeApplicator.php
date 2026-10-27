<?php

namespace Objectiv\Plugins\Checkout\Features\CustomFields;

use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Model\CustomField;
use WC_Cart;
use WC_Order_Item_Fee;
use WC_Tax;

/**
 * Adds a cart fee for each custom field configured to carry one.
 *
 * The money itself needs no help reaching the order: WooCommerce turns every cart fee into an order
 * fee line during checkout, and renders it on the order, in emails and on the thank-you page from
 * the fee's own name. What this class adds beyond the fee is traceability — which field produced a
 * given line, since WooCommerce does not persist the fee key.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Features\CustomFields
 */
class FeeApplicator {

	/**
	 * Prefix for the fee ID and the order-item meta that records the source field.
	 *
	 * @var string
	 */
	const FEE_ID_PREFIX = 'cfw_cf_';

	/**
	 * Order item meta key recording which field produced a fee line.
	 *
	 * @var string
	 */
	const ITEM_META_FIELD_ID = '_cfw_custom_field_id';

	/**
	 * Registers hooks.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_fees' ], 20 );
		add_action( 'woocommerce_checkout_create_order_fee_item', [ $this, 'add_fee_item_meta' ], 10, 3 );
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_fee_item_meta' ] );
	}

	/**
	 * Adds a fee for every qualifying field.
	 *
	 * Deliberately not gated on a checkout-page check. `is_checkout()` is false during both the
	 * checkout-refresh and the order-placement AJAX requests, so gating on it would drop the fee from
	 * the totals the customer sees *and* from the order that gets charged.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Cart $cart
	 *
	 * @return void
	 */
	public function add_fees( $cart ): void {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		/**
		 * Filters whether custom field fees are applied at all.
		 *
		 * A kill switch for support: it disables pricing without touching the merchant's field
		 * configuration.
		 *
		 * @since 11.4.0
		 *
		 * @param bool $enabled
		 */
		if ( ! apply_filters( 'cfw_custom_field_fees_enabled', true ) ) {
			return;
		}

		$manager = CustomFieldManager::instance();
		$values  = ValueStore::instance()->all();

		foreach ( $manager->get_eligible_fields() as $field_id => $field ) {
			if ( ! $field->has_fee() ) {
				continue;
			}

			$value = (string) ( $values[ $field_id ] ?? '' );

			if ( '' === $value ) {
				continue;
			}

			$amount = $this->calculate_amount( $field, $value, $cart );

			// A fee that rounds to zero is skipped rather than added: WooCommerce hides zero-value
			// fees from the customer's totals but still creates an order line item for them, and that
			// split is more confusing than no fee at all.
			if ( $amount <= 0 ) {
				continue;
			}

			// Currency conversion and the integration filter, both of which the customer-facing label
			// goes through as well, so the charge and the price shown cannot disagree.
			$amount = $field->to_effective_amount( $amount );

			if ( $amount <= 0 ) {
				continue;
			}

			$fee       = $field->get_fee_config();
			$taxable   = ! empty( $fee['taxable'] );
			$tax_class = (string) ( $fee['tax_class'] ?? '' );

			// An explicit ID rather than WC_Cart::add_fee(). That helper derives the ID from the fee
			// name and discards the duplicate-ID error it gets back, so two fields whose labels
			// sanitise to the same slug would silently produce a single fee line and undercharge.
			$cart->fees_api()->add_fee(
				[
					'id'        => self::FEE_ID_PREFIX . $field_id,
					'name'      => $this->get_fee_label( $field, $value ),
					'amount'    => $this->to_net_amount( $amount, $taxable, $tax_class, $cart ),
					'taxable'   => $taxable,
					'tax_class' => $tax_class,
				]
			);
		}
	}

	/**
	 * Works out a field's fee for the submitted value.
	 *
	 * @since 11.4.0
	 *
	 * @param CustomField $field
	 * @param string      $value
	 * @param WC_Cart     $cart
	 *
	 * @return float
	 */
	private function calculate_amount( CustomField $field, string $value, WC_Cart $cart ): float {
		$fee         = $field->get_fee_config();
		$basis       = (string) ( $fee['basis'] ?? PercentageBasis::ITEMS_AFTER_DISCOUNTS );
		$include_tax = ! empty( $fee['basis_include_tax'] );

		// Select and radio fields price per option, so the chosen option supplies the amount.
		if ( in_array( $field->get_type(), [ 'select', 'radio' ], true ) ) {
			foreach ( $field->get_options() as $option ) {
				if ( $option['value'] !== $value ) {
					continue;
				}

				return 'percent' === ( $fee['mode'] ?? 'flat' )
					? $this->percentage_of( (float) $option['fee_amount'], $basis, $include_tax, $cart )
					: (float) $option['fee_amount'];
			}

			return 0.0;
		}

		$amount = (float) ( $fee['amount'] ?? 0 );

		return 'percent' === ( $fee['mode'] ?? 'flat' )
			? $this->percentage_of( $amount, $basis, $include_tax, $cart )
			: $amount;
	}

	/**
	 * Returns a percentage of the configured basis, rounded to the store's precision.
	 *
	 * @since 11.4.0
	 *
	 * @param float   $percent
	 * @param string  $basis
	 * @param bool    $include_tax
	 * @param WC_Cart $cart
	 *
	 * @return float
	 */
	private function percentage_of( float $percent, string $basis, bool $include_tax, WC_Cart $cart ): float {
		if ( $percent <= 0 ) {
			return 0.0;
		}

		$base = PercentageBasis::resolve( $basis, $include_tax, $cart );

		return round( $base * ( $percent / 100 ), wc_get_price_decimals() );
	}

	/**
	 * Converts a configured amount into the net figure WooCommerce expects.
	 *
	 * WooCommerce always treats a taxable fee as net and adds tax on top, whatever the store's own
	 * pricing setting says. On a store that enters prices *inclusive* of tax that is the opposite of
	 * how every other amount the merchant types behaves, so a fee entered as 10 would charge 12.
	 * Where the fee really will be taxed, the tax is backed out of the entered amount first, leaving
	 * the customer charged exactly what was configured.
	 *
	 * The guards matter: with tax disabled, or for a VAT-exempt customer, WooCommerce adds no tax at
	 * all, so backing any out would quietly undercharge.
	 *
	 * @since 11.4.0
	 *
	 * @param float   $amount    The configured amount.
	 * @param bool    $taxable   Whether the fee is marked taxable.
	 * @param string  $tax_class The fee's tax class slug.
	 * @param WC_Cart $cart
	 *
	 * @return float
	 */
	private function to_net_amount( float $amount, bool $taxable, string $tax_class, WC_Cart $cart ): float {
		if ( ! $taxable || ! wc_prices_include_tax() || ! wc_tax_enabled() ) {
			return $amount;
		}

		$customer = $cart->get_customer();

		if ( $customer && $customer->get_is_vat_exempt() ) {
			return $amount;
		}

		$taxes = WC_Tax::calc_tax( $amount, WC_Tax::get_rates( $tax_class, $customer ), true );

		return round( $amount - array_sum( $taxes ), wc_get_price_decimals() + 2 );
	}

	/**
	 * Returns the label the fee appears under.
	 *
	 * The merchant's own wording, never a hardcoded string: this label is what reaches the order, the
	 * totals table and every email. For a per-option fee the chosen option is appended, so a single
	 * field does not produce an ambiguous line.
	 *
	 * @since 11.4.0
	 *
	 * @param CustomField $field
	 * @param string      $value
	 *
	 * @return string
	 */
	private function get_fee_label( CustomField $field, string $value ): string {
		$fee   = $field->get_fee_config();
		$label = (string) ( $fee['label'] ?? '' );

		if ( '' === $label ) {
			$label = $field->get_label();
		}

		if ( in_array( $field->get_type(), [ 'select', 'radio' ], true ) ) {
			foreach ( $field->get_options() as $option ) {
				if ( $option['value'] === $value && '' !== $option['label'] ) {
					$label .= ': ' . $option['label'];
					break;
				}
			}
		}

		/**
		 * Filters the label a custom field's fee appears under.
		 *
		 * @since 11.4.0
		 *
		 * @param string      $label
		 * @param CustomField $field
		 * @param string      $value
		 */
		return (string) apply_filters( 'cfw_custom_field_fee_label', $label, $field, $value );
	}

	/**
	 * Records which field produced a fee line on the order.
	 *
	 * WooCommerce keeps only the fee's name on the order item, so without this there is no way back
	 * from a fee line to the field that created it.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order_Item_Fee $item    The order fee item.
	 * @param string            $fee_key The cart fee key.
	 * @param object            $fee     The cart fee object.
	 *
	 * @return void
	 */
	public function add_fee_item_meta( $item, $fee_key, $fee ): void {
		if ( ! $item instanceof WC_Order_Item_Fee ) {
			return;
		}

		$id = is_object( $fee ) && isset( $fee->id ) ? (string) $fee->id : (string) $fee_key;

		if ( 0 !== strpos( $id, self::FEE_ID_PREFIX ) ) {
			return;
		}

		$item->add_meta_data( self::ITEM_META_FIELD_ID, substr( $id, strlen( self::FEE_ID_PREFIX ) ), true );
	}

	/**
	 * Keeps the traceability meta out of the admin order item display.
	 *
	 * @since 11.4.0
	 *
	 * @param array $meta_keys
	 *
	 * @return array
	 */
	public function hide_fee_item_meta( $meta_keys ) {
		if ( ! is_array( $meta_keys ) ) {
			return $meta_keys;
		}

		$meta_keys[] = self::ITEM_META_FIELD_ID;

		return $meta_keys;
	}
}
