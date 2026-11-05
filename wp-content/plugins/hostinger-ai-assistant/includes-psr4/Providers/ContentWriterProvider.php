<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Container;
use Hostinger\AiAssistant\ContentWriter\ContentWriterRoutes;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ContentWriterProvider implements ProviderInterface {
    public function register( Container $container ): void {
        add_action(
            'rest_api_init',
            static function () use ( $container ): void {
                $container->get( ContentWriterRoutes::class )->register_routes();
            }
        );
    }
}
