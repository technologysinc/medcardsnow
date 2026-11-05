<?php
/**
 * MCN Core module: Plans and pricing
 * State price table, plan choices on Form 2, legal-letter and hard-copy rules.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;




// Load prices based on the User state 

/**
 * Med Cards Now - Full California Add-ons Code
 *
 * Form ID: 2
 *
 * Existing fields:
 * Choose Plan field ID: 11
 * Payment Amount hidden field ID: 51
 * Hard Copy radio field ID: 54
 * Legal Support Letter hidden field ID: 58
 *
 * New fields required:
 * ID Card hidden field ID: 61
 * Years Plan hidden field ID: 63
 *
 * Shortcodes to add inside Gravity Forms HTML fields:
 * [medcards_california_year_plan_cards]
 * [medcards_california_id_card_cards]
 * [medcards_california_legal_letter_cards]
 *
 * Saved user meta for checkout breakdown:
 * payment_plan_price
 * california_year_plan
 * california_year_plan_price
 * california_id_card
 * california_id_card_price
 * california_hard_copy
 * california_hard_copy_price
 * california_legal_support_letter
 * california_legal_support_letter_value
 * california_legal_support_letter_price
 * selected_plan_addons
 * payment_amount
 */


/**
 * CALIFORNIA FLOW INCLUDED:
 * 1. Choose basic plan
 * 2. Add / do not add hard copy
 *    - This is hidden automatically when selected base plan already includes hard copy
 * 3. Add legal support letter
 * 4. Add ID card
 * 5. Choose year plan
 *
 * All prices are saved separately in user meta and also added to payment_amount.
 */

/*
 * UPDATED CALIFORNIA 5-STEP LOGIC:
 * 1. Choose basic/base plan.
 * 2. Add hard copy +$10 only if selected base plan does not already include hard copy.
 * 3. Add legal support letters. Multiple letters can be selected. Each letter adds +$79.
 * 4. Add ID card +$16.
 * 5. Choose year plan:
 *    1 year  = base plan price
 *    2 years = base plan price x 2 minus 8%
 *    3 years = base plan price x 3 minus 16%
 *
 * The year plan changes only the base plan price.
 * Hard copy, legal letters, and ID card are added after the year-plan calculation.
 */


/*
 * IMPORTANT PLAN VALUES:
 * new_pdf_id_card = Instant PDF + Patient ID Card. This includes ID card, NOT hard copy.
 * renewal_hard_copy = Existing patient renewal with hard copy. This includes hard copy.
 */

/* =====================================================
   GRAVITY FORMS HOOKS
   ===================================================== */

add_filter('gform_pre_render_2', 'medcards_populate_plan_choices_by_state');
add_filter('gform_pre_validation_2', 'medcards_populate_plan_choices_by_state');
add_filter('gform_pre_submission_filter_2', 'medcards_populate_plan_choices_by_state');
add_filter('gform_admin_pre_render_2', 'medcards_populate_plan_choices_by_state');


/* =====================================================
   STATE PRICING
   ===================================================== */

function medcards_get_state_pricing()
{

    $pricing = array(

        'Arizona' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Arkansas' => array(
            'new'        => 159,
            'renewal'    => 139,
            'two_year'   => 293,
            'three_year' => 410,
        ),

        'California' => array(
            'new_pdf'           => 37,
            'new_pdf_id_card'    => 64.99,
            'caregiver_new'     => 99.99,
            'growers_license'   => 189.99,
            'renewal_hard_copy' => 47,
        ),

        'Connecticut' => array(
            'new'        => 159,
            'renewal'    => 139,
            'two_year'   => 293,
            'three_year' => 410,
        ),

        'Florida' => array(
            'new'        => 199,
            'renewal'    => 179,
            'two_year'   => 366,
            'three_year' => 513,
        ),

        'Georgia' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Illinois' => array(
            'new'        => 169,
            'renewal'    => 149,
            'two_year'   => 311,
            'three_year' => 436,
        ),

        'Iowa' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Louisiana' => array(
            'new'        => 180,
            'renewal'    => 160,
            'two_year'   => 331,
            'three_year' => 464,
        ),

        'Maine' => array(
            'new'        => 89,
            'renewal'    => 79,
            'two_year'   => 164,
            'three_year' => 230,
        ),

        'Maryland' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Massachusetts' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Michigan' => array(
            'new'        => 129,
            'renewal'    => 119,
            'two_year'   => 237,
            'three_year' => 333,
        ),

        'Minnesota' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Missouri' => array(
            'new'        => 119,
            'renewal'    => 109,
            'two_year'   => 219,
            'three_year' => 307,
        ),

        'Montana' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Nevada' => array(
            'new'        => 69,
            'renewal'    => 59,
            'two_year'   => 127,
            'three_year' => 178,
        ),

        'New Hampshire' => array(
            'new'        => 180,
            'renewal'    => 160,
            'two_year'   => 331,
            'three_year' => 464,
        ),

        'New Jersey' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'New York' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Ohio' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Oklahoma' => array(
            'new'        => 119,
            'renewal'    => 109,
            'two_year'   => 219,
            'three_year' => 307,
        ),

        'Pennsylvania' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'Texas' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Virginia' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),

        'Washington' => array(
            'new'        => 139,
            'renewal'    => 129,
            'two_year'   => 256,
            'three_year' => 359,
        ),

        'West Virginia' => array(
            'new'        => 179,
            'renewal'    => 159,
            'two_year'   => 329,
            'three_year' => 462,
        ),
    );
    // MCN State Packages plugin = single source of truth.
    if (function_exists('mcn_sp_pricing_map')) {
        foreach (mcn_sp_pricing_map() as $mcn_state => $mcn_prices) {
            if ($mcn_state === 'California') { continue; } // never override CA
            $pricing[$mcn_state] = $mcn_prices;
            }
        }
        return $pricing;
}


/* =====================================================
   CALIFORNIA ADD-ON PRICING
   ===================================================== */

function medcards_get_california_legal_letters()
{
    return array(
        'none' => array(
            'label' => 'No Legal Letter',
            'price' => 0.00,
        ),
        'parole' => array(
            'label' => 'Cannabis Medical Letter For Parole',
            'price' => 79.00,
        ),
        'employer' => array(
            'label' => 'Cannabis Letter For Employer',
            'price' => 79.00,
        ),
        'driving' => array(
            'label' => 'Cannabis Medical Letter For Possession While Driving',
            'price' => 79.00,
        ),
        'possession_person' => array(
            'label' => 'Cannabis Medical Letter For Possession On Person',
            'price' => 79.00,
        ),
    );
}


function medcards_get_california_year_plan_prices()
{

    /*
     * Year plan is NOT a separate add-on price.
     * It recalculates the selected base plan price only.
     */
    return array(

        '1_year' => array(
            'title'    => '1 Year',
            'years'    => 1,
            'discount' => 0,
        ),

        '2_year' => array(
            'title'    => '2 Years',
            'years'    => 2,
            'discount' => 8,
        ),

        '3_year' => array(
            'title'    => '3 Years',
            'years'    => 3,
            'discount' => 16,
        ),
    );
}


function medcards_calculate_year_plan_price($base_plan_price, $year_plan)
{

    $year_plans = medcards_get_california_year_plan_prices();

    if (!isset($year_plans[$year_plan])) {
        $year_plan = '1_year';
    }

    $years           = (int) $year_plans[$year_plan]['years'];
    $discount        = (float) $year_plans[$year_plan]['discount'];
    $original_price  = (float) $base_plan_price * $years;
    $discount_amount = $original_price * ($discount / 100);
    $final_price     = $original_price - $discount_amount;

    return array(
        'year_plan'       => $year_plan,
        'title'           => $year_plans[$year_plan]['title'],
        'years'           => $years,
        'discount'        => $discount,
        'original_price'  => $original_price,
        'discount_amount' => $discount_amount,
        'final_price'     => $final_price,
    );
}




function medcards_extract_legal_letter_values_from_request_safe()
{

    $values = array();

    if (isset($_POST['input_58'])) {
        $raw_hidden = $_POST['input_58'];

        if (is_array($raw_hidden)) {
            $values = array_merge($values, $raw_hidden);
        } else {
            $values = array_merge($values, explode(',', (string) $raw_hidden));
        }
    }

    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $raw_ui = $_POST['ca_legal_support_letter_ui'];

        if (is_array($raw_ui)) {
            $values = array_merge($values, $raw_ui);
        } else {
            $values = array_merge($values, explode(',', (string) $raw_ui));
        }
    }

    $clean = array();

    foreach ($values as $value) {
        $value = sanitize_text_field(trim((string) $value));

        if ($value && $value !== 'none') {
            $clean[] = $value;
        }
    }

    $clean = array_values(array_unique($clean));

    $legal_letters = medcards_get_california_legal_letters();
    $valid = array();

    foreach ($clean as $value) {
        if (isset($legal_letters[$value])) {
            $valid[] = $value;
        }
    }

    $_POST['input_58'] = !empty($valid) ? implode(',', $valid) : 'none';

    return $valid;
}

function medcards_get_legal_letter_total_from_values_safe($values)
{

    $legal_letters = medcards_get_california_legal_letters();
    $total = 0;

    if (!is_array($values)) {
        $values = array_filter(array_map('trim', explode(',', (string) $values)));
    }

    foreach ($values as $value) {
        if ($value !== 'none' && isset($legal_letters[$value])) {
            $total += (float) $legal_letters[$value]['price'];
        }
    }

    return $total;
}



function medcards_get_legal_letters_total_from_field_58()
{

    $raw_value = '';

    if (isset($_POST['input_58'])) {
        $raw_value = $_POST['input_58'];
    }

    if (is_array($raw_value)) {
        $values = $raw_value;
    } else {
        $values = array_filter(array_map('trim', explode(',', (string) $raw_value)));
    }

    if (empty($values)) {
        return array(
            'values' => array(),
            'total'  => 0,
        );
    }

    $legal_letters = medcards_get_california_legal_letters();

    $valid_values = array();
    $total = 0;

    foreach ($values as $value) {
        $value = sanitize_text_field($value);

        if ($value !== 'none' && isset($legal_letters[$value])) {
            $valid_values[] = $value;
            $total += (float) $legal_letters[$value]['price'];
        }
    }

    $valid_values = array_values(array_unique($valid_values));

    $_POST['input_58'] = !empty($valid_values) ? implode(',', $valid_values) : 'none';

    return array(
        'values' => $valid_values,
        'total'  => $total,
    );
}


function medcards_plan_already_has_hard_copy($selected_plan)
{

    return in_array($selected_plan, array('renewal_hard_copy'), true);
}



function medcards_plan_already_has_id_card($selected_plan)
{

    return in_array($selected_plan, array('new_pdf_id_card'), true);
}


/* =====================================================
   POPULATE PLAN CHOICES + HIDDEN DEFAULTS
   ===================================================== */

function medcards_populate_plan_choices_by_state($form)
{

    if (!is_user_logged_in()) {
        return $form;
    }

    $user_id = get_current_user_id();
    $state   = mcn_get_pricing_state($user_id);

    if (!$state) {
        return $form;
    }

    $pricing = medcards_get_state_pricing();

    if (!isset($pricing[$state])) {
        return $form;
    }

    $prices = $pricing[$state];

    foreach ($form['fields'] as &$field) {

        /**
         * FIELD 11 = CHOOSE PLAN
         */
        if ((int) $field->id === 11) {

            if ($state === 'California') {

                $field->choices = array(

                    array(
                        'text'  => '<span class="badge-save">Save 35%</span><span class="badge-rec">Recommended</span><br><br>$64.99<br><br> Instant PDF + Patient ID Card - With Hard Copy',
                        'value' => 'new_pdf_id_card',
                        'isSelected' => true,
                    ),

                    array(
                        'text'  => '$99.99<br><br> New Patient (Caregiver) — Caregiver + New Patient',
                        'value' => 'caregiver_new',
                    ),

                    array(
                        'text'  => '$189.99<br><br> License to Cultivate up to 99 Plants',
                        'value' => 'growers_license',
                    ),

                    array(
                        'text'  => '$37<br><br> New Patient — PDF Only',
                        'value' => 'new_pdf',
                    ),

                    array(
                        'text'  => '$47<br><br> Existing patient renewal — With Hard Copy',
                        'value' => 'renewal_hard_copy',
                    ),
                );
            } else {

                if (function_exists('mcn_sp_is_managed') && mcn_sp_is_managed($state)) {

                    // Plugin-managed state: build the choices from State Packages.
                    $built_choices = array();
                    $first_choice  = true;

                    foreach (mcn_sp_get_packages($state) as $pkg) {
                        $built_choices[] = array(
                            'text'       => '$' . number_format((float) $pkg['price'], 0) . '<br><br> ' . $pkg['label'],
                            'value'      => $pkg['key'],
                            'isSelected' => $first_choice,
                        );
                        $first_choice = false;
                    }

                    $field->choices = $built_choices;

                } else {

                    // Legacy states still defined in functions.php (unchanged).
                    $field->choices = array(

                        array(
                            'text'  => '$' . number_format($prices['new'], 0) . '<br><br> New Patient',
                            'value' => 'new',
                        ),

                        array(
                            'text'  => '$' . number_format($prices['renewal'], 0) . '<br><br> Renewal',
                            'value' => 'renewal',
                        ),

                        array(
                            'text'  => '$' . number_format($prices['two_year'], 0) . '<br><br> 2 Year Pricing',
                            'value' => 'two_year',
                        ),

                        array(
                            'text'  => '$' . number_format($prices['three_year'], 0) . '<br><br> 3 Year Pricing',
                            'value' => 'three_year',
                        ),
                    );
                }
            }
        }


        /**
         * FIELD 54 = ADD A HARD COPY
         */
        if ((int) $field->id === 54) {

            if ($state === 'California') {

                $field->visibility = 'visible';

                $field->choices = array(
                    array(
                        'text'       => 'Yes +$10.00',
                        'value'      => 'yes',
                        'isSelected' => false,
                    ),
                    array(
                        'text'       => 'No',
                        'value'      => 'no',
                        'isSelected' => true,
                    ),
                );
            } else {

                $field->visibility = 'hidden';

                $field->choices = array(
                    array(
                        'text'       => 'No',
                        'value'      => 'no',
                        'isSelected' => true,
                    ),
                );
            }
        }


        /**
         * FIELD 58 = LEGAL SUPPORT LETTER HIDDEN FIELD
         */
        if ((int) $field->id === 58) {
            $field->visibility = 'hidden';
            $field->defaultValue = 'none';
        }


        /**
         * FIELD 61 = ID CARD HIDDEN FIELD
         */
        if ((int) $field->id === 61) {
            $field->visibility = 'hidden';
            $field->defaultValue = 'no';
        }


        /**
         * FIELD 63 = YEAR PLAN HIDDEN FIELD
         */
        if ((int) $field->id === 63) {
            $field->visibility = 'hidden';
            $field->defaultValue = '3_year';
        }
    }

    return $form;
}


/* =====================================================
   CALCULATE FINAL PAYMENT AMOUNT
   ===================================================== */

add_filter('gform_pre_submission_2', 'set_hidden_payment_amount_from_selected_plan');


function set_hidden_payment_amount_from_selected_plan($form)
{
    if (!is_user_logged_in()) {
        return $form;
    }
 
    $user_id       = get_current_user_id();
    $state         = get_user_meta($user_id, 'state', true);
    $selected_plan = rgpost('input_11');
    $hard_copy     = rgpost('input_54');
    $id_card       = rgpost('input_61');
    $year_plan     = rgpost('input_63');
    $pricing       = medcards_get_state_pricing();
 
    if (!$state || !$selected_plan) {
        return $form;
    }
 
    if (!isset($pricing[$state][$selected_plan])) {
        return $form;
    }
 
    $single_year_plan_price = (float) $pricing[$state][$selected_plan];
 
    /* NON-CALIFORNIA: flat price, no year multiplier, no add-ons. */
    if ($state !== 'California') {
 
        $amount = $single_year_plan_price;
 
        update_user_meta($user_id, 'payment_single_year_plan_price', number_format($single_year_plan_price, 2, '.', ''));
        update_user_meta($user_id, 'payment_plan_price', number_format($amount, 2, '.', ''));
        update_user_meta($user_id, 'payment_amount', number_format($amount, 2, '.', ''));
        update_user_meta($user_id, 'payment_selected_plan', $selected_plan);
 
        $_POST['input_51'] = number_format($amount, 2, '.', '');
 
        return $form;
    }
 
    /* CALIFORNIA: year logic + add-ons (unchanged). */
    $year_plan_data = medcards_calculate_year_plan_price(
        $single_year_plan_price,
        $year_plan ?: '1_year'
    );
 
    $adjusted_plan_price = (float) $year_plan_data['final_price'];
    $amount = $adjusted_plan_price;
 
    $hard_copy_price     = 0;
    $legal_letters_total = 0;
    $id_card_price       = 0;
 
    if (!medcards_plan_already_has_hard_copy($selected_plan) && $hard_copy === 'yes') {
        $hard_copy_price = 10;
        $amount += $hard_copy_price;
    }
 
    $selected_legal_letters = medcards_extract_legal_letter_values_from_request_safe();
    $legal_letters_total = medcards_get_legal_letter_total_from_values_safe($selected_legal_letters);
 
    if ($legal_letters_total > 0) {
        $amount += $legal_letters_total;
    }
 
    if (medcards_plan_already_has_id_card($selected_plan)) {
        $id_card_price = 0;
        $_POST['input_61'] = 'included';
    } elseif ($id_card === 'yes') {
        $id_card_price = 16;
        $amount += $id_card_price;
    }
 
    update_user_meta($user_id, 'payment_single_year_plan_price', number_format($single_year_plan_price, 2, '.', ''));
    update_user_meta($user_id, 'payment_plan_price', number_format($adjusted_plan_price, 2, '.', ''));
 
    update_user_meta($user_id, 'california_year_plan', $year_plan_data['year_plan']);
    update_user_meta($user_id, 'california_year_plan_title', $year_plan_data['title']);
    update_user_meta($user_id, 'california_year_plan_years', $year_plan_data['years']);
    update_user_meta($user_id, 'california_year_plan_discount_percent', number_format($year_plan_data['discount'], 2, '.', ''));
    update_user_meta($user_id, 'california_year_plan_original_price', number_format($year_plan_data['original_price'], 2, '.', ''));
    update_user_meta($user_id, 'california_year_plan_discount_amount', number_format($year_plan_data['discount_amount'], 2, '.', ''));
 
    update_user_meta($user_id, 'california_hard_copy_price', number_format($hard_copy_price, 2, '.', ''));
    update_user_meta($user_id, 'california_legal_support_letter_price', number_format($legal_letters_total, 2, '.', ''));
    update_user_meta($user_id, 'california_id_card_price', number_format($id_card_price, 2, '.', ''));
 
    update_user_meta($user_id, 'payment_amount', number_format($amount, 2, '.', ''));
 
    $_POST['input_51'] = number_format($amount, 2, '.', '');
 
    return $form;
}
