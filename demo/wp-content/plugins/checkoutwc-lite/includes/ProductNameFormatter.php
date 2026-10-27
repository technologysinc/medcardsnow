<?php
/**
 * Display names for products and variations.
 *
 * @package CheckoutWC
 */

namespace Objectiv\Plugins\Checkout;

use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Builds product names that identify a variation.
 *
 * WooCommerce drops the attribute suffix from a variation's post title when its parent has three or
 * more attributes ( see WC_Product_Variation_Data_Store_CPT::generate_product_title() ), so sibling
 * variations can be indistinguishable from each other and from the parent product. Anywhere admins
 * pick or review a product, that ambiguity leaves them unable to tell what is configured.
 *
 * @internal
 */
class ProductNameFormatter {

	/**
	 * Get a display name that identifies the product, including its variation attributes.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $fallback Name to use when a variation has no attribute summary or no parent. Defaults to the product's own name.
	 *
	 * @return string
	 */
	public static function get_display_name( WC_Product $product, string $fallback = '' ): string {
		$fallback = wp_strip_all_tags( '' !== $fallback ? $fallback : $product->get_name() );

		if ( ! $product instanceof WC_Product_Variation ) {
			return $fallback;
		}

		$summary = $product->get_attribute_summary();

		if ( empty( $summary ) ) {
			return $fallback;
		}

		// Read from the parent post rather than the product object: the summary is already stored on
		// the variation, so this keeps the whole name to a single cached lookup.
		$parent_title = $product->get_parent_id() ? get_post_field( 'post_title', $product->get_parent_id() ) : '';

		if ( empty( $parent_title ) ) {
			return $fallback;
		}

		return wp_strip_all_tags( $parent_title ) . ' – ' . wp_strip_all_tags( $summary );
	}
}
