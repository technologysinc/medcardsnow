<?php
/**
 * MCN Core module: Payment engine
 * Server-side price calculation, gateway settings storage, and payment error messages.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;


$GLOBALS['cap_payment_error'] = '';

/* =========================================================
 * PRICE / PLAN WHITELIST (server-side, cannot be tampered)
 * ========================================================= */
function cap_get_pricing_table()
{
    return array(
        'new_pdf_id_card'   => 64.99,
        'caregiver_new'     => 99.99,
        'growers_license'   => 189.99,
        'new_pdf'           => 37.00,
        'renewal_hard_copy' => 47.00,
    );
}

function cap_get_plan_labels()
{
    $labels = array(
        'new'               => 'New Patient',
        'renewal'           => 'Renewal',
        'two_year'          => '2 Year Pricing',
        'three_year'        => '3 Year Pricing',
        'caregiver_new'     => 'Caregiver + New Patient',
        'growers_license'   => 'License to Cultivate up to 99 Plants',
        'new_pdf'           => 'New Patient — PDF Only',
        'new_pdf_id_card'   => 'Instant PDF + Patient ID Card',
        'renewal_hard_copy' => 'Existing Patient Renewal — With Hard Copy',
    );

    // Packages created in the State Packages plugin (e.g. "1 Year" for Oklahoma).
    if (function_exists('mcn_sp_get_all')) {
        foreach ((array) mcn_sp_get_all() as $state_packages) {
            foreach ((array) $state_packages as $pkg) {
                if (!empty($pkg['key']) && !isset($labels[$pkg['key']])) {
                    $labels[$pkg['key']] = !empty($pkg['label']) ? (string) $pkg['label'] : (string) $pkg['key'];
                }
            }
        }
    }

    return $labels;
}

/**
 * True on a staging copy of the site (a "staging." address, a /staging path,
 * or WP_ENVIRONMENT_TYPE set to "staging").
 */
function mcn_is_staging_site()
{
    if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'staging') {
        return true;
    }
    $home = (string) home_url('/');
    $host = strtolower((string) wp_parse_url($home, PHP_URL_HOST));
    $path = strtolower((string) wp_parse_url($home, PHP_URL_PATH));
    return strpos($host, 'staging.') === 0 || strpos($path, '/staging/') === 0;
}

/** Folder that holds the Authorize.Net library (in the child theme, as before). */
function mcn_authorize_vendor_dir()
{
    return trailingslashit(get_stylesheet_directory()) . 'custom-authorize-payment/vendor/';
}

/**
 * Load the Authorize.Net PHP library. Tries Composer's autoloader first, then
 * the library's own autoloader. Returns how it loaded, or '' if it could not.
 */
function mcn_load_authorize_sdk()
{
    static $method = null;
    $class = 'net\\authorize\\api\\contract\\v1\\MerchantAuthenticationType';

    if ($method !== null && class_exists($class)) {
        return $method;
    }
    if (class_exists($class)) {
        return $method = 'already loaded';
    }

    $dir = mcn_authorize_vendor_dir();
    $loaders = array(
        'composer autoload'    => $dir . 'autoload.php',
        'library own autoload' => $dir . 'authorizenet/authorizenet/autoload.php',
    );
    foreach ($loaders as $name => $file) {
        if (is_readable($file)) {
            require_once $file;
            if (class_exists($class)) {
                return $method = $name;
            }
        }
    }
    return '';
}

/** File-by-file report used by the Checks section of Tools > MCN Modules. */
function mcn_authorize_sdk_report()
{
    $dir  = mcn_authorize_vendor_dir();
    $rel  = 'custom-authorize-payment/vendor/';
    $file = 'authorizenet/authorizenet/lib/net/authorize/api/contract/v1/MerchantAuthenticationType.php';
    $classmap = $dir . 'composer/autoload_classmap.php';

    return array(
        array($rel . 'autoload.php', is_readable($dir . 'autoload.php')),
        array($rel . 'composer/autoload_classmap.php lists the library', is_readable($classmap) && strpos((string) file_get_contents($classmap), 'MerchantAuthenticationType') !== false),
        array($rel . 'authorizenet/authorizenet/ (the library itself)', is_dir($dir . 'authorizenet/authorizenet')),
        array($rel . $file, is_readable($dir . $file)),
    );
}

add_action('mcn_core_admin_diagnostics', 'mcn_authorize_render_diagnostics');

function mcn_authorize_render_diagnostics()
{
    $settings = function_exists('cap_get_settings') ? cap_get_settings() : array();
    $sandbox  = !empty($settings['sandbox']);
    $staging  = mcn_is_staging_site();
    $loaded   = mcn_load_authorize_sdk();
    $ok  = '<span style="color:#0a7d32;font-weight:600">OK</span>';
    $bad = '<span style="color:#b32d2e;font-weight:600">Problem</span>';

    echo '<h3>Payments (Authorize.Net)</h3><table class="widefat striped" style="max-width:1200px"><tbody>';
    echo '<tr><td style="width:45%">Payment library loads</td><td>' . ($loaded !== '' ? $ok . ' (' . esc_html($loaded) . ')' : $bad . ' The library could not be loaded, so payments cannot run. Copy the <code>custom-authorize-payment</code> folder from the live child theme into this site\'s child theme.') . '</td></tr>';
    foreach (mcn_authorize_sdk_report() as $row) {
        echo '<tr><td><small>Child theme: <code>' . esc_html($row[0]) . '</code></small></td><td>' . ($row[1] ? $ok : $bad . ' missing') . '</td></tr>';
    }
    echo '<tr><td>Gateway mode</td><td>' . ($sandbox ? 'Sandbox (test cards, no real charges)' : '<strong>Production (real charges)</strong>') . '</td></tr>';
    echo '<tr><td>This site</td><td>' . ($staging ? 'Staging copy' : 'Live') . '</td></tr>';
    if ($staging && !$sandbox) {
        $allowed = defined('MCN_ALLOW_LIVE_PAYMENTS_ON_STAGING') && MCN_ALLOW_LIVE_PAYMENTS_ON_STAGING;
        echo '<tr><td>Real charges on this staging site</td><td>' . ($allowed ? $bad . ' allowed by MCN_ALLOW_LIVE_PAYMENTS_ON_STAGING' : $ok . ' blocked. Switch to Sandbox mode with sandbox keys to test payments.') . '</td></tr>';
    }
    echo '</tbody></table>';
}

/**
 * Recompute the total server-side from whitelisted values.
 * This is the ONLY trusted source for the charge amount.
 *
 * @return float
 */
function cap_compute_server_total($plan_key, $year_key, $hard_copy, $id_card, $legal_csv)
{
    $user_id = get_current_user_id();
    $state   = $user_id ? mcn_get_pricing_state($user_id) : '';
 
    /* -----------------------------------------------------------------
     * NON-CALIFORNIA STATES — flat per-plan price, no year math/add-ons.
     * ----------------------------------------------------------------- */
    if ($state !== '' && $state !== 'California') {
 
        $state_pricing = function_exists('medcards_get_state_pricing')
            ? medcards_get_state_pricing()
            : array();
 
        if (isset($state_pricing[$state][$plan_key])) {
            return round((float) $state_pricing[$state][$plan_key], 2);
        }
 
        return 0.0;
    }
 
    /* -----------------------------------------------------------------
     * CALIFORNIA (and unknown-state fallback) — unchanged.
     * ----------------------------------------------------------------- */
    $pricing = cap_get_pricing_table();
    if (!isset($pricing[$plan_key])) {
        return 0.0;
    }
 
    $base = (float) $pricing[$plan_key];
 
    $year_plans = array(
        '1_year' => array('years' => 1, 'discount' => 0),
        '2_year' => array('years' => 2, 'discount' => 8),
        '3_year' => array('years' => 3, 'discount' => 16),
    );
    $year_cfg = isset($year_plans[$year_key]) ? $year_plans[$year_key] : $year_plans['1_year'];
 
    $original     = $base * $year_cfg['years'];
    $discount_amt = $original * ($year_cfg['discount'] / 100);
    $total        = $original - $discount_amt;
 
    if ($plan_key !== 'renewal_hard_copy' && $hard_copy === 'yes') {
        $total += 10.00;
    }
 
    if ($plan_key !== 'new_pdf_id_card' && $id_card === 'yes') {
        $total += 16.00;
    }
 
    $legal_values = array_filter(array_map('trim', explode(',', (string) $legal_csv)));
    if (isset($_POST['ca_legal_support_letter_ui'])) {
        $ui_values = $_POST['ca_legal_support_letter_ui'];
        $legal_values = array_merge($legal_values, is_array($ui_values) ? $ui_values : explode(',', (string) $ui_values));
    }
    $legal_values = array_values(array_unique(array_filter(array_map('sanitize_text_field', $legal_values))));
    $total += function_exists('medcards_get_legal_letter_total_from_values_safe')
        ? (float) medcards_get_legal_letter_total_from_values_safe($legal_values)
        : (count(array_filter($legal_values, function ($v) { return $v !== '' && $v !== 'none'; })) * 79.00);
 
    return round($total, 2);
}


/* =========================================================
 * ERROR MESSAGE MAP
 * ========================================================= */
function cap_get_payment_error_details($payment_error)
{
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
        'staging_live_blocked' => array(
            'title'   => 'Live Payments Blocked on Staging',
            'message' => 'This is a staging copy of the site, so real card charges are blocked. Switch Authorize.net to Sandbox mode (with sandbox keys) to test payments here.'
        ),
        'amount_missing' => array(
            'title'   => 'Payment Error',
            'message' => 'Unable to determine the payment amount. Please go back and select a plan before checking out.'
        ),
        'expired_card' => array(
            'title'   => 'Card Expired',
            'message' => 'This card has expired. Please use a different card.'
        ),
        'duplicate' => array(
            'title'   => 'Duplicate Transaction',
            'message' => 'A similar transaction was just submitted. Please wait a minute before trying again.'
        ),
        'avs_mismatch' => array(
            'title'   => 'Address Mismatch',
            'message' => 'The billing address does not match the card. Please check your billing address and try again.'
        ),
        'invalid_address' => array(
            'title'   => 'Missing Information',
            'message' => 'Please complete your shipping address (address, city, state and ZIP) and try again.'
        ),
        'invalid_coupon' => array(
            'title'   => 'Invalid Coupon',
            'message' => 'The coupon code is invalid or no longer available. Please remove it or enter a valid code.'
        ),
        'security' => array(
            'title'   => 'Session Expired',
            'message' => 'Your session has expired. Please refresh the page and try again.'
        ),
        'not_logged_in' => array(
            'title'   => 'Please Log In',
            'message' => 'You must be logged in to complete this payment. Please log in and try again.'
        ),
        'processing' => array(
            'title'   => 'Payment In Progress',
            'message' => 'Your payment is already being processed. Please wait a moment.'
        ),
    );

    if (!isset($messages[$payment_error])) {
        $messages[$payment_error] = $messages['not_approved'];
    }

    return $messages[$payment_error];
}

/* =========================================================
 * PLUGIN SETTINGS
 * ========================================================= */
define('CAP_SETTINGS_OPTION', 'custom_authorize_payment_settings');

function cap_get_settings()
{
    $defaults = array(
        'enabled'           => 1,
        'sandbox'           => 1,
        'api_login_id'      => '',
        'transaction_key'   => '',
        'public_client_key' => '',
        'debug_logging'     => 0,
    );

    $settings = get_option(CAP_SETTINGS_OPTION, array());
    return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
}





/* =========================================================
 * PAYMENT HANDLER
 * ========================================================= */
/* =========================================================
 * PAYMENT HANDLER
 * ========================================================= */

function cap_payment_result($success, $error = '', $extra = array())
{
    return array_merge(array('success' => (bool) $success, 'error' => $error), $extra);
}

function cap_luhn_check($number)
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int) $number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) === 0;
}
