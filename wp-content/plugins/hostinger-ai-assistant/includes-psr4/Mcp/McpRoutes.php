<?php

namespace Hostinger\AiAssistant\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class McpRoutes {
    public const DEFAULT_SERVER_ID    = 'mcp-adapter-default-server';
    public const DEFAULT_SERVER_ROUTE = 'mcp/mcp-adapter-default-server';

    public static function is_mcp_request_uri( string $request_uri ): bool {
        if ( str_contains( $request_uri, HOSTINGER_AI_ASSISTANT_REST_API_BASE . '/mcp' ) ) {
            return true;
        }

        return str_contains( $request_uri, self::DEFAULT_SERVER_ROUTE );
    }
}
