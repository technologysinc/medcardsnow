<?php

/*
 * SOFTWARE LICENSE INFORMATION
 *
 * Copyright (c) 2017 Buttonizer, all rights reserved.
 *
 * This file is part of Buttonizer
 *
 * For detailed information regarding to the licensing of
 * this software, please review the license.txt or visit:
 * https://buttonizer.pro/license/
 */
namespace Buttonizer\Legacy\Admin;

use Buttonizer\Admin\Admin as NewAdmin;
use Buttonizer\Core\Utils\Editor;
use Buttonizer\Core\Utils\PermissionCheck;
use Buttonizer\Core\Utils\Settings;
# No script kiddies
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
class Admin {
    private static $showMigrationPage = false;

    /**
     * Admin constructor.
     */
    public function __construct() {
        // Lets do some admin stuff for Buttonizer
        add_action( 'admin_init', [$this, 'adminInit'] );
        // Add admin assets for migration
        add_action( 'admin_enqueue_scripts', [$this, 'adminAssets'] );
        // Add admin menu
        add_action( 'admin_menu', [$this, 'pluginAdminMenu'] );
        // Add deprecation notice for legacy users
        add_action( 'admin_notices', [$this, 'legacyDeprecationNotice'] );
        // Add AJAX handler for dismissing notice
        add_action( 'wp_ajax_buttonizer_dismiss_legacy_notice', [$this, 'dismissLegacyNotice'] );
    }

    /**
     * Create Admin menu
     */
    public function pluginAdminMenu() {
        if ( !PermissionCheck::hasPermission() ) {
            return;
        }
        // Admin menu - now points directly to migration page
        add_menu_page(
            'Buttonizer - Migrate to 3.0',
            'Buttonizer',
            'read',
            'Buttonizer',
            [$this, 'migrationAdminPage'],
            plugins_url( '/assets/images/wp-icon.png', BUTTONIZER_PLUGIN_DIR ),
            81
        );
        // Add community link
        add_submenu_page(
            'Buttonizer',
            __( 'Community', 'buttonizer-multifunctional-button' ),
            __( 'Community', 'buttonizer-multifunctional-button' ),
            'read',
            'https://community.buttonizer.pro/?referral=buttonizer-plugin-menu'
        );
        // Add knowledge base link
        add_submenu_page(
            'Buttonizer',
            __( 'Knowledge base', 'buttonizer-multifunctional-button' ),
            __( 'Knowledge base', 'buttonizer-multifunctional-button' ),
            'read',
            'https://community.buttonizer.pro/knowledgebase?referral=buttonizer-plugin-menu'
        );
        // Add support link
        add_submenu_page(
            'Buttonizer',
            __( 'I need support', 'buttonizer-multifunctional-button' ),
            __( 'I need support', 'buttonizer-multifunctional-button' ),
            'read',
            'https://community.buttonizer.pro/t/support?referral=buttonizer-plugin-menu'
        );
        // Plugin information, add links
        add_filter( "plugin_action_links_" . plugin_basename( BUTTONIZER_PLUGIN_DIR ), function ( $aLinks ) {
            $aButtonizerLinks = ['<a href="https://community.buttonizer.pro/" target="_blank">' . __( 'Community forums', 'buttonizer-multifunctional-button' ) . '</a>', '<a href="https://community.buttonizer.pro/knowledgebase" target="_blank">' . __( 'Knowledge base', 'buttonizer-multifunctional-button' ) . '</a>', '<a href="' . admin_url( 'admin.php?page=Buttonizer' ) . '">' . __( 'Migrate to 3.0', 'buttonizer-multifunctional-button' ) . '</a>'];
            return array_merge( $aLinks, $aButtonizerLinks );
        } );
    }

    /**
     * Show new Buttonizer admin dashboard
     */
    public function adminAssets() {
        // Make sure only admins see this page
        if ( !self::$showMigrationPage ) {
            return;
        }
        ( new NewAdmin() )->adminAssets();
    }

    /**
     * Populate admin HTML content
     */
    public function migrationAdminPage() {
        // Make sure only admins see this page
        if ( !self::$showMigrationPage ) {
            return;
        }
        ( new NewAdmin() )->page();
    }

    /**
     * Remove stylesheets when on Buttonizer page
     */
    public function adminInit() {
        // Only show migration page to admins
        if ( defined( "\\BUTTONIZER_LEGACY_REQUESTED_MIGRATION" ) && \BUTTONIZER_LEGACY_REQUESTED_MIGRATION === false && PermissionCheck::hasPermission( true ) ) {
            self::$showMigrationPage = true;
        }
    }

    /**
     * Show deprecation notice for legacy Buttonizer users
     */
    public function legacyDeprecationNotice() {
        // Only show to administrators
        if ( !PermissionCheck::hasPermission( true ) ) {
            return;
        }
        // Check if notice was dismissed and when
        $dismissedTime = get_option( 'buttonizer_legacy_notice_dismissed', 0 );
        $currentTime = time();
        $oneWeek = 7 * 24 * 60 * 60;
        // 7 days in seconds
        // If dismissed less than a week ago, don't show
        if ( $dismissedTime && $currentTime - $dismissedTime < $oneWeek ) {
            return;
        }
        $migrateUrl = admin_url( 'admin.php?page=Buttonizer&buttonizer_migrate=true' );
        $nonce = wp_create_nonce( 'buttonizer_dismiss_legacy_notice' );
        echo '
        <div id="buttonizer-legacy-notice" class="notice notice-warning is-dismissible">
            <p>
                <strong>⚠️ ' . esc_html__( 'Your Buttonizer version is outdated', 'buttonizer-multifunctional-button' ) . '</strong>
            </p>
            <p>
                ' . esc_html__( 'You\'re currently using an outdated version of Buttonizer that is not actively maintained anymore.', 'buttonizer-multifunctional-button' ) . '<br>' . esc_html__( 'As of', 'buttonizer-multifunctional-button' ) . ' <strong>' . esc_html__( 'July 2026', 'buttonizer-multifunctional-button' ) . '</strong>' . esc_html__( ', this version has limited functionality and no button editing capabilities.  Please migrate to our Buttonizer standalone version as soon as possible, and your buttons will automatically transfer.', 'buttonizer-multifunctional-button' ) . '
            </p>
            <p>
                <a href="' . esc_url( $migrateUrl ) . '" class="button button-primary">' . esc_html__( 'Migrate to 3.0', 'buttonizer-multifunctional-button' ) . '</a>
                <a href="https://community.buttonizer.pro/knowledgebase/1122-standalone-vs-wordpress" target="_blank" class="button">' . esc_html__( 'Learn more', 'buttonizer-multifunctional-button' ) . '</a>
            </p>
        </div>
        <script>
        (function() {
            var notice = document.getElementById("buttonizer-legacy-notice");
            if (notice) {
                notice.addEventListener("click", function(e) {
                    if (e.target.classList.contains("notice-dismiss")) {
                        fetch("' . admin_url( 'admin-ajax.php' ) . '", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: "action=buttonizer_dismiss_legacy_notice&nonce=' . esc_js( $nonce ) . '"
                        });
                    }
                });
            }
        })();
        </script>
        ';
    }

    /**
     * AJAX handler to dismiss legacy notice
     */
    public function dismissLegacyNotice() {
        // Verify nonce
        if ( !isset( $_POST['nonce'] ) || !wp_verify_nonce( $_POST['nonce'], 'buttonizer_dismiss_legacy_notice' ) ) {
            wp_send_json_error( 'Invalid nonce' );
            return;
        }
        // Only administrators can dismiss
        if ( !PermissionCheck::hasPermission( true ) ) {
            wp_send_json_error( 'Permission denied' );
            return;
        }
        // Store the current time when dismissed
        update_option( 'buttonizer_legacy_notice_dismissed', time() );
        wp_send_json_success();
    }

}
