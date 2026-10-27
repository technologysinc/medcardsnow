<?php
/*
Plugin Name: Custom Authorize Payment
Description: Authorize.net payment using user meta (plan + price)
Version: 2.0
*/

if (!defined('ABSPATH')) exit;


/**
 * Request-scoped payment error.
 *
 * The payment handler runs on init, before the shortcode is rendered.
 * Failed gateway states are stored here so the shortcode can display
 * the error on the same payment page without redirecting.
 */
$GLOBALS['cap_payment_error'] = '';

function cap_get_payment_error_details($payment_error) {
    $messages = array(
        'declined' => array(
            'title'   => 'Payment Declined',
            'message' => 'We were unable to process your transaction. Please verify your card details or try a different payment method.'
        ),
        'error' => array(
            'title'   => 'Payment Error',
            'message' => 'The payment could not be processed. Please try again or use another card.'
        ),
        'held' => array(
            'title'   => 'Payment Held for Review',
            'message' => 'Your payment was held for review and was not completed. Please contact support if you need assistance.'
        ),
        'no_response' => array(
            'title'   => 'Payment Processing Error',
            'message' => 'No response was received from the payment gateway. Please try again.'
        ),
        'api_exception' => array(
            'title'   => 'Payment Processing Error',
            'message' => 'A payment processing error occurred. Please try again.'
        ),
        'missing_transaction_id' => array(
            'title'   => 'Payment Verification Error',
            'message' => 'The payment could not be verified because the transaction ID was missing. Please try again or contact support.'
        ),
        'not_approved' => array(
            'title'   => 'Payment Not Approved',
            'message' => 'Your payment was not approved. Please try again or use another card.'
        ),
        'invalid_card' => array(
            'title'   => 'Invalid Card Number',
            'message' => 'The card number appears to be invalid. Please check your card details and try again.'
        ),
        'invalid_cvv' => array(
            'title'   => 'Invalid CVV',
            'message' => 'The CVV appears to be invalid. Please check your card security code and try again.'
        ),
        'invalid_expiry' => array(
            'title'   => 'Invalid Expiry Date',
            'message' => 'Please enter the card expiry date in MM/YY format.'
        ),
        'payment_disabled' => array(
            'title'   => 'Payment Unavailable',
            'message' => 'Payment processing is currently disabled. Please contact support.'
        ),
        'credentials_missing' => array(
            'title'   => 'Payment Configuration Error',
            'message' => 'Authorize.net payment credentials are not configured. Please contact support.'
        ),
        'sdk_missing' => array(
            'title'   => 'Payment Configuration Error',
            'message' => 'The payment gateway is temporarily unavailable. Please contact support.'
        ),
    );

    if (!isset($messages[$payment_error])) {
        $messages[$payment_error] = $messages['not_approved'];
    }

    return $messages[$payment_error];
}



/**
 * Plugin settings
 */
define('CAP_SETTINGS_OPTION', 'custom_authorize_payment_settings');

function cap_get_settings() {
    $defaults = array(
        'enabled'         => 1,
        'sandbox'         => 1,
        'api_login_id'    => '',
        'transaction_key' => '',
        'public_client_key' => '',
        'debug_logging'   => 0,
    );

    $settings = get_option(CAP_SETTINGS_OPTION, array());
    return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
}

add_action('admin_menu', function () {
    add_menu_page(
        'Custom Authorization',
        'Custom Authorization',
        'manage_options',
        'custom-authorization',
        'cap_render_settings_page',
        'dashicons-lock',
        58
    );
});

add_action('admin_init', function () {
    register_setting(
        'cap_settings_group',
        CAP_SETTINGS_OPTION,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'cap_sanitize_settings',
            'default'           => array(),
        )
    );

    add_settings_section(
        'cap_authorize_section',
        'Authorize.net Configuration',
        function () {
            echo '<p>Enter your Authorize.net credentials below. Use Sandbox/Test Mode while testing and Production Mode for live payments.</p>';
        },
        'custom-authorization'
    );

    add_settings_field(
        'cap_enabled',
        'Enable Payments',
        'cap_checkbox_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'enabled', 'label' => 'Enable the Custom Authorize payment gateway.')
    );

    add_settings_field(
        'cap_sandbox',
        'Sandbox / Test Mode',
        'cap_checkbox_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'sandbox', 'label' => 'Use Authorize.net Sandbox for testing. Uncheck for live Production payments.')
    );

    add_settings_field(
        'cap_api_login_id',
        'API Login ID',
        'cap_text_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'api_login_id', 'type' => 'text', 'description' => 'Your Authorize.net API Login ID.')
    );

    add_settings_field(
        'cap_transaction_key',
        'Transaction Key',
        'cap_text_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'transaction_key', 'type' => 'password', 'description' => 'Your Authorize.net Transaction Key. This is stored in WordPress options and never displayed in plain text here.')
    );

    add_settings_field(
        'cap_public_client_key',
        'Public Client Key',
        'cap_text_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'public_client_key', 'type' => 'text', 'description' => 'Optional. Saved for future client-side Authorize.net integrations. The current server-side payment flow does not use this key.')
    );

    add_settings_field(
        'cap_debug_logging',
        'Debug Logging',
        'cap_checkbox_field',
        'custom-authorization',
        'cap_authorize_section',
        array('key' => 'debug_logging', 'label' => 'Enable WordPress debug logging for payment configuration/response diagnostics. Never logs card numbers or CVV.')
    );
});

function cap_sanitize_settings($input) {
    $current = cap_get_settings();
    $input = is_array($input) ? $input : array();

    $output = array();
    $output['enabled'] = !empty($input['enabled']) ? 1 : 0;
    $output['sandbox'] = !empty($input['sandbox']) ? 1 : 0;
    $output['debug_logging'] = !empty($input['debug_logging']) ? 1 : 0;
    $output['api_login_id'] = isset($input['api_login_id']) ? sanitize_text_field($input['api_login_id']) : '';
    $output['public_client_key'] = isset($input['public_client_key']) ? sanitize_text_field($input['public_client_key']) : '';

    // Do not erase an existing Transaction Key if the password field is left blank.
    $transaction_key = isset($input['transaction_key']) ? trim((string) $input['transaction_key']) : '';
    $output['transaction_key'] = $transaction_key !== '' ? sanitize_text_field($transaction_key) : $current['transaction_key'];

    add_settings_error('cap_messages', 'cap_saved', 'Custom Authorization settings saved.', 'updated');
    return $output;
}

function cap_text_field($args) {
    $settings = cap_get_settings();
    $key = $args['key'];
    $type = isset($args['type']) ? $args['type'] : 'text';
    $value = isset($settings[$key]) ? $settings[$key] : '';

    // Never print the stored Transaction Key back into the page.
    if ($key === 'transaction_key') {
        $value = '';
        $placeholder = !empty($settings[$key]) ? 'Saved — leave blank to keep current key' : 'Enter Transaction Key';
    } else {
        $placeholder = '';
    }

    printf(
        '<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" autocomplete="%5$s" placeholder="%6$s">',
        esc_attr($type),
        esc_attr(CAP_SETTINGS_OPTION),
        esc_attr($key),
        esc_attr($value),
        $key === 'transaction_key' ? 'new-password' : 'off',
        esc_attr($placeholder)
    );

    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function cap_checkbox_field($args) {
    $settings = cap_get_settings();
    $key = $args['key'];
    printf(
        '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
        esc_attr(CAP_SETTINGS_OPTION),
        esc_attr($key),
        checked(!empty($settings[$key]), true, false),
        esc_html($args['label'])
    );
}

function cap_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = cap_get_settings();
    ?>
    <div class="wrap">
        <h1>Custom Authorization</h1>
        <?php settings_errors('cap_messages'); ?>

        <div style="max-width:900px;">
            <form method="post" action="options.php">
                <?php
                settings_fields('cap_settings_group');
                do_settings_sections('custom-authorization');
                submit_button('Save Authorization Settings');
                ?>
            </form>

            <div class="notice notice-warning inline" style="margin-top:20px;">
                <p><strong>Security:</strong> Never place your API Login ID or Transaction Key in frontend JavaScript, page content, or public repositories. The Transaction Key is used only on the server.</p>
            </div>

            <div class="card" style="max-width:100%; margin-top:20px; padding:15px 20px;">
                <h2 style="margin-top:0;">Current Configuration</h2>
                <p><strong>Environment:</strong> <?php echo !empty($settings['sandbox']) ? 'Sandbox / Test' : 'Production / Live'; ?></p>
                <p><strong>Gateway:</strong> <?php echo !empty($settings['enabled']) ? 'Enabled' : 'Disabled'; ?></p>
                <p><strong>API Login ID:</strong> <?php echo $settings['api_login_id'] ? 'Configured' : 'Not configured'; ?></p>
                <p><strong>Transaction Key:</strong> <?php echo $settings['transaction_key'] ? 'Configured' : 'Not configured'; ?></p>
                <p><strong>Public Client Key:</strong> <?php echo $settings['public_client_key'] ? 'Configured' : 'Not configured'; ?></p>
            </div>
        </div>
    </div>
    <?php
}

// SHORTCODE
add_shortcode('custom_authorize_payment', function () {

    if (!is_user_logged_in()) {
        return '<p>Please login to continue.</p>';
    }

    $user_id = get_current_user_id();

    // $price = get_user_meta($user_id, 'payment_amount', true);
    // $plan  = get_user_meta($user_id, 'selected_plan', true);

    $plan = isset($_POST['selected_plan'])
    ? sanitize_text_field(wp_unslash($_POST['selected_plan']))
    : '';

    $duration = isset($_POST['selected_duration'])
    ? sanitize_text_field(wp_unslash($_POST['selected_duration']))
    : '';


    
    
    $price = get_user_meta($user_id, 'payment_amount', true);

    // Read the selected plan from WordPress user meta.
    $plan_key = get_user_meta($user_id, 'selected_plan', true);
    
    if (empty($plan_key)) {
        $plan_key = get_user_meta($user_id, 'payment_plan', true);
    }
    
    // Read the selected duration from WordPress user meta.
    $duration = get_user_meta($user_id, 'payment_duration', true);
    
    if (empty($duration)) {
        $duration = get_user_meta($user_id, 'selected_duration', true);
    }
    
    // Convert plan keys into display labels.
    $plan_labels = array(
        'new_pdf_id_card' => 'New Patient — PDF + Patient ID Card',
        'new_pdf'         => 'New Patient — PDF Only',
        'new_hard_copy'   => 'Instant PDF + Hard Copy + Patient ID Card',
        'renewal'         => 'Renewal',
    );
    
    $plan = isset($plan_labels[$plan_key])
        ? $plan_labels[$plan_key]
        : $plan_key;
    
    
    

    if (!$plan) {
        $plan_key = get_user_meta($user_id, 'payment_plan_', true);

        $plan_labels = array(
            'new'        => 'New Patient',
            'renewal'    => 'Renewal',
            'two_year'   => '2 Year Pricing',
            'three_year' => '3 Year Pricing',

            'new_hard_copy'     => 'Instant PDF + Hard Copy With Embossed Seal + Patient ID Card',
            'caregiver_new'     => 'New Patient (Caregiver) — Caregiver + New Patient',
            'growers_license'   => 'License to Cultivate up to 99 Plants',
            'new_pdf'           => 'New Patient — PDF Only',
            'renewal_hard_copy' => 'Existing patient renewal — With Hard Copy',
        );

        $plan = isset($plan_labels[$plan_key]) ? $plan_labels[$plan_key] : $plan_key;
    }

    if (!$price) {
        return '<p>No plan selected. Please complete your profile first.</p>';
    }

    // Make sure Authorize.net receives numeric amount only.
    $price = preg_replace('/[^0-9.]/', '', $price);

    // Shipping fields
    $address_line_1 = get_user_meta($user_id, 'address_line_1', true);
    $address_line_2 = get_user_meta($user_id, 'address_line_2', true);
    $city           = get_user_meta($user_id, 'city', true);
    $country        = 'United States';
    $state          = get_user_meta($user_id, 'state', true);
    $zip_code       = get_user_meta($user_id, 'zip_code', true);

    // Billing fields
    $same_as_shipping = get_user_meta($user_id, 'billing_same_as_shipping', true);
    $same_as_shipping = $same_as_shipping !== 'no';

    $billing_address_line_1 = get_user_meta($user_id, 'billing_address_line_1', true);
    $billing_address_line_2 = get_user_meta($user_id, 'billing_address_line_2', true);
    $billing_city           = get_user_meta($user_id, 'billing_city', true);
    $billing_state          = get_user_meta($user_id, 'billing_state', true);
    $billing_zip            = get_user_meta($user_id, 'billing_zip', true);
    $billing_country        = 'United States';

    ob_start();

    $payment_error = !empty($GLOBALS['cap_payment_error'])
        ? sanitize_key($GLOBALS['cap_payment_error'])
        : '';

    $payment_error_details = $payment_error
        ? cap_get_payment_error_details($payment_error)
        : array();

    ?>

<?php if (!empty($payment_error_details)) : ?>
<style id="custom-authorize-payment-error-style">
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

    .custom-authorize-payment-error-icon::before {
        transform: rotate(45deg);
    }

    .custom-authorize-payment-error-icon::after {
        transform: rotate(-45deg);
    }

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
</style>

<div
    id="custom-authorize-payment-error"
    class="custom-authorize-payment-error"
    role="alert"
    aria-live="assertive"
>
    <button
        type="button"
        class="custom-authorize-payment-error-close"
        aria-label="Close"
        onclick="this.parentElement.style.display='none';"
    >&times;</button>

    <strong><?php echo esc_html($payment_error_details['title']); ?></strong>
    <?php echo esc_html($payment_error_details['message']); ?>
</div>

<div
    id="custom-authorize-payment-error-modal"
    class="custom-authorize-payment-error-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="custom-authorize-payment-error-modal-title"
>
    <div class="custom-authorize-payment-error-modal-box">
        <div class="custom-authorize-payment-error-icon" aria-hidden="true"></div>
        <h3 id="custom-authorize-payment-error-modal-title">
            <?php echo esc_html($payment_error_details['title']); ?>
        </h3>

        <p><?php echo esc_html($payment_error_details['message']); ?></p>

        <button
            type="button"
            class="custom-authorize-payment-error-modal-close"
            onclick="document.getElementById('custom-authorize-payment-error-modal').style.display='none';"
        >
            OK
        </button>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('custom-authorize-payment-error-modal');
    var errorBox = document.getElementById('custom-authorize-payment-error');

    if (!modal) {
        return;
    }

    modal.style.display = 'flex';

    if (errorBox) {
        setTimeout(function () {
            errorBox.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 100);
    }
})();
</script>
<?php endif; ?>

<div class="checkout-back-wrap">
    <a href="/medical-card-profile/#gf_2" class="checkout-back-btn">← Back To Profile</a>
</div>

<form method="post" class="auth-payment-form" autocomplete="on">
    <?php wp_nonce_field('custom_authorize_payment_action', 'custom_authorize_payment_nonce'); ?>
    
    <input type="hidden" name="selected_plan" id="checkout_selected_plan">
        <input type="hidden" name="selected_duration" id="checkout_selected_duration">

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
                    <input
                        type="text"
                        name="card_number"
                        placeholder="Card Number"
                        inputmode="numeric"
                        autocomplete="cc-number"
                        maxlength="19"
                        required
                    >
                </div>

                <div class="field-grid two-col">
                    <input
                        type="text"
                        name="expiry"
                        placeholder="MM/YY"
                        inputmode="numeric"
                        autocomplete="cc-exp"
                        maxlength="5"
                        required
                    >
                    <input
                        type="text"
                        name="cvv"
                        placeholder="CVV"
                        inputmode="numeric"
                        autocomplete="cc-csc"
                        maxlength="4"
                        required
                    >
                </div>
            </div>

            <div class="secure-note">
                Your payment is encrypted and securely processed.
            </div>

        </div>

        <div class="checkout-right">

            <div class="summary-card">
                <h4>Order Summary</h4>

                <div class="summary-row">
                    <span>Package</span>
                    <?php if (!empty($duration)) : ?>
                    <strong>
                        <?php
                        $duration_labels = array(
                            '1_year' => '1 Year',
                            '2_year' => '2 Years',
                            '3_year' => '3 Years',
                        );
            
                        echo esc_html(
                            isset($duration_labels[$duration])
                                ? $duration_labels[$duration]
                                : $duration
                        );
                        ?>
                    </strong>
                    <?php endif; ?>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong>$<?php echo esc_html(number_format((float)$price, 2)); ?></strong>
                </div>

                <div class="summary-row">
                    <span>Shipping</span>
                    <strong>Included</strong>
                </div>

                <div class="summary-row total">
                    <span>Total</span>
                    <strong>$<?php echo esc_html(number_format((float)$price, 2)); ?></strong>
                </div>
            </div>

            <div class="benefits-card">
                <h4>Order Benefits</h4>
                <div class="benefit-item"><span>✓</span><p><b>Fast approval process</b></p></div>
                <div class="benefit-item"><span>✓</span><p><b>Secure checkout</b></p></div>
                <div class="benefit-item"><span>✓</span><p><b>Licensed physician review</b></p></div>
                <div class="benefit-item"><span>✓</span><p><b>Money-back guarantee if you are not approved</b></p></div>
            </div>

            <button type="submit" name="process_payment" value="1" class="place-order-btn">Submit Application
            </button>

        </div>

    </div>
</form>

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
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    box-sizing: border-box;
}

.checkout-box {
    margin-bottom: 18px;
}

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

.field-grid.one-col {
    grid-template-columns: 1fr;
}

.field-grid.two-col {
    grid-template-columns: repeat(2, 1fr);
}

.field-grid.three-col {
    grid-template-columns: 1.2fr 0.8fr 0.8fr;
}

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
    box-shadow: 0 0 0 3px rgba(35,182,182,0.12);
}

.billing-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #333;
    margin-top: 4px;
}

.billing-check input {
    accent-color: #20b6b6;
}

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
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
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

.summary-card {
    margin-bottom: 16px;
}

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
    max-width: 150px;
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

.benefit-item:last-child {
    margin-bottom: 0;
}

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

.place-order-btn:hover {
    background: #168b8b;
}

.checkout-back-wrap {
    max-width: 1100px;
    margin: 0 auto;
}

.checkout-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 auto 24px;
    padding: 12px 18px;
    background: #f4fbfb;
    border: 1px solid #cfeeee;
    border-radius: 10px;
    color: #188888;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.25s ease;
}

.checkout-back-btn:hover {
    background: #2bb3b3;
    border-color: #2bb3b3;
    color: #fff;
    transform: translateY(-1px);
}

@media (max-width: 900px) {
    .checkout-wrap {
        grid-template-columns: 1fr;
    }

    .field-grid.two-col,
    .field-grid.three-col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .accepted-cards {
        gap: 10px;
    }

    .accepted-cards img {
        width: 64px;
        height: 44px;
        padding: 6px 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('same_as_shipping');
    const billingFields = document.querySelector('.billing-fields');

    if (checkbox && billingFields) {
        function toggleBillingFields() {
            billingFields.style.display = checkbox.checked ? 'none' : 'block';
        }

        checkbox.addEventListener('change', toggleBillingFields);
        toggleBillingFields();
    }

    const cardInput = document.querySelector('input[name="card_number"]');

    if (cardInput) {
        cardInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '').substring(0, 16);
            e.target.value = value.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    const expiryInput = document.querySelector('input[name="expiry"]');

    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '').substring(0, 4);
            let month = value.substring(0, 2);

            if (month.length === 2) {
                let monthNum = parseInt(month, 10);
                if (monthNum > 12) month = '12';
                if (monthNum === 0) month = '01';
            }

            if (value.length > 2) {
                value = month + '/' + value.substring(2);
            } else {
                value = month;
            }

            e.target.value = value;
        });
    }

    const cvvInput = document.querySelector('input[name="cvv"]');

    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });
    }
    
    
    const plan =
        sessionStorage.getItem('selected_plan') ||
        sessionStorage.getItem('payment_plan');

    const duration =
        sessionStorage.getItem('selected_duration') ||
        sessionStorage.getItem('payment_duration');

    const planField = document.getElementById('checkout_selected_plan');
    const durationField = document.getElementById('checkout_selected_duration');

    if (planField && plan) {
        planField.value = plan;
    }

    if (durationField && duration) {
        durationField.value = duration;
    }

    console.log('Checkout plan:', plan);
    console.log('Checkout duration:', duration);
    
    
    
    
});
</script>

<?php
    return ob_get_clean();
});


// PAYMENT HANDLER
add_action('init', function () {

    if (!isset($_POST['process_payment'])) return;
    if (!is_user_logged_in()) return;

    if (
        !isset($_POST['custom_authorize_payment_nonce']) ||
        !wp_verify_nonce($_POST['custom_authorize_payment_nonce'], 'custom_authorize_payment_action')
    ) {
        wp_die('Security check failed.');
    }

    $user_id = get_current_user_id();
    $price   = get_user_meta($user_id, 'payment_amount', true);

    if (!$price) return;

    $price = preg_replace('/[^0-9.]/', '', $price);

    // Save shipping address fields
    $address_line_1 = isset($_POST['address_line_1']) ? sanitize_text_field($_POST['address_line_1']) : '';
    $address_line_2 = isset($_POST['address_line_2']) ? sanitize_text_field($_POST['address_line_2']) : '';
    $city           = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    $country        = 'United States';
    $state          = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
    $zip_code       = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';

    update_user_meta($user_id, 'address_line_1', $address_line_1);
    update_user_meta($user_id, 'address_line_2', $address_line_2);
    update_user_meta($user_id, 'city', $city);
    update_user_meta($user_id, 'country', $country);
    update_user_meta($user_id, 'state', $state);
    update_user_meta($user_id, 'zip_code', $zip_code);

    // Save billing address fields
    $same_as_shipping = isset($_POST['same_as_shipping']) ? 'yes' : 'no';
    update_user_meta($user_id, 'billing_same_as_shipping', $same_as_shipping);

    if ($same_as_shipping === 'no') {

        $billing_address_line_1 = isset($_POST['billing_address_line_1']) ? sanitize_text_field($_POST['billing_address_line_1']) : '';
        $billing_address_line_2 = isset($_POST['billing_address_line_2']) ? sanitize_text_field($_POST['billing_address_line_2']) : '';
        $billing_city           = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';
        $billing_state          = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
        $billing_zip            = isset($_POST['billing_zip']) ? sanitize_text_field($_POST['billing_zip']) : '';

        update_user_meta($user_id, 'billing_address_line_1', $billing_address_line_1);
        update_user_meta($user_id, 'billing_address_line_2', $billing_address_line_2);
        update_user_meta($user_id, 'billing_city', $billing_city);
        update_user_meta($user_id, 'billing_state', $billing_state);
        update_user_meta($user_id, 'billing_zip', $billing_zip);
        update_user_meta($user_id, 'billing_country', 'United States');

    } else {

        update_user_meta($user_id, 'billing_address_line_1', $address_line_1);
        update_user_meta($user_id, 'billing_address_line_2', $address_line_2);
        update_user_meta($user_id, 'billing_city', $city);
        update_user_meta($user_id, 'billing_state', $state);
        update_user_meta($user_id, 'billing_zip', $zip_code);
        update_user_meta($user_id, 'billing_country', 'United States');
    }

    if (function_exists('update_field')) {
        update_field('address_line_1', $address_line_1, 'user_' . $user_id);
        update_field('address_line_2', $address_line_2, 'user_' . $user_id);
        update_field('city', $city, 'user_' . $user_id);
        update_field('country', $country, 'user_' . $user_id);
        update_field('state', $state, 'user_' . $user_id);
        update_field('zip_code', $zip_code, 'user_' . $user_id);
    }

    $vendor = plugin_dir_path(__FILE__) . 'vendor/autoload.php';

    if (!file_exists($vendor)) {
        $GLOBALS['cap_payment_error'] = 'sdk_missing';
        return;
    }

    require_once $vendor;

    // IMPORTANT: remove spaces/dashes from card number before sending to Authorize.net.
    $card_number = isset($_POST['card_number']) ? preg_replace('/\D/', '', $_POST['card_number']) : '';
    $expiry      = isset($_POST['expiry']) ? sanitize_text_field($_POST['expiry']) : '';
    $cvv         = isset($_POST['cvv']) ? preg_replace('/\D/', '', $_POST['cvv']) : '';

    if (strlen($card_number) < 13 || strlen($card_number) > 19) {
        $GLOBALS['cap_payment_error'] = 'invalid_card';
        return;
    }

    if (strlen($cvv) < 3 || strlen($cvv) > 4) {
        $GLOBALS['cap_payment_error'] = 'invalid_cvv';
        return;
    }

    $formatted_expiry = '';
    $exp = explode('/', $expiry);

    if (count($exp) === 2) {
        $month = trim($exp[0]);
        $year  = trim($exp[1]);

        if (!ctype_digit($month) || !ctype_digit($year)) {
            $GLOBALS['cap_payment_error'] = 'invalid_expiry';
            return;
        }

        if ((int)$month < 1 || (int)$month > 12) {
            $GLOBALS['cap_payment_error'] = 'invalid_expiry';
            return;
        }

        if (strlen($year) === 2) {
            $year = '20' . $year;
        }

        $formatted_expiry = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
    } else {
        $GLOBALS['cap_payment_error'] = 'invalid_expiry';
        return;
    }

    $settings = cap_get_settings();

    if (empty($settings['enabled'])) {
        $GLOBALS['cap_payment_error'] = 'payment_disabled';
        return;
    }

    if (empty($settings['api_login_id']) || empty($settings['transaction_key'])) {
        $GLOBALS['cap_payment_error'] = 'credentials_missing';
        return;
    }

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
    $transactionRequest->setTransactionType("authCaptureTransaction");
    $transactionRequest->setAmount($price);
    $transactionRequest->setPayment($payment);

    $request = new net\authorize\api\contract\v1\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setTransactionRequest($transactionRequest);

    $controller = new net\authorize\api\controller\CreateTransactionController($request);
    $environment = !empty($settings['sandbox'])
        ? net\authorize\api\constants\ANetEnvironment::SANDBOX
        : net\authorize\api\constants\ANetEnvironment::PRODUCTION;

    if (!empty($settings['debug_logging'])) {
        error_log('Custom Authorize Payment: sending transaction to ' . (!empty($settings['sandbox']) ? 'SANDBOX' : 'PRODUCTION') . ' environment.');
    }

    /*
     * Execute the Authorize.net request safely.
     *
     * IMPORTANT:
     * Never use the top-level API message result ("Ok") as the payment
     * approval test. Authorize.net can return API result "Ok" while the
     * actual transaction is declined.
     *
     * Transaction response codes:
     * 1 = Approved
     * 2 = Declined
     * 3 = Error
     * 4 = Held for Review
     *
     * Only transaction response code 1 AND a valid transaction ID are
     * allowed to enter the success flow.
     */
    $response = null;
    $transactionResponse = null;
    $transactionResponseCode = null;
    $transactionId = '';
    $apiResultCode = '';
    $responseReasonCode = '';
    $responseReasonText = '';
    $gatewayErrorCode = '';
    $gatewayErrorText = '';
    $api_exception = false;

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
            $transactionId = (string) $transactionResponse->getTransId();

            if ($transactionResponse->getErrors()) {
                $errors = $transactionResponse->getErrors();

                if (!empty($errors)) {
                    $gatewayErrorCode = (string) $errors[0]->getErrorCode();
                    $gatewayErrorText = (string) $errors[0]->getErrorText();

                    /*
                     * For transaction-level failures, the gateway error is
                     * more useful as the reason code/text if no API message
                     * code/text was supplied.
                     */
                    if ($responseReasonCode === '') {
                        $responseReasonCode = $gatewayErrorCode;
                    }

                    if ($responseReasonText === '') {
                        $responseReasonText = $gatewayErrorText;
                    }
                }
            }
        }

        /*
         * Safe diagnostic logging.
         *
         * NEVER log:
         * - full card number
         * - CVV
         * - expiry
         * - API Login ID
         * - Transaction Key
         */
        if (!empty($settings['debug_logging'])) {

            error_log('=== AUTHORIZE.NET TRANSACTION RESPONSE ===');
            error_log(
                'Environment: ' .
                (!empty($settings['sandbox']) ? 'SANDBOX' : 'PRODUCTION')
            );
            error_log(
                'API Result Code: ' .
                ($apiResultCode !== '' ? $apiResultCode : 'N/A')
            );
            error_log(
                'Transaction Response Code: ' .
                ($transactionResponseCode !== null ? $transactionResponseCode : 'N/A')
            );
            error_log(
                'Response Reason Code: ' .
                ($responseReasonCode !== '' ? $responseReasonCode : 'N/A')
            );
            error_log(
                'Response Reason Text: ' .
                ($responseReasonText !== '' ? $responseReasonText : 'N/A')
            );
            error_log(
                'Transaction ID: ' .
                ($transactionId !== '' ? $transactionId : 'N/A')
            );

            if ($transactionResponse) {
                $avsResult = $transactionResponse->getAvsResultCode();
                $cvvResult = $transactionResponse->getCvVResultCode();

                error_log(
                    'AVS Result: ' .
                    ($avsResult ? $avsResult : 'N/A')
                );
                error_log(
                    'CVV Result: ' .
                    ($cvvResult ? $cvvResult : 'N/A')
                );
                error_log(
                    'Gateway Response: ' .
                    ($transactionResponseCode !== null
                        ? $transactionResponseCode
                        : 'N/A')
                );

                if ($gatewayErrorCode !== '') {
                    error_log('Gateway Error Code: ' . $gatewayErrorCode);
                }

                if ($gatewayErrorText !== '') {
                    error_log('Gateway Error Text: ' . $gatewayErrorText);
                }
            }

            error_log('=== END AUTHORIZE.NET TRANSACTION RESPONSE ===');
        }

    } catch (\Throwable $e) {

        /*
         * Any SDK/API exception is a failed payment attempt.
         * It must NEVER enter the success flow.
         */
        if (!empty($settings['debug_logging'])) {
            error_log('Authorize.net Exception: ' . $e->getMessage());
        }

        $response = null;
        $transactionResponse = null;
        $transactionResponseCode = null;
        $transactionId = '';
        $responseReasonText = 'Payment processing error. Please try again.';
        $api_exception = true;
    }

    /*
     * FINAL SUCCESS GATE
     *
     * A transaction is considered paid ONLY when:
     *   1. A transaction response exists
     *   2. Transaction response code is exactly "1" (Approved)
     *   3. A transaction ID exists
     *
     * Therefore:
     *   Code 2 = Declined          -> NO Thank You page
     *   Code 3 = Error             -> NO Thank You page
     *   Code 4 = Held for Review   -> NO Thank You page
     *   NULL/no response           -> NO Thank You page
     *   Exception                  -> NO Thank You page
     *   Code 1 without trans ID    -> NO Thank You page
     */
    $payment_approved = (
        $transactionResponse !== null &&
        $transactionResponseCode === '1' &&
        $transactionId !== ''
    );

    if ($payment_approved) {

        update_user_meta($user_id, 'payment_status', 'paid');
        update_user_meta($user_id, 'transaction_id', $transactionId);

        wp_safe_redirect(home_url('/thank-you/'));
        exit;

    } else {

        /*
         * IMPORTANT:
         * Remove any previous transaction ID so an old successful
         * transaction cannot be mistaken for this failed attempt.
         */
        update_user_meta($user_id, 'payment_status', 'failed');
        delete_user_meta($user_id, 'transaction_id');

        /*
         * Do NOT use wp_die() here.
         *
         * Return the customer to the payment page and show a friendly
         * popup + inline message there. The customer should never see
         * the WordPress "There has been a critical error" / wp_die page
         * for a normal payment decline or gateway failure.
         */
        $payment_error_type = 'not_approved';

        if ($api_exception) {
            $payment_error_type = 'api_exception';
        } elseif ($transactionResponseCode === '2') {
            $payment_error_type = 'declined';
        } elseif ($transactionResponseCode === '3') {
            $payment_error_type = 'error';
        } elseif ($transactionResponseCode === '4') {
            $payment_error_type = 'held';
        } elseif (!$response) {
            $payment_error_type = 'no_response';
        } elseif (
            $transactionResponseCode === '1' &&
            $transactionId === ''
        ) {
            $payment_error_type = 'missing_transaction_id';
        }

        /*
         * IMPORTANT:
         * Do NOT redirect on a failed payment.
         *
         * The payment handler runs during the same request before the
         * shortcode is rendered. Store the error type in a request-scoped
         * global and let the shortcode render the payment page normally.
         * This keeps the customer on the payment screen and shows the
         * correct error message there.
         */
        $GLOBALS['cap_payment_error'] = $payment_error_type;
        return;
    }

    
    
    
});
