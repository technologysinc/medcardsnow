<?php
/**
 * Runs only when MCN Core is deleted from the Plugins screen.
 * Removes the plugin's own settings. Patient, payment and coupon data are untouched.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('mcn_core_disabled_modules');
delete_option('mcn_core_errors');
