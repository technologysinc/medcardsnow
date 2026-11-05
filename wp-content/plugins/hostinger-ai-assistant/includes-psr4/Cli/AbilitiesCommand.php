<?php

namespace Hostinger\AiAssistant\Cli;

use Hostinger\AiAssistant\Mcp\Abilities\AbilityCatalog;
use RuntimeException;
use WP_Ability;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Exports the abilities this plugin's MCP server exposes.
 */
class AbilitiesCommand {
    public const MANIFEST_SCHEMA_VERSION = 1;

    /**
     * Optional plugins that register additional abilities, keyed by slug, with the
     * constant that holds their version when they are active.
     */
    private const INTEGRATIONS = array(
        'woocommerce'     => 'WC_VERSION',
        'elementor'       => 'ELEMENTOR_VERSION',
        'litespeed-cache' => 'LSCWP_V',
        'hostinger'       => 'HOSTINGER_WORDPRESS_PLUGIN_VERSION',
    );

    /**
     * Exports a JSON manifest of every ability the MCP server exposes.
     *
     * Abilities that depend on an optional plugin (WooCommerce, Elementor, LiteSpeed Cache,
     * Hostinger Tools) are only included when that plugin is active, and input schemas built
     * from REST controllers reflect the active plugin versions. Run it on a site with every
     * integration active to get the complete list.
     *
     * ## OPTIONS
     *
     * [--output=<file>]
     * : Write the manifest to this file instead of stdout.
     *
     * ## EXAMPLES
     *
     *     # Print the manifest.
     *     $ wp hostinger-ai abilities export
     *
     *     # Save it to a file.
     *     $ wp hostinger-ai abilities export --output=abilities.json
     *
     * @subcommand export
     * @when after_wp_load
     */
    public function export( array $args, array $assoc_args ): void {
        try {
            $json = $this->encode_manifest();
        } catch ( RuntimeException $exception ) {
            WP_CLI::error( $exception->getMessage() );

            return;
        }

        if ( empty( $assoc_args['output'] ) ) {
            WP_CLI::line( $json );

            return;
        }

        $output = (string) $assoc_args['output'];

        if ( file_put_contents( $output, $json . "\n" ) === false ) {
            WP_CLI::error( sprintf( 'Could not write to %s.', $output ) );

            return;
        }

        WP_CLI::success( sprintf( 'Exported %d abilities to %s.', count( $this->get_abilities() ), $output ) );
    }

    /**
     * @throws RuntimeException When the manifest can't be encoded as JSON.
     */
    public function encode_manifest(): string {
        $json = wp_json_encode( $this->build_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        if ( $json === false ) {
            throw new RuntimeException( 'Could not encode the abilities manifest: ' . json_last_error_msg() );
        }

        return $json;
    }

    public function build_manifest(): array {
        return array(
            'schema_version' => self::MANIFEST_SCHEMA_VERSION,
            'plugin_version' => HOSTINGER_AI_PLUGIN_VERSION,
            'generated_at'   => gmdate( 'c' ),
            'integrations'   => (object) $this->get_active_integrations(),
            'abilities'      => array_map( array( $this, 'describe_ability' ), $this->get_abilities() ),
        );
    }

    public function describe_ability( WP_Ability $ability ): array {
        $meta = $ability->get_meta();

        $entry = array(
            'name'        => $ability->get_name(),
            'label'       => $ability->get_label(),
            'description' => $ability->get_description(),
            'type'        => $meta['mcp']['type'] ?? 'tool',
        );

        $uri = $meta['mcp']['uri'] ?? $meta['uri'] ?? '';
        if ( $uri !== '' ) {
            $entry['uri'] = $uri;
        }

        $entry['annotations']   = $this->as_json_object( $this->get_set_annotations( $meta ) );
        $entry['input_schema']  = $this->as_json_object( $ability->get_input_schema() );
        $entry['output_schema'] = $this->as_json_object( $ability->get_output_schema() );

        return $entry;
    }

    /**
     * Tools and resources, sorted by name so the output is stable.
     */
    private function get_abilities(): array {
        $abilities = array_merge( AbilityCatalog::get_by_type( 'tool' ), AbilityCatalog::get_by_type( 'resource' ) );

        usort(
            $abilities,
            function ( WP_Ability $first, WP_Ability $second ) {
                return strcmp( $first->get_name(), $second->get_name() );
            }
        );

        return $abilities;
    }

    /**
     * The Abilities API fills in its own annotation keys as null; keep only those that are set.
     */
    private function get_set_annotations( array $meta ): array {
        return array_filter(
            $meta['annotations'] ?? array(),
            function ( $value ) {
                return $value !== null;
            }
        );
    }

    /**
     * An empty array would encode as a JSON list; the manifest uses objects throughout.
     */
    private function as_json_object( array $value ): array|object {
        if ( empty( $value ) ) {
            return (object) array();
        }

        return $value;
    }

    private function get_active_integrations(): array {
        $active = array();

        foreach ( self::INTEGRATIONS as $slug => $version_constant ) {
            if ( defined( $version_constant ) ) {
                $active[ $slug ] = (string) constant( $version_constant );
            }
        }

        return $active;
    }
}
