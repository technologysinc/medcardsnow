<?php

namespace Hostinger\Cdn;

use Hostinger\WpHelper\Utils;
use WP_Http;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class CachePurger {
    private const INSTALLATIONS_BASE = '/api/v1/installations/';
    private const ALL_CACHE_PATH     = '/cache';
    private const CDN_CACHE_PATH     = '/cache/cdn';

    private CacheClient $client;
    private SoftwareIdResolver $resolver;
    private Utils $utils;

    public function __construct( CacheClient $client, SoftwareIdResolver $resolver, Utils $utils ) {
        $this->client   = $client;
        $this->resolver = $resolver;
        $this->utils    = $utils;
    }

    public function purge_all(): array|WP_Error {
        return $this->purge( self::ALL_CACHE_PATH );
    }

    public function purge_cdn(): array|WP_Error {
        return $this->purge( self::CDN_CACHE_PATH );
    }

    private function purge( string $path ): array|WP_Error {
        $domain = $this->resolver->site_domain();

        if ( ! $this->is_eligible( $domain ) ) {
            return array(
                'purged'  => false,
                'skipped' => true,
                'message' => __( 'Cache clearing is not available for this site.', 'hostinger' ),
            );
        }

        $software_id = $this->resolver->resolve();

        if ( $software_id === null ) {
            return new WP_Error(
                'hostinger_cdn_no_software_id',
                __( 'Could not determine the Hostinger installation for this site.', 'hostinger' )
            );
        }

        $endpoint = self::INSTALLATIONS_BASE . rawurlencode( $software_id ) . $path;
        $response = $this->client->delete( $endpoint, $domain );

        if ( is_wp_error( $response ) ) {
            $this->log_failure( $endpoint, $response->get_error_message() );

            return $this->purge_failed();
        }

        $response_code = wp_remote_retrieve_response_code( $response );

        if ( WP_Http::NOT_FOUND === $response_code ) {
            $retry = $this->retry_with_fresh_software_id( $software_id, $path, $domain );

            if ( $retry !== null ) {
                $response      = $retry;
                $response_code = wp_remote_retrieve_response_code( $response );
            }
        }

        if ( $response_code >= 300 ) {
            $this->log_failure( $endpoint, $response_code . ' ' . sanitize_text_field( wp_remote_retrieve_response_message( $response ) ) );

            return $this->purge_failed();
        }

        return array(
            'purged'  => true,
            'skipped' => false,
            'message' => __( 'Cache has been cleared', 'hostinger' ),
        );
    }

    private function is_eligible( ?string $domain ): bool {
        if ( empty( $_SERVER['H_PLATFORM'] ) ) {
            return false;
        }

        return $domain !== null;
    }

    private function retry_with_fresh_software_id( string $stale_id, string $path, string $domain ): array|null {
        $this->log_failure( self::INSTALLATIONS_BASE . $stale_id . $path, '404 - cached software_id looks stale, re-resolving' );

        $this->resolver->forget();

        $fresh_id = $this->resolver->resolve();

        if ( $fresh_id === null || $fresh_id === $stale_id ) {
            return null;
        }

        $response = $this->client->delete(
            self::INSTALLATIONS_BASE . rawurlencode( $fresh_id ) . $path,
            $domain
        );

        return is_wp_error( $response ) ? null : $response;
    }

    private function purge_failed(): WP_Error {
        return new WP_Error(
            'hostinger_cdn_purge_failed',
            __( 'We could not clear the cache. Please try again later.', 'hostinger' )
        );
    }

    private function log_failure( string $endpoint, string $reason ): void {
        $this->utils->errorLog( 'Hostinger Tools hCDN purge failed: DELETE ' . $endpoint . ' -- ' . $reason );
    }
}
