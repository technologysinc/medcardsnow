<?php

namespace Hostinger;

use Hostinger\Admin\PluginSettings;
use Hostinger\LlmsTxtGenerator\LlmsTxtFileHelper;
use Hostinger\LlmsTxtGenerator\LlmsTxtGenerator;
use Hostinger\LlmsTxtGenerator\LlmsTxtParser;
use Hostinger\LlmsTxtGenerator\LlmsTxtSummaryProvider;

defined( 'ABSPATH' ) || exit;

class Activator {
    public const INSTALLATION_OPTION_NAME = 'hts_new_installation';

    public static function activate(): void {
        $plugin_settings = new PluginSettings();

        $options = new DefaultOptions( $plugin_settings );
        $options->add_options();

        self::generate_llms_txt( $plugin_settings );
        self::update_installation_state_on_activation();
    }

    /**
     * The generator's own self-heal on `init` only runs for a logged-in administrator, so a site
     * provisioned headlessly would serve no llms.txt until someone opened wp-admin.
     */
    private static function generate_llms_txt( PluginSettings $plugin_settings ): void {
        $file_helper = new LlmsTxtFileHelper();

        if ( $file_helper->llmstxt_file_exists() ) {
            return;
        }

        $llms_txt_parser = new LlmsTxtParser( new LlmsTxtSummaryProvider() );
        $generator       = new LlmsTxtGenerator( $plugin_settings, $file_helper, $llms_txt_parser );
        $generator->generate();
    }

    /**
     * Saves installation state.
     *
     * @return void
     */
    public static function update_installation_state_on_activation(): void {
        $installation_state = get_option( self::INSTALLATION_OPTION_NAME, false );

        if ( $installation_state !== 'old' ) {
            add_option( self::INSTALLATION_OPTION_NAME, 'new' );
        }
    }
}
