<?php
/**
 * MCN Core module: Shared helpers
 * State-name cleanup (CA to California), staff-account check, login-token hashing, and the rule for where profile scripts load. Most modules need this.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;

/* ---------------------------------------------------------------------
 * STATE NORMALIZATION
 * The Shipping Address "State" field is free text, and it writes to the
 * SAME 'state' user meta that plan pricing depends on. A patient typing
 * "CA" or "IL" used to make the pricing lookup fail (the pricing table is
 * keyed by full names such as "California" / "Illinois"), which produced a
 * $0.00 total and the "Unable to determine the payment amount" error.
 * These helpers canonicalise the value on write and on read.
 * ------------------------------------------------------------------- */
if (!function_exists('mcn_normalize_state_name')) {

    function mcn_us_state_abbreviation_map()
    {
        return array(
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
            'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
            'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
            'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
            'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
            'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
            'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
            'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        );
    }

    /**
     * Turn "ca", "CA", " illinois " into the canonical "California" / "Illinois".
     * Unknown values are returned trimmed and unchanged.
     */
    function mcn_normalize_state_name($state)
    {
        $state = trim((string) $state);
        if ($state === '') {
            return '';
        }

        $map = mcn_us_state_abbreviation_map();

        // Two-letter abbreviation.
        $upper = strtoupper($state);
        if (isset($map[$upper])) {
            return $map[$upper];
        }

        // Full name in any casing / spacing.
        foreach ($map as $full) {
            if (strcasecmp($state, $full) === 0) {
                return $full;
            }
        }

        return sanitize_text_field($state);
    }
}

if (!function_exists('mcn_get_pricing_state')) {
    /**
     * Canonical state used for plan pricing and plan-choice rendering.
     */
    function mcn_get_pricing_state($user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        if (!$user_id) {
            return '';
        }
        return mcn_normalize_state_name((string) get_user_meta($user_id, 'state', true));
    }
}


if (!function_exists('mcn_user_is_privileged')) {
    /**
     * True for staff accounts (anyone who can edit content or manage the site).
     * Passwordless magic links are refused for these accounts. Fails closed.
     */
    function mcn_user_is_privileged($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return true;
        }
        return user_can($user_id, 'edit_posts')
            || user_can($user_id, 'manage_options')
            || user_can($user_id, 'list_users');
    }
}

if (!function_exists('mcn_secure_login_token_hash')) {
    /**
     * Keyed hash of a magic-link token. Only this hash is stored in the database.
     */
    function mcn_secure_login_token_hash($token)
    {
        return hash_hmac('sha256', (string) $token, wp_salt('auth'));
    }
}

if (!function_exists('mcn_should_load_profile_assets')) {
    /**
     * The patient-profile CSS/JS (about 40 KB inline) is only needed where
     * Form 2 lives. Logged-out visitors on every other page (home, blog,
     * state pages) no longer download it. If a public page ever needs these
     * card styles, return true for it via the 'mcn_load_profile_assets' filter.
     */
    function mcn_should_load_profile_assets()
    {
        $load = is_user_logged_in() || is_page('medical-card-profile');
        return (bool) apply_filters('mcn_load_profile_assets', $load);
    }
}

/*
 * Repair state values saved before normalization existed ("CA", "il", ...)
 * for the logged-in user, so every older code path that compares the state
 * with a full name ("California", "Illinois") keeps working.
 */
add_action('init', function () {
    if (!is_user_logged_in() || !function_exists('mcn_normalize_state_name')) {
        return;
    }
    $uid = get_current_user_id();
    $raw = (string) get_user_meta($uid, 'state', true);
    if ($raw === '') {
        return;
    }
    $normalized = mcn_normalize_state_name($raw);
    if ($normalized !== $raw) {
        update_user_meta($uid, 'state', $normalized);
    }
}, 1);
