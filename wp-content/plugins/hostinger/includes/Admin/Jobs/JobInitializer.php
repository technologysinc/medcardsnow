<?php

namespace Hostinger\Admin\Jobs;

use Hostinger\Admin\PluginSettings;
use Hostinger\Admin\Proxy;
use Hostinger\LlmsTxtGenerator\LlmsTxtFileHelper;
use Hostinger\LlmsTxtGenerator\LlmsTxtParser;
use Hostinger\Mcp\EventHandlerFactory;

defined( 'ABSPATH' ) || exit;

class JobInitializer {

    public function __construct( Proxy $proxy, LlmsTxtParser $llms_txt_parser ) {
        $jobs   = array();
        $jobs[] = new NotifyMcpJob( new ActionScheduler(), new EventHandlerFactory( $proxy ) );
        $jobs[] = new LlmsTxtInjectContentJob( new ActionScheduler(), $llms_txt_parser, new LlmsTxtFileHelper(), new PluginSettings() );

        foreach ( $jobs as $job ) {
            $job->init();
        }
    }
}
