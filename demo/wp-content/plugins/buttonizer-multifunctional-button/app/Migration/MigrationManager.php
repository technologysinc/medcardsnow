<?php
/*
 * SOFTWARE LICENSE INFORMATION
 *
 * Copyright (c) 2017 Buttonizer, all rights reserved.
 *
 * This file is part of Buttonizer
 *
 * For detailed information regarding to the licensing of
 * this software, please review the license.txt or visit:
 * https://buttonizer.pro/license/
 */

namespace Buttonizer\Migration;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Runs the migration of acquired plugins into Buttonizer.
 *
 * Order matters and is the whole safety story:
 *
 *   detect → asked? → back up → adopt the connection → verify → deactivate → record
 *
 * Nothing destructive happens before the backup exists, and the source plugin
 * is never deactivated until Buttonizer is verifiably able to serve its users.
 * And none of it starts on Buttonizer's own initiative: an installed source is
 * only absorbed after its own notice wrote down that the user asked for it.
 */
class MigrationManager
{
    /** The source plugin's own system is now served from modules/. */
    const RESULT_LEGACY_MODULE = 'legacy_module_embedded';

    /** Nothing was done: this build carries no module for that system. */
    const RESULT_MODULE_MISSING = 'module_missing';

    /**
     * Register the migration hooks.
     *
     * Runs on admin_init rather than on activation: it covers the plugin being
     * activated (the redirect right after activation is an admin request), the
     * source plugin showing up later, and a failed run being retried — without
     * deactivating plugins from inside another plugin's activation.
     */
    public static function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        // Deactivating a plugin in the middle of someone else's AJAX or cron
        // request is a good way to break unrelated features. Wait for a real
        // admin page load.
        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        add_action('admin_init', [AdminNotice::class, 'handleDismiss'], 1);
        add_action('admin_init', [self::class, 'run'], 5);
        add_action('admin_init', [self::class, 'redirectSourcePages'], 6);
        add_action('admin_notices', [AdminNotice::class, 'render']);
    }

    /**
     * Evaluate every registered source plugin and migrate what can be migrated.
     */
    public static function run(): void
    {
        foreach (SourcePlugins::all() as $source) {
            $state = Detector::detect($source);

            if ($state === Detector::STATE_ALREADY_MIGRATED) {
                continue;
            }

            $known = MigrationState::get($source->id());

            // Only write when something actually changed — run() is on every
            // admin request, and an option write per page load is not free.
            if (($known['detected_state'] ?? null) !== $state && !($state === Detector::STATE_NOT_PRESENT && empty($known))) {
                MigrationState::set($source->id(), [
                    'detected_state' => $state,
                    'checked_at'     => (new \DateTime('now'))->format(\DateTime::ATOM),
                ]);
            }

            if (!in_array($state, [
                Detector::STATE_CLOUD_CONNECTED,
                Detector::STATE_CLOUD_DISCONNECTED,
                Detector::STATE_LEGACY,
            ], true)) {
                continue;
            }

            // Nobody asked. The source plugin writes this before activating
            // Buttonizer; a build that predates the migration never does, and
            // neither does a user who installed Buttonizer next to it by hand.
            // Those sites keep both plugins, exactly as they had them.
            //
            // The one thing still worth doing unasked is picking data back up
            // that Buttonizer had absorbed before and whose plugin is now gone:
            // the backup is the proof it was ours to serve. Leftovers of a
            // plugin that was simply deleted are not.
            if (empty($known['requested']) && ($source->isInstalled() || !Backup::exists($source))) {
                continue;
            }

            // A user who cannot manage plugins cannot complete the handover.
            // Adopting the connection without being able to deactivate the
            // source would leave both plugins running against the same site.
            if (!Handoff::currentUserCanManagePlugins()) {
                continue;
            }

            self::migrate($source, $state);
        }
    }

    /**
     * Migrate a single source plugin.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     * @param string       $state  Detected state, as returned by Detector.
     */
    private static function migrate(SourcePlugin $source, string $state): void
    {
        // Resolve the plugin once, deeply, so the backup and the handoff agree
        // on which files they are talking about
        $source->resolveBaseName(true);

        // A legacy install is served by the plugin's own code, so Buttonizer
        // may only step in once it carries that code itself. Without the
        // module, deactivating would take the user's buttons off the frontend.
        if ($state === Detector::STATE_LEGACY && !$source->hasModule()) {
            MigrationState::set($source->id(), [
                'status' => MigrationState::STATUS_SKIPPED,
                'result' => self::RESULT_MODULE_MISSING,
            ]);

            return;
        }

        // 1. Everything from here on is reversible
        Backup::create($source);

        // 2. Take over the connection the user already has. A source that was
        // never connected has no token, no site and nothing rendering on the
        // frontend, so there is nothing to adopt and nothing to lose — it still
        // gets handed over, otherwise the user is left with two active plugins
        // after being invited to move.
        $result = $state === Detector::STATE_LEGACY
            ? self::RESULT_LEGACY_MODULE
            : ConnectionAdopter::RESULT_NOTHING_TO_ADOPT;

        if ($state === Detector::STATE_LEGACY) {
            // Remembered so the module knows what a later connection means: a
            // site that was already on the cloud keeps both, a site that was
            // not has just decided to move.
            MigrationState::set($source->id(), [
                'target_connected' => ConnectionAdopter::isTargetConnected(),
            ]);
        }

        if ($state === Detector::STATE_CLOUD_CONNECTED) {
            $adoption = ConnectionAdopter::adopt($source);

            if (!$adoption['adopted']) {
                MigrationState::set($source->id(), [
                    'status' => MigrationState::STATUS_FAILED,
                    'result' => $adoption['result'],
                ]);

                return;
            }

            // 3. Never hand over on an unverified connection
            if (!ConnectionAdopter::isTargetConnected()) {
                MigrationState::set($source->id(), [
                    'status' => MigrationState::STATUS_FAILED,
                    'result' => ConnectionAdopter::RESULT_FAILED,
                ]);

                return;
            }

            $result = $adoption['result'];
        }

        // 4. Step aside
        $handoff = Handoff::deactivateSource($source);

        if (!$handoff['deactivated'] && $handoff['result'] === Handoff::RESULT_NO_PERMISSION) {
            MigrationState::set($source->id(), [
                'status' => MigrationState::STATUS_PENDING,
                'result' => $handoff['result'],
            ]);

            return;
        }

        // 5. Done
        MigrationState::markMigrated($source->id(), $result);

        // Nothing was actually handed off: the source plugin's files are gone
        // (deleted, not just deactivated), so this is legacy data being picked
        // back up, not a migration happening right now. The green notice
        // announces an event, and none occurred on this request.
        if (!$source->isInstalled()) {
            MigrationState::set($source->id(), ['notice_dismissed' => true]);
        }

        // 6. Start again on a clean request
        self::land($source, $result);
    }

    /**
     * Send the user to where they belong now.
     *
     * Everything above happened halfway through a request whose decisions were
     * already taken: the source plugin registered its menu before it was
     * deactivated and its entry is still on the screen, and Buttonizer decided
     * not to serve the embedded module because the plugin was still running
     * when it loaded. The page being rendered is a state that existed for one
     * request and is wrong in both directions.
     *
     * Rather than patch each symptom, throw the request away. One redirect and
     * everything is decided from the new truth.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     * @param string       $result Migration result.
     */
    private static function land(SourcePlugin $source, string $result): void
    {
        // Endpoints that are answering something else, and redirects that were
        // never meant for a person to see
        if (in_array($GLOBALS['pagenow'] ?? '', ['admin-post.php', 'admin-ajax.php'], true)) {
            return;
        }

        // A site whose old system Buttonizer now serves belongs on its own
        // screens, not on a signup page for a product it has not asked for.
        $page = $result === self::RESULT_LEGACY_MODULE && $source->modulePageSlug()
            ? $source->modulePageSlug()
            : \Buttonizer\Core\PluginConfig::pageSlug();

        wp_safe_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }

    /**
     * Send old admin pages to Buttonizer.
     */
    public static function redirectSourcePages(): void
    {
        foreach (SourcePlugins::all() as $source) {
            if (!MigrationState::isMigrated($source->id())) {
                continue;
            }

            Handoff::redirectSourcePage($source);
        }
    }

    /**
     * Undo a migration: restore the snapshot and forget the bookkeeping.
     *
     * Not wired to any UI — this is the escape hatch for testing and for
     * support, callable from WP-CLI or a snippet.
     *
     * @param string $moduleId Module identifier.
     *
     * @return bool Whether a backup was found and restored.
     */
    public static function rollback(string $moduleId): bool
    {
        $source = SourcePlugins::get($moduleId);

        if (!$source) {
            return false;
        }

        if (!Backup::restore($source)) {
            return false;
        }

        MigrationState::reset($moduleId);

        return true;
    }
}
