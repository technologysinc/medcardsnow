<?php

namespace Hostinger\Cdn;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class CacheClient {
    private const DEFAULT_TIMEOUT   = 5;
    private const MAX_RESPONSE_SIZE = 65536;

    private Client $client;

    public function __construct( Client $client ) {
        $this->client = $client;
    }

    public function delete( string $endpoint, ?string $domain = null, int $timeout = self::DEFAULT_TIMEOUT ): array|WP_Error {
        $url = $this->client->get_api_url() . $endpoint;

        if ( preg_match( '/[\s\x00-\x1f]/', $endpoint ) || ! $this->is_allowed_url( $url ) ) {
            return new WP_Error(
                'hostinger_cdn_unexpected_host',
                'Refusing to send an authenticated request to an unexpected host.'
            );
        }

        return wp_remote_request(
            $url,
            array(
                'method'              => 'DELETE',
                'headers'             => $this->request_headers( $domain ),
                'timeout'             => $timeout,
                'redirection'         => 0,
                'sslverify'           => true,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => self::MAX_RESPONSE_SIZE,
            )
        );
    }

    private function request_headers( ?string $domain ): array {
        $headers = $this->client->get_default_headers();

        if ( $domain !== null ) {
            $headers[ Config::DOMAIN_HEADER ] = $domain;
        }

        return $headers;
    }

    private function is_allowed_url( string $url ): bool {
        $parts = wp_parse_url( $url );

        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return false;
        }

        return $parts['scheme'] === 'https' && $parts['host'] === HOSTINGER_PROXY_API_HOST;
    }
}
