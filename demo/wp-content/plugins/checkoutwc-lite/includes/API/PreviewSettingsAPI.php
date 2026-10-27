<?php

namespace Objectiv\Plugins\Checkout\API;

use WP_REST_Server;

class PreviewSettingsAPI {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			'checkoutwc/v1',
			'preview-settings',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'store_preview_settings' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'clear_preview_settings' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
			]
		);
	}

	public function store_preview_settings( \WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$settings = $request->get_json_params();

		set_transient( '_cfw_editor_preview_' . $user_id, $settings, 30 * MINUTE_IN_SECONDS );

		return rest_ensure_response( [ 'success' => true ] );
	}

	public function clear_preview_settings() {
		$user_id = get_current_user_id();

		delete_transient( '_cfw_editor_preview_' . $user_id );

		return rest_ensure_response( [ 'success' => true ] );
	}

	public function can_manage(): bool {
		return current_user_can( 'cfw_manage_pages' );
	}
}
