<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\Chatbot\Auth\AuthRoutes;
use Hostinger\AiAssistant\Container;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ChatbotAuthProvider implements ProviderInterface {
    public function register( Container $container ): void {
        $container->get( AuthRoutes::class )->init();
    }
}
