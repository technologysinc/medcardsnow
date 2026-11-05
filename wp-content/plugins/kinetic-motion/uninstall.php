<?php
/**
 * Remove the plugin's settings when it is deleted from the Plugins screen.
 * Animation settings saved inside Elementor pages are left alone (they are part of your designs).
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'kmo_settings' );
