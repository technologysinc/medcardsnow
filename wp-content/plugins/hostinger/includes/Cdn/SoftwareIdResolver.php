<?php

namespace Hostinger\Cdn;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use TypeError;

defined( 'ABSPATH' ) || exit;

class SoftwareIdResolver {
    public const OPTION_NAME = 'hostinger_sfid';

    private const LOOKUP_ENDPOINT         = '/api/v1/installations';
    private const LOOKUP_TIMEOUT          = 5;
    private const LOOKUP_FAILED_TRANSIENT = 'hostinger_sfid_lookup_failed';
    private const LOOKUP_FAILED_TTL       = 600;

    private Client $client;

    public function __construct( Client $client ) {
        $this->client = $client;
    }

    public function resolve(): ?string {
        if ( defined( 'HOSTINGER_SOFTWARE_ID_OVERRIDE' ) ) {
            $override = $this->normalize( HOSTINGER_SOFTWARE_ID_OVERRIDE );

            if ( $override !== null ) {
                return $override;
            }
        }

        $stored = $this->normalize( get_option( self::OPTION_NAME ) );

        if ( $stored !== null ) {
            return $stored;
        }

        $from_config = $this->from_config();

        if ( $from_config !== null ) {
            update_option( self::OPTION_NAME, $from_config, true );

            return $from_config;
        }

        return $this->from_lookup();
    }

    public function site_domain(): ?string {
        $host = wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_HOST );

        if ( ! is_string( $host ) || $host === '' ) {
            return null;
        }

        $host = preg_replace( '/^www\./', '', $host );

        if ( ! filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
            return null;
        }

        return $host;
    }

    public function forget(): void {
        delete_option( self::OPTION_NAME );
        delete_transient( self::LOOKUP_FAILED_TRANSIENT );
    }

    private function from_config(): ?string {
        try {
            $value = ( new Config() )->getConfigValue( 'software_id', '' );
        } catch ( TypeError $e ) {
            return null;
        }

        return $this->normalize( $value );
    }

    private function from_lookup(): ?string {
        if ( get_transient( self::LOOKUP_FAILED_TRANSIENT ) ) {
            return null;
        }

        $domain = $this->site_domain();

        if ( $domain === null ) {
            return null;
        }

        $response = $this->client->get(
            self::LOOKUP_ENDPOINT,
            array( 'domain' => $domain ),
            array( Config::DOMAIN_HEADER => $domain ),
            self::LOOKUP_TIMEOUT
        );

        $software_id = $this->id_from_response( $response );

        if ( $software_id === null ) {
            set_transient( self::LOOKUP_FAILED_TRANSIENT, 1, self::LOOKUP_FAILED_TTL );

            return null;
        }

        update_option( self::OPTION_NAME, $software_id, true );

        return $software_id;
    }

    private function id_from_response( $response ): ?string {
        if ( is_wp_error( $response ) ) {
            return null;
        }

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) || ! isset( $data['data'][0]['id'] ) ) {
            return null;
        }

        return $this->normalize( $data['data'][0]['id'] );
    }

    private function normalize( $raw ): ?string {
        if ( ! is_scalar( $raw ) || ! ctype_digit( (string) $raw ) ) {
            return null;
        }

        $software_id = (string) absint( $raw );

        return $software_id === '0' ? null : $software_id;
    }
}
