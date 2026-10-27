<?php
/**
 * Install Script - Creates database table on plugin activation
 * 
 * @package Medcardsnow
 */

// Prevent direct access
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Create database table on plugin activation
 */
function omc_create_transactions_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'omc_transactions';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        transaction_id varchar(50) NOT NULL,
        amount decimal(10,2) NOT NULL,
        customer_name varchar(100),
        customer_email varchar(100),
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY transaction_id (transaction_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Log that table was created
    error_log('Medcardsnow: Transactions table created/verified');
}

/**
 * Register activation hook
 */
register_activation_hook(__FILE__, 'omc_create_transactions_table');

/**
 * Optional: Add sample data or default settings on activation
 */
function omc_plugin_activation() {
    // Create transactions table
    omc_create_transactions_table();
    
    // Set default admin email if not set
    if (!get_option('omc_admin_email')) {
        update_option('omc_admin_email', get_option('admin_email'));
    }
    
    // Log activation
    error_log('Medcardsnow plugin activated');
}
register_activation_hook(__FILE__, 'omc_plugin_activation');

/**
 * Optional: Cleanup on deactivation
 */
function omc_plugin_deactivation() {
    // You can optionally clear transient data here
    // But DON'T delete the transactions table - keep order history
    error_log('Medcardsnow plugin deactivated');
}
register_deactivation_hook(__FILE__, 'omc_plugin_deactivation');