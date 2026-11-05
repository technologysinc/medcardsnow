<?php
/**
 * MCN Core module: ID and signature uploads
 * Saves the uploaded ID and the signature to the patient profile.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;



// ID and signature upload to meta
// ID and signature upload to meta
add_action('gform_after_submission_2', 'save_gf_uploaded_file_to_acf_user_field', 10, 2);

function save_gf_uploaded_file_to_acf_user_field($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    // GF multiple file upload field value
    $file_value = rgar($entry, '13'); // change field ID

    if (empty($file_value)) {
        return;
    }

    $file_url = '';

    // If multiple files, GF usually stores JSON
    $decoded = json_decode($file_value, true);

    if (is_array($decoded) && !empty($decoded)) {
        $file_url = $decoded[0]; // first uploaded file
    } else {
        $file_url = $file_value; // fallback
    }

    if (empty($file_url)) {
        return;
    }

    // Try to get existing attachment ID first
    $attachment_id = attachment_url_to_postid($file_url);

    // If not already in media library, create attachment
    if (!$attachment_id) {
        $attachment_id = medcards_create_attachment_from_local_url($file_url, $user_id);
    }

    if (!$attachment_id) {
        return;
    }

    // Save normal user meta too
    update_user_meta($user_id, 'patients_id', $attachment_id);

    // Save to ACF file field
    if (function_exists('update_field')) {
        update_field('patients_id', $attachment_id, 'user_' . $user_id);
    }
}



/**
 * Create a WP attachment from a local uploaded file URL
 */
function medcards_create_attachment_from_local_url($file_url, $user_id = 0)
{

    if (empty($file_url)) {
        return 0;
    }

    $upload_dir = wp_upload_dir();

    // Only handle local uploads
    if (strpos($file_url, $upload_dir['baseurl']) !== 0) {
        return 0;
    }

    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);

    if (!file_exists($file_path)) {
        return 0;
    }

    // Refuse anything that resolves outside the uploads folder (e.g. "../../wp-config.php").
    $real_base = realpath($upload_dir['basedir']);
    $real_file = realpath($file_path);
    if (!$real_base || !$real_file || strpos($real_file, trailingslashit($real_base)) !== 0) {
        return 0;
    }
    $file_path = $real_file;

    $filetype = wp_check_filetype(basename($file_path), null);

    $attachment = array(
        'guid'           => $file_url,
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($file_path)),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_author'    => $user_id ? $user_id : get_current_user_id(),
    );

    $attachment_id = wp_insert_attachment($attachment, $file_path);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    return $attachment_id;
}


    add_action('gform_after_submission_2', 'save_gf_signature_to_acf_user_field', 20, 2);

function save_gf_signature_to_acf_user_field($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();

    // Raw entry value from the signature field.
    $raw_signature_value = rgar($entry, '14');

    if (empty($raw_signature_value)) {
        return;
    }

    $raw_signature_value = trim($raw_signature_value);

    // Build a usable signature image URL.
    $signature_url = medcards_get_signature_url_from_entry_value($raw_signature_value);

    if (empty($signature_url)) {
        return;
    }

    // Import into Media Library as a real attachment.
    $attachment_id = medcards_import_signature_to_media($signature_url, $user_id);

    if (!$attachment_id) {
        return;
    }

    // Keep your typo field name if that is the real ACF field name.
    update_user_meta($user_id, 'patinet_signature', $attachment_id);

    if (function_exists('update_field')) {
        update_field('patinet_signature', $attachment_id, 'user_' . $user_id);
    }
}

function medcards_get_signature_url_from_entry_value($value)
{

    // Case 1: already a gf-signature URL or normal URL
    if (filter_var($value, FILTER_VALIDATE_URL)) {
        // Only fetch signature images from this site, never arbitrary URLs.
        $site_host = preg_replace('/^www\./i', '', (string) wp_parse_url(home_url(), PHP_URL_HOST));
        $url_host  = preg_replace('/^www\./i', '', (string) wp_parse_url($value, PHP_URL_HOST));
        if ($site_host !== '' && strcasecmp($site_host, $url_host) === 0) {
            return $value;
        }
        return '';
    }

    // Case 2: Gravity Forms raw entry value is the filename
    if (function_exists('gf_signature')) {
        $url = gf_signature()->get_signature_url($value);
        if (!empty($url)) {
            return $url;
        }
    }

    return '';
}

function medcards_import_signature_to_media($signature_url, $user_id = 0)
{

    if (empty($signature_url)) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($signature_url);

    if (is_wp_error($tmp)) {
        error_log('GF signature download failed: ' . $tmp->get_error_message());
        return 0;
    }

    $file_array = array(
        'name'     => 'patient-signature-' . time() . '.png',
        'tmp_name' => $tmp,
    );

    $attachment_id = media_handle_sideload($file_array, 0);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        error_log('GF signature sideload failed: ' . $attachment_id->get_error_message());
        return 0;
    }

    if ($user_id) {
        wp_update_post(array(
            'ID'          => $attachment_id,
            'post_author' => $user_id,
        ));
    }

    return $attachment_id;
}
