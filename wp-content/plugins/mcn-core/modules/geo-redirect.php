<?php
/**
 * MCN Core module: State landing redirects
 * Sends US visitors from /get-medical-card/ and /renew-medical-card/ to their state page.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;



// Get / renew medical card 
add_action('template_redirect', 'mcn_state_based_redirects');

function mcn_state_based_redirects()
{
    $request_uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = trim((string) parse_url($request_uri, PHP_URL_PATH), '/');

    // Only these two landing pages redirect by visitor state.
    $allowed_pages = array(
        'get-medical-card',
        'renew-medical-card'
    );

    if (!in_array($request_path, $allowed_pages, true)) {
        return;
    }

    // The result differs per visitor, so page caches (LiteSpeed etc.) must not
    // store one visitor's response and serve it to everyone else.
    if (!headers_sent()) {
        nocache_headers();
    }
    do_action('litespeed_control_set_nocache', 'MCN state-based redirect');

    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    if (!filter_var($ip, FILTER_VALIDATE_IP) || $ip === '127.0.0.1' || $ip === '::1') {
        return;
    }

    /*
     * Look up the visitor's location once per IP and cache it, with a short
     * timeout, so a slow geo service can never stall these pages.
     */
    $cache_key = 'mcn_geo_' . md5($ip);
    $geo       = get_transient($cache_key);

    if (!is_array($geo)) {

        $geo      = array();
        $response = wp_remote_get(
            'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode,regionName',
            array('timeout' => 2)
        );

        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (is_array($body) && isset($body['status']) && $body['status'] === 'success') {
                $geo = array(
                    'country' => strtoupper(isset($body['countryCode']) ? (string) $body['countryCode'] : ''),
                    'region'  => isset($body['regionName']) ? (string) $body['regionName'] : '',
                );
            }
        }

        // Cache failures briefly and successes for half a day.
        set_transient($cache_key, $geo, empty($geo) ? 10 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS);
    }

    // Only US visitors with a known state are redirected.
    if (empty($geo['country']) || $geo['country'] !== 'US' || empty($geo['region'])) {
        return;
    }

    $state_slug = sanitize_title($geo['region']);
    $page_slug  = $state_slug . '-medical-marijuana-card';
    $target_url = home_url("/{$page_slug}/");

    // Check that the state page exists locally (no HTTP request back to our own site).
    $target_id = url_to_postid($target_url);

    if (!$target_id) {
        $found = get_page_by_path($page_slug, OBJECT, get_post_types(array('public' => true)));
        $target_id = $found ? (int) $found->ID : 0;
    }

    if ($target_id && get_post_status($target_id) === 'publish') {
        wp_safe_redirect($target_url, 302);
        exit;
    }

    // No page exists for this state.
    if ($request_path === 'get-medical-card') {
        wp_safe_redirect(home_url('/medical-card/'), 302);
        exit;
    }

    // renew-medical-card: stay on this page. The previous code redirected to
    // /renew-medical-card/ itself here, which looped forever.
}
