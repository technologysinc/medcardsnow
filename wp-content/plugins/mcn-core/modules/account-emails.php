<?php
/**
 * MCN Core module: Account email settings
 * Sender name and address, and switches off WordPress password-reset and new-user emails.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;



/**
 * =========================================================
 * 2. Global Email Configuration
 * =========================================================
 *
 * All WordPress and Gravity Forms emails should use:
 *
 * contact@medcardsnow.com
 *
 * WP Mail SMTP Force From Email should remain enabled.
 */


/**
 * Change WordPress email sender name
 */
add_filter('wp_mail_from_name', function ($name) {

    return 'Med Cards Now';

});


/**
 * Change WordPress email sender address
 */
add_filter('wp_mail_from', function ($email) {

    return 'contact@medcardsnow.com';

});


/**
 * Force Gravity Forms notification From email
 *
 * This applies to Gravity Forms notifications.
 */
add_filter('gform_notification', function ($notification, $form, $entry) {

    if (!is_array($notification)) {
        return $notification;
    }

    $notification['from'] = 'contact@medcardsnow.com';

    /**
     * Optional:
     * Set Reply-To only if required.
     *
     * Keep the existing Reply-To configuration
     * unless you specifically need to change it.
     */

    return $notification;

}, 10, 3);


// Disable Reset password email 

/* ------------------------------------------------
   Disable WordPress Password & User Emails
------------------------------------------------ */

/* Disable password reset emails */
add_filter('send_password_change_email', '__return_false');
add_filter('retrieve_password_message', '__return_empty_string');

function disable_reset_password_email()
{
    return false;
}
add_filter('send_retrieve_password_email', 'disable_reset_password_email');

/* Disable password reset functionality completely */
add_filter('allow_password_reset', '__return_false');

/* Disable new user notification email to user */
remove_action('register_new_user', 'wp_send_new_user_notifications');
remove_action('edit_user_created_user', 'wp_send_new_user_notifications', 10, 2);

add_filter('wp_new_user_notification_email', '__return_false');

/* Disable WooCommerce customer new account email */
add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');

/* Disable admin notification for new users */
add_filter('wp_send_new_user_notification_to_admin', '__return_false');

/* Disable user notification for new users */
add_filter('wp_send_new_user_notification_to_user', '__return_false');
