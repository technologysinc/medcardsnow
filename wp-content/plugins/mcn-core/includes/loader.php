<?php
/**
 * MCN Core module loader.
 *
 * Protections:
 *  - Legacy guard: if the old monolithic functions.php is still active, no module
 *    loads (prevents "Cannot redeclare function" crashes in any deploy order).
 *  - Per-module checks before loading: switched off by an admin, a required module
 *    not running, or a function name already taken by other code.
 *  - Load errors (e.g. a syntax error after an edit) skip only that module, and
 *    anything it half-registered is removed again.
 *  - Runtime guard: every hook and shortcode a module registers is wrapped, so an
 *    error inside it is logged and contained instead of crashing the page. Filters
 *    keep their original value, and partial output from the failed callback is
 *    discarded.
 *  - Emergency switch: define('MCN_CORE_SAFE_MODE', true); in wp-config.php stops
 *    all modules without touching any files.
 */

if (!defined('ABSPATH')) {
    exit;
}

const MCN_CORE_OPT_DISABLED = 'mcn_core_disabled_modules';
const MCN_CORE_OPT_ERRORS   = 'mcn_core_errors';

/** Functions only the old monolithic functions.php (or this plugin) defines. */
function mcn_core_legacy_sentinels()
{
    return array(
        'cap_compute_server_total',
        'medcards_get_state_pricing',
        'handle_secure_login_link',
        'medcards_california_id_card_cards_shortcode',
    );
}

function mcn_core_disabled_modules()
{
    $disabled = get_option(MCN_CORE_OPT_DISABLED, array());
    return is_array($disabled) ? array_values(array_filter($disabled, 'is_string')) : array();
}

/** Current mode: 'normal', 'legacy', 'safe' or 'pending' (before loading ran). */
function mcn_core_mode()
{
    return isset($GLOBALS['mcn_core_mode']) ? $GLOBALS['mcn_core_mode'] : 'pending';
}

/** Per-module result of this request: state is loaded|disabled|blocked|conflict|error. */
function mcn_core_status()
{
    return isset($GLOBALS['mcn_core_status']) && is_array($GLOBALS['mcn_core_status'])
        ? $GLOBALS['mcn_core_status']
        : array();
}

function mcn_core_load_modules()
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    $GLOBALS['mcn_core_status'] = array();

    if (defined('MCN_CORE_SAFE_MODE') && MCN_CORE_SAFE_MODE) {
        $GLOBALS['mcn_core_mode'] = 'safe';
        return;
    }

    $legacy_hits = array_values(array_filter(mcn_core_legacy_sentinels(), 'function_exists'));
    if ($legacy_hits) {
        $GLOBALS['mcn_core_mode']        = 'legacy';
        $GLOBALS['mcn_core_legacy_hits'] = $legacy_hits;
        return;
    }

    $GLOBALS['mcn_core_mode'] = 'normal';

    $manifest = mcn_core_manifest();
    $disabled = mcn_core_disabled_modules();
    $status   = array();

    foreach ($manifest as $slug => $module) {

        if (in_array($slug, $disabled, true)) {
            $status[$slug] = array('state' => 'disabled');
            continue;
        }

        $missing = array();
        foreach ($module['requires'] as $required) {
            if (!isset($status[$required]) || $status[$required]['state'] !== 'loaded') {
                $missing[] = $required;
            }
        }
        if ($missing) {
            $status[$slug] = array('state' => 'blocked', 'detail' => $missing);
            continue;
        }

        $taken = array_values(array_filter($module['functions'], 'function_exists'));
        if ($taken) {
            $status[$slug] = array('state' => 'conflict', 'detail' => $taken);
            continue;
        }

        $file = MCN_CORE_DIR . 'modules/' . $module['file'];
        if (!is_readable($file)) {
            $status[$slug] = array('state' => 'error', 'detail' => 'File missing: modules/' . $module['file']);
            continue;
        }

        try {
            mcn_core_include_module($file);
            $status[$slug] = array('state' => 'loaded');
        } catch (\Throwable $e) {
            mcn_core_record_error($slug, 'loading the module', $e);
            $status[$slug] = array(
                'state'  => 'error',
                'detail' => get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ' line ' . $e->getLine() . ')',
            );
        }
    }

    $GLOBALS['mcn_core_status'] = $status;

    // Wrap what the running modules registered; remove what failed modules left behind.
    mcn_core_secure_registrations($manifest, $status);
}

/** Isolated scope for a module file. */
function mcn_core_include_module($file)
{
    include $file;
}

/** Which module a callback belongs to, or '' if it is not ours. */
function mcn_core_callback_owner($callback, $function_owners)
{
    if (is_string($callback)) {
        $key = strtolower($callback);
        return isset($function_owners[$key]) ? $function_owners[$key] : '';
    }

    if ($callback instanceof \Closure) {
        try {
            $file = (new \ReflectionFunction($callback))->getFileName();
        } catch (\ReflectionException $e) {
            return '';
        }
        $dir = wp_normalize_path(MCN_CORE_DIR . 'modules/');
        $file = wp_normalize_path((string) $file);
        if (strpos($file, $dir) === 0) {
            return basename($file, '.php');
        }
    }

    return '';
}

function mcn_core_secure_registrations($manifest, $status)
{
    global $wp_filter, $shortcode_tags;

    if (defined('MCN_CORE_NO_GUARD') && MCN_CORE_NO_GUARD) {
        return;
    }

    // Map every function name a module defines (including guarded helpers) to its module.
    $owners = array();
    foreach ($manifest as $slug => $module) {
        foreach (array_merge($module['functions'], isset($module['guarded']) ? $module['guarded'] : array()) as $fn) {
            $owners[strtolower($fn)] = $slug;
        }
    }

    foreach ((array) $wp_filter as $hook => $hook_obj) {
        if (!($hook_obj instanceof \WP_Hook)) {
            continue;
        }
        foreach ($hook_obj->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $id => $entry) {
                $owner = mcn_core_callback_owner($entry['function'], $owners);
                if ($owner === '' || !isset($status[$owner])) {
                    continue;
                }
                if ($status[$owner]['state'] !== 'loaded') {
                    // Roll back what a failed module registered before it broke.
                    $hook_obj->remove_filter($hook, $entry['function'], $priority);
                    continue;
                }
                $hook_obj->callbacks[$priority][$id]['function'] =
                    mcn_core_guard($entry['function'], $owner, $hook, false);
            }
        }
    }

    foreach ((array) $shortcode_tags as $tag => $callback) {
        $owner = mcn_core_callback_owner($callback, $owners);
        if ($owner === '' || !isset($status[$owner])) {
            continue;
        }
        if ($status[$owner]['state'] !== 'loaded') {
            remove_shortcode($tag);
            continue;
        }
        $shortcode_tags[$tag] = mcn_core_guard($callback, $owner, 'shortcode [' . $tag . ']', true);
    }
}

/**
 * Wrap a module callback so an error inside it cannot take the page down.
 * The original callback stays reachable through mcn_core_unwrap().
 */
function mcn_core_guard($callback, $module, $hook, $is_shortcode)
{
    $guarded = function (...$args) use ($callback, $module, $hook, $is_shortcode) {
        $level = ob_get_level();
        ob_start();
        try {
            $result = call_user_func_array($callback, $args);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            mcn_core_record_error($module, $hook, $e);
            // A browser waiting on an AJAX request gets a clear JSON answer
            // instead of WordPress's bare "0" (which looked like a card decline).
            if (strpos((string) $hook, 'wp_ajax_') === 0 && function_exists('wp_send_json_error')) {
                wp_send_json_error(array(
                    'code'    => 'server_error',
                    'title'   => 'Technical Problem',
                    'message' => 'This could not be completed because of a technical problem on our side. Please try again in a few minutes or contact us.',
                ), 500);
            }
            if ($is_shortcode) {
                return '';
            }
            return isset($args[0]) ? $args[0] : null;
        }
        if (ob_get_level() === $level + 1) {
            ob_end_flush();
        }
        return $result;
    };

    mcn_core_guard_registry()->attach($guarded, $callback);

    return $guarded;
}

/** @return \SplObjectStorage */
function mcn_core_guard_registry()
{
    static $registry = null;
    if ($registry === null) {
        $registry = new \SplObjectStorage();
    }
    return $registry;
}

/** The original callback behind a guarded one (or the callback itself). */
function mcn_core_unwrap($callback)
{
    if ($callback instanceof \Closure && mcn_core_guard_registry()->contains($callback)) {
        return mcn_core_guard_registry()[$callback];
    }
    return $callback;
}

/**
 * Log an error and keep a short, de-duplicated history for the admin screen.
 * Only the error type, message and location are stored; never request data.
 */
function mcn_core_record_error($module, $context, $e)
{
    $message = (string) $e->getMessage();
    $message = function_exists('mb_substr') ? mb_substr($message, 0, 300) : substr($message, 0, 300);

    error_log(sprintf(
        '[MCN Core] module "%s" failed in %s: %s: %s (%s:%d)',
        $module,
        $context,
        get_class($e),
        $message,
        basename($e->getFile()),
        $e->getLine()
    ));

    $signature = md5($module . '|' . $context . '|' . get_class($e) . '|' . $e->getFile() . '|' . $e->getLine());

    // Store each distinct error at most once per 5 minutes (protects the database
    // if an error repeats on every page view).
    if (get_transient('mcn_core_err_' . $signature)) {
        return;
    }
    set_transient('mcn_core_err_' . $signature, 1, 5 * MINUTE_IN_SECONDS);

    $log = get_option(MCN_CORE_OPT_ERRORS, array());
    if (!is_array($log)) {
        $log = array();
    }
    $previous = isset($log[$signature]['count']) ? (int) $log[$signature]['count'] : 0;
    $log[$signature] = array(
        'module'  => (string) $module,
        'context' => (string) $context,
        'type'    => get_class($e),
        'message' => $message,
        'file'    => basename($e->getFile()),
        'line'    => (int) $e->getLine(),
        'count'   => $previous + 1,
        'last'    => time(),
    );
    uasort($log, function ($a, $b) {
        return $b['last'] - $a['last'];
    });
    update_option(MCN_CORE_OPT_ERRORS, array_slice($log, 0, 25, true), false);
}
