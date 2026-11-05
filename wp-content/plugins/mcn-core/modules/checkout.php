<?php
/**
 * MCN Core module: Checkout and payment
 * The [custom_authorize_payment] checkout, the Authorize.Net charge, and the ID card PDF email after payment.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;


/* =========================================================
 * SHORTCODE: [custom_authorize_payment]
 * ========================================================= */
add_shortcode('custom_authorize_payment', function () {
    if (!is_user_logged_in()) {
        return '<p>Please login to continue.</p>';
    }

    $user_id = get_current_user_id();

    /* -------------------------------------------------
     * PLAN LABEL
     * ------------------------------------------------- */
    $plan = get_user_meta($user_id, 'payment_plan_', true);

    if (empty($plan) || !is_string($plan)) {
        $plan_key = get_user_meta($user_id, 'selected_plan', true);
        if ($plan_key === '') {
            $plan_key = get_user_meta($user_id, 'payment_plan', true);
        }
        $plan_key = sanitize_key((string) $plan_key);

        $plan_labels = cap_get_plan_labels();
        $plan = isset($plan_labels[$plan_key])
            ? $plan_labels[$plan_key]
            : 'Medical Cannabis Card';
    }

    /* -------------------------------------------------
     * PRICE — computed server-side from live POST selections
     * with fallback to saved meta. NEVER trust POST amount directly.
     * ------------------------------------------------- */
     /* -------------------------------------------------
     * PRICE — computed server-side.
     * Priority:
     *   1. live POST hidden fields (synced by JS)
     *   2. Gravity Forms fields carried in $_POST during multi-page
     *      navigation (rgpost) — this is what makes step 6 know the plan
     *   3. saved plan meta
     *   4. stale payment_amount meta (last resort only)
     * NEVER trust a POSTed amount directly.
     * ------------------------------------------------- */
    $live_plan_key  = isset($_POST['live_plan_key'])  ? sanitize_key(wp_unslash($_POST['live_plan_key']))  : '';
    $live_year_plan = isset($_POST['live_year_plan']) ? sanitize_key(wp_unslash($_POST['live_year_plan'])) : '';
    $live_hard_copy = isset($_POST['live_hard_copy']) ? sanitize_text_field(wp_unslash($_POST['live_hard_copy'])) : '';
    $live_id_card   = isset($_POST['live_id_card'])   ? sanitize_text_field(wp_unslash($_POST['live_id_card']))   : '';
    $live_legal     = isset($_POST['live_legal'])     ? sanitize_text_field(wp_unslash($_POST['live_legal']))     : '';
 
    /* Gravity Forms keeps every prior-page value in $_POST as the patient
       walks the wizard, so read the real selections straight from the
       form fields whenever the JS hidden fields have not synced yet. */
    if (function_exists('rgpost')) {
        if ($live_plan_key === '')  { $live_plan_key  = sanitize_key((string) rgpost('input_11')); }
        if ($live_year_plan === '') { $live_year_plan = sanitize_key((string) rgpost('input_63')); }
        if ($live_hard_copy === '') { $live_hard_copy = sanitize_text_field((string) rgpost('input_54')); }
        if ($live_id_card === '')   { $live_id_card   = sanitize_text_field((string) rgpost('input_61')); }
        if ($live_legal === '')     { $live_legal     = sanitize_text_field((string) rgpost('input_58')); }
    }
 
    /* Saved selection as a further fallback. */
    if ($live_plan_key === '') {
        $live_plan_key = sanitize_key((string) get_user_meta($user_id, 'payment_selected_plan', true));
    }
    if ($live_plan_key === '') {
        $live_plan_key = sanitize_key((string) get_user_meta($user_id, 'selected_plan', true));
    }
 
    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $posted_legal = wp_unslash($_POST['ca_legal_support_letter_ui']);
        $posted_legal = is_array($posted_legal) ? $posted_legal : explode(',', (string) $posted_legal);
        $live_legal = implode(',', array_values(array_unique(array_filter(array_map('sanitize_key', $posted_legal)))));
    }
 
    $price = 0.0;
 
    if ($live_plan_key !== '') {
        $price = cap_compute_server_total(
            $live_plan_key,
            $live_year_plan ?: '3_year',
            $live_hard_copy ?: 'no',
            $live_id_card ?: 'no',
            $live_legal
        );
    }
 
    if ($price <= 0) {
        $raw_price = get_user_meta($user_id, 'payment_amount', true);
        $raw_price = is_scalar($raw_price) ? trim((string) $raw_price) : '';
        if ($raw_price !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $raw_price) && (float) $raw_price > 0) {
            $price = (float) $raw_price;
        }
    }
 
    /* NON-CA FIX: prices + labels handed to the checkout JS so the
       Order Summary can also recalc generic plans live when the plan
       radio is on the same page. */
    $cap_user_state        = mcn_get_pricing_state($user_id);
    $cap_state_pricing_all = function_exists('medcards_get_state_pricing') ? medcards_get_state_pricing() : array();
    $cap_non_ca_pricing    = array();
 
    if ($cap_user_state && $cap_user_state !== 'California' && isset($cap_state_pricing_all[$cap_user_state])) {
        foreach ($cap_state_pricing_all[$cap_user_state] as $cap_k => $cap_price) {
            if (is_numeric($cap_price)) {
                $cap_non_ca_pricing[$cap_k] = (float) $cap_price;
            }
        }
    }
 
    // Base labels for legacy keys...
    $cap_non_ca_labels = array(
        'new'        => 'New Patient',
        'renewal'    => 'Renewal',
        'two_year'   => '2 Year Pricing',
        'three_year' => '3 Year Pricing',
    );
    
    // ...plus every plugin-defined label (e.g. "1 Year", "Medical Card").
    if (function_exists('mcn_sp_label_map')) {
        foreach (mcn_sp_label_map() as $mcn_k => $mcn_label) {
            $cap_non_ca_labels[$mcn_k] = $mcn_label;
        }
    }
    // Also every label the payment engine knows (includes State Packages labels).
    foreach (cap_get_plan_labels() as $mcn_k => $mcn_label) {
        if (!isset($cap_non_ca_labels[$mcn_k])) {
            $cap_non_ca_labels[$mcn_k] = $mcn_label;
        }
    }

    $coupon_code = isset($_POST['coupon_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['coupon_code']))) : '';
    $coupon_result = $coupon_code !== '' ? mcn_get_coupon_discount($coupon_code, $price) : array('valid'=>false,'discount'=>0,'message'=>'');
    $coupon_discount = !empty($coupon_result['valid']) ? (float) $coupon_result['discount'] : 0;
    $display_price = max(0, round($price - $coupon_discount, 2));
    $price_str = number_format($display_price, 2, '.', '');

    /* -------------------------------------------------
     * PERSIST LIVE SELECTIONS
     * ------------------------------------------------- */
    if ($live_plan_key) {
        update_user_meta($user_id, 'payment_selected_plan', $live_plan_key);
    }
    if ($live_year_plan) {
        update_user_meta($user_id, 'california_year_plan', $live_year_plan);
    }
    if ($price > 0) {
        // Keep the original subtotal in user meta. The coupon discount is applied separately
        // for checkout display and again server-side when processing the payment.
        update_user_meta($user_id, 'payment_amount', number_format($price, 2, '.', ''));
    }

    /* -------------------------------------------------
     * ADDRESS FIELDS
     * ------------------------------------------------- */
    $address_line_1 = get_user_meta($user_id, 'address_line_1', true);
    if ($address_line_1 === '') $address_line_1 = get_user_meta($user_id, 'address', true);
    if ($address_line_1 === '') $address_line_1 = get_user_meta($user_id, 'billing_address_line_1', true);

    $address_line_2 = get_user_meta($user_id, 'address_line_2', true);
    if ($address_line_2 === '') $address_line_2 = get_user_meta($user_id, 'billing_address_line_2', true);

    $city = get_user_meta($user_id, 'city', true);
    if ($city === '') $city = get_user_meta($user_id, 'billing_city', true);

    $country = get_user_meta($user_id, 'country', true);
    if ($country === '') $country = get_user_meta($user_id, 'billing_country', true);
    $country = $country ?: 'United States';

    $state = get_user_meta($user_id, 'state', true);
    if ($state === '') $state = get_user_meta($user_id, 'billing_state', true);

    $zip_code = get_user_meta($user_id, 'zip_code', true);
    if ($zip_code === '') $zip_code = get_user_meta($user_id, 'billing_zip', true);

    $same_as_shipping_meta = get_user_meta($user_id, 'billing_same_as_shipping', true);
    $same_as_shipping      = ($same_as_shipping_meta !== 'no'); // bool

    $billing_address_line_1 = get_user_meta($user_id, 'billing_address_line_1', true);
    $billing_address_line_2 = get_user_meta($user_id, 'billing_address_line_2', true);
    $billing_city           = get_user_meta($user_id, 'billing_city', true);
    $billing_state          = get_user_meta($user_id, 'billing_state', true);
    $billing_zip            = get_user_meta($user_id, 'billing_zip', true);
    
    
    
    // Fallback to Gravity Forms entry if user meta is empty
    if (empty($address_line_1) && class_exists('GFAPI')) {
        $entries = GFAPI::get_entries(2, array(
            'status' => 'active',
            'field_filters' => array(
                array('key' => 'created_by', 'value' => $user_id)
            )
        ), null, array('page_size' => 1));
        
        if (!is_wp_error($entries) && !empty($entries)) {
            $gf_entry = $entries[0];
            
            // Try single Address field pattern first (field_id.1 ... field_id.6)
            $address_field_id = 37; // Profile address field ID (input_37)
            $sub_1 = rgar($gf_entry, $address_field_id . '.1');
            if (!empty($sub_1)) {
                $address_line_1 = $sub_1;
                $address_line_2 = rgar($gf_entry, $address_field_id . '.2');
                $city           = rgar($gf_entry, $address_field_id . '.3');
                $state          = rgar($gf_entry, $address_field_id . '.4');
                $zip_code       = rgar($gf_entry, $address_field_id . '.5');
                $country        = rgar($gf_entry, $address_field_id . '.6');
            }
            
            // Fallback: separate field IDs
            if (empty($address_line_1)) {
                // Profile fields (verified 2026-10-30): address 37, line 2 38, city 39, state 49, ZIP 42, country 48.
                $address_line_1 = rgar($gf_entry, '37');
                $address_line_2 = rgar($gf_entry, '38');
                $city           = rgar($gf_entry, '39');
                $state          = rgar($gf_entry, '49');
                $zip_code       = rgar($gf_entry, '42');
                $country        = rgar($gf_entry, '48');
            }
            
            // Save to user meta for future
            if (!empty($address_line_1)) update_user_meta($user_id, 'address_line_1', $address_line_1);
            if (!empty($address_line_2)) update_user_meta($user_id, 'address_line_2', $address_line_2);
            if (!empty($city))           update_user_meta($user_id, 'city', $city);
            if (!empty($state))          update_user_meta($user_id, 'state', mcn_normalize_state_name($state));
            if (!empty($zip_code))       update_user_meta($user_id, 'zip_code', $zip_code);
            if (!empty($country))        update_user_meta($user_id, 'country', $country);
        }
    }
    
    
    

    ob_start();

    /* -------------------------------------------------
     * ERROR DISPLAY (from payment handler)
     * ------------------------------------------------- */
    $payment_error = !empty($GLOBALS['cap_payment_error'])
        ? sanitize_key($GLOBALS['cap_payment_error'])
        : '';

    $payment_error_details = $payment_error
        ? cap_get_payment_error_details($payment_error)
        : array();
    ?>

    <?php if (true) : ?>
        <!-- <style id="custom-authorize-payment-error-style">
            .custom-authorize-payment-error {
                position: relative;
                margin: 0 auto 24px;
                padding: 16px 20px;
                max-width: 1100px;
                border: 1px solid #d63638;
                border-radius: 10px;
                background: #fff5f5;
                color: #8a1f1f;
                font-size: 15px;
                line-height: 1.5;
                box-sizing: border-box;
                box-shadow: 0 5px 18px rgba(214, 54, 56, 0.08);
            }
            .custom-authorize-payment-error strong {
                display: block;
                margin-bottom: 4px;
                font-size: 18px;
            }
            .custom-authorize-payment-error-close {
                float: right;
                border: 0;
                background: transparent;
                color: inherit;
                font-size: 22px;
                line-height: 1;
                cursor: pointer;
                padding: 0 4px;
            }
            .custom-authorize-payment-error-modal {
                position: fixed;
                inset: 0;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: rgba(0, 0, 0, 0.55);
                box-sizing: border-box;
            }
            .custom-authorize-payment-error-modal-box {
                width: 100%;
                max-width: 520px;
                background: #fff;
                border-radius: 10px;
                padding: 25px;
                box-sizing: border-box;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0, 0, 0, 0.25);
            }
            .custom-authorize-payment-error-icon {
                width: 72px;
                height: 72px;
                margin: 0 auto 16px;
                border-radius: 50%;
                background: #e53935;
                position: relative;
                box-sizing: border-box;
            }
            .custom-authorize-payment-error-icon::before,
            .custom-authorize-payment-error-icon::after {
                content: '';
                position: absolute;
                width: 38px;
                height: 4px;
                background: #fff;
                border-radius: 3px;
                left: 17px;
                top: 34px;
            }
            .custom-authorize-payment-error-icon::before { transform: rotate(45deg); }
            .custom-authorize-payment-error-icon::after  { transform: rotate(-45deg); }
            .custom-authorize-payment-error-modal-box h3 {
                margin: 0 0 10px;
                font-size: 22px;
            }
            .custom-authorize-payment-error-modal-box p {
                margin: 0 0 20px;
                font-size: 16px;
                line-height: 1.5;
            }
            .custom-authorize-payment-error-modal-close {
                display: inline-block;
                border: 0;
                border-radius: 6px;
                padding: 10px 22px;
                cursor: pointer;
                font-size: 15px;
                background: #20a7a7;
                color: #fff;
            }
        </style> -->

        <style id="cap-payment-modal-style">
            .custom-authorize-payment-error-modal {
                position: fixed; inset: 0; z-index: 999999; display: none;
                align-items: center; justify-content: center; padding: 20px;
                background: rgba(0,0,0,.55); box-sizing: border-box;
            }
            .custom-authorize-payment-error-modal-box {
                width: 100%; max-width: 520px; background: #fff; border-radius: 10px;
                padding: 25px; box-sizing: border-box; text-align: center;
                box-shadow: 0 10px 35px rgba(0,0,0,.25);
            }
            .custom-authorize-payment-error-icon {
                width: 72px; height: 72px; margin: 0 auto 16px; border-radius: 50%;
                background: #e53935; position: relative; box-sizing: border-box;
            }
            .custom-authorize-payment-error-icon::before,
            .custom-authorize-payment-error-icon::after {
                content: ''; position: absolute; width: 38px; height: 4px;
                background: #fff; border-radius: 3px; left: 17px; top: 34px;
            }
            .custom-authorize-payment-error-icon::before { transform: rotate(45deg); }
            .custom-authorize-payment-error-icon::after  { transform: rotate(-45deg); }
            .custom-authorize-payment-error-modal-box h3 { margin: 0 0 10px; font-size: 22px; }
            .custom-authorize-payment-error-modal-box p  { margin: 0 0 20px; font-size: 16px; line-height: 1.5; }
            .custom-authorize-payment-error-modal-close {
                display: inline-block; border: 0; border-radius: 6px; padding: 10px 22px;
                cursor: pointer; font-size: 15px; background: #20a7a7; color: #fff;
            }
            .auth-payment-form input.cap-field-error {
                border-color: #d63638 !important;
                box-shadow: 0 0 0 3px rgba(214,54,56,.12);
            }
            .place-order-btn[disabled] { opacity: .7; cursor: not-allowed; }
        </style>

        <div id="cap-error-modal" class="custom-authorize-payment-error-modal" role="dialog" aria-modal="true" aria-labelledby="cap-error-modal-title">
        <div class="custom-authorize-payment-error-modal-box">
            <div class="custom-authorize-payment-error-icon" aria-hidden="true"></div>
            <h3 id="cap-error-modal-title"></h3>
            <p id="cap-error-modal-message"></p>
            <button type="button" id="cap-error-modal-close" class="custom-authorize-payment-error-modal-close">OK</button>
        </div>
    </div>

    <?php if (!empty($payment_error_details)) : ?>
        <script>
            window.capServerPaymentError = <?php echo wp_json_encode(array(
                'code'    => $payment_error,
                'title'   => $payment_error_details['title'],
                'message' => $payment_error_details['message'],
            )); ?>;
        </script>
    <?php endif; ?>

        <script>
            (function () {
                var modal = document.getElementById('cap-error-modal');
                var title = document.getElementById('cap-error-modal-title');
                var message = document.getElementById('cap-error-modal-message');
                if (!modal || !window.capServerPaymentError) return;
                title.textContent = window.capServerPaymentError.title || 'Payment Error';
                message.textContent = window.capServerPaymentError.message || 'The payment could not be processed. Please try again.';
                modal.style.display = 'flex';
            })();
        </script>
    <?php endif; ?>

    <div class="auth-payment-form" autocomplete="on" id="medcards-payment-form" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <?php wp_nonce_field('custom_authorize_payment_action', 'custom_authorize_payment_nonce'); ?>

        <!-- Hidden fields synced live from Form 2 selections -->
        <input type="hidden" name="live_plan_key"  id="cap_live_plan_key"  value="<?php echo esc_attr($live_plan_key); ?>">
        <input type="hidden" name="live_year_plan" id="cap_live_year_plan" value="<?php echo esc_attr($live_year_plan ?: '3_year'); ?>">
        <input type="hidden" name="live_hard_copy" id="cap_live_hard_copy" value="<?php echo esc_attr($live_hard_copy ?: 'no'); ?>">
        <input type="hidden" name="live_id_card"   id="cap_live_id_card"   value="<?php echo esc_attr($live_id_card ?: 'no'); ?>">
        <input type="hidden" name="live_legal"     id="cap_live_legal"     value="<?php echo esc_attr($live_legal); ?>">
        <input type="hidden" name="live_amount"    id="cap_live_amount"    value="<?php echo esc_attr($price_str); ?>">
        <input type="hidden" id="cap_coupon_discount" value="<?php echo esc_attr(number_format($coupon_discount, 2, '.', '')); ?>">

        <div class="checkout-wrap">
            <div class="checkout-left">
                <div class="checkout-box">
                    <h3>Shipping Address</h3>

                    <div class="field-grid two-col">
                        <input type="text" name="address_line_1" placeholder="Address" value="<?php echo esc_attr($address_line_1); ?>" required>
                        <input type="text" name="address_line_2" placeholder="Apartment, suite, etc." value="<?php echo esc_attr($address_line_2); ?>">
                    </div>

                    <div class="field-grid three-col">
                        <input type="text" name="city" placeholder="City" value="<?php echo esc_attr($city); ?>" required>
                        <input type="text" name="state" placeholder="State" value="<?php echo esc_attr($state); ?>" required>
                        <input type="text" name="zip_code" placeholder="ZIP" value="<?php echo esc_attr($zip_code); ?>" required>
                    </div>

                    <div class="field-grid one-col">
                        <input type="text" name="country" value="United States" readonly required>
                    </div>

                    <label class="billing-check">
                        <input type="checkbox" id="same_as_shipping" name="same_as_shipping" value="yes" <?php checked($same_as_shipping, true); ?>>
                        Billing address same as shipping
                    </label>

                    <div class="billing-fields" style="<?php echo $same_as_shipping ? 'display:none;' : 'display:block;'; ?>">
                        <div class="field-grid two-col">
                            <input type="text" name="billing_address_line_1" placeholder="Billing Address" value="<?php echo esc_attr($billing_address_line_1); ?>">
                            <input type="text" name="billing_address_line_2" placeholder="Apartment, suite, etc." value="<?php echo esc_attr($billing_address_line_2); ?>">
                        </div>

                        <div class="field-grid three-col">
                            <input type="text" name="billing_city" placeholder="Billing City" value="<?php echo esc_attr($billing_city); ?>">
                            <input type="text" name="billing_state" placeholder="Billing State" value="<?php echo esc_attr($billing_state); ?>">
                            <input type="text" name="billing_zip" placeholder="Billing ZIP" value="<?php echo esc_attr($billing_zip); ?>">
                        </div>

                        <div class="field-grid one-col">
                            <input type="text" name="billing_country" value="United States" readonly>
                        </div>
                    </div>
                </div>

                <div class="checkout-box">
                    <h3>Payment Information</h3>

                    <div class="accepted-cards">
                        <img src="https://img.icons8.com/color/96/visa.png" alt="Visa">
                        <img src="https://img.icons8.com/color/96/mastercard-logo.png" alt="Mastercard">
                        <img src="https://img.icons8.com/color/96/amex.png" alt="Amex">
                        <img src="https://img.icons8.com/color/96/discover.png" alt="Discover">
                    </div>

                    <div class="field-grid one-col">
                        <input type="text" name="card_number" placeholder="Card Number" inputmode="numeric" autocomplete="cc-number" maxlength="19" pattern="[0-9 ]{13,19}" aria-label="Card Number" required>
                    </div>

                    <div class="field-grid two-col">
                        <input type="text" name="expiry" placeholder="MM/YY" inputmode="numeric" autocomplete="cc-exp" maxlength="5" aria-label="Card expiry month and year" required>
                        <input type="password" name="cvv" placeholder="CVV" inputmode="numeric" autocomplete="cc-csc" maxlength="4" pattern="[0-9]{3,4}" aria-label="Card security code" required>
                    </div>
                </div>

                <div class="secure-note">
                    Your payment is encrypted and securely processed.
                </div>
            </div>

            <div class="checkout-right">
                <div class="summary-card">
                    <h4>Order Summary</h4>
                    <div class="mcn-coupon-checkout" style="padding:12px 0;border-bottom:1px solid #edf0f2;margin-bottom:8px;">
                        <label for="mcn_coupon_code" style="display:block;font-weight:600;margin-bottom:7px;">Coupon Code</label>
                        <div style="display:flex;gap:8px;">
                            <input id="mcn_coupon_code" name="coupon_code" type="text" value="<?php echo esc_attr($coupon_code); ?>" placeholder="Enter coupon code" style="min-width:0;flex:1;text-transform:uppercase;">
                            <button type="button" id="mcn_apply_coupon_btn" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('mcn_apply_checkout_coupon')); ?>" class="button" style="border:1px solid #20a7a7;border-radius:7px;padding:0 12px;cursor:pointer;">Apply</button>
                        </div>
                        <p id="mcn_coupon_message" aria-live="polite" style="margin:8px 0 0;color:<?php echo !empty($coupon_result['valid']) ? '#16803c' : '#b32d2e'; ?>;font-size:13px;" <?php echo $coupon_code === '' ? 'hidden' : ''; ?>><?php echo esc_html($coupon_result['message']); ?></p>
                    </div>

                    <div class="summary-row">
                        <span>Package</span>
                        <strong id="cap_summary_plan"><?php echo esc_html($plan); ?></strong>
                    </div>

                    <div class="summary-row" id="cap_summary_addons_row" style="display:none;">
                        <span>Add-ons</span>
                        <strong id="cap_summary_addons">—</strong>
                    </div>

                    <div class="summary-row">
                        <span>Subtotal</span>
                        <strong id="cap_summary_subtotal">$<?php echo esc_html(number_format($price, 2)); ?></strong>
                    </div>

                    <div class="summary-row" id="mcn_coupon_discount_row" <?php echo $coupon_discount <= 0 ? 'style="display:none;"' : ''; ?>><span>Coupon (<span id="mcn_coupon_applied_code"><?php echo esc_html($coupon_code); ?></span>)</span><strong id="mcn_coupon_discount_amount" style="color:#16803c;">−$<?php echo esc_html(number_format($coupon_discount, 2)); ?></strong></div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <strong>Included</strong>
                    </div>

                    <div class="summary-row total">
                        <span>Total</span>
                        <strong id="cap_summary_total">$<?php echo esc_html(number_format($display_price, 2)); ?></strong>
                    </div>
                </div>

                <div class="benefits-card">
                    <h4>Order Benefits</h4>
                    <div class="benefit-item"><span>✓</span><p><b>Fast approval process</b></p></div>
                    <div class="benefit-item"><span>✓</span><p><b>Secure checkout</b></p></div>
                    <div class="benefit-item"><span>✓</span><p><b>Licensed physician review</b></p></div>
                    <div class="benefit-item"><span>✓</span><p><b>Money-back guarantee if you are not approved</b></p></div>
                </div>

                <button type="button" name="process_payment" value="1" class="place-order-btn">
                    Submit Application
                </button>
            </div>
        </div>
    </div>

    <style>
        .auth-payment-form {
            max-width: 1100px;
            margin: 35px auto;
            font-family: inherit;
        }
        .checkout-wrap {
            display: grid;
            grid-template-columns: 2fr 0.9fr;
            gap: 26px;
            align-items: start;
        }
        .checkout-box,
        .summary-card,
        .benefits-card {
            background: #fff;
            border: 1px solid #e4e8ec;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
            box-sizing: border-box;
        }
        .checkout-box { margin-bottom: 18px; }
        .checkout-box h3,
        .summary-card h4,
        .benefits-card h4 {
            margin: 0 0 16px;
            font-size: 18px;
            font-weight: 700;
            color: #1d1d1f;
        }
        .field-grid {
            display: grid;
            gap: 12px;
            margin-bottom: 12px;
        }
        .field-grid.one-col   { grid-template-columns: 1fr; }
        .field-grid.two-col   { grid-template-columns: repeat(2, 1fr); }
        .field-grid.three-col { grid-template-columns: 1.2fr 0.8fr 0.8fr; }

        .auth-payment-form input[type="text"] {
            width: 100%;
            height: 44px;
            padding: 10px 13px;
            border: 1px solid #d9dee3;
            border-radius: 8px;
            font-size: 14px;
            color: #111;
            box-sizing: border-box;
            background: #fff;
        }
        .auth-payment-form input:focus {
            border-color: #23b6b6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(35, 182, 182, 0.12);
        }
        .billing-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #333;
            margin-top: 4px;
        }
        .billing-check input { accent-color: #20b6b6; }
        .billing-fields {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #e8ecef;
        }
        .accepted-cards {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .accepted-cards img {
            width: 78px;
            height: 52px;
            object-fit: contain;
            background: #fff;
            border: 1px solid #d9eeee;
            border-radius: 10px;
            padding: 8px 12px;
            box-sizing: border-box;
            transition: all 0.25s ease;
        }
        .accepted-cards img:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
            border-color: #2bb3b3;
        }
        .secure-note {
            background: #f6fbfb;
            border: 1px solid #d8eeee;
            color: #286b6b;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            margin-top: 12px;
        }
        .summary-card { margin-bottom: 16px; }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #edf0f2;
            font-size: 14px;
            color: #505050;
        }
        .summary-row strong {
            text-align: right;
            color: #111;
            font-weight: 700;
            max-width: 200px;
            word-break: break-word;
        }
        .summary-row.total {
            border-bottom: none;
            padding-top: 16px;
            font-size: 16px;
            color: #111;
            font-weight: 700;
        }
        .summary-row.total strong {
            font-size: 18px;
            color: #20a7a7;
        }
        .benefits-card {
            margin-bottom: 16px;
            background: #f9ffff;
        }
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }
        .benefit-item:last-child { margin-bottom: 0; }
        .benefit-item span {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #20b6b6;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex: 0 0 20px;
            margin-top: 1px;
        }
        .benefit-item p {
            margin: 0;
            font-size: 13px;
            line-height: 1.4;
            color: #333;
        }
        .place-order-btn {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 9px;
            background: #20a7a7;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.25s ease;
        }
        .place-order-btn:hover { background: #168b8b; }

        @media (max-width: 900px) {
            .checkout-wrap { grid-template-columns: 1fr; }
            .field-grid.two-col,
            .field-grid.three-col { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .accepted-cards { gap: 10px; }
            .accepted-cards img { width: 64px; height: 44px; padding: 6px 8px; }
        }
    </style>

    <script>
    (function () {
        'use strict';

        var CA_PRICING = {
            new_pdf_id_card:   64.99,
            caregiver_new:     99.99,
            growers_license:  189.99,
            new_pdf:           37.00,
            renewal_hard_copy: 47.00
        };
       var NON_CA_PRICING = <?php echo wp_json_encode($cap_non_ca_pricing); ?>;
       var NON_CA_LABELS  = <?php echo wp_json_encode($cap_non_ca_labels); ?>;

        var PLAN_LABELS = {
            new_pdf_id_card:   'Instant PDF + Patient ID Card',
            caregiver_new:     'Caregiver + New Patient',
            growers_license:   'License to Cultivate up to 99 Plants',
            new_pdf:           'New Patient — PDF Only',
            renewal_hard_copy: 'Existing Patient Renewal — With Hard Copy'
        };

        var LEGAL_PRICE = 79;

        var YEAR_PLANS = {
            '1_year': { years: 1, discount: 0  },
            '2_year': { years: 2, discount: 8  },
            '3_year': { years: 3, discount: 16 }
        };

        function readRadioOrText(name) {
            var el = document.querySelector('input[name="' + name + '"]:checked');
            if (el) return el.value;
            el = document.querySelector('input[name="' + name + '"]');
            return el ? el.value : '';
        }

        function readLegalLetters() {
            var allowed = [
                'parole',
                'employer',
                'driving',
                'possession_person'
            ];
        
            var selected = [];
        
            // Read checked legal-letter checkboxes.
            document.querySelectorAll(
                'input[name="ca_legal_support_letter_ui"]:checked'
            ).forEach(function (input) {
                if (allowed.includes(input.value)) {
                    selected.push(input.value);
                }
            });
        
            // Use the hidden field only if no checkbox is selected.
            if (selected.length === 0) {
                var hidden = document.querySelector(
                    'input[name="input_58"]'
                );
        
                if (hidden && hidden.value && hidden.value !== 'none') {
                    selected = hidden.value.split(',').filter(function (value) {
                        return allowed.includes(value.trim());
                    });
                }
            }
        
            return Array.from(new Set(selected));
        }

        // Keep legal-letter card styling and checked values synchronized.
        document.addEventListener('change', function (event) {
            if (!event.target.matches('input[name="ca_legal_support_letter_ui"]')) return;
            var card = event.target.closest('.ca-legal-card, .ca-legal-letter-card');
            if (card) card.classList.toggle('is-selected', event.target.checked);
            updateSummary();
        });

        function readYearPlan() {
            // 1. Hidden field on page
            var hidden = document.querySelector('input[name="input_63"]');
            if (hidden && hidden.value) return hidden.value;
        
            // 2. Selected card on page
            var sel = document.querySelector('.ca-years-card.is-selected');
            if (sel) return sel.getAttribute('data-value');
        
            // 3. sessionStorage
            try {
                var stored = sessionStorage.getItem('medcards_selected_year_plan');
                if (stored) return stored;
            } catch (e) {}
        
            return '3_year';
        }
        
        function readHardCopy() {
            var el = document.querySelector('input[name="input_54"]:checked');
            if (el) return el.value;
        
            try {
                var stored = sessionStorage.getItem('medcards_selected_hard_copy');
                if (stored) return stored;
            } catch (e) {}
        
            return 'no';
        }
        
        function readIdCard() {
            var el = document.querySelector('input[name="input_61"]');
            if (el && el.value) return el.value;
        
            try {
                var stored = sessionStorage.getItem('medcards_selected_id_card');
                if (stored) return stored;
            } catch (e) {}
        
            return 'no';
        }
        

         function computeTotal() {
            var planKey = readRadioOrText('input_11');
 
            // No Gravity Form on page -> do not touch the amount fields.
            if (!planKey) {
                return null;
            }
 
            // NON-CA FIX: generic plans are a flat price, no add-ons.
            if (NON_CA_PRICING[planKey] !== undefined) {
                return {
                    total:     Number(NON_CA_PRICING[planKey]),
                    planKey:   planKey,
                    planLabel: NON_CA_LABELS[planKey] || planKey,
                    addons:    [],
                    yearKey:   '',
                    hardCopy:  'no',
                    idCard:    'no',
                    legal:     []
                };
            }
 
            if (!CA_PRICING[planKey]) {
                return null;
            }

            var base    = CA_PRICING[planKey];
            var yearKey = readYearPlan();
            var yearCfg = YEAR_PLANS[yearKey] || YEAR_PLANS['1_year'];

            var original       = base * yearCfg.years;
            var discountAmount = original * (yearCfg.discount / 100);
            var adjustedBase   = original - discountAmount;

            var total  = adjustedBase;
            var addons = [];

            addons.push(
                yearCfg.years + ' Year' + (yearCfg.years > 1 ? 's' : '') +
                (yearCfg.discount > 0 ? ' (Save ' + yearCfg.discount + '%)' : '')
            );

            var hardCopy = readRadioOrText('input_54') || 'no';
            if (planKey !== 'renewal_hard_copy' && hardCopy === 'yes') {
                total += 10;
                addons.push('Hard Copy +$10.00');
            }

            var legal = readLegalLetters();

            if (legal.length > 0) {
                var legalTotal = legal.length * LEGAL_PRICE;
                total += legalTotal;
            
                addons.push(
                    'Legal Letters (' + legal.length + ') +$' +
                    legalTotal.toFixed(2)
                );
            }

            var idCard = readRadioOrText('input_61') || 'no';
            if (planKey === 'new_pdf_id_card') {
                addons.push('ID Card Included');
            } else if (idCard === 'yes') {
                total += 16;
                addons.push('ID Card +$16.00');
            }

            return {
                total: total,
                planKey: planKey,
                planLabel: PLAN_LABELS[planKey] || planKey,
                addons: addons,
                yearKey: yearKey,
                hardCopy: hardCopy,
                idCard: idCard,
                legal: legal
            };
        }

        // Apply coupon immediately via AJAX; do not submit the payment form or validate card fields.
        (function setupInstantCouponApply() {
            var button = document.getElementById('mcn_apply_coupon_btn');
            var input = document.getElementById('mcn_coupon_code');
            var message = document.getElementById('mcn_coupon_message');
            if (!button || !input) return;

            button.addEventListener('click', function () {
                if (typeof window.medcardsUpdatePaymentSummary === 'function') {
                    window.medcardsUpdatePaymentSummary();
                }
                var data = new URLSearchParams();
                data.append('action', 'mcn_apply_checkout_coupon');
                data.append('nonce', button.getAttribute('data-nonce') || '');
                data.append('coupon_code', input.value.trim().toUpperCase());
                data.append('plan_key', (document.getElementById('cap_live_plan_key') || {}).value || '');
                data.append('year_plan', (document.getElementById('cap_live_year_plan') || {}).value || '3_year');
                data.append('hard_copy', (document.getElementById('cap_live_hard_copy') || {}).value || 'no');
                data.append('id_card', (document.getElementById('cap_live_id_card') || {}).value || 'no');
                data.append('legal', (document.getElementById('cap_live_legal') || {}).value || '');

                button.disabled = true;
                button.textContent = 'Checking…';
                fetch(button.getAttribute('data-ajax-url'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: data.toString()
                }).then(function (response) { return response.json(); })
                  .then(function (response) {
                    var discountEl = document.getElementById('cap_coupon_discount');
                    var row = document.getElementById('mcn_coupon_discount_row');
                    var discountAmount = document.getElementById('mcn_coupon_discount_amount');
                    var appliedCode = document.getElementById('mcn_coupon_applied_code');
                    var totalEl = document.getElementById('cap_summary_total');
                    var subtotalEl = document.getElementById('cap_summary_subtotal');
                    var amountEl = document.getElementById('cap_live_amount');

                    if (response && response.success && response.data) {
                        var discount = parseFloat(response.data.discount) || 0;
                        var total = parseFloat(response.data.total) || 0;
                        if (discountEl) discountEl.value = discount.toFixed(2);
                        if (row) row.style.display = discount > 0 ? '' : 'none';
                        if (discountAmount) discountAmount.textContent = '−$' + discount.toFixed(2);
                        if (appliedCode) appliedCode.textContent = response.data.code;
                        if (subtotalEl) subtotalEl.textContent = '$' + (parseFloat(response.data.subtotal) || 0).toFixed(2);
                        if (totalEl) totalEl.textContent = '$' + total.toFixed(2);
                        if (amountEl) amountEl.value = total.toFixed(2);
                        input.value = response.data.code;
                        if (message) { message.hidden = false; message.style.color = '#16803c'; message.textContent = response.data.message; }
                    } else {
                        if (discountEl) discountEl.value = '0.00';
                        if (row) row.style.display = 'none';
                        if (message) { message.hidden = false; message.style.color = '#b32d2e'; message.textContent = (response && response.data && response.data.message) || 'Could not apply this coupon. Please try again.'; }
                        if (typeof window.medcardsUpdatePaymentSummary === 'function') window.medcardsUpdatePaymentSummary();
                    }
                  }).catch(function () {
                    if (message) { message.hidden = false; message.style.color = '#b32d2e'; message.textContent = 'Could not check coupon. Please try again.'; }
                  }).finally(function () {
                    button.disabled = false;
                    button.textContent = 'Apply';
                  });
            });
        })();

        function updateSummary() {
            var data = computeTotal();

            // Gravity Form not present -> leave hidden fields and summary as-is.
            if (!data) return;

            var planEl     = document.getElementById('cap_summary_plan');
            var addonsEl   = document.getElementById('cap_summary_addons');
            var addonsRow  = document.getElementById('cap_summary_addons_row');
            var subtotalEl = document.getElementById('cap_summary_subtotal');
            var totalEl    = document.getElementById('cap_summary_total');

            if (planEl) planEl.textContent = data.planLabel || 'Medical Cannabis Card';

            if (data.addons && data.addons.length) {
                if (addonsRow) addonsRow.style.display = '';
                if (addonsEl)  addonsEl.textContent = data.addons.join(' · ');
            } else {
                if (addonsRow) addonsRow.style.display = 'none';
            }

            // Keep subtotal at the regular price, but preserve the server-validated
            // coupon discount in the displayed total and hidden checkout amount.
            var couponDiscountEl = document.getElementById('cap_coupon_discount');
            var couponDiscount = couponDiscountEl ? (parseFloat(couponDiscountEl.value) || 0) : 0;
            var baseTotal = Number(data.total || 0);
            var payableTotal = Math.max(0, Math.round((baseTotal - couponDiscount) * 100) / 100);
            var subtotalText = '$' + baseTotal.toFixed(2);
            var totalText = '$' + payableTotal.toFixed(2);
            if (subtotalEl) subtotalEl.textContent = subtotalText;
            if (totalEl)    totalEl.textContent = totalText;

            var f1 = document.getElementById('cap_live_plan_key');  if (f1) f1.value = data.planKey || '';
            var f2 = document.getElementById('cap_live_year_plan'); if (f2) f2.value = data.yearKey || '';
            var f3 = document.getElementById('cap_live_hard_copy'); if (f3) f3.value = data.hardCopy || '';
            var f4 = document.getElementById('cap_live_id_card');   if (f4) f4.value = data.idCard || '';
            var f5 = document.getElementById('cap_live_legal');     if (f5) f5.value = (data.legal || []).join(',');
            var f6 = document.getElementById('cap_live_amount');    if (f6) f6.value = payableTotal.toFixed(2);
        }

        document.addEventListener('change', updateSummary, true);
        document.addEventListener('input',  updateSummary, true);

        document.addEventListener('click', function (e) {
            if (
                e.target.closest('.ca-legal-card') ||
                e.target.closest('.ca-years-card') ||
                e.target.closest('.ca-id-add') ||
                e.target.closest('.ca-id-no') ||
                e.target.closest('.ca-legal-clear')
            ) {
                setTimeout(updateSummary, 50);
                setTimeout(updateSummary, 250);
            }
        }, true);

        document.addEventListener('gform_post_render', function () {
            setTimeout(updateSummary, 100);
            setTimeout(updateSummary, 400);
        });

        function boot() {
            setTimeout(updateSummary, 200);
            setTimeout(updateSummary, 800);
            setTimeout(updateSummary, 1500);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }

        var payForm = document.getElementById('medcards-payment-form');
        if (payForm) {
            payForm.addEventListener('submit', function () {
                updateSummary();
            }, true);
        }

        window.medcardsUpdatePaymentSummary = updateSummary;

        document.addEventListener('DOMContentLoaded', function () {
            var checkbox = document.getElementById('same_as_shipping');
            var billingFields = document.querySelector('.billing-fields');

            if (checkbox && billingFields) {
                function toggleBillingFields() {
                    billingFields.style.display = checkbox.checked ? 'none' : 'block';
                }
                checkbox.addEventListener('change', toggleBillingFields);
                toggleBillingFields();
            }

            // var cardInput = document.querySelector('input[name="card_number"]');
            // if (cardInput) {
            //     cardInput.addEventListener('input', function (e) {
            //         var value = e.target.value.replace(/\D/g, '').substring(0, 16);
            //         e.target.value = value.replace(/(.{4})/g, '$1 ').trim();
            //     });
            // }

            // var expiryInput = document.querySelector('input[name="expiry"]');
            // if (expiryInput) {
            //     expiryInput.addEventListener('input', function (e) {
            //         var value = e.target.value.replace(/\D/g, '').substring(0, 4);
            //         var month = value.substring(0, 2);

            //         if (month.length === 2) {
            //             var monthNum = parseInt(month, 10);
            //             if (monthNum > 12) month = '12';
            //             if (monthNum === 0) month = '01';
            //         }

            //         if (value.length > 2) {
            //             value = month + '/' + value.substring(2);
            //         } else {
            //             value = month;
            //         }
            //         e.target.value = value;
            //     });
            // }

            // var cvvInput = document.querySelector('input[name="cvv"]');
            // if (cvvInput) {
            //     cvvInput.addEventListener('input', function (e) {
            //         e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
            //     });
            // }
        });

    })();
    </script>

    <script>
        (function () {
            'use strict';

            var form = document.getElementById('medcards-payment-form');
            if (!form) return;

            // This checkout can live inside Gravity Forms, so it must be a DIV, not a nested FORM.
            function getField(name) { return form.querySelector('[name="' + name + '"]'); }
            var cardEl  = getField('card_number');
            var expEl   = getField('expiry');
            var cvvEl   = getField('cvv');
            var btn     = form.querySelector('.place-order-btn');
            var modal   = document.getElementById('cap-error-modal');
            var titleEl = document.getElementById('cap-error-modal-title');
            var msgEl   = document.getElementById('cap-error-modal-message');
            var closeBt = document.getElementById('cap-error-modal-close');
            var btnLabel = btn ? btn.textContent.trim() : 'Submit Application';
            var busy = false;
            var focusField = null;

            // Move modal to <body> so theme containers can't clip it.
            if (modal && modal.parentNode !== document.body) document.body.appendChild(modal);

            var MSG = {
                invalid_card:   ['Invalid Card Number', 'The card number appears to be invalid. Please check your card details and try again.'],
                invalid_expiry: ['Invalid Expiry Date', 'Please enter the card expiry date in MM/YY format.'],
                expired_card:   ['Card Expired', 'This card has expired. Please use a different card.'],
                invalid_cvv:    ['Invalid CVV', 'The CVV appears to be invalid. Please check your card security code and try again.']
            };

            var FIELD_FOR_CODE = {
                invalid_card: cardEl, declined: cardEl, invalid_expiry: expEl,
                expired_card: expEl, invalid_cvv: cvvEl,
                avs_mismatch: getField('address_line_1'),
                invalid_address: getField('address_line_1')
            };

            /* ---------- popup ---------- */
            function showError(title, message, field) {
                title = title || 'Payment Error';
                message = message || 'The payment could not be processed. Please check your details and try again.';
                if (field && field.classList) field.classList.add('cap-field-error');
                focusField = field || null;
                // Modal can be moved/inserted by the theme; resolve it again if needed.
                if (!modal) modal = document.getElementById('cap-error-modal');
                if (!titleEl) titleEl = document.getElementById('cap-error-modal-title');
                if (!msgEl) msgEl = document.getElementById('cap-error-modal-message');
                if (!modal || !titleEl || !msgEl) { window.alert(title + '\n\n' + message); return; }
                titleEl.textContent = title;
                msgEl.textContent = message;
                modal.setAttribute('aria-hidden', 'false');
                modal.style.setProperty('display', 'flex', 'important');
                if (closeBt) closeBt.focus();
            }
            function hideError() {
                if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
                if (focusField) { try { focusField.focus(); } catch (e) {} }
            }
            if (closeBt) closeBt.addEventListener('click', hideError);
            if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) hideError(); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.style.display === 'flex') hideError();
            });
            window.capShowPaymentError = showError;

            /* ---------- helpers ---------- */
            function digits(v) { return String(v || '').replace(/\D/g, ''); }
            function isAmex(num) { return /^3[47]/.test(num); }

            function luhn(num) {
                var sum = 0, alt = false;
                for (var i = num.length - 1; i >= 0; i--) {
                    var n = parseInt(num.charAt(i), 10);
                    if (alt) { n *= 2; if (n > 9) n -= 9; }
                    sum += n; alt = !alt;
                }
                return sum % 10 === 0;
            }

            function formatCard(raw) {
                var d = digits(raw);
                if (isAmex(d)) {
                    d = d.substr(0, 15);
                    return [d.substr(0, 4), d.substr(4, 6), d.substr(10, 5)].filter(Boolean).join(' ');
                }
                // Visa test/card numbers beginning with 4 are limited to 16 digits here.
                // Other standard non-Amex cards are also capped at 16 digits for this form.
                d = d.substr(0, 16);
                return d.replace(/(.{4})/g, '$1 ').trim();
            }

            function formatExpiry(raw) {
                var d = digits(raw);
                // Accept MMYY, MMYYYY, or partially typed values; display MM/YY.
                if (d.length > 4) d = d.substr(0, 2) + d.substr(-2);
                d = d.substr(0, 4);
                if (d.length === 1 && d > '1') d = '0' + d;
                if (d.length >= 2) {
                    var m = parseInt(d.substr(0, 2), 10);
                    if (m === 0) d = '01' + d.substr(2);
                    if (m > 12) d = '12' + d.substr(2);
                }
                return d.length > 2 ? d.substr(0, 2) + '/' + d.substr(2) : d;
            }

            function clearErr(el) { if (el) el.classList.remove('cap-field-error'); }

            /* ---------- live formatting ---------- */
            ['input', 'change'].forEach(function (evt) {
                if (cardEl) cardEl.addEventListener(evt, function () { cardEl.value = formatCard(cardEl.value); clearErr(cardEl); });
                if (expEl)  expEl.addEventListener(evt,  function () { expEl.value  = formatExpiry(expEl.value); clearErr(expEl); });
                if (cvvEl)  cvvEl.addEventListener(evt,  function () { cvvEl.value  = digits(cvvEl.value).substr(0, 4); clearErr(cvvEl); });
            });

            /* ---------- sync current Gravity Forms address values ---------- */
            function firstValue(selectors) {
                for (var i = 0; i < selectors.length; i++) {
                    var el = document.querySelector(selectors[i]);
                    if (el && typeof el.value === 'string' && el.value.trim() !== '') {
                        return el.value.trim();
                    }
                }
                return '';
            }

            function syncAddressFromProfile() {
                var map = [
                    {
                        target: 'address_line_1',
                        selectors: [
                            'input[name="input_37.1"]', '#input_2_37_1', 'input[name="input_2_37_1"]',
                            '#input_2_37', 'input[name="input_37"]', 'input[name="input_2_37"]'
                        ]
                    },
                    {
                        target: 'address_line_2',
                        selectors: [
                            'input[name="input_37.2"]', '#input_2_37_2', 'input[name="input_2_37_2"]',
                            'input[name="input_38"]', 'input[name="input_2_38"]', '#input_2_38'
                        ]
                    },
                    {
                        target: 'city',
                        selectors: [
                            'input[name="input_37.3"]', '#input_2_37_3', 'input[name="input_2_37_3"]',
                            'input[name="input_39"]', 'input[name="input_2_39"]', '#input_2_39'
                        ]
                    },
                    {
                        target: 'state',
                        selectors: [
                            'input[name="input_37.4"]', '#input_2_37_4', 'input[name="input_2_37_4"]',
                            'input[name="input_40"]', 'input[name="input_2_40"]', '#input_2_40',
                            'select[name="input_40"]', 'select[name="input_2_40"]'
                        ]
                    },
                    {
                        target: 'zip_code',
                        selectors: [
                            'input[name="input_37.5"]', '#input_2_37_5', 'input[name="input_2_37_5"]',
                            'input[name="input_41"]', 'input[name="input_2_41"]', '#input_2_41'
                        ]
                    }
                ];

                map.forEach(function (item) {
                    var target = getField(item.target);
                    var value = firstValue(item.selectors);
                    if (target && value) target.value = value;
                });

                var same = getField('same_as_shipping');
                if (same && same.checked) {
                    ['address_line_1', 'address_line_2', 'city', 'state', 'zip_code'].forEach(function (name) {
                        var target = getField(name);
                        if (target) target.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                }
            }

            /* ---------- validation ---------- */
            function fail(code, field) {
                var m = MSG[code];
                return { code: code, field: field, title: m[0], message: m[1] };
            }

            function validate() {
                var req = [
                    ['address_line_1', 'shipping address'],
                    ['city', 'city'],
                    ['state', 'state'],
                    ['zip_code', 'ZIP code']
                ];
                var same = getField('same_as_shipping');
                if (same && !same.checked) {
                    req.push(['billing_address_line_1', 'billing address'], ['billing_city', 'billing city'],
                            ['billing_state', 'billing state'], ['billing_zip', 'billing ZIP code']);
                }
                for (var i = 0; i < req.length; i++) {
                    var el = getField(req[i][0]);
                    if (el && !String(el.value).trim()) {
                        return { code: 'invalid_address', field: el, title: 'Missing Information',
                                message: 'Please enter your ' + req[i][1] + '.' };
                    }
                }

                var num = digits(cardEl.value);
                var amex = isAmex(num);
                if (num.length < 13 || num.length > 19 || (amex && num.length !== 15) || !luhn(num)) {
                    return fail('invalid_card', cardEl);
                }

                var m = /^(\d{2})\/(\d{2})$/.exec(expEl.value.trim());
                if (!m) return fail('invalid_expiry', expEl);
                var month = parseInt(m[1], 10), year = 2000 + parseInt(m[2], 10);
                if (month < 1 || month > 12) return fail('invalid_expiry', expEl);
                var now = new Date(), ny = now.getFullYear(), nm = now.getMonth() + 1;
                if (year < ny || (year === ny && month < nm)) return fail('expired_card', expEl);
                if (year > ny + 20) return fail('invalid_expiry', expEl);

                var cvv = digits(cvvEl.value);
                if (cvv.length !== (amex ? 4 : 3)) return fail('invalid_cvv', cvvEl);

                return null;
            }

            /* ---------- submit via AJAX ---------- */
            function resetButton() {
                busy = false;
                if (btn) { btn.disabled = false; btn.textContent = btnLabel; }
            }

            if (!btn) return;
            btn.addEventListener('click', function (e) {
                // Payment is AJAX-only. The button is type=button so Gravity Forms cannot submit/reload.
                e.preventDefault();
                e.stopPropagation();
                if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                if (busy) return false;

                try {
                    syncAddressFromProfile();
                    var problem = validate();
                    if (problem) {
                        showError(problem.title, problem.message, problem.field);
                        return false;
                    }
                    if (typeof window.medcardsUpdatePaymentSummary === 'function') window.medcardsUpdatePaymentSummary();

                    busy = true;
                    if (btn) { btn.disabled = true; btn.textContent = 'Processing…'; }
                    var fd = new FormData();
                    Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name], textarea[name]'), function (control) {
                        if (control.disabled || !control.name) return;
                        if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;
                        if (control.type === 'file') {
                            Array.prototype.forEach.call(control.files || [], function (file) { fd.append(control.name, file); });
                        } else {
                            fd.append(control.name, control.value);
                        }
                    });
                    fd.set('action', 'cap_process_payment');
                    var ajaxUrl = form.getAttribute('data-ajax-url');
                    if (!ajaxUrl) throw new Error('Payment endpoint is missing.');

                    fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd, headers: {'Accept': 'application/json'} })
                        .then(function (r) {
                            return r.text().then(function (body) {
                                var parsed;
                                try { parsed = JSON.parse(body); }
                                catch (parseError) { throw new Error('The payment server returned an unexpected response. Please try again.'); }
                                return parsed;
                            });
                        })
                        .then(function (res) {
                            if (res && res.success === true && res.data && res.data.redirect) {
                                if (btn) btn.textContent = 'Payment approved…';
                                window.location.assign(res.data.redirect);
                                return;
                            }
                            var d = (res && res.data) || {};
                            showError(d.title || 'Payment Not Approved',
                                d.message || 'Authorize.net did not approve this payment. Please check your card details or try another card.',
                                FIELD_FOR_CODE[d.code]);
                            resetButton();
                        })
                        .catch(function (err) {
                            showError('Payment Processing Error',
                                (err && err.message) || 'We could not confirm the payment result. Please try again.');
                            resetButton();
                        });
                } catch (err) {
                    showError('Payment Processing Error', 'The payment form could not be submitted safely. Please refresh the page and try again.');
                    resetButton();
                }
                return false;
            });

            // Error returned by a non-JS / fallback form post.
            if (window.capServerPaymentError) {
                var se = window.capServerPaymentError;
                showError(se.title, se.message, FIELD_FOR_CODE[se.code]);
            }
        })();
    </script>


    <script>
    /* Delegated formatter: also works if WordPress/Gravity Forms inserts the payment form dynamically. */
    (function () {
        'use strict';
        function digits(value) { return String(value || '').replace(/\D/g, ''); }
        function formatCard(value) {
            var d = digits(value);
            if (/^3[47]/.test(d)) {
                d = d.slice(0, 15);
                return [d.slice(0, 4), d.slice(4, 10), d.slice(10, 15)].filter(Boolean).join(' ');
            }
            // Prevent a 19-digit value such as 4111 1111 1111 1111 111 on this form.
            d = d.slice(0, 16);
            return d.replace(/(.{4})/g, '$1 ').trim();
        }
        function formatExpiry(value) {
            var d = digits(value);
            if (d.length > 4) d = d.slice(0, 2) + d.slice(-2);
            d = d.slice(0, 4);
            if (d.length === 1 && d > '1') d = '0' + d;
            if (d.length >= 2) {
                var month = parseInt(d.slice(0, 2), 10);
                if (month === 0) d = '01' + d.slice(2);
                if (month > 12) d = '12' + d.slice(2);
            }
            return d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
        }
        document.addEventListener('input', function (event) {
            var el = event.target;
            if (!el || !el.name) return;
            if (el.name === 'card_number') {
                el.value = formatCard(el.value);
                el.maxLength = 19;
            } else if (el.name === 'expiry') {
                el.value = formatExpiry(el.value);
                el.maxLength = 5;
                el.placeholder = 'MM/YY';
            } else if (el.name === 'cvv') {
                var form = el.form || el.closest('form');
                var card = form ? form.querySelector('[name="card_number"]') : null;
                var amex = card && /^3[47]/.test(digits(card.value));
                el.value = digits(el.value).slice(0, amex ? 4 : 3);
                el.maxLength = amex ? 4 : 3;
            }
        }, true);
        document.addEventListener('paste', function (event) {
            var el = event.target;
            if (!el || !['card_number', 'expiry', 'cvv'].includes(el.name)) return;
            window.setTimeout(function () {
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        }, true);
    })();
    </script>

    <?php
    return ob_get_clean();
});

/* =========================================================
 * EMAIL PATIENT ID CARD PDF AFTER SUCCESSFUL PAYMENT
 *
 * Requires Dompdf installed in:
 * custom-authorize-payment/vendor/autoload.php
 * Install from the child-theme folder:
 * composer require dompdf/dompdf
 *
 * Only plans 2-5 trigger the PDF email. The default
 * new_pdf_id_card plan (box 1) is intentionally excluded.
 * ========================================================= */































/**
 * Email patient ID card PDF after successful payment.
 */
if (!function_exists('cap_email_patient_card_pdf_after_payment')) {
    function cap_email_patient_card_pdf_after_payment($user_id, $plan_key, $transaction_id)
    {
        $user_id        = absint($user_id);
        $transaction_id = sanitize_text_field((string) $transaction_id);
        $plan_key       = sanitize_key((string) $plan_key);

        if (!$user_id || empty($transaction_id)) {
            error_log('MCN PDF email failed: Invalid user ID or transaction ID.');
            return false;
        }

        // Prevent duplicate emails for the same transaction
        $sent_transaction = (string) get_user_meta($user_id, 'payment_pdf_email_transaction_id', true);
        if ($sent_transaction === $transaction_id) {
            return true;
        }

        $user = get_userdata($user_id);
        if (!$user || !is_email($user->user_email)) {
            error_log('MCN PDF email failed: No valid email for user ID ' . $user_id);
            return false;
        }

        // Load Autoloader for Dompdf
        $vendor_paths = array(
            get_stylesheet_directory() . '/custom-authorize-payment/vendor/autoload.php',
            get_stylesheet_directory() . '/custom-authorize-payment/dompdf-libs/autoload.inc.php'
        );

        foreach ($vendor_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }

        if (!class_exists('\Dompdf\Dompdf')) {
            error_log('MCN PDF email failed: Dompdf class not loaded.');
            return false;
        }

        // The ID card comes from the "Patient ID card" module. If that module is
        // switched off, skip the PDF instead of emailing one with a raw shortcode in it.
        if (!shortcode_exists('medcards_california_id_card_cards')) {
            error_log('MCN PDF email skipped: the Patient ID card module is not active.');
            return false;
        }

        // Switch to the target user context to accurately generate shortcode content
        $current_user = get_current_user_id();
        wp_set_current_user($user_id);
        $card_html = do_shortcode('[medcards_california_id_card_cards]');
        wp_set_current_user($current_user);

        if (empty(trim(wp_strip_all_tags($card_html)))) {
            error_log('MCN PDF email failed: ID card shortcode returned empty content.');
            return false;
        }

        // Full HTML wrapper with inline styling compatibility for Dompdf
        $full_html = '<!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                @page { margin: 10px; }
                body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
                .mcn-ca-card-wrapper { width: 100%; max-width: 800px; margin: 0 auto; }
                img { max-width: 100%; height: auto; }
            </style>
        </head>
        <body>' . $card_html . '</body>
        </html>';

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($full_html, 'UTF-8');
            $dompdf->setPaper('letter', 'portrait');
            $dompdf->render();

            $pdf_bytes = $dompdf->output();

            if (empty($pdf_bytes)) {
                error_log('MCN PDF email failed: Rendered PDF is empty.');
                return false;
            }

            // Write PDF file temporarily
            $upload_dir = wp_upload_dir();
            $temp_dir   = trailingslashit($upload_dir['basedir']) . 'mcn-temp-cards';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            $pdf_filename = 'medical-card-' . $user_id . '-' . time() . '.pdf';
            $pdf_path     = trailingslashit($temp_dir) . $pdf_filename;

            file_put_contents($pdf_path, $pdf_bytes);

            // Send Email
            $subject = 'Your Med Cards Now Patient ID Card';
            $message = "<p>Hi " . esc_html($user->display_name) . ",</p>"
                     . "<p>Thank you for your payment. Your California Medical Cannabis Patient ID Card is attached to this email.</p>"
                     . "<p><strong>Transaction ID:</strong> " . esc_html($transaction_id) . "</p>"
                     . "<p>Best regards,<br>Med Cards Now Team</p>";

            $headers = array(
                'From: Med Cards Now <contact@medcardsnow.com>',
                'Content-Type: text/html; charset=UTF-8'
            );

            $mail_sent = wp_mail($user->user_email, $subject, $message, $headers, array($pdf_path));

            // Clean up temporary file
            if (file_exists($pdf_path)) {
                @unlink($pdf_path);
            }

            if ($mail_sent) {
                update_user_meta($user_id, 'payment_pdf_email_transaction_id', $transaction_id);
                return true;
            } else {
                error_log('MCN PDF email failed: wp_mail returned false.');
                return false;
            }
        } catch (\Throwable $e) {
            error_log('MCN PDF email exception: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Entry point. Verifies nonce, prevents double-submits, then processes the payment.
 * Returns array('success' => bool, 'error' => code, ...)
 */
function cap_process_payment_request()
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return cap_payment_result(false, 'not_logged_in');
    }

    if (
        !isset($_POST['custom_authorize_payment_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['custom_authorize_payment_nonce'])), 'custom_authorize_payment_action')
    ) {
        return cap_payment_result(false, 'security');
    }

    // Block double-clicks / parallel submits (prevents double charging).
    $lock_key = 'cap_pay_lock_' . $user_id;
    if (get_transient($lock_key)) {
        return cap_payment_result(false, 'processing');
    }
    set_transient($lock_key, 1, 60);

    $result = cap_process_payment_locked($user_id);

    delete_transient($lock_key);
    return $result;
}

function cap_process_payment_locked($user_id)
{
    /* ---------- PRICE (server-side only) ---------- */
    $live_plan_key  = isset($_POST['live_plan_key'])  ? sanitize_key(wp_unslash($_POST['live_plan_key']))  : '';
    $live_year_plan = isset($_POST['live_year_plan']) ? sanitize_key(wp_unslash($_POST['live_year_plan'])) : '1_year';
    $live_hard_copy = isset($_POST['live_hard_copy']) ? sanitize_text_field(wp_unslash($_POST['live_hard_copy'])) : 'no';
    $live_id_card   = isset($_POST['live_id_card'])   ? sanitize_text_field(wp_unslash($_POST['live_id_card']))   : 'no';
    $live_legal     = isset($_POST['live_legal'])     ? sanitize_text_field(wp_unslash($_POST['live_legal']))     : '';

    /* Plan key fallbacks: the hidden field can be empty if the checkout JS did
       not sync (or the page was reloaded). Mirror the display-side chain so the
       charge can still resolve the selected plan. */
    if ($live_plan_key === '' && function_exists('rgpost')) {
        $live_plan_key = sanitize_key((string) rgpost('input_11'));
    }
    if ($live_plan_key === '') {
        $live_plan_key = sanitize_key((string) get_user_meta($user_id, 'payment_selected_plan', true));
    }
    if ($live_plan_key === '') {
        $live_plan_key = sanitize_key((string) get_user_meta($user_id, 'selected_plan', true));
    }

    $price = 0.0;

    if ($live_plan_key !== '') {
        $price = cap_compute_server_total($live_plan_key, $live_year_plan, $live_hard_copy, $live_id_card, $live_legal);
    }

    if ($price <= 0) {
        $raw_price = get_user_meta($user_id, 'payment_amount', true);
        $raw_price = is_scalar($raw_price) ? trim((string) $raw_price) : '';
        if ($raw_price !== '' && preg_match('/^\d+(?:\.\d{1,2})?$/', $raw_price) && (float) $raw_price > 0) {
            $price = (float) $raw_price;
        }
    }

    if ($price <= 0) {
        error_log(sprintf(
            'MCN amount_missing: user=%d plan_key=%s pricing_state=%s raw_state_meta=%s payment_amount_meta=%s',
            $user_id,
            $live_plan_key !== '' ? $live_plan_key : '(empty)',
            mcn_get_pricing_state($user_id) !== '' ? mcn_get_pricing_state($user_id) : '(empty)',
            (string) get_user_meta($user_id, 'state', true),
            (string) get_user_meta($user_id, 'payment_amount', true)
        ));
        return cap_payment_result(false, 'amount_missing');
    }

    /* ---------- COUPON ---------- */
    $subtotal      = $price;
    $coupon_code   = isset($_POST['coupon_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['coupon_code']))) : '';
    $coupon_result = $coupon_code !== ''
        ? mcn_get_coupon_discount($coupon_code, $subtotal)
        : array('valid' => false, 'discount' => 0, 'message' => '');

    if ($coupon_code !== '' && empty($coupon_result['valid'])) {
        return cap_payment_result(false, 'invalid_coupon');
    }

    $coupon_discount = !empty($coupon_result['valid']) ? (float) $coupon_result['discount'] : 0;
    $charge          = round($subtotal - $coupon_discount, 2);

    if ($charge <= 0) {
        return cap_payment_result(false, 'amount_missing');
    }
    $price = number_format($charge, 2, '.', '');

    /* ---------- CARD VALIDATION (server-side) ---------- */
    $card_number = isset($_POST['card_number']) ? preg_replace('/\D/', '', wp_unslash($_POST['card_number'])) : '';
    $expiry      = isset($_POST['expiry']) ? sanitize_text_field(wp_unslash($_POST['expiry'])) : '';
    $cvv         = isset($_POST['cvv']) ? preg_replace('/\D/', '', wp_unslash($_POST['cvv'])) : '';
    $is_amex     = (bool) preg_match('/^3[47]/', $card_number);

    if (
        strlen($card_number) < 13 || strlen($card_number) > 19 ||
        ($is_amex && strlen($card_number) !== 15) ||
        !cap_luhn_check($card_number)
    ) {
        return cap_payment_result(false, 'invalid_card');
    }

    if (!preg_match('/^(\d{1,2})\s*\/\s*(\d{2}|\d{4})$/', $expiry, $m)) {
        return cap_payment_result(false, 'invalid_expiry');
    }
    $exp_month = (int) $m[1];
    $exp_year  = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];
    $now_year  = (int) current_time('Y');
    $now_month = (int) current_time('n');

    if ($exp_month < 1 || $exp_month > 12 || $exp_year > $now_year + 20) {
        return cap_payment_result(false, 'invalid_expiry');
    }
    if ($exp_year < $now_year || ($exp_year === $now_year && $exp_month < $now_month)) {
        return cap_payment_result(false, 'expired_card');
    }
    $formatted_expiry = sprintf('%04d-%02d', $exp_year, $exp_month);

    if (strlen($cvv) !== ($is_amex ? 4 : 3)) {
        return cap_payment_result(false, 'invalid_cvv');
    }

    /* ---------- ADDRESSES ---------- */
    $address_line_1 = isset($_POST['address_line_1']) ? sanitize_text_field(wp_unslash($_POST['address_line_1'])) : '';
    $address_line_2 = isset($_POST['address_line_2']) ? sanitize_text_field(wp_unslash($_POST['address_line_2'])) : '';
    $city           = isset($_POST['city'])           ? sanitize_text_field(wp_unslash($_POST['city']))           : '';
    $state          = isset($_POST['state'])          ? sanitize_text_field(wp_unslash($_POST['state']))          : '';
    $zip_code       = isset($_POST['zip_code'])       ? sanitize_text_field(wp_unslash($_POST['zip_code']))       : '';
    $country        = 'United States';

    if ($address_line_1 === '' || $city === '' || $state === '' || $zip_code === '') {
        return cap_payment_result(false, 'invalid_address');
    }

    update_user_meta($user_id, 'address_line_1', $address_line_1);
    update_user_meta($user_id, 'address_line_2', $address_line_2);
    update_user_meta($user_id, 'city', $city);
    update_user_meta($user_id, 'country', $country);
    update_user_meta($user_id, 'shipping_state', $state);
    // The shipping state must not replace the patient's card state (it drives
    // their plans and prices). Only fill it in if the profile has none yet.
    $card_state_missing = ((string) get_user_meta($user_id, 'state', true) === '');
    if ($card_state_missing) {
        update_user_meta($user_id, 'state', mcn_normalize_state_name($state));
    }
    update_user_meta($user_id, 'zip_code', $zip_code);

    $same_as_shipping = isset($_POST['same_as_shipping']) ? 'yes' : 'no';
    update_user_meta($user_id, 'billing_same_as_shipping', $same_as_shipping);

    if ($same_as_shipping === 'no') {
        $b1 = isset($_POST['billing_address_line_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_line_1'])) : '';
        $b2 = isset($_POST['billing_address_line_2']) ? sanitize_text_field(wp_unslash($_POST['billing_address_line_2'])) : '';
        $bc = isset($_POST['billing_city'])           ? sanitize_text_field(wp_unslash($_POST['billing_city']))           : '';
        $bs = isset($_POST['billing_state'])          ? sanitize_text_field(wp_unslash($_POST['billing_state']))          : '';
        $bz = isset($_POST['billing_zip'])            ? sanitize_text_field(wp_unslash($_POST['billing_zip']))            : '';
    } else {
        $b1 = $address_line_1; $b2 = $address_line_2; $bc = $city; $bs = $state; $bz = $zip_code;
    }
    update_user_meta($user_id, 'billing_address_line_1', $b1);
    update_user_meta($user_id, 'billing_address_line_2', $b2);
    update_user_meta($user_id, 'billing_city', $bc);
    update_user_meta($user_id, 'billing_state', $bs);
    update_user_meta($user_id, 'billing_zip', $bz);
    update_user_meta($user_id, 'billing_country', 'United States');

    if (function_exists('update_field')) {
        update_field('address_line_1', $address_line_1, 'user_' . $user_id);
        update_field('address_line_2', $address_line_2, 'user_' . $user_id);
        update_field('city', $city, 'user_' . $user_id);
        update_field('country', $country, 'user_' . $user_id);
        if ($card_state_missing) {
            update_field('state', mcn_normalize_state_name($state), 'user_' . $user_id);
        }
        update_field('zip_code', $zip_code, 'user_' . $user_id);
    }

    if ($live_plan_key) {
        update_user_meta($user_id, 'payment_selected_plan', $live_plan_key);
    }
    if ($live_year_plan) {
        update_user_meta($user_id, 'california_year_plan', $live_year_plan);
    }
    // Keep the ORIGINAL subtotal (before coupon) so the coupon is never applied twice.
    update_user_meta($user_id, 'payment_amount', number_format($subtotal, 2, '.', ''));

    /* ---------- SETTINGS + SDK ---------- */
    $settings = cap_get_settings();

    if (empty($settings['enabled'])) {
        return cap_payment_result(false, 'payment_disabled');
    }
    if (empty($settings['api_login_id']) || empty($settings['transaction_key'])) {
        return cap_payment_result(false, 'credentials_missing');
    }

    // Never charge real cards from a staging copy of the site.
    if (empty($settings['sandbox']) && mcn_is_staging_site()
        && !(defined('MCN_ALLOW_LIVE_PAYMENTS_ON_STAGING') && MCN_ALLOW_LIVE_PAYMENTS_ON_STAGING)) {
        error_log('Custom Authorize Payment: blocked a PRODUCTION charge on a staging site. Switch Authorize.net to Sandbox mode with sandbox keys.');
        return cap_payment_result(false, 'staging_live_blocked');
    }

    if (mcn_load_authorize_sdk() === '') {
        error_log('Custom Authorize Payment: the Authorize.Net library could not be loaded. See Tools > MCN Modules > Checks.');
        return cap_payment_result(false, 'sdk_missing');
    }

    /* ---------- GATEWAY REQUEST ---------- */
    $merchantAuthentication = new net\authorize\api\contract\v1\MerchantAuthenticationType();
    $merchantAuthentication->setName($settings['api_login_id']);
    $merchantAuthentication->setTransactionKey($settings['transaction_key']);

    $creditCard = new net\authorize\api\contract\v1\CreditCardType();
    $creditCard->setCardNumber($card_number);
    $creditCard->setExpirationDate($formatted_expiry);
    $creditCard->setCardCode($cvv);

    $payment = new net\authorize\api\contract\v1\PaymentType();
    $payment->setCreditCard($creditCard);

    $transactionRequest = new net\authorize\api\contract\v1\TransactionRequestType();
    $transactionRequest->setTransactionType('authCaptureTransaction');
    $transactionRequest->setAmount($price);
    $transactionRequest->setPayment($payment);

    $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest($transactionRequest);

    $controller  = new net\authorize\api\controller\CreateTransactionController($request);
    $environment = !empty($settings['sandbox'])
        ? net\authorize\api\constants\ANetEnvironment::SANDBOX
        : net\authorize\api\constants\ANetEnvironment::PRODUCTION;

    if (!empty($settings['debug_logging'])) {
        error_log('Custom Authorize Payment: sending transaction to ' . (!empty($settings['sandbox']) ? 'SANDBOX' : 'PRODUCTION') . ' environment.');
    }

    $response                = null;
    $transactionResponse     = null;
    $transactionResponseCode = null;
    $transactionId           = '';
    $apiResultCode           = '';
    $responseReasonCode      = '';
    $responseReasonText      = '';
    $gatewayErrorCode        = '';
    $gatewayErrorText        = '';
    $gatewayMsgCode          = '';
    $api_exception           = false;

    try {
        $response = $controller->executeWithApiResponse($environment);

        if ($response && $response->getMessages()) {
            $apiResultCode = (string) $response->getMessages()->getResultCode();
            $messages = $response->getMessages()->getMessage();
            if (!empty($messages)) {
                $responseReasonCode = (string) $messages[0]->getCode();
                $responseReasonText = (string) $messages[0]->getText();
            }
        }

        if ($response) {
            $transactionResponse = $response->getTransactionResponse();
        }

        if ($transactionResponse) {
            $transactionResponseCode = (string) $transactionResponse->getResponseCode();
            $transactionId           = (string) $transactionResponse->getTransId();

            $errors = $transactionResponse->getErrors();
            if (!empty($errors)) {
                $gatewayErrorCode = (string) $errors[0]->getErrorCode();
                $gatewayErrorText = (string) $errors[0]->getErrorText();
            }

            $tmsgs = $transactionResponse->getMessages();
            if (!empty($tmsgs)) {
                $gatewayMsgCode = (string) $tmsgs[0]->getCode();
            }
        }

        if (!empty($settings['debug_logging'])) {
            error_log('=== AUTHORIZE.NET TRANSACTION RESPONSE ===');
            error_log('API Result Code: ' . ($apiResultCode !== '' ? $apiResultCode : 'N/A'));
            error_log('Transaction Response Code: ' . ($transactionResponseCode !== null ? $transactionResponseCode : 'N/A'));
            error_log('Response Reason Code: ' . ($responseReasonCode !== '' ? $responseReasonCode : 'N/A'));
            error_log('Response Reason Text: ' . ($responseReasonText !== '' ? $responseReasonText : 'N/A'));
            error_log('Gateway Error Code/Text: ' . $gatewayErrorCode . ' / ' . $gatewayErrorText);
            error_log('Gateway Message Code: ' . $gatewayMsgCode);
            error_log('Transaction ID: ' . ($transactionId !== '' ? $transactionId : 'N/A'));
            error_log('=== END AUTHORIZE.NET TRANSACTION RESPONSE ===');
        }
    } catch (\Throwable $e) {
        if (!empty($settings['debug_logging'])) {
            error_log('Authorize.net Exception: ' . $e->getMessage());
        }
        $response            = null;
        $transactionResponse = null;
        $transactionId       = '';
        $api_exception       = true;
    }

    /* ---------- APPROVED ---------- */
    $payment_approved = (
        $transactionResponse !== null &&
        $transactionResponseCode === '1' &&
        $transactionId !== ''
    );

    if ($payment_approved) {
        if ($coupon_code !== '' && !empty($coupon_result['valid'])) {
            $all_coupons = mcn_get_coupons();
            if (isset($all_coupons[$coupon_code])) {
                $all_coupons[$coupon_code]['used_count'] = absint($all_coupons[$coupon_code]['used_count'] ?? 0) + 1;
                update_option(MCN_COUPONS_OPTION, $all_coupons, false);
            }
            update_user_meta($user_id, 'payment_coupon_code', $coupon_code);
            update_user_meta($user_id, 'payment_coupon_discount', number_format($coupon_discount, 2, '.', ''));
        }

        update_user_meta($user_id, 'payment_status', 'paid');
        update_user_meta($user_id, 'transaction_id', $transactionId);

        if ($live_plan_key !== '') {
            update_user_meta($user_id, 'payment_selected_plan', $live_plan_key);
        }

        // Email failures must never block the customer from reaching the thank-you page.
        try {
            cap_email_patient_card_pdf_after_payment($user_id, $live_plan_key, $transactionId);
        } catch (\Throwable $e) {
            error_log('MCN PDF email exception after payment: ' . $e->getMessage());
        }

        return cap_payment_result(true, '', array('transaction_id' => $transactionId));
    }

    /* ---------- NOT APPROVED ---------- */
    if (get_user_meta($user_id, 'payment_status', true) !== 'paid') {
        update_user_meta($user_id, 'payment_status', 'failed');
        delete_user_meta($user_id, 'transaction_id');
    }

    // Map Authorize.Net codes to friendly errors.
    $code_map = array(
        '6'      => 'invalid_card',     // card number invalid
        '37'     => 'invalid_card',
        '7'      => 'invalid_expiry',   // expiry invalid
        '8'      => 'expired_card',     // card expired
        '44'     => 'invalid_cvv',      // card code mismatch
        '45'     => 'invalid_cvv',
        '65'     => 'invalid_cvv',
        '78'     => 'invalid_cvv',
        '27'     => 'avs_mismatch',     // address mismatch
        '11'     => 'duplicate',        // duplicate transaction
        'E00007' => 'credentials_missing',
        'E00008' => 'credentials_missing',
    );

    $error_type = 'not_approved';

    if ($api_exception) {
        $error_type = 'api_exception';
    } else {
        foreach (array($gatewayErrorCode, $gatewayMsgCode, $responseReasonCode) as $c) {
            if ($c !== '' && isset($code_map[$c])) {
                $error_type = $code_map[$c];
                break;
            }
        }

        if ($error_type === 'not_approved') {
            if (!$response) {
                $error_type = 'no_response';
            } elseif ($transactionResponseCode === '2') {
                $error_type = 'declined';
            } elseif ($transactionResponseCode === '3') {
                $error_type = 'error';
            } elseif ($transactionResponseCode === '4') {
                $error_type = 'held';
            } elseif ($transactionResponseCode === '1' && $transactionId === '') {
                $error_type = 'missing_transaction_id';
            }
        }
    }

    return cap_payment_result(false, $error_type);
}

/* AJAX endpoint used by the checkout form (no page reload). */
add_action('wp_ajax_cap_process_payment', 'cap_ajax_process_payment');
add_action('wp_ajax_nopriv_cap_process_payment', 'cap_ajax_process_payment');
function cap_ajax_process_payment()
{
    // Capture any stray PHP notices/warnings/deprecations produced during the
    // charge (e.g. the Authorize.Net SDK on PHP 8) so they can never be
    // prepended to the JSON body and break JSON.parse on the client
    // ("The payment server returned an unexpected response").
    ob_start();

    $result = cap_process_payment_request();

    // Discard anything that leaked to the output buffer; wp_send_json below
    // emits the real, clean JSON response on its own.
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!empty($result['success'])) {
        wp_send_json_success(array('redirect' => home_url('/thank-you/')));
    }

    $error   = !empty($result['error']) ? $result['error'] : 'not_approved';
    // Log only the normalized result when debug is enabled; never log card data or CVV.
    $cap_settings_for_log = cap_get_settings();
    if (!empty($cap_settings_for_log['debug_logging'])) {
        error_log('Custom Authorize Payment AJAX outcome: ' . sanitize_key($error));
    }
    $details = cap_get_payment_error_details($error);

    wp_send_json_error(array(
        'code'    => $error,
        'title'   => $details['title'],
        'message' => $details['message'],
    ));
}

/* Fallback for a normal (non-AJAX) form post, e.g. JavaScript disabled. */
add_action('init', function () {
    if (wp_doing_ajax() || !isset($_POST['process_payment']) || !is_user_logged_in()) {
        return;
    }

    $result = cap_process_payment_request();

    if (!empty($result['success'])) {
        wp_safe_redirect(home_url('/thank-you/'));
        exit;
    }

    $GLOBALS['cap_payment_error'] = !empty($result['error']) ? $result['error'] : 'not_approved';
});
