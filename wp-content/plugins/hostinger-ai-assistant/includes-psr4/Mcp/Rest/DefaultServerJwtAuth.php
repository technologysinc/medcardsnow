<?php

namespace Hostinger\AiAssistant\Mcp\Rest;

use Hostinger\AiAssistant\Mcp\McpRoutes;
use WP\MCP\Core\McpAdapter;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DefaultServerJwtAuth {
    private const INVALID_TOKEN_STATUS = 401;

    private JwtAuth $jwt_auth;

    public function __construct( JwtAuth $jwt_auth ) {
        $this->jwt_auth = $jwt_auth;
    }

    public function init(): void {
        add_filter( 'rest_pre_dispatch', array( $this, 'authenticate_request' ), 10, 3 );
    }

    public function authenticate_request( mixed $result, WP_REST_Server $server, WP_REST_Request $request ): mixed {
        if ( $result !== null || ! $this->is_default_server_request( $request ) ) {
            return $result;
        }

        $authentication = $this->jwt_auth->authenticate_request( $request, self::INVALID_TOKEN_STATUS );
        if ( is_wp_error( $authentication ) ) {
            return $authentication;
        }

        return $result;
    }

    private function is_default_server_request( WP_REST_Request $request ): bool {
        $default_server = McpAdapter::instance()->get_server( McpRoutes::DEFAULT_SERVER_ID );
        if ( $default_server === null ) {
            return false;
        }

        $default_route = '/' . trim( $default_server->get_server_route_namespace(), '/' ) . '/' . trim( $default_server->get_server_route(), '/' );

        return strtolower( untrailingslashit( $request->get_route() ) ) === strtolower( $default_route );
    }
}
