<?php
/**
 * Plugin Name: MCN State Packages
 * Description: Manage per-state medical card packages (label + price) from one admin screen. Feeds the Med Cards Now Gravity Forms plan field and payment flow, and provides a display shortcode.
 * Version: 2.0.0
 * Author: Med Cards Now
 * License: GPL-2.0-or-later
 *
 * =====================================================================
 * WHAT CHANGED IN v2
 * =====================================================================
 * Package KEYS are now generated with UNDERSCORES (e.g. "1_year"),
 * not hyphens ("1-year"). Hyphenated keys did not survive every layer
 * of the checkout (sanitize_key / GF POST), which produced the $0.00
 * total. After updating, open each saved state in "State Packages" and
 * click Save once so its keys regenerate as underscores.
 *
 * SETUP (one-time wiring in functions.php) is unchanged from v1:
 *   A) medcards_get_state_pricing() merges mcn_sp_pricing_map()
 *   B) the non-CA `} else {` in medcards_populate_plan_choices_by_state()
 *      delegates to this plugin when mcn_sp_is_managed($state) is true
 * =====================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MCN_SP_OPTION', 'mcn_sp_data');

/* =====================================================================
 * KEY GENERATION — underscores, checkout-safe
 * ===================================================================== */
function mcn_sp_make_key($label)
{
    // Lowercase, strip to a-z 0-9, collapse separators into single underscores.
    $key = strtolower((string) $label);
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim($key, '_');
    return $key !== '' ? $key : 'plan';
}

/* =====================================================================
 * PUBLIC DATA API
 * ===================================================================== */
function mcn_sp_get_all()
{
    $data = get_option(MCN_SP_OPTION, array());
    return is_array($data) ? $data : array();
}

function mcn_sp_get_packages($state)
{
    $data  = mcn_sp_get_all();
    $state = (string) $state;
    return (isset($data[$state]) && is_array($data[$state])) ? $data[$state] : array();
}

function mcn_sp_is_managed($state)
{
    if ((string) $state === 'California') {
        return false;
    }
    return !empty(mcn_sp_get_packages($state));
}

function mcn_sp_get_price($state, $key)
{
    foreach (mcn_sp_get_packages($state) as $pkg) {
        if (isset($pkg['key']) && (string) $pkg['key'] === (string) $key) {
            return (float) $pkg['price'];
        }
    }
    return null;
}

/** [ state => [ key => price ] ] for every managed state. */
function mcn_sp_pricing_map()
{
    $map = array();
    foreach (mcn_sp_get_all() as $state => $pkgs) {
        if ((string) $state === 'California' || !is_array($pkgs)) {
            continue;
        }
        foreach ($pkgs as $pkg) {
            if (isset($pkg['key'])) {
                $map[$state][$pkg['key']] = (float) $pkg['price'];
            }
        }
    }
    return $map;
}

/** [ key => label ] across all managed states (for checkout display). */
function mcn_sp_label_map()
{
    $map = array();
    foreach (mcn_sp_get_all() as $state => $pkgs) {
        if ((string) $state === 'California' || !is_array($pkgs)) {
            continue;
        }
        foreach ($pkgs as $pkg) {
            if (isset($pkg['key']) && !isset($map[$pkg['key']])) {
                $map[$pkg['key']] = isset($pkg['label']) ? $pkg['label'] : $pkg['key'];
            }
        }
    }
    return $map;
}

/* =====================================================================
 * US STATES (California removed)
 * ===================================================================== */
function mcn_sp_states_list()
{
    return array(
        'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'Colorado', 'Connecticut',
        'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois',
        'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine',
        'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi',
        'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire',
        'New Jersey', 'New Mexico', 'New York', 'North Carolina',
        'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania',
        'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee',
        'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington',
        'West Virginia', 'Wisconsin', 'Wyoming', 'District of Columbia',
    );
}

/* =====================================================================
 * ADMIN MENU + PAGE
 * ===================================================================== */
add_action('admin_menu', function () {
    add_menu_page(
        'State Packages',
        'State Packages',
        'manage_options',
        'mcn-state-packages',
        'mcn_sp_render_admin',
        'dashicons-list-view',
        56
    );
});

function mcn_sp_render_admin()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $all       = mcn_sp_get_all();
    $editing   = isset($_GET['edit']) ? sanitize_text_field(wp_unslash($_GET['edit'])) : '';
    $edit_pkgs = ($editing !== '' && isset($all[$editing])) ? $all[$editing] : array();

    if (empty($edit_pkgs)) {
        $edit_pkgs = array(array('key' => '', 'label' => '', 'price' => ''));
    }

    $notice = isset($_GET['mcn_sp_notice']) ? sanitize_key($_GET['mcn_sp_notice']) : '';
    ?>
    <div class="wrap mcn-sp-wrap">
        <h1>State Packages</h1>
        <p class="description">Add a state and its packages (label + price). These feed the plan selection and the payment total automatically. California is handled separately and is not listed here.</p>

        <?php if ($notice === 'saved') : ?>
            <div class="notice notice-success is-dismissible"><p>Packages saved.</p></div>
        <?php elseif ($notice === 'deleted') : ?>
            <div class="notice notice-success is-dismissible"><p>State removed.</p></div>
        <?php elseif ($notice === 'error') : ?>
            <div class="notice notice-error is-dismissible"><p>Nothing saved — add at least one package with a label.</p></div>
        <?php endif; ?>

        <div class="mcn-sp-panel">
            <h2><?php echo $editing !== '' ? 'Edit ' . esc_html($editing) : 'Add / Update a State'; ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mcn_sp_save_state">
                <?php wp_nonce_field('mcn_sp_save_state'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mcn_sp_state">State</label></th>
                        <td>
                            <select name="mcn_sp_state" id="mcn_sp_state" required>
                                <option value="">— Select a state —</option>
                                <?php foreach (mcn_sp_states_list() as $st) : ?>
                                    <option value="<?php echo esc_attr($st); ?>" <?php selected($editing, $st); ?>>
                                        <?php echo esc_html($st); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h3>Packages</h3>
                <p class="description">Shown to the patient top to bottom, in this order. Price is the full amount charged for that package.</p>

                <table class="widefat mcn-sp-rows">
                    <thead>
                        <tr>
                            <th style="width:55%;">Label (what the patient sees)</th>
                            <th style="width:30%;">Price (USD)</th>
                            <th style="width:15%;"></th>
                        </tr>
                    </thead>
                    <tbody id="mcn-sp-rows-body">
                        <?php foreach ($edit_pkgs as $pkg) : ?>
                            <tr class="mcn-sp-row">
                                <td><input type="text" name="mcn_sp_label[]" value="<?php echo esc_attr(isset($pkg['label']) ? $pkg['label'] : ''); ?>" placeholder="e.g. New Patient — 1 Year"></td>
                                <td><input type="number" step="0.01" min="0" name="mcn_sp_price[]" value="<?php echo esc_attr(isset($pkg['price']) ? $pkg['price'] : ''); ?>" placeholder="e.g. 99"></td>
                                <td><button type="button" class="button mcn-sp-remove">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p><button type="button" class="button" id="mcn-sp-add-row">+ Add package</button></p>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save State Packages</button>
                    <?php if ($editing !== '') : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mcn-state-packages')); ?>" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <div class="mcn-sp-panel">
            <h2>Saved States</h2>
            <?php if (empty($all)) : ?>
                <p>No states configured yet.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>State</th><th>Packages</th><th style="width:160px;">Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($all as $state => $pkgs) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($state); ?></strong></td>
                                <td>
                                    <?php
                                    $bits = array();
                                    foreach ((array) $pkgs as $pkg) {
                                        $bits[] = esc_html($pkg['label']) . ' — $' . esc_html(number_format((float) $pkg['price'], 2)) . ' <code>' . esc_html($pkg['key']) . '</code>';
                                    }
                                    echo implode('<br>', $bits);
                                    ?>
                                </td>
                                <td>
                                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mcn-state-packages&edit=' . rawurlencode($state))); ?>">Edit</a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline" onsubmit="return confirm('Remove <?php echo esc_js($state); ?>?');">
                                        <input type="hidden" name="action" value="mcn_sp_delete_state">
                                        <input type="hidden" name="mcn_sp_state" value="<?php echo esc_attr($state); ?>">
                                        <?php wp_nonce_field('mcn_sp_delete_state'); ?>
                                        <button type="submit" class="button button-link-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p class="description" style="margin-top:14px;">
                Display shortcode (optional): <code>[mcn_state_packages]</code> shows the logged-in patient's state, or
                <code>[mcn_state_packages state="Illinois"]</code> for a specific state. You do <strong>not</strong> need a
                shortcode in the application form — the plan radio fills itself from here.
            </p>
        </div>
    </div>

    <style>
        .mcn-sp-panel{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px 22px;margin:18px 0;max-width:1000px;}
        .mcn-sp-rows input[type="text"],.mcn-sp-rows input[type="number"]{width:100%;}
        .mcn-sp-rows td{vertical-align:middle;}
        .mcn-sp-panel code{background:#f0f0f1;padding:1px 5px;border-radius:3px;font-size:11px;}
    </style>

    <script>
    (function () {
        var addBtn = document.getElementById('mcn-sp-add-row');
        var body   = document.getElementById('mcn-sp-rows-body');
        if (!addBtn || !body) return;

        function wireRemove(btn) {
            btn.addEventListener('click', function () {
                var rows = body.querySelectorAll('.mcn-sp-row');
                if (rows.length > 1) {
                    btn.closest('.mcn-sp-row').remove();
                } else {
                    btn.closest('.mcn-sp-row').querySelectorAll('input').forEach(function (i) { i.value = ''; });
                }
            });
        }

        body.querySelectorAll('.mcn-sp-remove').forEach(wireRemove);

        addBtn.addEventListener('click', function () {
            var row = document.createElement('tr');
            row.className = 'mcn-sp-row';
            row.innerHTML =
                '<td><input type="text" name="mcn_sp_label[]" placeholder="e.g. Renewal"></td>' +
                '<td><input type="number" step="0.01" min="0" name="mcn_sp_price[]" placeholder="e.g. 129"></td>' +
                '<td><button type="button" class="button mcn-sp-remove">Remove</button></td>';
            body.appendChild(row);
            wireRemove(row.querySelector('.mcn-sp-remove'));
        });
    })();
    </script>
    <?php
}

/* =====================================================================
 * SAVE / DELETE HANDLERS
 * ===================================================================== */
add_action('admin_post_mcn_sp_save_state', 'mcn_sp_handle_save');
function mcn_sp_handle_save()
{
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }
    check_admin_referer('mcn_sp_save_state');

    $state = isset($_POST['mcn_sp_state']) ? sanitize_text_field(wp_unslash($_POST['mcn_sp_state'])) : '';

    if ($state === '' || $state === 'California') {
        wp_safe_redirect(admin_url('admin.php?page=mcn-state-packages&mcn_sp_notice=error'));
        exit;
    }

    $labels = isset($_POST['mcn_sp_label']) ? (array) wp_unslash($_POST['mcn_sp_label']) : array();
    $prices = isset($_POST['mcn_sp_price']) ? (array) wp_unslash($_POST['mcn_sp_price']) : array();

    $packages  = array();
    $used_keys = array();

    foreach ($labels as $i => $raw_label) {
        $label = sanitize_text_field(trim((string) $raw_label));
        if ($label === '') {
            continue;
        }

        $price = isset($prices[$i]) ? round((float) $prices[$i], 2) : 0.0;

        $key  = mcn_sp_make_key($label); // underscores, checkout-safe
        $base = $key;
        $n    = 2;
        while (in_array($key, $used_keys, true)) {
            $key = $base . '_' . $n;
            $n++;
        }
        $used_keys[] = $key;

        $packages[] = array('key' => $key, 'label' => $label, 'price' => $price);
    }

    $all = mcn_sp_get_all();

    if (empty($packages)) {
        unset($all[$state]);
        update_option(MCN_SP_OPTION, $all);
        wp_safe_redirect(admin_url('admin.php?page=mcn-state-packages&mcn_sp_notice=error'));
        exit;
    }

    $all[$state] = $packages;
    update_option(MCN_SP_OPTION, $all);

    wp_safe_redirect(admin_url('admin.php?page=mcn-state-packages&mcn_sp_notice=saved'));
    exit;
}

add_action('admin_post_mcn_sp_delete_state', 'mcn_sp_handle_delete');
function mcn_sp_handle_delete()
{
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied.');
    }
    check_admin_referer('mcn_sp_delete_state');

    $state = isset($_POST['mcn_sp_state']) ? sanitize_text_field(wp_unslash($_POST['mcn_sp_state'])) : '';

    $all = mcn_sp_get_all();
    if ($state !== '' && isset($all[$state])) {
        unset($all[$state]);
        update_option(MCN_SP_OPTION, $all);
    }

    wp_safe_redirect(admin_url('admin.php?page=mcn-state-packages&mcn_sp_notice=deleted'));
    exit;
}

/* =====================================================================
 * DISPLAY SHORTCODE  [mcn_state_packages]
 * ===================================================================== */
add_shortcode('mcn_state_packages', 'mcn_sp_shortcode');
function mcn_sp_shortcode($atts)
{
    $atts = shortcode_atts(array('state' => ''), $atts, 'mcn_state_packages');

    $state = sanitize_text_field((string) $atts['state']);
    if ($state === '' && is_user_logged_in()) {
        $state = (string) get_user_meta(get_current_user_id(), 'state', true);
    }

    if ($state === '') {
        return '';
    }

    $packages = mcn_sp_get_packages($state);
    if (empty($packages)) {
        return '';
    }

    ob_start();
    ?>
    <div class="mcn-sp-cards">
        <?php foreach ($packages as $pkg) : ?>
            <div class="mcn-sp-card">
                <div class="mcn-sp-card-price">$<?php echo esc_html(number_format((float) $pkg['price'], 0)); ?></div>
                <div class="mcn-sp-card-label"><?php echo esc_html($pkg['label']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <style>
        .mcn-sp-cards{display:flex;flex-wrap:wrap;gap:16px;margin:20px 0;}
        .mcn-sp-card{flex:1 1 200px;max-width:280px;background:#fff;border:1px solid #e3e8ec;border-radius:14px;padding:22px 20px;text-align:center;box-shadow:0 6px 18px rgba(0,0,0,.05);}
        .mcn-sp-card-price{font-size:30px;font-weight:800;color:#176b3a;margin-bottom:8px;}
        .mcn-sp-card-label{font-size:15px;color:#333;}
    </style>
    <?php
    return ob_get_clean();
}