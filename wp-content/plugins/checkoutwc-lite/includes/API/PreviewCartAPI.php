<?php
/**
 * REST API for the checkout editor preview cart.
 *
 * @package CheckoutWC\API
 */

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\EditorPreviewCart;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Adds products to, and removes lines from, the checkout editor preview cart.
 *
 * POST   /checkoutwc/v1/preview-cart — asks for a product to be added.
 * DELETE /checkoutwc/v1/preview-cart — asks for a cart line to be removed.
 *
 * Both only record what was asked for. The editor then reloads the preview iframe, which is the request that applies
 * the change and reports back what the cart now holds — WC()->session, and so the preview cart, is not available here.
 *
 * @internal
 */
class PreviewCartAPI {

	/**
	 * Hooks up route registration.
	 *
	 * @since 11.3.1
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Registers the preview cart route.
	 *
	 * @since 11.3.1
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'checkoutwc/v1',
			'preview-cart',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_products' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'product_ids' => [
							'type'     => 'array',
							'required' => true,
							'items'    => [
								'type' => 'integer',
							],
						],
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_item' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'item_key' => [
							'type'     => 'string',
							'required' => true,
						],
					],
				],
			]
		);
	}

	/**
	 * POST /checkoutwc/v1/preview-cart
	 *
	 * @since 11.3.1
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function add_products( WP_REST_Request $request ): WP_REST_Response {
		EditorPreviewCart::queue_add( (array) $request->get_param( 'product_ids' ) );

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * DELETE /checkoutwc/v1/preview-cart
	 *
	 * @since 11.3.1
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function remove_item( WP_REST_Request $request ): WP_REST_Response {
		EditorPreviewCart::queue_remove( sanitize_text_field( (string) $request->get_param( 'item_key' ) ) );

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Whether the current user may change the preview cart.
	 *
	 * @since 11.3.1
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'cfw_manage_pages' );
	}
}
