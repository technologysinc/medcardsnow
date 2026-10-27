<?php
/**
 * Install / Upgrade Script
 *
 * Creates (and upgrades, on version bumps) the applications/transactions
 * table on plugin activation.
 *
 * @package Medcardsnow
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Bump this whenever the table schema changes. omc_maybe_upgrade_db()
 * re-runs dbDelta automatically when this no longer matches the stored
 * option, so existing installs get upgraded without reactivating the
 * plugin (register_activation_hook only fires on activation).
 */
define('OMC_DB_VERSION', '2.0.0');

/**
 * Create / upgrade the applications table.
 *
 * One row represents one applicant's journey from "started the medical
 * form" through "paid". It is created in 'draft' status as soon as the
 * applicant reaches the review step, and is the single source of truth
 * the server uses to (a) know what the applicant actually told us and
 * (b) compute the price it is willing to charge — the client is never
 * trusted for price.
 */
function omc_create_transactions_table() {
    global $wpdb;

    $table_name       = $wpdb->prefix . 'omc_transactions';
    $charset_collate  = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        application_uid varchar(40) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        first_name varchar(100) DEFAULT '',
        last_name varchar(100) DEFAULT '',
        email varchar(100) DEFAULT '',
        phone varchar(30) DEFAULT '',
        dob varchar(10) DEFAULT '',
        gender varchar(20) DEFAULT '',
        state varchar(50) DEFAULT '',
        allergies text,
        medical_conditions text,
        other_condition text,
        telemedicine_consent tinyint(1) NOT NULL DEFAULT 0,
        signature longtext,
        plan_name varchar(100) DEFAULT '',
        duration_years tinyint(2) NOT NULL DEFAULT 1,
        add_hard_copy tinyint(1) NOT NULL DEFAULT 0,
        add_id_card tinyint(1) NOT NULL DEFAULT 0,
        coupon_code varchar(50) DEFAULT '',
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        transaction_id varchar(50) DEFAULT NULL,
        auth_code varchar(50) DEFAULT '',
        payment_method varchar(50) DEFAULT '',
        last4 varchar(4) DEFAULT '',
        customer_name varchar(150) DEFAULT '',
        customer_email varchar(100) DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'draft',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY application_uid (application_uid),
        UNIQUE KEY transaction_id (transaction_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    update_option('omc_db_version', OMC_DB_VERSION);
}
register_activation_hook(OMC_PLUGIN_DIR . 'omc-medical-card.php', 'omc_create_transactions_table');

/**
 * Re-run dbDelta whenever OMC_DB_VERSION is bumped, even without a plugin
 * reactivation (e.g. after a plain file update on an existing site).
 */
function omc_maybe_upgrade_db() {
    if (get_option('omc_db_version') !== OMC_DB_VERSION) {
        omc_create_transactions_table();
    }
}
add_action('plugins_loaded', 'omc_maybe_upgrade_db');

/**
 * Plugin activation: create table + sensible defaults.
 */
function omc_plugin_activation() {
    omc_create_transactions_table();

    if (!get_option('omc_admin_email')) {
        update_option('omc_admin_email', get_option('admin_email'));
    }

    // Default to TEST mode until an admin explicitly flips it live.
    if (get_option('omc_live_mode', null) === null) {
        update_option('omc_live_mode', '0');
    }
}
register_activation_hook(OMC_PLUGIN_DIR . 'omc-medical-card.php', 'omc_plugin_activation');

/**
 * Deactivation: keep transaction/application history, nothing to clean up.
 */
function omc_plugin_deactivation() {
    // Intentionally a no-op — order/application history must survive deactivation.
}
register_deactivation_hook(OMC_PLUGIN_DIR . 'omc-medical-card.php', 'omc_plugin_deactivation');
