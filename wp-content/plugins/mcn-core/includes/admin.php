<?php
/**
 * MCN Core admin: Tools > MCN Modules, plus alerts for problems.
 * Only administrators (manage_options) can see or change anything here.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_management_page('MCN Modules', 'MCN Modules', 'manage_options', 'mcn-modules', 'mcn_core_render_admin_page');
});

/** Modules that need $slug (directly or through another module). */
function mcn_core_dependents($slug, $manifest)
{
    $out = array();
    $changed = true;
    $targets = array($slug);
    while ($changed) {
        $changed = false;
        foreach ($manifest as $other => $module) {
            if (in_array($other, $out, true) || $other === $slug) {
                continue;
            }
            if (array_intersect($module['requires'], $targets)) {
                $out[] = $other;
                $targets[] = $other;
                $changed = true;
            }
        }
    }
    return $out;
}

function mcn_core_admin_url($args = array())
{
    return add_query_arg($args, admin_url('tools.php?page=mcn-modules'));
}

add_action('admin_post_mcn_core_toggle_module', function () {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 403);
    }
    check_admin_referer('mcn_core_toggle_module');

    $slug     = isset($_POST['module']) ? sanitize_key(wp_unslash($_POST['module'])) : '';
    $enable   = isset($_POST['enable']) && $_POST['enable'] === '1';
    $manifest = mcn_core_manifest();

    if (!isset($manifest[$slug])) {
        wp_safe_redirect(mcn_core_admin_url(array('mcn_notice' => 'unknown')));
        exit;
    }

    $disabled = mcn_core_disabled_modules();
    $disabled = $enable
        ? array_values(array_diff($disabled, array($slug)))
        : array_values(array_unique(array_merge($disabled, array($slug))));
    update_option(MCN_CORE_OPT_DISABLED, $disabled, true);

    wp_safe_redirect(mcn_core_admin_url(array('mcn_notice' => $enable ? 'enabled' : 'disabled', 'module' => $slug)));
    exit;
});

add_action('admin_post_mcn_core_clear_errors', function () {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 403);
    }
    check_admin_referer('mcn_core_clear_errors');
    delete_option(MCN_CORE_OPT_ERRORS);
    wp_safe_redirect(mcn_core_admin_url(array('mcn_notice' => 'cleared')));
    exit;
});

/** Site-wide admin alerts (administrators only). */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'tools_page_mcn-modules') {
        return; // the page itself shows everything
    }

    $link = '<a href="' . esc_url(mcn_core_admin_url()) . '">Tools &gt; MCN Modules</a>';
    $mode = mcn_core_mode();

    if ($mode === 'legacy') {
        echo '<div class="notice notice-warning"><p><strong>MCN Core is installed but waiting.</strong> '
            . 'Your theme\'s functions.php still contains the old site code, so the modules are not loaded (this prevents a crash). '
            . 'Replace the theme\'s functions.php with the slim version and MCN Core starts automatically.</p></div>';
        return;
    }
    if ($mode === 'safe') {
        echo '<div class="notice notice-error"><p><strong>MCN Core safe mode is on:</strong> all site features are off. '
            . 'Remove <code>MCN_CORE_SAFE_MODE</code> from wp-config.php to turn them back on.</p></div>';
        return;
    }

    $problems = array();
    foreach (mcn_core_status() as $slug => $row) {
        if (in_array($row['state'], array('error', 'blocked', 'conflict'), true)) {
            $problems[] = $slug;
        }
    }
    if ($problems) {
        echo '<div class="notice notice-error"><p><strong>MCN Core:</strong> these modules are not running: <code>'
            . esc_html(implode('</code>, <code>', $problems)) . '</code>. The rest of the site is unaffected. Details: ' . $link . '</p></div>';
    }

    $recent = 0;
    foreach ((array) get_option(MCN_CORE_OPT_ERRORS, array()) as $err) {
        if (!empty($err['last']) && $err['last'] > time() - DAY_IN_SECONDS) {
            $recent++;
        }
    }
    if ($recent) {
        echo '<div class="notice notice-warning"><p><strong>MCN Core</strong> caught ' . (int) $recent
            . ' error' . ($recent === 1 ? '' : 's') . ' in the last 24 hours and kept the site running. Details: ' . $link . '</p></div>';
    }
});

function mcn_core_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $manifest = mcn_core_manifest();
    $status   = mcn_core_status();
    $mode     = mcn_core_mode();
    $errors   = get_option(MCN_CORE_OPT_ERRORS, array());
    $errors   = is_array($errors) ? $errors : array();
    $notice   = isset($_GET['mcn_notice']) ? sanitize_key($_GET['mcn_notice']) : '';
    $labels   = array(
        'loaded'   => array('Running', '#0a7d32'),
        'disabled' => array('Switched off', '#646970'),
        'blocked'  => array('Not running: needs another module', '#b32d2e'),
        'conflict' => array('Not running: name clash', '#b32d2e'),
        'error'    => array('Not running: error', '#b32d2e'),
    );
    ?>
    <div class="wrap">
        <h1>MCN Modules</h1>
        <p>Each site feature runs as its own module. If one breaks, only that module stops and the rest of the site keeps working.</p>

        <?php if ($notice === 'enabled' || $notice === 'disabled') : ?>
            <div class="notice notice-success is-dismissible"><p>Module <code><?php echo esc_html(isset($_GET['module']) ? sanitize_key($_GET['module']) : ''); ?></code> switched <?php echo $notice === 'enabled' ? 'on' : 'off'; ?>.</p></div>
        <?php elseif ($notice === 'cleared') : ?>
            <div class="notice notice-success is-dismissible"><p>Error history cleared.</p></div>
        <?php endif; ?>

        <?php if ($mode === 'legacy') : ?>
            <div class="notice notice-warning inline"><p><strong>Waiting:</strong> the theme's functions.php still contains the old code
                (found <code><?php echo esc_html(implode(', ', isset($GLOBALS['mcn_core_legacy_hits']) ? $GLOBALS['mcn_core_legacy_hits'] : array())); ?></code>).
                No module is loaded until it is replaced with the slim version.</p></div>
        <?php elseif ($mode === 'safe') : ?>
            <div class="notice notice-error inline"><p><strong>Safe mode is on</strong> (<code>MCN_CORE_SAFE_MODE</code> in wp-config.php). No module is loaded.</p></div>
        <?php endif; ?>

        <table class="widefat striped" style="max-width:1200px;margin-top:16px">
            <thead><tr><th style="width:24%">Module</th><th>What it does</th><th style="width:20%">Status</th><th style="width:12%"></th></tr></thead>
            <tbody>
            <?php foreach ($manifest as $slug => $module) :
                $row        = isset($status[$slug]) ? $status[$slug] : array('state' => $mode === 'normal' ? 'error' : $mode);
                $state      = $row['state'];
                $is_off     = in_array($slug, mcn_core_disabled_modules(), true);
                $dependents = mcn_core_dependents($slug, $manifest);
                $label      = isset($labels[$state]) ? $labels[$state] : array(ucfirst($state), '#646970');
                ?>
                <tr>
                    <td><strong><?php echo esc_html($module['title']); ?></strong><br><code><?php echo esc_html($slug); ?></code>
                        <?php if ($module['requires']) : ?><br><small>Needs: <?php echo esc_html(implode(', ', $module['requires'])); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo esc_html($module['description']); ?>
                        <?php if (!empty($module['optional'])) : ?><br><small>Works best with: <?php echo esc_html(implode(', ', $module['optional'])); ?></small><?php endif; ?>
                    </td>
                    <td><span style="color:<?php echo esc_attr($label[1]); ?>;font-weight:600"><?php echo esc_html($label[0]); ?></span>
                        <?php if (!empty($row['detail'])) : ?><br><small><?php echo esc_html(is_array($row['detail']) ? implode(', ', $row['detail']) : $row['detail']); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              <?php if (!$is_off && $dependents) : ?>onsubmit="return confirm(<?php echo esc_attr(wp_json_encode('Switching this off also stops: ' . implode(', ', $dependents) . '. Continue?')); ?>);"<?php endif; ?>>
                            <input type="hidden" name="action" value="mcn_core_toggle_module">
                            <input type="hidden" name="module" value="<?php echo esc_attr($slug); ?>">
                            <input type="hidden" name="enable" value="<?php echo $is_off ? '1' : '0'; ?>">
                            <?php wp_nonce_field('mcn_core_toggle_module'); ?>
                            <button type="submit" class="button<?php echo $is_off ? ' button-primary' : ''; ?>"><?php echo $is_off ? 'Switch on' : 'Switch off'; ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (has_action('mcn_core_admin_diagnostics')) : ?>
            <h2 style="margin-top:28px">Checks</h2>
            <?php do_action('mcn_core_admin_diagnostics'); ?>
        <?php endif; ?>

        <h2 style="margin-top:28px">Errors caught</h2>
        <?php if (!$errors) : ?>
            <p>None. Everything has run cleanly.</p>
        <?php else : ?>
            <p>These errors were contained: the module call was skipped and the page kept working. Fix the cause, then clear the list.</p>
            <table class="widefat striped" style="max-width:1200px">
                <thead><tr><th>Last seen</th><th>Module</th><th>Where</th><th>Error</th><th>Times</th></tr></thead>
                <tbody>
                <?php foreach ($errors as $err) : ?>
                    <tr>
                        <td><?php echo esc_html(wp_date('Y-m-d H:i', (int) $err['last'])); ?></td>
                        <td><code><?php echo esc_html($err['module']); ?></code></td>
                        <td><?php echo esc_html($err['context']); ?></td>
                        <td><?php echo esc_html($err['type'] . ': ' . $err['message']); ?><br><small><?php echo esc_html($err['file'] . ' line ' . $err['line']); ?></small></td>
                        <td><?php echo (int) $err['count']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
                <input type="hidden" name="action" value="mcn_core_clear_errors">
                <?php wp_nonce_field('mcn_core_clear_errors'); ?>
                <button type="submit" class="button">Clear error history</button>
            </form>
        <?php endif; ?>

        <h2 style="margin-top:28px">Emergency switch</h2>
        <p>If the site ever misbehaves and you cannot reach this screen, add this line to <code>wp-config.php</code> to stop every module at once without deleting anything:<br>
            <code>define('MCN_CORE_SAFE_MODE', true);</code></p>
    </div>
    <?php
}
