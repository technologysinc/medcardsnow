<?php

namespace Hostinger\EasyOnboarding\Admin\Onboarding;

use Hostinger\EasyOnboarding\Admin\Actions;
use Hostinger\EasyOnboarding\AmplitudeEvents\Amplitude;
use Hostinger\EasyOnboarding\AmplitudeEvents\Actions as AmplitudeActions;
use Hostinger\EasyOnboarding\Helper;
use Hostinger\EasyOnboarding\Admin\Actions as Admin_Actions;
use WP_Post;

defined( 'ABSPATH' ) || exit;

class AutocompleteSteps {
    private const INITIAL_PLUGINS_OPTION_NAME = 'hostinger_easy_onboarding_initial_plugins';

    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var Onboarding
     */
    private Onboarding $onboarding;

    /**
     * @var Amplitude
     */
    private Amplitude $amplitude;

    public function __construct() {
        $this->onboarding = new Onboarding();
        $this->helper     = new Helper();
        $this->amplitude  = new Amplitude();

        add_action( 'admin_init', array( $this, 'init_onboarding' ), 0 );

        add_action( 'save_post_product', array( $this, 'new_product_creation' ), 10, 3 );
        add_action( 'woocommerce_shipping_zone_method_added', array( $this, 'shipping_zone_added' ), 10, 3 );
        add_action( 'googlesitekit_authorize_user', array( $this, 'googlesite_connected' ) );

        add_action( 'hostinger_easy_onboarding_step_completed', array( $this, 'woocommerce_steps_completed' ) );
        add_action( 'admin_init', array( $this, 'woocommerce_steps_completed' ) );

        if ( is_plugin_active( 'hostinger-affiliate-plugin/hostinger-affiliate-plugin.php' ) ) {
            add_action( 'admin_init', array( $this, 'affiliate_plugin_connected' ) );
        }

        add_action( 'admin_init', array( $this, 'domain_is_connected' ) );

        if ( $this->helper->is_store_setup_completed() ) {
            add_action( 'admin_init', array( $this, 'website_setup_completed' ) );
        }

        add_action( 'astra_sites_import_complete', array( $this, 'astra_website_import_completed' ) );

        add_action( 'admin_init', array( $this, 'check_ai_website_created' ) );

        add_action( 'admin_init', array( $this, 'check_ai_discovery_is_enabled' ) );

        add_action( 'admin_init', array( $this, 'check_plugin_was_added' ) );

        if ( is_plugin_active( 'hostinger-reach/hostinger-reach.php' ) ) {
            add_action( 'admin_init', array( $this, 'reach_plugin_connected' ) );
        }
    }

    public function init_onboarding(): void {
        $this->onboarding->init();
    }

    public function affiliate_plugin_connected(): void {
        if ( ! class_exists( '\Hostinger\AffiliatePlugin\Admin\PluginSettings' ) ) {
            return;
        }

        $action = Admin_Actions::AMAZON_AFFILIATE;

        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $settings = new \Hostinger\AffiliatePlugin\Admin\PluginSettings();

        $plugin_settings = $settings->get_plugin_settings()->to_array();

        $amazon_connection_status  = $plugin_settings['amazon_connection_status'] ?? '';
        $mercado_connection_status = $plugin_settings['mercado_connection_status'] ?? '';

        if ( $amazon_connection_status === \Hostinger\AffiliatePlugin\Admin\Options\PluginOptions::STATUS_CONNECTED || $mercado_connection_status === \Hostinger\AffiliatePlugin\Admin\Options\PluginOptions::STATUS_CONNECTED ) {
            $this->onboarding->complete_step( $category_id, $action );

            $params = array(
                'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
                'step_type' => $action,
            );

            $this->amplitude->send_event( $params );
        }
    }

    public function reach_plugin_connected(): void {
        $action      = Admin_Actions::REACH;
        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $api_key = get_option( 'hostinger_reach_api_key', null );
        if ( ! empty( $api_key ) ) {
            $this->onboarding->complete_step( $category_id, $action );

            $params = array(
                'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
                'step_type' => $action,
            );

            $this->amplitude->send_event( $params );
        }
    }

    public function woocommerce_steps_completed(): void {
        if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            return;
        }

        if ( ! $this->helper->is_woocommerce_onboarding_completed() ) {
            return;
        }

        $action = Admin_Actions::STORE_TASKS;

        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $this->onboarding->complete_step( $category_id, $action );

        $params = array(
            'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
            'step_type' => Admin_Actions::STORE_TASKS,
        );

        $this->amplitude->send_event( $params );
    }

    /**
     * @return void
     */
    public function domain_is_connected(): void {
        $action = Admin_Actions::DOMAIN_IS_CONNECTED;

        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        if ( ! $this->helper->is_free_subdomain() ) {
            if ( ! did_action( 'hostinger_domain_connected' ) ) {
                $this->onboarding->complete_step( $category_id, $action );

                $params = array(
                    'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
                    'step_type' => Admin_Actions::DOMAIN_IS_CONNECTED,
                );

                $this->amplitude->send_event( $params );

                do_action( 'hostinger_domain_connected' );
            }
        }
    }

    public function website_setup_completed(): void {
        $action      = Admin_Actions::SETUP_STORE;
        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $this->onboarding->complete_step( $category_id, $action );
    }

    /**
     * @param int    $post_id
     * @param bool   $update
     * @param string $action
     *
     * @return void
     */
    public function new_post_item_creation( int $post_id, bool $update, string $action ): void {
        $cookie_value = isset( $_COOKIE[ $action ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $action ] ) ) : '';

        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( $update && $cookie_value === $action ) {
            $this->onboarding->complete_step( $category_id, $action );
        }
    }

    /**
     * @param int     $post_id
     * @param WP_Post $post
     * @param bool    $update
     *
     * @return void
     */
    public function new_product_creation( int $post_id, WP_Post $post, bool $update ): void {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( $post->post_status !== 'publish' ) {
            return;
        }

        if ( empty( $post->post_author ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Admin_Actions::ADD_PRODUCT ) ) {
            return;
        }

        $this->onboarding->complete_step( Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Admin_Actions::ADD_PRODUCT );

        $add_product_event_sent = get_option( 'hostinger_add_product_event_sent', false );

        if ( ! empty( $add_product_event_sent ) ) {
            return;
        }

        $params = array(
            'action'    => AmplitudeActions::WOO_ITEM_COMPLETED,
            'step_type' => Admin_Actions::ADD_PRODUCT,
        );

        $this->amplitude->send_event( $params );

        update_option( 'hostinger_add_product_event_sent', true );
    }

    /**
     * @param $instance_id
     * @param $type
     * @param $zone_id
     *
     * @return void
     */
    public function shipping_zone_added( int $instance_id, string $type, int $zone_id ): void {
        if ( $this->onboarding->is_completed_successfully( Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Admin_Actions::ADD_SHIPPING ) ) {
            return;
        }

        $this->onboarding->complete_step( Onboarding::HOSTINGER_EASY_ONBOARDING_STORE_STEP_CATEGORY_ID, Admin_Actions::ADD_SHIPPING );

        $params = array(
            'action'    => AmplitudeActions::WOO_ITEM_COMPLETED,
            'step_type' => Admin_Actions::ADD_SHIPPING,
        );

        $this->amplitude->send_event( $params );
    }

    public function googlesite_connected(): void {
        $category = Onboarding::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID;

        if ( $this->onboarding->is_completed_successfully( $category, Admin_Actions::GOOGLE_KIT ) ) {
            return;
        }

        $this->onboarding->complete_step( $category, Admin_Actions::GOOGLE_KIT );

        $params = array(
            'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
            'step_type' => Admin_Actions::GOOGLE_KIT,
        );

        $this->amplitude->send_event( $params );
    }

    public function astra_website_import_completed(): void {
        $action      = Admin_Actions::AI_STEP;
        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $this->onboarding->complete_step( $category_id, $action );
    }

    public function check_ai_website_created(): void {
        $ai_created_pages = get_option( 'hostinger_ai_created_pages', array() );

        if ( empty( $ai_created_pages ) ) {
            return;
        }

        $action      = Admin_Actions::AI_STEP;
        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $this->onboarding->complete_step( $category_id, $action );

        $params = array(
            'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
            'step_type' => $action,
        );

        $this->amplitude->send_event( $params );
    }

    public function check_ai_discovery_is_enabled(): void {
        if ( ! Actions::is_enable_ai_discovery_step_eligible() ) {
            return;
        }

        $options_key     = defined( 'HOSTINGER_PLUGIN_SETTINGS_OPTION' ) ? HOSTINGER_PLUGIN_SETTINGS_OPTION : 'hostinger_tools';
        $settings        = get_option( $options_key, array() );
        $optin_mcp       = $settings['optin_mcp'] ?? false;
        $enable_llms_txt = $settings['enable_llms_txt'] ?? false;

        if ( $optin_mcp && $enable_llms_txt ) {
            $this->onboarding->complete_step( Onboarding::HOSTINGER_EASY_ONBOARDING_WEBSITE_STEP_CATEGORY_ID, Actions::ENABLE_AI_DISCOVERY );
        }
    }

    public function check_plugin_was_added(): void {
        if ( wp_doing_ajax() ) {
            return;
        }

        if ( $this->helper->is_full_screen_onboarding_pending() ) {
            return;
        }

        $action      = Admin_Actions::PLUGINS;
        $category_id = $this->find_category_from_actions( $action );

        if ( empty( $category_id ) ) {
            return;
        }

        if ( $this->onboarding->is_completed_successfully( $category_id, $action ) ) {
            return;
        }

        $current_plugins = $this->get_site_plugins();
        $initial_plugins = get_option( self::INITIAL_PLUGINS_OPTION_NAME, array() );

        if ( empty( $initial_plugins ) ) {
            update_option( self::INITIAL_PLUGINS_OPTION_NAME, $current_plugins, false );

            return;
        }

        if ( ! $this->has_plugin_been_added( $initial_plugins, $current_plugins ) ) {
            return;
        }

        $this->onboarding->complete_step( $category_id, $action );

        $params = array(
            'action'    => AmplitudeActions::ONBOARDING_ITEM_COMPLETED,
            'step_type' => $action,
        );

        $this->amplitude->send_event( $params );
    }

    /**
     * @param $action
     *
     * @return string
     */
    private function find_category_from_actions( string $action ): string {
        foreach ( Admin_Actions::get_category_action_lists() as $category => $actions ) {
            if ( in_array( $action, $actions, true ) ) {
                return $category;
            }
        }
        return '';
    }

    private function get_site_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed = array_keys( get_plugins() );

        return array(
            'installed' => $installed,
            'active'    => array_values( array_filter( $installed, 'is_plugin_active' ) ),
        );
    }

    private function has_plugin_been_added( array $initial_plugins, array $current_plugins ): bool {
        $newly_installed = array_diff( $current_plugins['installed'], $initial_plugins['installed'] );
        $newly_activated = array_diff( $current_plugins['active'], $initial_plugins['active'] );

        return ! empty( $newly_installed ) || ! empty( $newly_activated );
    }
}
