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
namespace Buttonizer\Legacy\Utils;

use Buttonizer\Core\Utils\PermissionCheck;
use Buttonizer\Core\Utils\Settings;
# No script kiddies
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
class Maintain {
    // Construct
    public function __construct( $ready = false ) {
        if ( !$ready ) {
            return;
        }
        register_activation_hook( 'buttonizer', array(&$this, 'pluginActivate') );
        register_deactivation_hook( 'buttonizer', array(&$this, 'pluginDeactivate') );
        add_action(
            'upgrader_process_complete',
            array(&$this, 'pluginUpdated'),
            10,
            2
        );
        add_action( 'admin_bar_menu', array(&$this, 'wordpress_admin_bar'), 100 );
    }

    /**
     * Activate Buttonizer, AWESOMAAH!
     */
    public function pluginActivate() {
        // Check updated data
        $this->pluginUpdated();
    }

    /**
     * Deactivate plugin, SEE YOU SOON!
     */
    public function pluginDeactivate() {
        // Nothing to handle right now. Maybe later
    }

    /**
     * Updated?
     */
    public function pluginUpdated() {
        if ( !Settings::isset( "migration_version" ) ) {
            ( new Update() )->run();
        } else {
            if ( Settings::getSetting( 'migration_version', BUTTONIZER_LAST_MIGRATION ) !== BUTTONIZER_LAST_MIGRATION ) {
                ( new Update() )->selfMigrate( Settings::getSetting( 'migration_version' ) );
            }
        }
    }

    /**
     * Add Buttonizer to the admin bar
     * @param $admin_bar
     */
    public function wordpress_admin_bar( $admin_bar ) {
        // Only show to admins and when enabled
        if ( PermissionCheck::hasPermission() ) {
            $showTopBar = Settings::getSetting( 'admin_top_bar_show_button', true );
            if ( $showTopBar && filter_var( $showTopBar, FILTER_VALIDATE_BOOLEAN, [
                'options' => [
                    'default' => false,
                ],
            ] ) === true ) {
                $admin_bar->add_menu( array(
                    'id'    => 'buttonizer',
                    'title' => '<img src="' . plugins_url( '/assets/images/wp-icon.png', BUTTONIZER_PLUGIN_DIR ) . '" style="vertical-align: text-bottom; opacity: 0.7; display: inline-block;" />',
                    'href'  => admin_url() . 'admin.php?page=Buttonizer',
                    'meta'  => [],
                ) );
                // Buttonizer buttons
                $admin_bar->add_menu( array(
                    'id'     => 'buttonizer_buttons',
                    'parent' => 'buttonizer',
                    'title'  => __( 'Manage buttons', 'buttonizer-multifunctional-button' ),
                    'href'   => admin_url() . 'admin.php?page=Buttonizer',
                    'meta'   => array(),
                ) );
                // Settings
                $admin_bar->add_menu( array(
                    'id'     => 'buttonizer_knowledgebase',
                    'parent' => 'buttonizer',
                    'title'  => __( 'Knowledge base', 'buttonizer-multifunctional-button' ),
                    'href'   => "https://community.buttonizer.pro/knowledgebase",
                    'meta'   => [
                        "target" => "_blank",
                        "title"  => __( 'Find out everything you need to know about Buttonizer', 'buttonizer-multifunctional-button' ),
                    ],
                ) );
            }
        }
    }

    /**
     * Get WordPress timezone
     */
    public static function getTimezone() {
        $timezone_string = get_option( 'timezone_string' );
        if ( !empty( $timezone_string ) ) {
            return $timezone_string;
        }
        $offset = get_option( 'gmt_offset' );
        $hours = (int) $offset;
        $minutes = ($offset - floor( $offset )) * 60;
        $offset = sprintf( '%+03d:%02d', $hours, $minutes );
        return $offset;
    }

    /**
     * Generate a uuid unique id
     */
    public static function GenerateUniqueId() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xfff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff )
        );
    }

}
