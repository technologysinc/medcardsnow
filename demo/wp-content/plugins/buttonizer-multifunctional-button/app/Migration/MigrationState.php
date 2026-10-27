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
 * Per-module migration bookkeeping.
 *
 * One option holds the state of every module:
 *
 *   buttonizer_migration_state = [
 *       'chat-button' => [
 *           'requested'        => true,   // written by the source plugin's own notice
 *           'status'           => 'migrated',
 *           'detected_state'   => 'cloud_connected',
 *           'result'           => 'connection_adopted',
 *           'migrated_at'      => '2026-08-14T10:00:00+00:00',
 *           'notice_dismissed' => false,
 *       ],
 *   ]
 *
 * Backups are stored separately, one option per module, so they can be
 * restored or deleted independently of this bookkeeping.
 */
class MigrationState
{
    const OPTION = 'buttonizer_migration_state';

    const STATUS_PENDING  = 'pending';
    const STATUS_MIGRATED = 'migrated';
    const STATUS_SKIPPED  = 'skipped';
    const STATUS_FAILED   = 'failed';

    /**
     * Full state map.
     */
    public static function all(): array
    {
        $state = get_option(self::OPTION, []);

        return is_array($state) ? $state : [];
    }

    /**
     * State of a single module.
     */
    public static function get(string $moduleId): array
    {
        $state = self::all();

        return isset($state[$moduleId]) && is_array($state[$moduleId]) ? $state[$moduleId] : [];
    }

    /**
     * Merge values into a module's state.
     *
     * @param string $moduleId Module identifier.
     * @param array  $values   Values to merge.
     */
    public static function set(string $moduleId, array $values): void
    {
        $state = self::all();

        $state[$moduleId] = array_merge(self::get($moduleId), $values);

        update_option(self::OPTION, $state);
    }

    /**
     * Has this module already been migrated?
     */
    public static function isMigrated(string $moduleId): bool
    {
        $module = self::get($moduleId);

        return isset($module['status']) && $module['status'] === self::STATUS_MIGRATED;
    }

    /**
     * Mark a module as migrated.
     *
     * @param string $moduleId Module identifier.
     * @param string $result   How the migration resolved (for support/debugging).
     */
    public static function markMigrated(string $moduleId, string $result): void
    {
        self::set($moduleId, [
            'status'           => self::STATUS_MIGRATED,
            'result'           => $result,
            'migrated_at'      => (new \DateTime('now'))->format(\DateTime::ATOM),
            'notice_dismissed' => false,
        ]);
    }

    /**
     * Reset a module back to its pre-migration bookkeeping.
     *
     * Used by the rollback path; the actual data is restored by Backup.
     */
    public static function reset(string $moduleId): void
    {
        $state = self::all();

        unset($state[$moduleId]);

        update_option(self::OPTION, $state);
    }
}
