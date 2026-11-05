<?php
/**
 * MCN Core module: Admin user tools
 * Extra columns and sorting on the Users screen, and the patient ID download button.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;




// Sorting of users 
add_action('pre_get_users', 'medcards_sort_users_by_registration_date');
function medcards_sort_users_by_registration_date($query)
{

    global $pagenow;

    // Only affect the Users admin screen
    if (
        is_admin() &&
        $pagenow === 'users.php' &&
        !isset($_GET['orderby']) // Don't override manual sorting
    ) {
        $query->set('orderby', 'registered');
        $query->set('order', 'DESC');
    }
}


// download button
add_action('acf/render_field/name=patients_id', 'medcards_add_download_link_to_acf_file_field');

function medcards_add_download_link_to_acf_file_field($field)
{

    if (empty($field['value'])) {
        return;
    }

    $attachment_id = $field['value'];

    // Handle ACF returning an array
    if (is_array($attachment_id) && isset($attachment_id['ID'])) {
        $attachment_id = $attachment_id['ID'];
    }

    $file_url = wp_get_attachment_url($attachment_id);

    if ($file_url) {
        echo '<p style="margin-top:10px;">
            <a href="' . esc_url($file_url) . '" class="button button-primary" download target="_blank">
                Download Patient ID
            </a>
        </p>';
    }
}


// Phone and Date - Time
/* ==============================
   Med Cards Now - Users Admin Columns
   Phone + Registration Date
   ============================== */

/* Add new columns */
add_filter('manage_users_columns', function ($columns) {

    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'email') {
            $new_columns['phone_number'] = 'Phone #';
            $new_columns['registration_date'] = 'Registration Date';
        }
    }

    return $new_columns;
});


/* Show column values */
add_filter('manage_users_custom_column', function ($value, $column_name, $user_id) {

    if ($column_name === 'phone_number') {

        // Change this meta key if your phone field key is different
        $phone = get_user_meta($user_id, 'phone', true);

        if (!$phone) {
            $phone = get_user_meta($user_id, 'phone_number', true);
        }

        if (!$phone) {
            $phone = get_user_meta($user_id, 'billing_phone', true);
        }

        return $phone ? esc_html($phone) : '—';
    }

    if ($column_name === 'registration_date') {
        $user = get_userdata($user_id);

        if (!empty($user->user_registered)) {
            return date_i18n('M d, Y', strtotime($user->user_registered));
        }

        return '—';
    }

    return $value;
}, 10, 3);


/* Make registration date sortable */
add_filter('manage_users_sortable_columns', function ($columns) {
    $columns['registration_date'] = 'registered';
    return $columns;
});
