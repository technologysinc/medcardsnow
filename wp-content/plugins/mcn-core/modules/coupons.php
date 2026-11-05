<?php
/**
 * MCN Core module: Coupons
 * Coupon admin page and the checkout coupon field.
 *
 * Moved unchanged from the child theme's functions.php on 2026-10-30.
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;

 

/* =========================================================
 * COUPON MANAGEMENT
 * Admin menu: Coupons. Stored in WordPress option mcn_payment_coupons.
 * ========================================================= */
if (!defined('MCN_COUPONS_OPTION')) define('MCN_COUPONS_OPTION', 'mcn_payment_coupons');

function mcn_get_coupons() {
    $coupons = get_option(MCN_COUPONS_OPTION, array());
    return is_array($coupons) ? $coupons : array();
}

function mcn_get_coupon_discount($code, $subtotal) {
    $code = strtoupper(trim(sanitize_text_field((string) $code)));
    $subtotal = max(0, (float) $subtotal);
    if ($code === '' || $subtotal <= 0) return array('valid' => false, 'discount' => 0, 'message' => '');
    foreach (mcn_get_coupons() as $coupon) {
        if (strtoupper((string) ($coupon['code'] ?? '')) !== $code) continue;
        if (empty($coupon['enabled'])) return array('valid'=>false,'discount'=>0,'message'=>'This coupon is currently disabled.');
        if (!empty($coupon['expiry']) && strtotime($coupon['expiry'] . ' 23:59:59') < current_time('timestamp')) return array('valid'=>false,'discount'=>0,'message'=>'This coupon has expired.');
        $limit = absint($coupon['usage_limit'] ?? 0);
        if ($limit > 0 && absint($coupon['used_count'] ?? 0) >= $limit) return array('valid'=>false,'discount'=>0,'message'=>'This coupon has reached its usage limit.');
        if ($subtotal < (float) ($coupon['minimum'] ?? 0)) return array('valid'=>false,'discount'=>0,'message'=>'The order does not meet this coupon’s minimum amount.');
        $value = max(0, (float) ($coupon['value'] ?? 0));
        $discount = (($coupon['type'] ?? 'percent') === 'fixed') ? $value : ($subtotal * min(100, $value) / 100);
        $discount = min($subtotal, round($discount, 2));
        return array('valid'=>true,'discount'=>$discount,'message'=>'Coupon applied successfully.');
    }
    return array('valid'=>false,'discount'=>0,'message'=>'Invalid coupon code.');
}


/* AJAX coupon validation: applies the coupon immediately without submitting card fields. */
add_action('wp_ajax_mcn_apply_checkout_coupon', 'mcn_apply_checkout_coupon_ajax');
function mcn_apply_checkout_coupon_ajax() {
    if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Please log in again.'), 401);
    check_ajax_referer('mcn_apply_checkout_coupon', 'nonce');

    $plan_key = sanitize_key(wp_unslash($_POST['plan_key'] ?? ''));
    $year_key = sanitize_key(wp_unslash($_POST['year_plan'] ?? '3_year'));
    $hard_copy = sanitize_text_field(wp_unslash($_POST['hard_copy'] ?? 'no'));
    $id_card = sanitize_text_field(wp_unslash($_POST['id_card'] ?? 'no'));
    $legal = sanitize_text_field(wp_unslash($_POST['legal'] ?? ''));
    $code = strtoupper(sanitize_text_field(wp_unslash($_POST['coupon_code'] ?? '')));

    if (!$plan_key) {
        $plan_key = sanitize_key((string) get_user_meta(get_current_user_id(), 'payment_selected_plan', true));
    }
    if (!$plan_key) wp_send_json_error(array('message' => 'Please select a plan first.'));

    // Reuse the trusted server-side price calculation; never trust a browser-sent amount.
    $subtotal = cap_compute_server_total($plan_key, $year_key, $hard_copy, $id_card, $legal);
    if ($subtotal <= 0) {
        $saved = get_user_meta(get_current_user_id(), 'payment_amount', true);
        $subtotal = is_numeric($saved) ? (float) $saved : 0;
    }
    $result = mcn_get_coupon_discount($code, $subtotal);
    if (empty($result['valid'])) wp_send_json_error(array('message' => $result['message'] ?: 'Please enter a valid coupon code.'));

    $discount = (float) $result['discount'];
    $total = max(0, round($subtotal - $discount, 2));
    wp_send_json_success(array(
        'message' => $result['message'],
        'code' => $code,
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'discount' => number_format($discount, 2, '.', ''),
        'total' => number_format($total, 2, '.', ''),
    ));
}

add_action('admin_menu', function () {
    add_menu_page('Coupons', 'Coupons', 'manage_options', 'mcn-coupons', 'mcn_render_coupons_page', 'dashicons-tickets-alt', 59);
});
add_action('admin_post_mcn_save_coupon', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('mcn_save_coupon');
    $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['code'] ?? ''))));
    $type = sanitize_key(wp_unslash($_POST['type'] ?? 'percent'));
    $value = (float) ($_POST['value'] ?? 0);
    $expiry = sanitize_text_field(wp_unslash($_POST['expiry'] ?? ''));
    $limit = absint($_POST['usage_limit'] ?? 0);
    $minimum = max(0, (float) ($_POST['minimum'] ?? 0));
    if (!$code || !in_array($type, array('percent','fixed'), true) || $value <= 0 || ($type === 'percent' && $value > 100)) {
        wp_safe_redirect(add_query_arg('coupon_notice', 'invalid', admin_url('admin.php?page=mcn-coupons'))); exit;
    }
    if ($expiry && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) $expiry = '';
    $coupons = mcn_get_coupons();
    $old_used = isset($coupons[$code]['used_count']) ? absint($coupons[$code]['used_count']) : 0;
    $coupons[$code] = array('code'=>$code,'type'=>$type,'value'=>round($value,2),'expiry'=>$expiry,'usage_limit'=>$limit,'minimum'=>round($minimum,2),'enabled'=>isset($_POST['enabled']) ? 1 : 0,'used_count'=>$old_used);
    update_option(MCN_COUPONS_OPTION, $coupons, false);
    wp_safe_redirect(add_query_arg('coupon_notice', 'saved', admin_url('admin.php?page=mcn-coupons'))); exit;
});
add_action('admin_post_mcn_delete_coupon', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('mcn_delete_coupon');
    $code = strtoupper(sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    $coupons = mcn_get_coupons(); unset($coupons[$code]); update_option(MCN_COUPONS_OPTION, $coupons, false);
    wp_safe_redirect(admin_url('admin.php?page=mcn-coupons')); exit;
});
add_action('admin_post_mcn_toggle_coupon', function () {
    if (!current_user_can('manage_options')) wp_die('Permission denied.');
    check_admin_referer('mcn_toggle_coupon');
    $code = strtoupper(sanitize_text_field(wp_unslash($_POST['code'] ?? '')));
    $coupons = mcn_get_coupons();
    if (isset($coupons[$code])) { $coupons[$code]['enabled'] = empty($coupons[$code]['enabled']) ? 1 : 0; update_option(MCN_COUPONS_OPTION, $coupons, false); }
    wp_safe_redirect(admin_url('admin.php?page=mcn-coupons')); exit;
});
function mcn_render_coupons_page() {
    if (!current_user_can('manage_options')) return;
    $coupons = mcn_get_coupons();
    echo '<div class="wrap mcn-coupons-admin"><h1>Coupon Management</h1>';
    if (isset($_GET['coupon_notice']) && $_GET['coupon_notice'] === 'saved') echo '<div class="notice notice-success"><p>Coupon saved.</p></div>';
    if (isset($_GET['coupon_notice']) && $_GET['coupon_notice'] === 'invalid') echo '<div class="notice notice-error"><p>Please enter a valid code and discount value (percentage must be 1–100).</p></div>';
    echo '<style>.mcn-coupon-panel{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;max-width:1100px;margin:18px 0}.mcn-coupon-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px}.mcn-coupon-grid label{display:block;font-weight:600;margin-bottom:6px}.mcn-coupon-grid input,.mcn-coupon-grid select{width:100%;max-width:none}.mcn-coupon-table{width:100%;border-collapse:collapse}.mcn-coupon-table th,.mcn-coupon-table td{padding:12px;border-bottom:1px solid #eee;text-align:left}.mcn-coupon-code{font-weight:700;letter-spacing:.5px}@media(max-width:782px){.mcn-coupon-grid{grid-template-columns:1fr}}</style>';
    echo '<div class="mcn-coupon-panel"><h2>Add Coupon</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    echo '<input type="hidden" name="action" value="mcn_save_coupon">'; wp_nonce_field('mcn_save_coupon');
    echo '<div class="mcn-coupon-grid"><div><label>Coupon Code</label><input required name="code" type="text" placeholder="e.g. WELCOME20" maxlength="40"></div><div><label>Discount Type</label><select name="type"><option value="percent">Percentage (%)</option><option value="fixed">Fixed amount ($)</option></select></div><div><label>Discount Value</label><input required name="value" type="number" min="0.01" step="0.01"></div><div><label>Expiry Date (optional)</label><input name="expiry" type="date"></div><div><label>Usage Limit (0 = unlimited)</label><input name="usage_limit" type="number" min="0" step="1" value="0"></div><div><label>Minimum Order ($)</label><input name="minimum" type="number" min="0" step="0.01" value="0"></div></div><p><label><input type="checkbox" name="enabled" value="1" checked> Enable coupon immediately</label></p><p><button class="button button-primary">Save Coupon</button></p></form></div>';
    echo '<div class="mcn-coupon-panel"><h2>Existing Coupons</h2><table class="mcn-coupon-table"><thead><tr><th>Code</th><th>Discount</th><th>Expiry</th><th>Usage</th><th>Minimum</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    if (!$coupons) echo '<tr><td colspan="7">No coupons created yet.</td></tr>';
    foreach ($coupons as $c) {
        $code = (string) ($c['code'] ?? ''); $limit = absint($c['usage_limit'] ?? 0); $used = absint($c['used_count'] ?? 0);
        echo '<tr><td class="mcn-coupon-code">'.esc_html($code).'</td><td>'.esc_html(($c['type'] ?? '') === 'fixed' ? '$'.number_format((float)$c['value'],2) : number_format((float)$c['value'],2).'%').'</td><td>'.esc_html($c['expiry'] ?: 'Never').'</td><td>'.esc_html($used.' / '.($limit ? $limit : '∞')).'</td><td>$'.esc_html(number_format((float)($c['minimum'] ?? 0),2)).'</td><td>'.(!empty($c['enabled']) ? '<span style="color:#16803c">Enabled</span>' : '<span style="color:#b32d2e">Disabled</span>').'</td><td><form style="display:inline-block" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mcn_toggle_coupon"><input type="hidden" name="code" value="'.esc_attr($code).'">'; wp_nonce_field('mcn_toggle_coupon'); echo '<button class="button">'.(!empty($c['enabled']) ? 'Disable' : 'Enable').'</button></form> <form style="display:inline-block" method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Delete this coupon?\')"><input type="hidden" name="action" value="mcn_delete_coupon"><input type="hidden" name="code" value="'.esc_attr($code).'">'; wp_nonce_field('mcn_delete_coupon'); echo '<button class="button button-link-delete">Delete</button></form></td></tr>';
    }
    echo '</tbody></table></div></div>';
}
