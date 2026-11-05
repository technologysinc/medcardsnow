<?php
/**
 * MCN Core module: California order totals
 * Works out the final California total before and after Form 2 is submitted.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;





/* =====================================================
   GRAVITY FORMS FIELD SETUP

   Existing:
   Field 11 = Choose Plan radio field
   Field 51 = Payment Amount hidden field
   Field 54 = Add a Hard Copy radio field
   Field 58 = Legal Support Letter hidden field

   New:
   Field 61 = California ID Card hidden field
       Default Value: no

   Field 63 = California Years Plan hidden field
       Default Value: 1_year

   HTML field after Choose Plan:
       [medcards_california_year_plan_cards]

   HTML field after Hard Copy / before payment:
       [medcards_california_id_card_cards]

   HTML field for legal letters:
       [medcards_california_legal_letter_cards]

   HTML field IDs can be anything. The code only depends on fields:
   11, 51, 54, 58, 61, 63

   ===================================================== */


/* =====================================================

IMPORTANT:
Replace this:

REPLACE_WITH_MEDCARDSNOW_LOGO_URL

with your uploaded Med Cards Now logo image URL.

Example:
https://yourwebsite.com/wp-content/uploads/2026/05/medcardsnow-logo.png

===================================================== */




/* =====================================================
   FINAL CALIFORNIA FULL TOTAL + PLAN META OVERRIDE
   California only.

   payment_amount =
   year-adjusted base plan
   + hard copy
   + legal letters
   + ID card

   payment_plan_ =
   readable full plan string:
   Base plan + Hard Copy + Letters + ID Card + Year Plan
   ===================================================== */

add_filter('gform_pre_submission_2', 'medcards_final_ca_force_full_payment_total_before_submit', 99999);
function medcards_final_ca_force_full_payment_total_before_submit($form)
{

    $result = medcards_final_ca_get_full_total_and_plan_summary(null);

    if (!empty($result) && isset($result['total'])) {
        $_POST['input_51'] = number_format((float) $result['total'], 2, '.', '');
        $_POST['input_58'] = $result['legal_values_string'];
        $_POST['input_61'] = $result['id_card_value'];
    }

    return $form;
}


add_action('gform_after_submission_2', 'medcards_final_ca_save_full_payment_total_meta_after_submit', 99999, 2);
function medcards_final_ca_save_full_payment_total_meta_after_submit($entry, $form)
{

    $result = medcards_final_ca_get_full_total_and_plan_summary($entry);

    if (empty($result) || !isset($result['total'])) {
        return;
    }

    $user_id = get_current_user_id();

    if (!$user_id) {
        return;
    }

    /*
     * Main ACF/user meta fields from your screenshot.
     */
    update_user_meta($user_id, 'payment_amount', number_format((float) $result['total'], 2, '.', '')); // final numeric total only
    update_user_meta($user_id, 'payment_plan_', $result['payment_plan_string']);

    error_log(print_r([
        'payment_plan_'      => get_user_meta($user_id, 'payment_plan_', true),
        'payment_amount'     => get_user_meta($user_id, 'payment_amount', true),
        'selected_plan_addons' => get_user_meta($user_id, 'selected_plan_addons', true),
    ], true));

    /*
     * Breakdown meta.
     */
    update_user_meta($user_id, 'payment_single_year_plan_price', number_format((float) $result['single_year_base_price'], 2, '.', ''));
    update_user_meta($user_id, 'payment_plan_price', number_format((float) $result['year_adjusted_base_price'], 2, '.', ''));

    update_user_meta($user_id, 'california_hard_copy', $result['hard_copy_value']);
    update_user_meta($user_id, 'california_hard_copy_price', number_format((float) $result['hard_copy_price'], 2, '.', ''));

    update_user_meta($user_id, 'california_id_card', $result['id_card_value']);
    update_user_meta($user_id, 'california_id_card_price', number_format((float) $result['id_card_price'], 2, '.', ''));

    update_user_meta($user_id, 'california_legal_support_letter', !empty($result['legal_titles']) ? implode(' | ', $result['legal_titles']) : 'none');
    update_user_meta($user_id, 'california_legal_support_letter_value', $result['legal_values_string']);
    update_user_meta($user_id, 'california_legal_support_letter_price', number_format((float) $result['legal_total'], 2, '.', ''));

    update_user_meta($user_id, 'california_year_plan', $result['year_plan']);
    update_user_meta($user_id, 'california_year_plan_title', $result['year_title']);
    update_user_meta($user_id, 'california_year_plan_years', $result['year_count']);
    update_user_meta($user_id, 'california_year_plan_discount_percent', number_format((float) $result['year_discount_percent'], 2, '.', ''));
    update_user_meta($user_id, 'california_year_plan_original_price', number_format((float) $result['year_original_price'], 2, '.', ''));
    update_user_meta($user_id, 'california_year_plan_discount_amount', number_format((float) $result['year_discount_amount'], 2, '.', ''));

    update_user_meta($user_id, 'selected_plan_addons', $result['addons_string']);

    /*
     * Force GF hidden payment field value too.
     */
    $_POST['input_51'] = number_format((float) $result['total'], 2, '.', '');
    $_POST['input_58'] = $result['legal_values_string'];
    $_POST['input_61'] = $result['id_card_value'];
}


// PRIVACY: this dumps every patient answer (name, DOB, phone, address, medical
// history) into debug.log. It is OFF by default. To use it briefly while
// debugging, add  define('MCN_DEBUG_ENTRY_DUMP', true);  to wp-config.php,
// then remove that line again and delete the log.
if (defined('MCN_DEBUG_ENTRY_DUMP') && MCN_DEBUG_ENTRY_DUMP) {
    add_action('gform_after_submission_2', 'medcards_debug_gf_entry_fields', 5, 2);
}
function medcards_debug_gf_entry_fields($entry, $form) {
    error_log('=== GF FORM 2 ENTRY DEBUG ===');
    error_log('Entry ID: ' . rgar($entry, 'id'));
    
    // Log all field values
    foreach ($form['fields'] as $field) {
        $field_id = $field->id;
        $value = rgar($entry, (string) $field_id);
        
        // Handle address field sub-inputs
        if ($field->type === 'address') {
            error_log("Field {$field_id} (address): " . print_r(array(
                $field_id . '.1' => rgar($entry, $field_id . '.1'),
                $field_id . '.2' => rgar($entry, $field_id . '.2'),
                $field_id . '.3' => rgar($entry, $field_id . '.3'),
                $field_id . '.4' => rgar($entry, $field_id . '.4'),
                $field_id . '.5' => rgar($entry, $field_id . '.5'),
                $field_id . '.6' => rgar($entry, $field_id . '.6'),
            ), true));
        } else {
            if (!empty($value)) {
                error_log("Field {$field_id} ({$field->type}, label: {$field->label}): " . print_r($value, true));
            }
        }
    }
    error_log('=== END GF FORM 2 ENTRY DEBUG ===');
}




function medcards_final_ca_get_full_total_and_plan_summary($entry = null)
{

    if (!is_user_logged_in()) {
        return array();
    }

    $user_id = get_current_user_id();
    $state   = mcn_get_pricing_state($user_id);

    if ($state !== 'California') {
        return array();
    }

    $selected_plan = medcards_final_ca_read_value($entry, '11', 'input_11');
    $hard_copy     = medcards_final_ca_read_value($entry, '54', 'input_54');
    $id_card       = medcards_final_ca_read_value($entry, '61', 'input_61');
    $year_plan     = medcards_final_ca_read_value($entry, '63', 'input_63');

    if (!$year_plan) {
        $year_plan = '1_year';
    }

    $pricing = medcards_get_state_pricing();

    if (!$selected_plan || empty($pricing['California'][$selected_plan])) {
        return array();
    }

    $plan_titles = array(
        'new_pdf_id_card'   => 'Instant PDF + Patient ID Card', 
        'caregiver_new'     => 'Caregiver + New Patient',
        'growers_license'   => 'License to Cultivate up to 99 Plants',
        'new_pdf'           => 'New Patient — PDF Only',
        'renewal_hard_copy' => 'Existing patient renewal — With Hard Copy',
    );

    $base_title = isset($plan_titles[$selected_plan]) ? $plan_titles[$selected_plan] : $selected_plan;

    /*
     * BASE PLAN WITH YEAR LOGIC
     */
    $single_year_base_price = (float) $pricing['California'][$selected_plan];
    $year_data = medcards_calculate_year_plan_price($single_year_base_price, $year_plan);

    $year_adjusted_base_price = (float) $year_data['final_price'];
    $total = $year_adjusted_base_price;

    $year_title = isset($year_data['title']) ? $year_data['title'] : '1 Year';
    $year_count = isset($year_data['years']) ? (int) $year_data['years'] : 1;
    $year_discount_percent = isset($year_data['discount']) ? (float) $year_data['discount'] : 0;
    $year_original_price = isset($year_data['original_price']) ? (float) $year_data['original_price'] : $single_year_base_price;
    $year_discount_amount = isset($year_data['discount_amount']) ? (float) $year_data['discount_amount'] : 0;

    /*
     * HARD COPY
     */
    $hard_copy_price = 0;
    $hard_copy_value = 'no';

    if (medcards_plan_already_has_hard_copy($selected_plan)) {
        $hard_copy_value = 'included';
    } elseif ($hard_copy === 'yes') {
        $hard_copy_value = 'yes';
        $hard_copy_price = 10;
        $total += $hard_copy_price;
    }

    /*
     * LEGAL LETTERS
     */
    $legal_values = medcards_final_ca_read_legal_values($entry);
    $legal_letters = medcards_get_california_legal_letters();

    $legal_titles = array();
    $legal_total = 0;

    foreach ($legal_values as $value) {
        if ($value !== 'none' && isset($legal_letters[$value])) {
            $legal_titles[] = $legal_letters[$value]['title'];
            $legal_total += (float) $legal_letters[$value]['price'];
        }
    }

    if ($legal_total > 0) {
        $total += $legal_total;
    }

    $legal_values_string = !empty($legal_values) ? implode(',', $legal_values) : 'none';

    /*
     * ID CARD
     */
    $id_card_price = 0;
    $id_card_value = 'no';

    if (medcards_plan_already_has_id_card($selected_plan)) {
        $id_card_value = 'included';
    } elseif ($id_card === 'yes') {
        $id_card_value = 'yes';
        $id_card_price = 16;
        $total += $id_card_price;
    }

    /*
     * READABLE PAYMENT PLAN STRING
     * Saved to ACF/user meta key: payment_plan_
     *
     * Important:
     * Do NOT include "Base Plan:".
     * Do NOT include "Total:" here.
     * Total belongs only in payment_amount.
     */
    $parts = array();

    /*
     * Clean plan/package name.
     */
    $parts[] = $base_title;

    /*
     * Year plan label.
     */
    if ($year_discount_percent > 0) {
        $parts[] = $year_title . ' Plan - Save ' . number_format($year_discount_percent, 0) . '%';
    } else {
        $parts[] = $year_title . ' Plan';
    }

    /*
     * Add-ons.
     */
    if ($hard_copy_value === 'included') {
        $parts[] = 'Hard Copy Included';
    } elseif ($hard_copy_price > 0) {
        $parts[] = 'Hard Copy +$' . number_format($hard_copy_price, 2);
    }

    if (!empty($legal_titles)) {
        $parts[] = 'Letters: ' . implode(', ', $legal_titles) . ' +$' . number_format($legal_total, 2);
    }

    if ($id_card_value === 'included') {
        $parts[] = 'ID Card Included';
    } elseif ($id_card_price > 0) {
        $parts[] = 'ID Card +$' . number_format($id_card_price, 2);
    }

    $payment_plan_string = implode(' + ', $parts);

    $addons = array();

    if ($hard_copy_value === 'included') {
        $addons[] = 'Hard Copy Included';
    } elseif ($hard_copy_price > 0) {
        $addons[] = 'Hard Copy +$' . number_format($hard_copy_price, 2);
    }

    if (!empty($legal_titles)) {
        $addons[] = 'Letters: ' . implode(', ', $legal_titles) . ' +$' . number_format($legal_total, 2);
    }

    if ($id_card_value === 'included') {
        $addons[] = 'ID Card Included';
    } elseif ($id_card_price > 0) {
        $addons[] = 'ID Card +$' . number_format($id_card_price, 2);
    }

    if ($year_discount_percent > 0) {
        $addons[] = $year_title . ' Plan - Save ' . number_format($year_discount_percent, 0) . '%';
    } else {
        $addons[] = $year_title . ' Plan';
    }

    return array(
        'selected_plan'             => $selected_plan,
        'base_title'                => $base_title,
        'single_year_base_price'    => $single_year_base_price,
        'year_adjusted_base_price'  => $year_adjusted_base_price,
        'year_plan'                 => $year_plan,
        'year_title'                => $year_title,
        'year_count'                => $year_count,
        'year_discount_percent'     => $year_discount_percent,
        'year_original_price'       => $year_original_price,
        'year_discount_amount'      => $year_discount_amount,
        'hard_copy_value'           => $hard_copy_value,
        'hard_copy_price'           => $hard_copy_price,
        'legal_values'              => $legal_values,
        'legal_values_string'       => $legal_values_string,
        'legal_titles'              => $legal_titles,
        'legal_total'               => $legal_total,
        'id_card_value'             => $id_card_value,
        'id_card_price'             => $id_card_price,
        'total'                     => $total,
        'payment_plan_string'       => $payment_plan_string,
        'addons_string'             => !empty($addons) ? implode(' | ', $addons) : 'No add-ons selected',
    );
}

function medcards_final_ca_read_value($entry, $entry_key, $post_key)
{

    $value = '';

    if (!empty($entry) && function_exists('rgar')) {
        $value = rgar($entry, $entry_key);
    }

    if (($value === '' || $value === null) && function_exists('rgpost')) {
        $value = rgpost($post_key);
    }

    if (($value === '' || $value === null) && isset($_POST[$post_key])) {
        $value = $_POST[$post_key];
    }

    if (is_array($value)) {
        $value = reset($value);
    }

    return sanitize_text_field((string) $value);
}

function medcards_final_ca_read_legal_values($entry = null)
{

    $raw_values = array();

    /*
     * Entry field 58.
     */
    if (!empty($entry) && function_exists('rgar')) {
        $entry_value = rgar($entry, '58');

        if (!empty($entry_value) && $entry_value !== 'none') {
            $raw_values[] = $entry_value;
        }
    }

    /*
     * POST hidden field 58.
     */
    if (function_exists('rgpost')) {
        $post_value = rgpost('input_58');

        if (!empty($post_value) && $post_value !== 'none') {
            $raw_values[] = $post_value;
        }
    }

    if (isset($_POST['input_58']) && $_POST['input_58'] !== 'none') {
        $raw_values[] = $_POST['input_58'];
    }

    /*
     * UI fallback.
     */
    
    $live_legal = '';

    $allowed_legal_letters = array(
        'parole',
        'employer',
        'driving',
        'possession_person',
    );
    
    // Read the payment form's submitted legal-letter values.
    $raw_legal = array();
    
    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $posted = wp_unslash($_POST['ca_legal_support_letter_ui']);
    
        $raw_legal = is_array($posted)
            ? $posted
            : explode(',', (string) $posted);
    
    } elseif (isset($_POST['live_legal'])) {
        $posted = wp_unslash($_POST['live_legal']);
    
        $raw_legal = is_array($posted)
            ? $posted
            : explode(',', (string) $posted);
    
    } elseif (isset($_POST['input_58'])) {
        $posted = wp_unslash($_POST['input_58']);
    
        $raw_legal = is_array($posted)
            ? $posted
            : explode(',', (string) $posted);
    }
    
    $valid_legal = array();
    
    foreach ($raw_legal as $value) {
        $value = sanitize_key(trim((string) $value));
    
        if (in_array($value, $allowed_legal_letters, true)) {
            $valid_legal[] = $value;
        }
    }
    
    $valid_legal = array_values(array_unique($valid_legal));
    $live_legal = implode(',', $valid_legal);

    /*
     * Backup hidden field injected by JS on every page.
     * This fixes Gravity Forms multi-page issue where custom HTML selections
     * may not be present on the final submit page.
     */
    if (isset($_POST['medcards_legal_letters_backup']) && $_POST['medcards_legal_letters_backup'] !== 'none') {
        $raw_values[] = $_POST['medcards_legal_letters_backup'];
    }

    $values = array();

    foreach ($raw_values as $raw) {
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $values[] = $item;
            }
        } else {
            foreach (explode(',', (string) $raw) as $item) {
                $values[] = $item;
            }
        }
    }

    $legal_letters = medcards_get_california_legal_letters();
    $clean = array();

    foreach ($values as $value) {
        $value = sanitize_text_field(trim((string) $value));

        if ($value && $value !== 'none' && isset($legal_letters[$value])) {
            $clean[] = $value;
        }
    }

    return array_values(array_unique($clean));
}
