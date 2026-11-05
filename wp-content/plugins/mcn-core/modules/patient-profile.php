<?php
/**
 * MCN Core module: Patient profile fields
 * Pre-fills Gravity Forms fields from the patient account and saves the birthdate from Form 2.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;


/* ---------------------------------------------------------------------
 * Permanently save Birthdate (GF field 32 -> user meta 'date_of_birth')
 * on every Form 2 submission. Runs as PHP at top level (NOT inside any
 * <script> block). Signature save stays in its own existing function.
 * ------------------------------------------------------------------- */
if (!function_exists('mcn_save_dob_to_user_meta_after_submit')) {

    add_action('gform_after_submission_2', 'mcn_save_dob_to_user_meta_after_submit', 10, 2);

    function mcn_save_dob_to_user_meta_after_submit($entry, $form)
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // GF Date field 32 can arrive whole ("MM/DD/YYYY") or split (32.1/.2/.3).
        $dob = rgar($entry, '32');

        if (empty($dob)) {
            $m = rgar($entry, '32.1'); // month
            $d = rgar($entry, '32.2'); // day
            $y = rgar($entry, '32.3'); // year

            if (!empty($m) && !empty($d) && !empty($y)) {
                $dob = sprintf('%02d/%02d/%04d', (int) $m, (int) $d, (int) $y);
            }
        }

        if (empty($dob)) {
            return; // nothing submitted this time - keep whatever is stored
        }

        $dob = sanitize_text_field($dob);

        update_user_meta($user_id, 'date_of_birth', $dob);

        if (function_exists('update_field')) {
            update_field('date_of_birth', $dob, 'user_' . $user_id);
        }
    }
}


// Dynamically populate user meta 
add_filter('gform_field_value_first_name', 'populate_user_meta_first_name');
function populate_user_meta_first_name($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'first_name', true) : $value;
}

add_filter('gform_field_value_last_name', 'populate_user_meta_last_name');
function populate_user_meta_last_name($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'last_name', true) : $value;
}

add_filter('gform_field_value_phone', 'populate_user_meta_phone');
function populate_user_meta_phone($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'phone', true) : $value;
}

add_filter('gform_field_value_date_of_birth', 'populate_user_meta_dob');
function populate_user_meta_dob($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'date_of_birth', true) : $value;
}

add_filter('gform_field_value_address_line_1', 'populate_user_meta_address1');
function populate_user_meta_address1($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'address_line_1', true) : $value;
}

add_filter('gform_field_value_address_line_2', 'populate_user_meta_address2');
function populate_user_meta_address2($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'address_line_2', true) : $value;
}

add_filter('gform_field_value_city', 'populate_user_meta_city');
function populate_user_meta_city($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'city', true) : $value;
}

add_filter('gform_field_value_state', 'populate_user_meta_state');
function populate_user_meta_state($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'state', true) : $value;
}

add_filter('gform_field_value_zip_code', 'populate_user_meta_zip');
function populate_user_meta_zip($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'zip_code', true) : $value;
}

add_filter('gform_field_value_country', 'populate_user_meta_country');
function populate_user_meta_country($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'country', true) : $value;
}


add_filter('gform_field_value_payment_plan', 'populate_user_meta_plan');
function populate_user_meta_plan($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'payment_plan_', true) : $value;
}

add_filter('gform_field_value_payment_amount', 'populate_user_meta_payment_amount');
function populate_user_meta_payment_amount($value)
{
    return is_user_logged_in() ? get_user_meta(get_current_user_id(), 'payment_amount', true) : $value;
}
