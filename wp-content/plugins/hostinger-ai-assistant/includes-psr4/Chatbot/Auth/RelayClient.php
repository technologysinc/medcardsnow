<?php

namespace Hostinger\AiAssistant\Chatbot\Auth;

use Hostinger\WpHelper\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Server-to-server client for the message-hub WordPress login relay.
 *
 * The site proof (the hosting order token and the domain) is attached here and sent to message-hub server to server.
 * It never reaches the browser.
 */
class RelayClient {
    public const STATUS_UNREACHABLE = 0;

    private const SITE_PATH          = '/api/v2/site';
    private const PROOF_HEADER       = 'X-Kodee-Site-Proof';
    private const DOMAIN_HEADER      = 'X-Kodee-Site-Domain';
    private const CORRELATION_HEADER = 'X-Correlation-ID';
    private const REQUEST_TIMEOUT    = 15;

    private Utils $utils;

    public function __construct( Utils $utils ) {
        $this->utils = $utils;
    }

    /**
     * The correlation id is sent to message-hub, which tags every log line of the call with it, so one login attempt
     * can be found in its logs from what the site or the browser saw.
     */
    public function post( string $path, array $body ): array {
        $correlation_id = wp_generate_uuid4();
        $response       = wp_remote_post(
            $this->get_base_url() . self::SITE_PATH . $path,
            array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array(
                    'Content-Type'           => 'application/json',
                    'Accept'                 => 'application/json',
                    self::PROOF_HEADER       => trim( (string) Utils::getApiToken() ),
                    self::DOMAIN_HEADER      => $this->utils->getHostInfo(),
                    self::CORRELATION_HEADER => $correlation_id,
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'status'         => self::STATUS_UNREACHABLE,
                'body'           => array(),
                'correlation_id' => $correlation_id,
            );
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        return array(
            'status'         => (int) wp_remote_retrieve_response_code( $response ),
            'body'           => is_array( $decoded ) ? $decoded : array(),
            'correlation_id' => $correlation_id,
        );
    }

    private function get_base_url(): string {
        if ( isset( $_SERVER['H_STAGING'] ) && filter_var( $_SERVER['H_STAGING'], FILTER_VALIDATE_BOOLEAN ) === true ) {
            return 'https://message-hub.hostinger.dev';
        }

        return 'https://message-hub.hostinger.com';
    }
}
