<?php
/**
 * MCN Core module: California add-ons
 * California year-plan and legal-letter cards, and saving those selections.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;



/* =====================================================
   SAVE ADD-ON VALUES TO USER META AFTER SUBMISSION
   ===================================================== */

add_action('gform_after_submission_2', 'medcards_save_california_addons_to_user_meta', 20, 2);

function medcards_save_california_addons_to_user_meta($entry, $form)
{

    if (!is_user_logged_in()) {
        return;
    }

    $user_id       = get_current_user_id();
    $state         = get_user_meta($user_id, 'state', true);
    $selected_plan = rgar($entry, '11');
    $hard_copy     = rgar($entry, '54');
    $legal_letters_selected = rgar($entry, '58'); // comma-separated values

    if (empty($legal_letters_selected) || $legal_letters_selected === 'none') {
        $posted_selected_letters = medcards_extract_legal_letter_values_from_request_safe();
        if (!empty($posted_selected_letters)) {
            $legal_letters_selected = implode(',', $posted_selected_letters);
        }
    }
    $id_card       = rgar($entry, '61');
    $year_plan     = rgar($entry, '63');
    $amount        = rgar($entry, '51');

    $pricing = medcards_get_state_pricing();
    $addons  = array();

    /*
     * Save selected base plan + year plan pricing breakdown.
     */
    if ($state && $selected_plan && isset($pricing[$state][$selected_plan])) {

        $single_year_plan_price = (float) $pricing[$state][$selected_plan];

        $year_plan_data = medcards_calculate_year_plan_price(
            $single_year_plan_price,
            $year_plan ?: '1_year'
        );

        update_user_meta($user_id, 'payment_single_year_plan_price', number_format($single_year_plan_price, 2, '.', ''));
        update_user_meta($user_id, 'payment_plan_price', number_format($year_plan_data['final_price'], 2, '.', ''));

        update_user_meta($user_id, 'california_year_plan', $year_plan_data['year_plan']);
        update_user_meta($user_id, 'california_year_plan_title', $year_plan_data['title']);
        update_user_meta($user_id, 'california_year_plan_years', $year_plan_data['years']);
        update_user_meta($user_id, 'california_year_plan_discount_percent', number_format($year_plan_data['discount'], 2, '.', ''));
        update_user_meta($user_id, 'california_year_plan_original_price', number_format($year_plan_data['original_price'], 2, '.', ''));
        update_user_meta($user_id, 'california_year_plan_discount_amount', number_format($year_plan_data['discount_amount'], 2, '.', ''));

        if ((float) $year_plan_data['discount'] > 0) {
            $addons[] = $year_plan_data['title'] . ' Access - Save ' . number_format($year_plan_data['discount'], 0) . '%';
        }
    }

    /*
     * Hard copy meta.
     */
    if ($state === 'California' && !medcards_plan_already_has_hard_copy($selected_plan) && $hard_copy === 'yes') {

        $addons[] = 'Add A Hard Copy +$10.00';

        update_user_meta($user_id, 'california_hard_copy', 'yes');
        update_user_meta($user_id, 'california_hard_copy_price', '10.00');
    } else {

        update_user_meta($user_id, 'california_hard_copy', 'no');
        update_user_meta($user_id, 'california_hard_copy_price', '0.00');
    }
    /*
     * Legal support letters meta.
     * Multiple selected letters are allowed.
     */
    $legal_letters_total = 0;
    $legal_letter_titles = array();
    $legal_letter_values = array();

    $selected_letters = medcards_extract_legal_letter_values_from_request_safe();

    if (empty($selected_letters) && !empty($legal_letters_selected) && $legal_letters_selected !== 'none') {
        $selected_letters = array_filter(array_map('trim', explode(',', $legal_letters_selected)));
    }

    if ($state === 'California' && !empty($selected_letters)) {

        $legal_letters = medcards_get_california_legal_letters();

        foreach ($selected_letters as $letter_key) {

            if ($letter_key !== 'none' && isset($legal_letters[$letter_key])) {

                $legal_letter_titles[] = $legal_letters[$letter_key]['title'];
                $legal_letter_values[] = $letter_key;
                $legal_letters_total += (float) $legal_letters[$letter_key]['price'];

                $addons[] = $legal_letters[$letter_key]['title'] . ' +$' . number_format((float) $legal_letters[$letter_key]['price'], 2);
            }
        }

        update_user_meta($user_id, 'california_legal_support_letter', implode(' | ', $legal_letter_titles));
        update_user_meta($user_id, 'california_legal_support_letter_value', implode(',', $legal_letter_values));
        update_user_meta($user_id, 'california_legal_support_letter_price', number_format($legal_letters_total, 2, '.', ''));
    } else {

        update_user_meta($user_id, 'california_legal_support_letter', 'none');
        update_user_meta($user_id, 'california_legal_support_letter_value', 'none');
        update_user_meta($user_id, 'california_legal_support_letter_price', '0.00');
    }

    /*
     * ID card meta.
     * If selected base plan already includes ID card, do not charge again.
     */
    if ($state === 'California' && medcards_plan_already_has_id_card($selected_plan)) {

        $addons[] = 'ID Card Included in Selected Plan';

        update_user_meta($user_id, 'california_id_card', 'included');
        update_user_meta($user_id, 'california_id_card_price', '0.00');
    } elseif ($state === 'California' && $id_card === 'yes') {

        $addons[] = 'ID Card +$16.00';

        update_user_meta($user_id, 'california_id_card', 'yes');
        update_user_meta($user_id, 'california_id_card_price', '16.00');
    } else {

        update_user_meta($user_id, 'california_id_card', 'no');
        update_user_meta($user_id, 'california_id_card_price', '0.00');
    }

    /*
     * Readable summary.
     */
    update_user_meta(
        $user_id,
        'selected_plan_addons',
        !empty($addons) ? implode(' | ', $addons) : 'No add-ons selected'
    );

    /*
     * Final total amount.
     */
    if ($amount) {
        update_user_meta($user_id, 'payment_amount', number_format((float) $amount, 2, '.', ''));
    }
}




/**
 * ============================================================
 * SAVE GRAVITY FORMS FORM 2 FIELD 13 PHOTO TO USER META
 * ============================================================
 */





/* =====================================================
   SHORTCODE: LEGAL SUPPORT LETTER CARDS
   Add this in a Gravity Forms HTML field:
   [medcards_california_legal_letter_cards]
   ===================================================== */

add_shortcode('medcards_california_legal_letter_cards', 'medcards_california_legal_letter_cards_shortcode');

function medcards_california_legal_letter_cards_shortcode()
{

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if ($state !== 'California') {
        return '';
    }

    ob_start();
?>

    <div class="ca-legal-addon-wrap">

        <div class="ca-legal-grid">

            <div class="ca-legal-card ca-legal-card-muted" data-value="parole">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="parole">
                <div class="ca-legal-icon">👤</div>
                <h4>Cannabis Medical Letter For Parole</h4>
                <p>A doctor-signed letter for communication with parole or supervising authorities. It confirms cannabis is included in your treatment plan and is intended to support compliance with applicable legal and supervision requirements.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

            <div class="ca-legal-card" data-value="employer">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="employer">
                <div class="ca-legal-icon">⚖</div>
                <h4>Cannabis Letter For Employer</h4>
                <p>A physician-signed letter to support conversations with your employer or HR. It confirms a doctor has determined cannabis is part of your treatment plan, subject to applicable laws and workplace policies.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

            <div class="ca-legal-card" data-value="driving">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="driving">
                <div class="ca-legal-icon">🚗</div>
                <h4>Cannabis Medical Letter For Possession While Driving</h4>
                <p>A doctor-signed letter confirming you are authorized to transport medical cannabis in your vehicle. Intended to support lawful possession in accordance with state and local laws.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

            <div class="ca-legal-card" data-value="possession_person">
                <input type="checkbox" name="ca_legal_support_letter_ui" value="possession_person">
                <div class="ca-legal-icon">i</div>
                <h4>Cannabis Medical Letter For Possession On Person</h4>
                <p>A physician-signed document verifying cannabis is part of your medical care. It may be presented to law enforcement to clarify lawful possession, subject to applicable laws and not a guarantee against enforcement.</p>
                <div class="ca-legal-bottom">
                    <strong>$79.00</strong>
                    <button type="button">Add</button>
                </div>
            </div>

        </div>

        <button type="button" class="ca-legal-clear">Remove Legal Letters</button>

    </div>

<?php
    return ob_get_clean();
}


/* =====================================================
   SHORTCODE: YEAR PLAN CARDS
   Add this in a Gravity Forms HTML field:
   [medcards_california_year_plan_cards]
   ===================================================== */

add_shortcode('medcards_california_year_plan_cards', 'medcards_california_year_plan_cards_shortcode');

function medcards_california_year_plan_cards_shortcode()
{

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $state   = get_user_meta($user_id, 'state', true);

    if ($state !== 'California') {
        return '';
    }

    ob_start();
?>

    <div class="ca-years-wrap">

        <h3 class="ca-addon-title">How long do you want access to medical cannabis?</h3>
        <p class="ca-addon-subtitle">Your certification must be renewed yearly, but you can save on annual renewal costs by paying ahead.</p>

        <div class="ca-years-grid">

            <div class="ca-years-card" data-value="3_year">
                <span class="ca-years-radio"></span>
                <span class="ca-years-badge">MOST POPULAR</span>
                <h4>3 years</h4>
                <p>Choose 3 years and save 16% on your selected base plan total.</p>
                <div class="ca-years-price">
                    <del>$75</del>
                    <strong>Save 16%</strong>
                    <span>Best Value</span>
                </div>
                <small>221 people chose this today</small>
            </div>

            <div class="ca-years-card" data-value="2_year">
                <span class="ca-years-radio"></span>
                <h4>2 years</h4>
                <p>Choose 2 years and save 8% on your selected base plan total.</p>
                <div class="ca-years-price">
                    <del>$75</del>
                    <strong>Save 8%</strong>
                    <span>Popular</span>
                </div>
                <small>31 people chose this today</small>
            </div>

            <div class="ca-years-card" data-value="1_year">
                <span class="ca-years-radio"></span>
                <h4>1 year</h4>
                <p>Choose 1 year and keep the selected base plan price the same.</p>
                <div class="ca-years-price">
                    <strong>No Discount</strong>
                    <span>Regular Price</span>
                </div>
                <small>65 people chose this today</small>
            </div>

        </div>

    </div>

<?php
    return ob_get_clean();
}
