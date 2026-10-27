<?php
/*
Plugin Name: Patient Verification System
Plugin URI: https://modernlisim.com/medical-patient-verification
Description: A WordPress plugin that verifies patient details through a unique verification code. It provides a frontend form for code input and displays relevant patient information such as doctor name, verification status, and expiry details.
Version: 1.2
Requires PHP: 8.2
Requires at least: 6.7.1
Tested up to: 6.7.1
Author: Modernlisim
Author URI: https://www.modernlisim.com
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Support URI: https://www.modernlisim.com/support
*/

// Changelog
//  1.0.0 = Initial Version with automated verificaiton code generation
//  1.1 = manual verification code upon client request. improved search removed id column. improved new record form to keep data incase of error.
//  1.2 = Edit Functionality


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
include_once plugin_dir_path(__FILE__) . 'backend.php';



// Register activation hook to create the database table
register_activation_hook(__FILE__, 'mpv_create_database');

function mpv_create_database()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_verifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_name VARCHAR(255) NOT NULL,
        doctor_name VARCHAR(255) NOT NULL,
        verification_code CHAR(7) NOT NULL UNIQUE,
        expiry_date DATE NOT NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register the shortcode
add_shortcode('patient_verification', 'mpv_render_verification_form');

function mpv_render_verification_form()
{
    ob_start();
    ?>
    <div class="verification-container">
        <p class="ver-heading">Patient Verification System</p>
        <div class="ver-form">
            <form id="patient-verification-form" action="javascript:void(0);">
                <input class="ver-code" type="number" placeholder="Please enter your verification number" required>
                <button class="ver-submit" type="submit">Verify</button>
            </form>
        </div>
        <div class="ver-output-error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                    stroke="#E40000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M4.93018 4.92999L19.0702 19.07" stroke="#E40000" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
            <p class="ver-error">Oops! We couldn't find that record.</p>
        </div>
        <div class="ver-out-success">
            <div class="success-msg">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.709 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4881 2.02168 11.3363C2.16356 9.18455 2.99721 7.13631 4.39828 5.49706C5.79935 3.85781 7.69279 2.71537 9.79619 2.24013C11.8996 1.7649 14.1003 1.98232 16.07 2.85999"
                        stroke="#22CD00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M22 4L12 14.01L9 11.01" stroke="#22CD00" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
                <p class="success-alert">Patient verification data has matched.</p>
            </div>
            <table class="ver-table">
                <tr class="ver-row1">
                    <th>Patient Name</th>
                    <th>Doctor Name</th>
                    <th>Verification Code</th>
                    <th>Expiry Date</th>
                    <th>Valid</th> <!-- New Valid column -->
                </tr>
                <tr class="ver-row2">
                    <td id="patient-name">-</td>
                    <td id="doctor-name">-</td>
                    <td id="verification-code">-</td>
                    <td id="expiry-date">-</td>
                    <td id="valid-status">-</td> <!-- Placeholder for validity -->
                </tr>
            </table>
        </div>

    </div>
    <?php
    return ob_get_clean();
}

// Enqueue styles and scripts
add_action('wp_enqueue_scripts', 'mpv_enqueue_assets');
function mpv_enqueue_assets()
{
    wp_enqueue_style('mpv-styles', plugins_url('style.css', __FILE__));
    wp_enqueue_script('mpv-scripts', plugins_url('frontend.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('mpv-scripts', 'mpv_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}

// AJAX handler
add_action('wp_ajax_mpv_verify_code', 'mpv_verify_code');
add_action('wp_ajax_nopriv_mpv_verify_code', 'mpv_verify_code');

function mpv_verify_code()
{
   global $wpdb;
    $code = sanitize_text_field($_POST['code']);
    $table_name = $wpdb->prefix . 'patient_verifications';

    // Fetch the record
    $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE verification_code = %s", $code));

    if ($result) {
        // --- FIX: Format the Expiry Date to MM-DD-YYYY (e.g., 10-14-2026) ---
        // 'm' is month number, 'd' is day of the month, 'Y' is four digit year
        $formatted_expiry_date = date('m-d-Y', strtotime($result->expiry_date)); 
        $is_expired = (strtotime($result->expiry_date) < current_time('timestamp'));

        wp_send_json_success(array(
            'patient_name' => $result->patient_name,
            'doctor_name' => $result->doctor_name,
            'verification_code' => $result->verification_code,
            'expiry_date' => $formatted_expiry_date, // <- NOW SENDING MM-DD-YYYY
            'is_expired' => $is_expired,
        ));

    } else {
        wp_send_json_error(array('message' => "Verification code not found."));
    }

    wp_die();
}