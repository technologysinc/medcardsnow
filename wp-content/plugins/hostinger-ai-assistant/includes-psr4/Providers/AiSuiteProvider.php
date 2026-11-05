<?php

namespace Hostinger\AiAssistant\Providers;

use Hostinger\AiAssistant\AiSuite\AiSuiteAssets;
use Hostinger\AiAssistant\AiSuite\AiSuitePage;
use Hostinger\AiAssistant\Container;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AiSuiteProvider implements ProviderInterface {
    public function register( Container $container ): void {
        if ( ! is_admin() ) {
            return;
        }

        $container->get( AiSuitePage::class )->init();
        $container->get( AiSuiteAssets::class )->init();
    }
}
