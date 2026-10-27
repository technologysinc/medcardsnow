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
 * Describes a plugin whose users are being migrated into Buttonizer.
 *
 * Everything plugin-specific about a migration lives in one of these
 * descriptors, so the migration machinery itself stays generic. New
 * acquisitions are added to SourcePlugins::all() and nothing else changes.
 */
class SourcePlugin
{
    /**
     * @var array Raw descriptor configuration.
     */
    private $config;

    /**
     * @var string|null Resolved plugin basename, cached per request.
     */
    private $resolvedBaseName = null;

    /**
     * @var bool Whether the fallback scan already ran this request.
     */
    private $deepScanned = false;

    /**
     * @param array $config Descriptor configuration.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Module identifier (e.g. "chat-button").
     */
    public function id(): string
    {
        return $this->config['id'];
    }

    /**
     * Human readable product name, used in admin notices.
     */
    public function label(): string
    {
        return $this->config['label'];
    }

    /**
     * What the plugin is called on a site still running its old system.
     *
     * The two systems label their own menu entries, and a user who never left
     * the old one has never seen the other name. Falls back to the current
     * name for a plugin that only ever had one.
     */
    public function legacyLabel(): string
    {
        return $this->config['legacy_label'] ?? $this->config['label'];
    }

    /**
     * Option prefix used by the source plugin (e.g. "bz_contact_button").
     */
    public function optionPrefix(): string
    {
        return $this->config['option_prefix'];
    }

    /**
     * Admin page slug of the source plugin (e.g. "bz_button_contact").
     */
    public function pageSlug(): string
    {
        return $this->config['page_slug'] ?? '';
    }

    /**
     * Option holding the source plugin's API token.
     */
    public function tokenOption(): string
    {
        return $this->optionPrefix() . '_site_connection';
    }

    /**
     * Option holding the source plugin's settings array.
     */
    public function settingsOption(): string
    {
        return $this->optionPrefix() . '_settings';
    }

    /**
     * Option holding the source plugin's cached account data.
     */
    public function accountOption(): string
    {
        return $this->optionPrefix() . '_account';
    }

    /**
     * Option holding the source plugin's own legacy flag, if it has one.
     *
     * @return string|null
     */
    public function legacyFlagOption()
    {
        return $this->config['legacy_flag_option'] ?? null;
    }

    /**
     * Value of the legacy flag option that means "this site runs legacy".
     */
    public function legacyFlagValue(): string
    {
        return $this->config['legacy_flag_value'] ?? 'yes';
    }

    /**
     * Options that only exist on installs still running the source plugin's
     * own pre-Buttonizer (legacy) system. Used for detection and for backups.
     *
     * @return string[]
     */
    public function legacyOptions(): array
    {
        return $this->config['legacy_options'] ?? [];
    }

    /**
     * Settings keys carried over from the source plugin into Buttonizer.
     *
     * These are copied on top of the connection itself (token / site_id), so
     * behaviour the user configured in the source plugin survives the move.
     *
     * @return string[]
     */
    public function settingsToCarryOver(): array
    {
        return $this->config['settings_to_carry_over'] ?? [];
    }

    /**
     * Every option name that must be snapshotted before touching anything.
     *
     * @return string[]
     */
    public function optionsToBackup(): array
    {
        $options = [
            $this->tokenOption(),
            $this->settingsOption(),
            $this->accountOption(),
        ];

        if ($this->legacyFlagOption()) {
            $options[] = $this->legacyFlagOption();
        }

        $options = array_merge($options, $this->legacyOptions(), $this->config['extra_options'] ?? []);

        return array_values(array_unique($options));
    }

    /**
     * Does this source plugin ship an embedded module?
     *
     * Cloud installs need no module — their data lives in the Buttonizer
     * account, not in code. Only the source plugin's own pre-Buttonizer system
     * has to be carried over verbatim.
     */
    public function hasModule(): bool
    {
        return !empty($this->config['module_bootstrap']) && file_exists($this->modulePath());
    }

    /**
     * Absolute path to the module's bootstrap file.
     */
    public function modulePath(): string
    {
        return BUTTONIZER_DIR . '/modules/' . ($this->config['module_bootstrap'] ?? '');
    }

    /**
     * Admin page the module registers for itself (e.g. "contact_vr").
     */
    public function modulePageSlug(): string
    {
        return $this->config['module_page_slug'] ?? '';
    }

    /**
     * Class that fits the module into Buttonizer, if it needs one.
     *
     * @return string|null Fully qualified class name with an attach() method.
     */
    public function moduleAdapter()
    {
        $adapter = $this->config['module_adapter'] ?? null;

        return $adapter && class_exists($adapter) ? $adapter : null;
    }

    /**
     * Is the module's code already in memory?
     *
     * The source plugin declares the same classes and functions in the global
     * namespace, so loading the module while it is still running is a fatal
     * redeclaration error.
     */
    public function isModuleLoaded(): bool
    {
        $guardClass = $this->config['module_guard_class'] ?? null;

        if ($guardClass && class_exists($guardClass, false)) {
            return true;
        }

        $guardConstant = $this->config['module_guard_constant'] ?? null;

        return $guardConstant ? defined($guardConstant) : false;
    }

    /**
     * Possible plugin basenames for the source plugin.
     *
     * The folder name differs between the development checkout and the build
     * published to wordpress.org, so the basename is resolved at runtime
     * instead of being hardcoded.
     *
     * @return string[]
     */
    public function baseNameCandidates(): array
    {
        return $this->config['base_name_candidates'] ?? [];
    }

    /**
     * Resolve the basename of the installed source plugin.
     *
     * Tries the known candidates first — two file_exists() calls. The fallback
     * scan over every installed plugin is opt-in, because this runs on every
     * admin request and get_plugins() walks the whole plugin directory.
     *
     * @param bool $deepScan Allow scanning installed plugins by text domain.
     *
     * @return string|null Basename, or null when the plugin is not installed.
     */
    public function resolveBaseName(bool $deepScan = false)
    {
        if ($this->resolvedBaseName) {
            return $this->resolvedBaseName;
        }

        foreach ($this->baseNameCandidates() as $candidate) {
            if (file_exists(WP_PLUGIN_DIR . '/' . $candidate)) {
                return $this->resolvedBaseName = $candidate;
            }
        }

        if (!$deepScan || $this->deepScanned) {
            return null;
        }

        $this->deepScanned = true;
        $textDomain        = $this->config['text_domain'] ?? null;

        if ($textDomain && function_exists('get_plugins')) {
            foreach (get_plugins() as $baseName => $plugin) {
                if (isset($plugin['TextDomain']) && $plugin['TextDomain'] === $textDomain) {
                    return $this->resolvedBaseName = $baseName;
                }
            }
        }

        return null;
    }

    /**
     * Is the source plugin installed on this site?
     *
     * @param bool $deepScan Allow the fallback scan by text domain.
     */
    public function isInstalled(bool $deepScan = false): bool
    {
        return $this->resolveBaseName($deepScan) !== null;
    }

    /**
     * Is the source plugin currently active?
     */
    public function isActive(): bool
    {
        $baseName = $this->resolveBaseName();

        if (!$baseName) {
            return false;
        }

        if (function_exists('is_plugin_active')) {
            return is_plugin_active($baseName);
        }

        // plugin.php is not loaded yet, read the option directly
        return in_array($baseName, (array) get_option('active_plugins', []), true);
    }
}
