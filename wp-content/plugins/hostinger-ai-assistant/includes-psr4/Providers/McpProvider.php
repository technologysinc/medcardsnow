<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Container;
use Hostinger\AiAssistant\Functions;
use Hostinger\AiAssistant\Mcp\Abilities\AbilitiesRegistry;
use Hostinger\AiAssistant\Mcp\McpConnectionTracker;
use Hostinger\AiAssistant\Mcp\Hooks;
use Hostinger\AiAssistant\Mcp\McpServer;
use Hostinger\AiAssistant\Mcp\Rest\DefaultServerJwtAuth;
use Hostinger\AiAssistant\Mcp\Rest\JwtAuth;
use Hostinger\Amplitude\AmplitudeManager;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class McpProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $jwt_auth = $container->get( JwtAuth::class );
        $jwt_auth->init();

        if ( ! class_exists( 'WP\MCP\Core\McpAdapter' ) ) {
            return;
        }

        $hooks = $container->get( Hooks::class );
        $hooks->init();

        $abilities_registry = $container->get( AbilitiesRegistry::class );
        $abilities_registry->init();

        $container->set(
            McpServer::class,
            function () use ( $container ) {
                return new McpServer( $container->get( JwtAuth::class ) );
            }
        );

        $mcp_server = $container->get( McpServer::class );
        $mcp_server->init();

        $container->set(
            DefaultServerJwtAuth::class,
            function () use ( $container ) {
                return new DefaultServerJwtAuth( $container->get( JwtAuth::class ) );
            }
        );

        $default_server_jwt_auth = $container->get( DefaultServerJwtAuth::class );
        $default_server_jwt_auth->init();

        $container->set(
            McpConnectionTracker::class,
            function () use ( $container ) {
                return new McpConnectionTracker( $container->get( AmplitudeManager::class ), $container->get( JwtAuth::class ) );
            }
        );

        $connection_tracker = $container->get( McpConnectionTracker::class );
        $connection_tracker->init();
    }
}
