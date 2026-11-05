<?php

namespace Hostinger;

use Hostinger\Admin\PluginSettings;
use Hostinger\Helper;

defined( 'ABSPATH' ) || exit;

class DefaultOptions {

    private PluginSettings $plugin_settings;

    public function __construct( PluginSettings $plugin_settings ) {
        $this->plugin_settings = $plugin_settings;
    }

    public function add_options(): void {
        $this->configure_plugin_settings();

        foreach ( $this->options() as $key => $option ) {
            update_option( $key, $option );
        }
    }

    /**
     * A later activation must never override a choice the site owner has already made.
     */
    private function configure_plugin_settings(): void {
        $is_first_setup    = ! $this->plugin_settings->has_stored_settings();
        $plugin_options    = $this->plugin_settings->get_plugin_settings();
        $needs_bypass_code = empty( $plugin_options->get_bypass_code() );

        if ( ! $is_first_setup && ! $needs_bypass_code ) {
            return;
        }

        if ( $is_first_setup ) {
            $plugin_options->set_enable_llms_txt( true );
        }

        if ( $needs_bypass_code ) {
            $plugin_options->set_bypass_code( Helper::generate_bypass_code( 16 ) );
        }

        $this->plugin_settings->save_plugin_settings( $plugin_options );
    }

    /**
     * @return string[]
     */
    private function options(): array {
        $options = array(
            'optin_monster_api_activation_redirect_disabled' => 'true',
            'wpforms_activation_redirect'                    => 'true',
            'aioseo_activation_redirect'                     => 'false',
        );

        if ( Helper::is_plugin_active( 'astra-sites' ) ) {
            $options = array_merge( $options, $this->get_astra_options() );
        }

        return $options;
    }

    /**
     * @return string[]
     */
    private function get_astra_options(): array {
        return array(
            'astra_sites_settings' => 'gutenberg',
        );
    }
}
