<?php
/**
 * The cart behind the checkout editor preview.
 *
 * @package CheckoutWC
 */

namespace Objectiv\Plugins\Checkout;

use WC_Product;
use WC_Product_Query;
use WC_Product_Variable;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * The checkout editor preview's cart, isolated from the merchant's own cart.
 *
 * The editor preview needs a populated cart to render a useful checkout, and which products are in it changes what
 * the merchant can see: a digital-only cart has no shipping step, and an order bump only renders when its trigger
 * product is present. So the merchant controls it, from the Preview cart control in the editor header.
 *
 * The preview runs on the front end as the logged-in merchant, so it shares their WooCommerce session. Rather than
 * driving their real cart, every preview request swaps WooCommerce's cart session keys out for a bucket of our own
 * before the cart loads, and swaps the merchant's values back before WooCommerce persists the session. In between,
 * the cart is an ordinary cart: bumps, coupons and quantity changes behave exactly as they do live, and what the
 * merchant does in the preview sticks for the next preview request without ever reaching their real cart.
 *
 * That bucket is the truth about what the preview holds, and it lives in a session that can expire. So each preview
 * request also mirrors the cart's products into user meta, which is what the bucket is rebuilt from when it is gone.
 * Nothing else reads the mirror: the editor lists what the preview iframe reports, not what was chosen.
 *
 * The editor cannot touch the bucket directly, because WooCommerce leaves WC()->session null on REST requests. So it
 * queues what it wants in user meta instead, and the next preview request applies it through WC_Cart — which is the
 * better place for it anyway, since that is what resolves variations, validates products, keeps totals honest and
 * fires the add and remove hooks order bumps depend on.
 *
 * @internal
 */
class EditorPreviewCart {

	/**
	 * User meta holding the products last seen in the preview cart, and the changes the editor has asked for.
	 *
	 * @var string
	 */
	public const SELECTION_META_KEY = '_cfw_editor_preview_cart';

	/**
	 * WooCommerce session key holding the swapped-out preview cart.
	 *
	 * @var string
	 */
	private const SESSION_KEY = 'cfw_editor_preview_cart';

	/**
	 * Most products a merchant can put in the preview cart.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 10;

	/**
	 * Session keys WooCommerce keeps the cart in.
	 *
	 * The first six are what WC_Cart_Session reads in get_cart_from_session() and writes in set_session(). The
	 * shipping keys are swapped too because choosing a rate in the preview writes them, and that should no more
	 * reach the merchant's session than a cart item should.
	 *
	 * @var string[]
	 */
	private const CART_SESSION_KEYS = [
		'cart',
		'cart_totals',
		'applied_coupons',
		'coupon_discount_totals',
		'coupon_discount_tax_totals',
		'removed_cart_contents',
		'chosen_shipping_methods',
		'shipping_method_counts',
		// Custom field values drive fees, so the preview must not inherit the merchant's own
		// selections — they would show as a phantom fee in the preview totals.
		'cfw_custom_field_values',
	];

	/**
	 * The merchant's own cart session values, held for the length of a preview request.
	 *
	 * @var array<string, mixed>
	 */
	private $merchant_session = [];

	/**
	 * Whether this request swapped the session, and so owes a swap back.
	 *
	 * @var bool
	 */
	private $swapped = false;

	/**
	 * Sets up the preview cart for editor preview requests.
	 *
	 * @since 11.3.1
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! cfw_is_editor_preview() || null === WC()->session ) {
			return;
		}

		// So a bump taken in the preview, or any other cart change, lands in the preview cart rather than the merchant's.
		add_filter( 'woocommerce_ajax_get_endpoint', [ $this, 'filter_ajax_endpoint' ] );

		// Otherwise WC_Cart_Session::persistent_cart_update() mirrors the preview cart into the merchant's account.
		add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );

		// Catches the merchant emptying the cart from inside the preview, which happens long after the cart loaded.
		add_action( 'cfw_cart_updated', [ $this, 'refill_if_empty' ] );

		$this->swap_in();

		// WC_Session_Handler::save_data() runs at 20, so the merchant's own values are back in place by then.
		add_action( 'shutdown', [ $this, 'swap_out' ], 5 );
	}

	/**
	 * Adds the preview flag to WooCommerce AJAX endpoints so requests from the preview iframe are recognized as preview requests.
	 *
	 * Appended by hand rather than with add_query_arg(), which re-encodes the `%%endpoint%%` placeholder WooCommerce
	 * leaves in the URL for the front end to substitute. Encoded, it no longer matches, so every AJAX call lands on a
	 * `wc-ajax` action that has no handler and returns an empty response.
	 *
	 * @since 11.3.1
	 *
	 * @param string $endpoint
	 *
	 * @return string
	 */
	public function filter_ajax_endpoint( $endpoint ): string {
		$endpoint  = (string) $endpoint;
		$separator = strpos( $endpoint, '?' ) === false ? '?' : '&';

		return $endpoint . $separator . 'cfw-editor-preview=1&_cfw_preview_nonce=' . wp_create_nonce( 'cfw-editor-preview' );
	}

	/**
	 * Puts the preview cart into the session in place of the merchant's cart.
	 *
	 * @since 11.3.1
	 *
	 * @return void
	 */
	private function swap_in(): void {
		$stored = WC()->session->get( self::SESSION_KEY, null );

		// Anything without a cart key is not a preview cart this version wrote, so treat it as nothing stored and
		// rebuild rather than swapping in a cart that isn't there.
		$stored = is_array( $stored ) && array_key_exists( 'cart', $stored ) ? $stored : null;

		foreach ( self::CART_SESSION_KEYS as $key ) {
			$this->merchant_session[ $key ] = WC()->session->get( $key, null );

			// An empty rather than absent cart, because a null one sends WC_Cart_Session::get_cart_from_session()
			// down its saved-cart branch, which consumes the merchant's post-login cart merge flag.
			$empty = 'cart' === $key ? [] : null;

			WC()->session->set( $key, null === $stored ? $empty : ( $stored[ $key ] ?? $empty ) );
		}

		$this->swapped = true;

		// Nothing stored, so this is a first open or the session has expired since the last one.
		$build = null === $stored;

		add_action(
			'woocommerce_cart_loaded_from_session',
			function () use ( $build ) {
				$this->apply_changes( $build );
			}
		);
	}

	/**
	 * Brings the freshly loaded preview cart up to date with what the editor has asked for.
	 *
	 * @since 11.3.1
	 *
	 * @param bool $build Whether the cart is being built from scratch rather than restored.
	 *
	 * @return void
	 */
	private function apply_changes( bool $build ): void {
		$stored = self::get_stored();

		foreach ( $stored['remove'] as $item_key ) {
			WC()->cart->remove_cart_item( $item_key );
		}

		$products = $build ? self::get_products_to_build() : self::get_products( $stored['add'] );
		$in_cart  = self::get_cart_product_ids( WC()->cart->get_cart() );

		foreach ( $products as $product ) {
			// Adding a product already in the cart would raise its quantity. That happens when the editor restores the
			// cart after an order placed in the preview, which does not always empty it first.
			if ( in_array( $product->get_id(), $in_cart, true ) ) {
				continue;
			}

			self::add_to_cart( $product );
		}

		// Cleared whether or not they applied, so a product that has since sold out, or a line already gone, cannot
		// stick around and be retried on every later request.
		if ( ! empty( $stored['add'] ) || ! empty( $stored['remove'] ) ) {
			self::store( $stored['items'], [], [] );
		}

		$this->refill_if_empty();
	}

	/**
	 * Puts a product back when the preview cart has ended up empty.
	 *
	 * An empty checkout is not a state customers ever reach, since WooCommerce redirects them to the cart, so there is
	 * nothing there for the merchant to lay out. It is also the state CheckoutWC answers update_order_review with a
	 * session-expired fragment for, which carries none of the data the checkout needs to re-render.
	 *
	 * @since 11.3.1
	 *
	 * @return void
	 */
	public function refill_if_empty(): void {
		if ( null === WC()->cart || ! WC()->cart->is_empty() ) {
			return;
		}

		$product = self::get_auto_selected_product();

		if ( null !== $product ) {
			self::add_to_cart( $product );
		}
	}

	/**
	 * Adds a product to the cart, resolving a variation to the parent and attributes WooCommerce expects.
	 *
	 * @since 11.3.1
	 *
	 * @param WC_Product $product
	 *
	 * @return void
	 */
	private static function add_to_cart( WC_Product $product ): void {
		if ( $product instanceof WC_Product_Variation ) {
			WC()->cart->add_to_cart( $product->get_parent_id(), 1, $product->get_id(), $product->get_variation_attributes() );

			return;
		}

		WC()->cart->add_to_cart( $product->get_id() );
	}

	/**
	 * Stores the preview cart and gives the merchant their own cart back.
	 *
	 * Cart cookies are set at shutdown priority 0, so the merchant's browser briefly carries the preview cart's item
	 * count. WooCommerce rewrites those on the next front end request, and they hold no cart data of their own.
	 *
	 * @since 11.3.1
	 *
	 * @return void
	 */
	public function swap_out(): void {
		if ( ! $this->swapped || null === WC()->session ) {
			return;
		}

		$this->swapped = false;
		$preview       = [];

		foreach ( self::CART_SESSION_KEYS as $key ) {
			$preview[ $key ] = WC()->session->get( $key, null );

			WC()->session->set( $key, $this->merchant_session[ $key ] ?? null );
		}

		WC()->session->set( self::SESSION_KEY, $preview );

		$stored = self::get_stored();

		self::store( self::get_cart_product_ids( (array) ( $preview['cart'] ?? [] ) ), $stored['add'], $stored['remove'] );
	}

	/**
	 * Asks for products to be added to the preview cart on its next request.
	 *
	 * @since 11.3.1
	 *
	 * @param int[] $ids
	 *
	 * @return void
	 */
	public static function queue_add( array $ids ): void {
		$stored = self::get_stored();
		$add    = $stored['add'];

		foreach ( $ids as $id ) {
			$id = absint( $id );

			if ( $id > 0 && ! in_array( $id, $add, true ) ) {
				$add[] = $id;
			}
		}

		self::store( $stored['items'], array_slice( $add, 0, self::MAX_ITEMS ), $stored['remove'] );
	}

	/**
	 * Asks for a line to be removed from the preview cart on its next request.
	 *
	 * @since 11.3.1
	 *
	 * @param string $item_key
	 *
	 * @return void
	 */
	public static function queue_remove( string $item_key ): void {
		$stored = self::get_stored();
		$remove = $stored['remove'];

		if ( '' !== $item_key && ! in_array( $item_key, $remove, true ) ) {
			$remove[] = $item_key;
		}

		self::store( $stored['items'], $stored['add'], $remove );
	}

	/**
	 * Describes the products last seen in the preview cart, for the editor's first paint.
	 *
	 * The editor lists what the preview iframe reports once it has loaded. This covers the moment before that.
	 *
	 * @since 11.3.1
	 *
	 * @return array<int, array{id: int, label: string}>
	 */
	public static function get_resolved_mirror(): array {
		$resolved = [];

		foreach ( self::get_products( self::get_stored()['items'] ) as $product ) {
			$resolved[] = [
				'id'    => $product->get_id(),
				'label' => ProductNameFormatter::get_display_name( $product ),
			];
		}

		return $resolved;
	}

	/**
	 * Gets the product the preview falls back to when there is nothing to rebuild from.
	 *
	 * @since 11.3.1
	 *
	 * @return WC_Product|null
	 */
	public static function get_auto_selected_product(): ?WC_Product {
		foreach ( [ 'simple', 'variation' ] as $type ) {
			$query = new WC_Product_Query(
				[
					'limit'        => 10,
					'status'       => 'publish',
					'stock_status' => 'instock',
					'type'         => [ $type ],
				]
			);

			foreach ( $query->get_products() as $product ) {
				if ( $product instanceof WC_Product && self::can_add_to_cart( $product ) ) {
					return $product;
				}
			}
		}

		return null;
	}

	/**
	 * Gets the products to build a preview cart that has nothing stored.
	 *
	 * @since 11.3.1
	 *
	 * @return WC_Product[]
	 */
	private static function get_products_to_build(): array {
		$products = self::get_products( self::get_stored()['items'] );

		if ( ! empty( $products ) ) {
			return $products;
		}

		$auto = self::get_auto_selected_product();

		return null === $auto ? [] : [ $auto ];
	}

	/**
	 * Resolves product IDs to the products that can go in a cart.
	 *
	 * @since 11.3.1
	 *
	 * @param int[] $ids
	 *
	 * @return WC_Product[]
	 */
	private static function get_products( array $ids ): array {
		$products = [];

		foreach ( $ids as $id ) {
			$product = wc_get_product( absint( $id ) );

			if ( $product instanceof WC_Product && self::can_add_to_cart( $product ) ) {
				$products[] = $product;
			}
		}

		return $products;
	}

	/**
	 * Whether a product can go in the preview cart as it stands.
	 *
	 * A variable product is excluded because adding one needs a variation chosen, which the preview cannot do on the
	 * merchant's behalf without guessing at what they wanted to look at.
	 *
	 * @since 11.3.1
	 *
	 * @param WC_Product $product
	 *
	 * @return bool
	 */
	public static function can_add_to_cart( WC_Product $product ): bool {
		if ( $product instanceof WC_Product_Variable ) {
			return false;
		}

		return $product->is_purchasable() && $product->is_in_stock();
	}

	/**
	 * Gets the product IDs in a stored cart, preferring the variation over its parent.
	 *
	 * @since 11.3.1
	 *
	 * @param array<string, array<string, mixed>> $cart
	 *
	 * @return int[]
	 */
	private static function get_cart_product_ids( array $cart ): array {
		$ids = [];

		foreach ( $cart as $item ) {
			$id = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );

			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Gets the products last seen in the preview cart, and the changes waiting to be applied to it.
	 *
	 * @since 11.3.1
	 *
	 * @return array{items: int[], add: int[], remove: string[]}
	 */
	private static function get_stored(): array {
		$stored = get_user_meta( get_current_user_id(), self::SELECTION_META_KEY, true );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'items'  => array_values( array_filter( array_map( 'absint', (array) ( $stored['items'] ?? [] ) ) ) ),
			'add'    => array_values( array_filter( array_map( 'absint', (array) ( $stored['add'] ?? [] ) ) ) ),
			'remove' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $stored['remove'] ?? [] ) ) ) ),
		];
	}

	/**
	 * Saves the products last seen in the preview cart, and the changes waiting to be applied to it.
	 *
	 * @since 11.3.1
	 *
	 * @param int[]    $items
	 * @param int[]    $add
	 * @param string[] $remove
	 *
	 * @return void
	 */
	private static function store( array $items, array $add, array $remove ): void {
		update_user_meta(
			get_current_user_id(),
			self::SELECTION_META_KEY,
			[
				'items'  => array_slice( $items, 0, self::MAX_ITEMS ),
				'add'    => $add,
				'remove' => $remove,
			]
		);
	}
}
