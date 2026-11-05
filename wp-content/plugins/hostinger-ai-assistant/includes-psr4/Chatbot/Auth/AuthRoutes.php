<?php

namespace Hostinger\AiAssistant\Chatbot\Auth;

use Hostinger\AiAssistant\Functions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Admin-only REST routes the chatbot widget uses to log the wp-admin user in with their Hostinger account.
 *
 * Each route forwards to the message-hub relay. The relay session reference is kept in a per-site user option and is
 * never sent to the browser: the widget only ever receives the short-lived ticket.
 */
class AuthRoutes {
    public const SESSION_REF_OPTION      = 'hostinger_ai_assistant_agent_session_ref';
    public const PENDING_LOGIN_ID_OPTION = 'hostinger_ai_assistant_agent_pending_login_id';
    public const LOGIN_ID_PATTERN        = '\A[A-Za-z0-9_-]{16,128}\z';

    private const ROUTE_PREFIX       = '/hostinger-agent/auth';
    private const WP_USER_REF        = 'wp-user-';
    private const RELAY_LOGIN_START  = '/login/start';
    private const RELAY_LOGIN_STATUS = '/login/status';
    private const RELAY_TICKET       = '/ticket';
    private const RELAY_LOGOUT       = '/logout';

    private RelayClient $relay_client;

    public function __construct( RelayClient $relay_client ) {
        $this->relay_client = $relay_client;
    }

    public function init(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            self::ROUTE_PREFIX . '/start',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'start' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            self::ROUTE_PREFIX . '/status',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'status' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'login_id' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'validate_callback' => array( $this, 'is_valid_login_id' ),
                    ),
                ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            self::ROUTE_PREFIX . '/ticket',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'ticket' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

        register_rest_route(
            HOSTINGER_AI_ASSISTANT_REST_API_BASE,
            self::ROUTE_PREFIX . '/logout',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'logout' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    public function check_permissions(): bool {
        return current_user_can( 'manage_options' );
    }

    public function is_valid_login_id( mixed $value ): bool {
        return is_string( $value ) && preg_match( '/' . self::LOGIN_ID_PATTERN . '/', $value ) === 1;
    }

    public function start(): WP_REST_Response|WP_Error {
        $result = $this->relay_client->post(
            self::RELAY_LOGIN_START,
            array( 'wp_user_ref' => self::WP_USER_REF . get_current_user_id() )
        );

        if ( $result['status'] === 409 && ( $result['body']['detail'] ?? null ) === AuthErrors::SITE_NOT_READY ) {
            return AuthErrors::site_not_ready();
        }

        if ( $result['status'] !== 200 ) {
            return $this->relay_error( self::RELAY_LOGIN_START, 'status ' . $result['status'], $result );
        }

        $login_id = $result['body']['login_id'] ?? null;

        if ( ! $this->is_valid_login_id( $login_id ) ) {
            return $this->relay_error( self::RELAY_LOGIN_START, 'a missing or malformed login_id', $result );
        }

        update_user_option( get_current_user_id(), self::PENDING_LOGIN_ID_OPTION, $login_id );

        return rest_ensure_response( $this->pick( $result['body'], array( 'authorization_url', 'login_id', 'user_code' ) ) );
    }

    public function status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $login_id = (string) $request->get_param( 'login_id' );
        $pending  = (string) get_user_option( self::PENDING_LOGIN_ID_OPTION, get_current_user_id() );

        if ( $pending === '' || ! hash_equals( $pending, $login_id ) ) {
            return AuthErrors::invalid_login();
        }

        $result = $this->relay_client->post( self::RELAY_LOGIN_STATUS, array( 'login_id' => $login_id ) );

        if ( $result['status'] !== 200 ) {
            return $this->relay_error( self::RELAY_LOGIN_STATUS, 'status ' . $result['status'], $result );
        }

        $session_ref = $result['body']['session_ref'] ?? null;

        if ( is_string( $session_ref ) && $session_ref !== '' ) {
            update_user_option( get_current_user_id(), self::SESSION_REF_OPTION, $session_ref );
            delete_user_option( get_current_user_id(), self::PENDING_LOGIN_ID_OPTION );
        }

        return rest_ensure_response( $this->pick( $result['body'], array( 'status', 'tier' ) ) );
    }

    public function ticket(): WP_REST_Response|WP_Error {
        $session_ref = $this->get_session_ref();

        if ( $session_ref === '' ) {
            return AuthErrors::login_required();
        }

        $result = $this->relay_client->post( self::RELAY_TICKET, array( 'session_ref' => $session_ref ) );

        if ( $result['status'] === 401 ) {
            $this->forget_session_ref();

            return AuthErrors::login_required();
        }

        if ( $result['status'] !== 200 ) {
            return $this->relay_error( self::RELAY_TICKET, 'status ' . $result['status'], $result );
        }

        return rest_ensure_response( $this->pick( $result['body'], array( 'ticket', 'expires_in', 'tier' ) ) );
    }

    public function logout(): WP_REST_Response {
        $session_ref = $this->get_session_ref();
        $this->forget_session_ref();
        delete_user_option( get_current_user_id(), self::PENDING_LOGIN_ID_OPTION );

        if ( $session_ref !== '' ) {
            $this->relay_client->post( self::RELAY_LOGOUT, array( 'session_ref' => $session_ref ) );
        }

        return new WP_REST_Response( null, 204 );
    }

    private function get_session_ref(): string {
        return (string) get_user_option( self::SESSION_REF_OPTION, get_current_user_id() );
    }

    private function forget_session_ref(): void {
        delete_user_option( get_current_user_id(), self::SESSION_REF_OPTION );
    }

    private function relay_error( string $path, string $failure, array $result ): WP_Error {
        $correlation_id = (string) ( $result['correlation_id'] ?? '' );

        Functions::log_event(
            'Hostinger agent login relay ' . $path . ' failed with ' . $failure . ' (correlation id ' . $correlation_id . ')'
        );

        return AuthErrors::relay_error( $correlation_id );
    }

    private function pick( array $body, array $keys ): array {
        return array_intersect_key( $body, array_flip( $keys ) );
    }
}
