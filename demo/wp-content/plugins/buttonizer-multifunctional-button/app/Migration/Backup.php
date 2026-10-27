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

use Buttonizer\Core\PluginConfig;

# No script kiddies
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Snapshot of a source plugin's data, taken before anything is touched.
 *
 * One option per module (buttonizer_migration_backup_<module>) so a second
 * acquisition never overwrites the first, and so a single module can be
 * restored or dropped on its own.
 *
 * Follows the same idea as the legacy *_backup_30 snapshots: nothing in the
 * migration is destructive while this exists.
 */
class Backup
{
    const OPTION_PREFIX = 'buttonizer_migration_backup_';

    /** Marker for options that did not exist when the snapshot was taken. */
    const MISSING = '__buttonizer_option_missing__';

    /**
     * Option name holding the backup for a module.
     */
    public static function optionName(SourcePlugin $source): string
    {
        return self::OPTION_PREFIX . str_replace('-', '_', $source->id());
    }

    /**
     * Does a backup already exist?
     */
    public static function exists(SourcePlugin $source): bool
    {
        return get_option(self::optionName($source), null) !== null;
    }

    /**
     * Read the stored backup.
     *
     * @return array Empty array when there is none.
     */
    public static function get(SourcePlugin $source): array
    {
        $backup = get_option(self::optionName($source), []);

        return is_array($backup) ? $backup : [];
    }

    /**
     * Snapshot every option belonging to the source plugin.
     *
     * The first snapshot wins: re-running the migration must never overwrite
     * the pre-migration state with post-migration values.
     *
     * @param SourcePlugin $source Source plugin descriptor.
     * @param bool         $force  Overwrite an existing backup.
     *
     * @return bool Whether a snapshot was written.
     */
    public static function create(SourcePlugin $source, bool $force = false): bool
    {
        if (!$force && self::exists($source)) {
            return false;
        }

        return update_option(self::optionName($source), [
            'created_at'     => (new \DateTime('now'))->format(\DateTime::ATOM),
            'plugin_version' => BUTTONIZER_VERSION,
            'base_name'      => $source->resolveBaseName(),
            'was_active'     => $source->isActive(),
            'options'        => self::snapshot($source->optionsToBackup()),

            // Adopting a connection overwrites Buttonizer's own settings, so
            // without this half of the snapshot there is no way back to the
            // state both plugins were in before the migration.
            'target_options' => self::snapshot(self::targetOptions()),
        ]);
    }

    /**
     * Restore a snapshot.
     *
     * Puts every option back exactly as it was, including deleting options
     * that did not exist before the migration.
     *
     * @return bool Whether a backup was found and restored.
     */
    public static function restore(SourcePlugin $source): bool
    {
        $backup = self::get($source);

        if (empty($backup['options']) || !is_array($backup['options'])) {
            return false;
        }

        self::restoreOptions($backup['options']);

        if (!empty($backup['target_options']) && is_array($backup['target_options'])) {
            self::restoreOptions($backup['target_options']);

            // The token is cached in a transient too, and a stale one would
            // resurrect a connection the snapshot does not contain.
            delete_transient(PluginConfig::name() . '_site_connection');
        }

        return true;
    }

    /**
     * Read a set of options, marking the ones that do not exist.
     *
     * @param string[] $optionNames Option names.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(array $optionNames): array
    {
        $snapshot = [];

        foreach ($optionNames as $option) {
            $snapshot[$option] = get_option($option, self::MISSING);
        }

        return $snapshot;
    }

    /**
     * Write a set of options back, deleting the ones that did not exist.
     *
     * @param array<string, mixed> $options Snapshotted options.
     */
    private static function restoreOptions(array $options): void
    {
        foreach ($options as $option => $value) {
            if ($value === self::MISSING) {
                delete_option($option);
                continue;
            }

            update_option($option, $value);
        }
    }

    /**
     * Buttonizer's own connection options.
     *
     * @return string[]
     */
    private static function targetOptions(): array
    {
        $name = PluginConfig::name();

        return [
            $name . '_settings',
            $name . '_site_connection',
            $name . '_account',
        ];
    }

    /**
     * Drop a snapshot.
     */
    public static function delete(SourcePlugin $source): bool
    {
        return delete_option(self::optionName($source));
    }
}
