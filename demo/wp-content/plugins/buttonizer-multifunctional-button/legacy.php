<?php

// Fixes bug with multiple buttonizer installs
use Buttonizer\Core\Utils\Settings;
if ( !defined( 'BUTTONIZER_DEFINED' ) ) {
    /*
     * License setup
     */
    $oButtonizer = new Buttonizer\Legacy\Licensing\License();
    $oButtonizer->init();
    if ( !function_exists( "ButtonizerLicense" ) ) {
        function ButtonizerLicense() {
            global $oButtonizer;
            return $oButtonizer->get();
        }

    }
    // Define legacy data.
    // Defined after the license setup above on purpose: BUTTONIZER_LEGACY_REQUESTED_MIGRATION depends on
    // ButtonizerLicense() being initialized. In the free build we force it false so the user always lands on
    // the migration flow instead of the legacy editor (which is stripped from that build).
    define( "BUTTONIZER_LEGACY", true );
    // This should be false to show migration
    define( "BUTTONIZER_LEGACY_REQUESTED_MIGRATION", false );
    /*
     * Installation, removing and initiallization
     */
    $oButtonizerMaintain = new Buttonizer\Legacy\Utils\Maintain(true);
    /*
     * Buttonizer Admin Dashboard
     */
    if ( is_admin() ) {
        // Load Admin page
        new Buttonizer\Legacy\Admin\Admin();
    }
    /**
     * Create Buttonizer API endpoints
     */
    add_action( 'rest_api_init', function () {
        new Buttonizer\Legacy\Api\Api();
    } );
    /**
     * Frontend
     */
    new Buttonizer\Legacy\Frontend\Ajax();
    /* LAST FEW FUNCTIONS */
    if ( !function_exists( "buttonizer_custom_connect_message" ) ) {
        function buttonizer_custom_connect_message(
            $message,
            $user_first_name,
            $plugin_title,
            $user_login,
            $site_link,
            $freemius_link
        ) {
            return sprintf(
                __( 'Hey %1$s', 'buttonizer-multifunctional-button' ) . '!<br><br>
                <p>Thank you for trying out our plugin!</p><br>
                <p>Our goal is to provide you excellent support and make the Plugin better and more secure. We do that by tracking how our users are using the plugin, learning why they abandon it, which environments we need to continue supporting, etc. Those valuable data points are key to making data-driven decisions and lead to better UX (user experience), new features, better documentation and other good things.</p><br>
                <p>Click on Allow and Continue (blue button) so that we can learn how to improve our plugin and help you better when you have support issues.</p><br>
                <p>You can always use Buttonizer Free version without opting-in. Just click \'Skip\' (white button) if you don\'t want to opt-in.</p><br>
                <p>Click on the link below (<a href="https://community.buttonizer.pro/knowledgebase/58" target="_blank">or click here</a>) to have a quick overview what gets tracked.</p><br>
                <p>Much Buttonizing fun,<br />
                <b>Team Buttonizer</b></p>',
                $user_first_name,
                '<b>' . $plugin_title . '</b>',
                '<b>' . $user_login . '</b>',
                $site_link,
                $freemius_link
            );
        }

        if ( $oButtonizer->get() ) {
            $oButtonizer->get()->add_filter(
                'connect_message',
                'buttonizer_custom_connect_message',
                10,
                6
            );
        }
    }
    // The Freemius object is null in the free build (SDK is stripped), so guard every ->get() call.
    if ( $oButtonizer->get() ) {
        // Add Buttonizer Community
        $oButtonizer->get()->add_filter( 'support_forum_url', function ( $wp_org_support_url ) {
            return 'https://community.buttonizer.pro/';
        } );
        // Buttonizer icon
        $oButtonizer->get()->add_filter( 'plugin_icon', function () {
            return dirname( __FILE__ ) . '/assets/images/plugin-icon.png';
        } );
    }
    // Localization
    add_action( 'init', 'buttonizer_load_plugin_textdomain' );
    function buttonizer_load_plugin_textdomain() {
        load_plugin_textdomain( 'buttonizer-multifunctional-button', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    // System, buttonizer is loaded
    do_action( 'buttonizer_loaded' );
    // Ok, define
    define( 'BUTTONIZER_DEFINED', '1.0' );
}