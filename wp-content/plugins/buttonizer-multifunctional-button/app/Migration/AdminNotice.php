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
 * The one thing the user gets to see about the migration.
 *
 * Light on purpose: their plugin kept working, their settings are where they
 * were, and the only real change is which menu item they click.
 */
class AdminNotice
{
    const DISMISS_ACTION = 'buttonizer_dismiss_migration_notice';

    /**
     * Where the notice sends someone who hit a problem.
     */
    const SUPPORT_URL = 'https://r.buttonizer.io/support/community?utm_source=wp-plugin-migration-notice';

    /**
     * Handle the dismiss link.
     */
    public static function handleDismiss(): void
    {
        if (!isset($_GET[self::DISMISS_ACTION])) {
            return;
        }

        $moduleId = sanitize_text_field(wp_unslash($_GET[self::DISMISS_ACTION]));

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], self::DISMISS_ACTION . $moduleId)) {
            return;
        }

        if (!SourcePlugins::get($moduleId)) {
            return;
        }

        MigrationState::set($moduleId, ['notice_dismissed' => true]);
    }

    /**
     * Render the notice for every module migrated on this site.
     */
    public static function render(): void
    {
        if (!Handoff::currentUserCanManagePlugins()) {
            return;
        }

        foreach (SourcePlugins::all() as $source) {
            $state = MigrationState::get($source->id());

            if (empty($state['status']) || $state['status'] !== MigrationState::STATUS_MIGRATED) {
                continue;
            }

            if (!empty($state['notice_dismissed'])) {
                continue;
            }

            // The user reactivated the source plugin, which now explains itself
            // from its own admin notice. Two notices saying the same thing in
            // the same screen is worse than one.
            if ($source->isActive()) {
                continue;
            }

            // The signup screen already told the user this was coming, right
            // before they clicked the button that did it — no need to say it
            // again once they land in Buttonizer.
            if (!empty($state['handover'])) {
                continue;
            }

            self::renderFor($source);

            // Said once, on the first screen after the move, and never again.
            // The Dismiss link is still there to close it on the spot rather
            // than having to look at it for the rest of the page.
            MigrationState::set($source->id(), ['notice_dismissed' => true]);
        }
    }

    /**
     * Render the notice for a single module.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     */
    private static function renderFor(SourcePlugin $source): void
    {
        $dismissUrl = wp_nonce_url(
            add_query_arg(self::DISMISS_ACTION, $source->id()),
            self::DISMISS_ACTION . $source->id()
        );

        // One wording for every way a site can get here. It holds for the user
        // whose old system is now served from inside Buttonizer, for the one
        // whose cloud account came across, and for the one who had nothing to
        // bring: in all three the features moved, and nothing looks different.
        $message = sprintf(
            /* translators: %s: link to the support community, already built. */
            esc_html__('Your plugin features and related settings have been successfully migrated to our main Buttonizer plugin! Everything should look and function the same as before. If you notice any issues or have any questions, please feel free to ask our %s for assistance.', 'buttonizer-multifunctional-button'),
            '<a href="' . esc_url(self::SUPPORT_URL) . '" target="_blank" rel="noopener">' . esc_html__('support team', 'buttonizer-multifunctional-button') . '</a>'
        );

        // The move worked. Saying so in the colour WordPress uses for that
        // answers the question before the user has to ask it.
        echo '<div class="notice notice-success">';
        echo '<p>' . $message . '</p>';
        echo '<p>';
        echo '<a href="' . esc_url($dismissUrl) . '">' . esc_html__('Dismiss', 'buttonizer-multifunctional-button') . '</a>';
        echo '</p>';
        echo '</div>';
    }
}
