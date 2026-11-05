<?php

namespace Hostinger\Cdn;

use Hostinger\WpHelper\Utils;
use Throwable;

defined( 'ABSPATH' ) || exit;

class LiteSpeedHooks {
    public const THROTTLE_TRANSIENT = 'hostinger_cdn_purge_throttle';
    public const THROTTLE_SECONDS   = 10;

    private CachePurger $purger;
    private Utils $utils;

    private bool $purged_this_request = false;

    public function __construct( CachePurger $purger, Utils $utils ) {
        $this->purger = $purger;
        $this->utils  = $utils;

        add_action( 'litespeed_purged_all', array( $this, 'purge_cdn_cache' ) );
    }

    public function purge_cdn_cache(): void {
        if ( $this->purged_this_request ) {
            return;
        }

        if ( ! apply_filters( 'hostinger_cdn_purge_on_litespeed_purge', true ) ) {
            return;
        }

        if ( get_transient( self::THROTTLE_TRANSIENT ) ) {
            return;
        }

        $this->purged_this_request = true;
        set_transient( self::THROTTLE_TRANSIENT, 1, self::THROTTLE_SECONDS );

        try {
            $result = $this->purger->purge_cdn();

            if ( is_wp_error( $result ) ) {
                $this->utils->errorLog( 'Hostinger Tools hCDN purge failed after LiteSpeed purge: ' . $result->get_error_code() );
            } elseif ( ! empty( $result['skipped'] ) ) {
                $this->utils->errorLog( 'Hostinger Tools hCDN purge skipped after LiteSpeed purge: site not eligible' );
            }
        } catch ( Throwable $e ) {
            $this->utils->errorLog( 'Hostinger Tools hCDN purge threw after LiteSpeed purge: ' . $e->getMessage() );
        }
    }
}
