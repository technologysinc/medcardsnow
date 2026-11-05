<?php
/**
 * MCN Core module: Patient address
 * Saves the Form 2 address and state to the patient profile, both while the
 * patient moves through the form and on final submission. Includes an admin
 * tool (Tools > Repair Addresses) to fix addresses saved wrongly before
 * 2026-10-30.
 *
 * Loaded by MCN Core; switch it on or off under Tools > MCN Modules.
 */
if (!defined('ABSPATH')) exit;

/**
 * Form 2 address fields, verified against real entries on 2026-10-30.
 * (The previous code read fields 15-20, which are the pet, grow-plants and
 * health questions, so it saved those answers as the patient's address.)
 */
function mcn_profile_address_field_ids()
{
    return array(
        'address_line_1' => '37',
        'address_line_2' => '38',
        'city'           => '39',
        'state'          => '49',
        'zip_code'       => '42',
        'country'        => '48',
    );
}

/**
 * Read the Form 2 address from a saved entry (array) or, when $entry is null,
 * from the form currently being submitted. Values are sanitized; the state is
 * normalized to its full name ("OK" becomes "Oklahoma").
 */
function mcn_read_profile_address($entry = null)
{
    $values = array();

    foreach (mcn_profile_address_field_ids() as $key => $field_id) {
        if (is_array($entry)) {
            $raw = rgar($entry, $field_id);
        } else {
            $raw = isset($_POST['input_' . $field_id]) ? wp_unslash($_POST['input_' . $field_id]) : '';
        }
        $values[$key] = is_scalar($raw) ? trim(sanitize_text_field((string) $raw)) : '';
    }

    if ($values['state'] !== '') {
        $values['state'] = mcn_normalize_state_name($values['state']);
    }

    return $values;
}

/**
 * Save the address to user meta (and the matching ACF fields). Empty values
 * never overwrite what is already saved.
 */
function mcn_save_profile_address($user_id, array $values)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }

    foreach ($values as $key => $value) {
        if ($value === '') {
            continue;
        }
        update_user_meta($user_id, $key, $value);
        if (function_exists('update_field')) {
            update_field($key, $value, 'user_' . $user_id);
        }
    }

    if ((string) get_user_meta($user_id, 'country', true) === '') {
        update_user_meta($user_id, 'country', 'United States');
    }
}

/*
 * Save the address as soon as the patient moves to another step of Form 2.
 * The plan choices, prices and checkout on later steps read the patient's
 * state and address from their profile, so they now see what the patient
 * just entered instead of an older value (for example the state from the
 * registration page).
 */
add_action('gform_post_paging_2', 'mcn_sync_profile_address_on_page_change', 10, 3);

function mcn_sync_profile_address_on_page_change($form, $source_page_number, $current_page_number)
{
    if (!is_user_logged_in()) {
        return;
    }

    // Only when the address fields are part of this submission.
    if (!isset($_POST['input_37']) && !isset($_POST['input_49'])) {
        return;
    }

    mcn_save_profile_address(get_current_user_id(), mcn_read_profile_address(null));
}

/*
 * Save the address from the final Form 2 submission.
 * Same hook and priority as before.
 */
add_action('gform_after_submission_2', 'medcards_save_gf_address_to_user_meta', 15, 2);

function medcards_save_gf_address_to_user_meta($entry, $form)
{
    if (!is_user_logged_in()) {
        return;
    }

    mcn_save_profile_address(get_current_user_id(), mcn_read_profile_address($entry));
}

/* =========================================================================
 * Repair tool: Tools > Repair Addresses (administrators only)
 * Finds patients whose saved address holds a Yes/No answer (the old bug) and
 * restores the address and state from their latest Form 2 entry.
 * Shows a dry run first; nothing changes until "Repair" is clicked.
 * ========================================================================= */

function mcn_address_repair_value_is_answer($value)
{
    return in_array(strtolower(trim((string) $value)), array('yes', 'no'), true);
}

/** IDs of patients whose saved address is corrupted (one batch). */
function mcn_address_repair_find_users($limit = 200)
{
    $answers = array('Yes', 'No', 'yes', 'no', 'YES', 'NO');
    $query = new WP_User_Query(array(
        'fields'     => 'ID',
        'number'     => (int) $limit,
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => 'address_line_1', 'value' => $answers, 'compare' => 'IN'),
            array('key' => 'country', 'value' => $answers, 'compare' => 'IN'),
        ),
    ));
    return array_map('intval', (array) $query->get_results());
}

function mcn_address_repair_latest_entry($user_id)
{
    if (!class_exists('GFAPI')) {
        return null;
    }
    $entries = GFAPI::get_entries(
        2,
        array(
            'status'        => 'active',
            'field_filters' => array(array('key' => 'created_by', 'value' => (int) $user_id)),
        ),
        array('key' => 'date_created', 'direction' => 'DESC'),
        array('offset' => 0, 'page_size' => 1)
    );
    return (!is_wp_error($entries) && !empty($entries)) ? $entries[0] : null;
}

/**
 * Work out (and optionally apply) the repair for one patient.
 * Returns array('status' => repairable|no_entry, 'fields' => [changed field names]).
 */
function mcn_address_repair_user($user_id, $apply)
{
    $entry   = mcn_address_repair_latest_entry($user_id);
    $changes = array();

    if (!$entry) {
        // Nothing to restore from: just remove the Yes/No answers so they stop
        // appearing as an address (country falls back to United States).
        foreach (array('address_line_1', 'address_line_2', 'city', 'zip_code', 'country') as $key) {
            if (mcn_address_repair_value_is_answer(get_user_meta($user_id, $key, true))) {
                $changes[$key] = ($key === 'country') ? 'United States' : '';
            }
        }
        if ($apply) {
            mcn_address_repair_apply($user_id, $changes);
        }
        return array('status' => 'no_entry', 'fields' => array_keys($changes));
    }

    $from_entry = mcn_read_profile_address($entry);

    foreach ($from_entry as $key => $value) {
        $current = (string) get_user_meta($user_id, $key, true);
        if ($value !== '' && $value !== $current) {
            $changes[$key] = $value;
        } elseif ($value === '' && mcn_address_repair_value_is_answer($current)) {
            // A Yes/No answer with nothing better in the entry: clear it (country falls back to United States).
            $changes[$key] = ($key === 'country') ? 'United States' : '';
        }
    }

    if ($apply) {
        mcn_address_repair_apply($user_id, $changes);
    }

    return array('status' => 'repairable', 'fields' => array_keys($changes));
}

function mcn_address_repair_apply($user_id, array $changes)
{
    foreach ($changes as $key => $value) {
        if ($value === '') {
            delete_user_meta($user_id, $key);
        } else {
            update_user_meta($user_id, $key, $value);
        }
        if (function_exists('update_field')) {
            update_field($key, $value, 'user_' . $user_id);
        }
    }
}

function mcn_address_repair_run($apply)
{
    $report = array('found' => 0, 'repairable' => 0, 'no_entry' => array(), 'fields' => array());
    foreach (mcn_address_repair_find_users(200) as $user_id) {
        $report['found']++;
        $result = mcn_address_repair_user($user_id, $apply);
        if ($result['status'] === 'no_entry') {
            $report['no_entry'][] = $user_id;
            continue;
        }
        $report['repairable']++;
        foreach ($result['fields'] as $field) {
            $report['fields'][$field] = isset($report['fields'][$field]) ? $report['fields'][$field] + 1 : 1;
        }
    }
    return $report;
}

add_action('admin_menu', function () {
    add_management_page('Repair Patient Addresses', 'Repair Addresses', 'manage_options', 'mcn-repair-addresses', 'mcn_address_repair_render_page');
});

add_action('admin_post_mcn_repair_addresses', function () {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 403);
    }
    check_admin_referer('mcn_repair_addresses');
    $report = mcn_address_repair_run(true);
    set_transient('mcn_address_repair_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS);
    wp_safe_redirect(admin_url('tools.php?page=mcn-repair-addresses&repaired=1'));
    exit;
});

function mcn_address_repair_render_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $labels = array(
        'address_line_1' => 'Address line 1',
        'address_line_2' => 'Address line 2',
        'city'           => 'City',
        'state'          => 'State',
        'zip_code'       => 'ZIP',
        'country'        => 'Country',
    );

    $applied = null;
    if (!empty($_GET['repaired'])) {
        $applied = get_transient('mcn_address_repair_report_' . get_current_user_id());
        delete_transient('mcn_address_repair_report_' . get_current_user_id());
    }

    $preview = mcn_address_repair_run(false);
    ?>
    <div class="wrap">
        <h1>Repair Patient Addresses</h1>
        <p>Before 2026-10-30, completing the profile form saved the answer to "Do you have a pet?" as the patient's
            address line 1 and a health answer as their country. This tool restores each affected patient's address
            and state from their own latest profile form entry. Patient details are not shown on this page.</p>

        <?php if (is_array($applied)) : ?>
            <div class="notice notice-success"><p>Repaired <?php echo (int) $applied['repairable']; ?> patient(s) in this batch.</p></div>
        <?php endif; ?>

        <h2>Dry run (nothing changed yet)</h2>
        <?php if ($preview['found'] === 0) : ?>
            <p><strong>No affected patients found.</strong> Nothing to repair.</p>
        <?php else : ?>
            <ul style="list-style:disc;margin-left:20px">
                <li><?php echo (int) $preview['found']; ?> affected patient(s) found in this batch (up to 200 at a time).</li>
                <li><?php echo (int) $preview['repairable']; ?> can be restored from their profile form entry.</li>
                <?php foreach ($preview['fields'] as $field => $count) : ?>
                    <li><?php echo esc_html(isset($labels[$field]) ? $labels[$field] : $field); ?> would change for <?php echo (int) $count; ?> patient(s).</li>
                <?php endforeach; ?>
                <?php if ($preview['no_entry']) : ?>
                    <li><?php echo count($preview['no_entry']); ?> have no profile form entry to restore from; only the Yes/No answers are removed (please check these by hand):
                        <?php foreach ($preview['no_entry'] as $uid) : ?>
                            <a href="<?php echo esc_url(get_edit_user_link($uid)); ?>">#<?php echo (int) $uid; ?></a>
                        <?php endforeach; ?>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if ($preview['repairable'] > 0) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      onsubmit="return confirm('Restore these patients’ addresses from their profile form entries?');">
                    <input type="hidden" name="action" value="mcn_repair_addresses">
                    <?php wp_nonce_field('mcn_repair_addresses'); ?>
                    <button type="submit" class="button button-primary">Repair this batch</button>
                </form>
                <p class="description">If more than 200 patients are affected, run it again until none are left.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
