<?php
/*
Plugin Name: Custom Authorize Payment
Description: Authorize.net payment using user meta (plan + price)
Version: 1.5
*/

if (!defined('ABSPATH')) exit;

// SHORTCODE
add_shortcode('custom_authorize_payment', function () {

    if (!is_user_logged_in()) {
        return '<p>Please login to continue.</p>';
    }

    $user_id = get_current_user_id();

    $price = get_user_meta($user_id, 'payment_amount', true);
    $price = wp_strip_all_tags($price);
    $price = html_entity_decode($price, ENT_QUOTES, 'UTF-8');
    $price = preg_replace('/[^0-9.]/', '', $price);
    
    if (!is_numeric($price) || (float) $price <= 0) {
        wp_die('Invalid payment amount.');
    }
    
    $price = number_format((float) $price, 2, '.', '');
    $plan  = get_user_meta($user_id, 'selected_plan', true);

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
    ?>

<div class="checkout-back-wrap">
    <a href="/medical-card-profile/#gf_2" class="checkout-back-btn">← Back To Profile</a>
</div>

<form method="post" class="auth-payment-form" autocomplete="on">
    <?php wp_nonce_field('custom_authorize_payment_action', 'custom_authorize_payment_nonce'); ?>

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
                    <strong><?php echo esc_html($plan); ?></strong>
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
        wp_die('Authorize.net SDK missing.');
    }

    require_once $vendor;

    // IMPORTANT: remove spaces/dashes from card number before sending to Authorize.net.
    $card_number = isset($_POST['card_number']) ? preg_replace('/\D/', '', $_POST['card_number']) : '';
    $expiry      = isset($_POST['expiry']) ? sanitize_text_field($_POST['expiry']) : '';
    $cvv         = isset($_POST['cvv']) ? preg_replace('/\D/', '', $_POST['cvv']) : '';

    if (strlen($card_number) < 13 || strlen($card_number) > 19) {
        wp_die('Invalid card number. Please check your card details and try again.');
    }

    if (strlen($cvv) < 3 || strlen($cvv) > 4) {
        wp_die('Invalid CVV. Please check your card details and try again.');
    }

    $formatted_expiry = '';
    $exp = explode('/', $expiry);

    if (count($exp) === 2) {
        $month = trim($exp[0]);
        $year  = trim($exp[1]);

        if (!ctype_digit($month) || !ctype_digit($year)) {
            wp_die('Invalid expiry format. Please use MM/YY.');
        }

        if ((int)$month < 1 || (int)$month > 12) {
            wp_die('Invalid expiry month.');
        }

        if (strlen($year) === 2) {
            $year = '20' . $year;
        }

        $formatted_expiry = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
    } else {
        wp_die('Invalid expiry format. Please use MM/YY.');
    }

    $merchantAuthentication = new net\authorize\api\contract\v1\MerchantAuthenticationType();
    $merchantAuthentication->setName("7j87z4CE6");
    $merchantAuthentication->setTransactionKey("65s49567GDHLhp27");
    // $merchantAuthentication->setPublicClientKey("24h24xBYB5XBPuvMYvDqK8xLUg38SgypjXZE473c93bLz43r9Ctay8Y4BUBys6dK");

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
    $response = $controller->executeWithApiResponse(
        net\authorize\api\constants\ANetEnvironment::PRODUCTION
    );


    // if ($response && $response->getMessages()->getResultCode() == "Ok") {

    //     update_user_meta($user_id, 'payment_status', 'paid');

    //     $transId = $response->getTransactionResponse()->getTransId();
    //     update_user_meta($user_id, 'transaction_id', $transId);

    //     wp_redirect('/thank-you/');
    //     exit;

    // } else {

    //     update_user_meta($user_id, 'payment_status', 'failed');

    //     $error_message = 'Payment failed. Try again.';

    //     if ($response && $response->getTransactionResponse() && $response->getTransactionResponse()->getErrors()) {
    //         $errors = $response->getTransactionResponse()->getErrors();
    //         if (!empty($errors)) {
    //             $error_message = $errors[0]->getErrorText();
    //         }
    //     } elseif ($response && $response->getMessages()) {
    //         $messages = $response->getMessages()->getMessage();
    //         if (!empty($messages)) {
    //             $error_message = $messages[0]->getText();
    //         }
    //     } else {
    //         $error_message = 'No response from Authorize.net. Please contact support.';
    //     }

    //     wp_die($error_message);
    // }
    
    // if ($response && $response->getMessages()) {

    // $result_code = $response->getMessages()->getResultCode();

    // if ($result_code === "Ok") {

    //     update_user_meta($user_id, 'payment_status', 'paid');
        
    //     $transaction_response = $response->getTransactionResponse();
        
    //     if ($transaction_response) {
    //             $transId = $transaction_response->getTransId();
            
    //             if ($transId) {
    //                 update_user_meta($user_id, 'transaction_id', $transId);
    //             }
    //         }
            
    //             wp_redirect(home_url('/thank-you/'));
    //             exit;
    //     }
        
    //         update_user_meta($user_id, 'payment_status', 'failed');
            
    //         $error_message = 'Payment failed. Please try again.';
            
    //         $messages = $response->getMessages()->getMessage();
            
    //         if (!empty($messages)) {
    //             $error_message = $messages[0]->getText();
    //         }
        
    //     $transaction_response = $response->getTransactionResponse();
        
    //     if (
    //         $transaction_response &&
    //         $transaction_response->getErrors()
    //     )  {
    //     $errors = $transaction_response->getErrors();
        
    //         if (!empty($errors)) {
    //             $error_message = $errors[0]->getErrorText();
    //         }
    //     }
        
    //         wp_die(esc_html($error_message));
        
    //     } else {
        
    //         update_user_meta($user_id, 'payment_status', 'failed');
        
    //         wp_die(
    //             'No response from Authorize.net. Please try again or contact support.'
    //         );
    //     }
    
    
        if ($response && $response->getMessages()) {

            $result_code = $response->getMessages()->getResultCode();
        
            if ($result_code === "Ok") {
        
                update_user_meta($user_id, 'payment_status', 'paid');
        
                $transaction_response = $response->getTransactionResponse();
        
                if ($transaction_response) {
                    $transId = $transaction_response->getTransId();
        
                    if ($transId) {
                        update_user_meta($user_id, 'transaction_id', $transId);
                    }
                }
        
                wp_redirect(home_url('/thank-you/'));
                exit;
            }
        
            update_user_meta($user_id, 'payment_status', 'failed');
        
            $error_message = 'Payment failed. Please try again.';
        
            $messages = $response->getMessages()->getMessage();
        
            if (!empty($messages)) {
                $error_message = $messages[0]->getText();
            }
        
            $transaction_response = $response->getTransactionResponse();
        
            if (
                $transaction_response &&
                $transaction_response->getErrors()
            ) {
                $errors = $transaction_response->getErrors();
        
                if (!empty($errors)) {
                    $error_message = $errors[0]->getErrorText();
                }
            }
        
            wp_die(esc_html($error_message));
        
        } else {
        
            update_user_meta($user_id, 'payment_status', 'failed');
        
            wp_die(
                'No response from Authorize.net. Please try again or contact support.'
            );
        }
    
    
});
