<?php

namespace Hostinger\Rest;

use Hostinger\Cdn\CachePurger;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class CacheRoutes {
    private const ERROR_STATUSES = array(
        'hostinger_cdn_purge_failed'   => WP_Http::BAD_GATEWAY,
        'hostinger_cdn_no_software_id' => WP_Http::CONFLICT,
    );

    private CachePurger $cache_purger;

    public function __construct( CachePurger $cache_purger ) {
        $this->cache_purger = $cache_purger;
    }

    /** PHPCS:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found */
    public function clear_cache( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $result = $this->cache_purger->purge_all();

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result );
        }

        $response = new WP_REST_Response( array( 'data' => $result ) );

        $response->set_headers( array( 'Cache-Control' => 'no-store' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }
    /** PHPCS:enable */

    private function error_response( WP_Error $error ): WP_Error {
        $code = $error->get_error_code();

        return new WP_Error(
            $code,
            $error->get_error_message(),
            array( 'status' => self::ERROR_STATUSES[ $code ] ?? WP_Http::INTERNAL_SERVER_ERROR )
        );
    }
}
