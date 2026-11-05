<?php

namespace Hostinger\AiAssistant\ContentWriter;

use Hostinger\AiAssistant\AiSuite\AiSuitePage;
use Hostinger_Ai_Assistant_Content_Generation;
use Hostinger_Ai_Assistant_Requests;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ContentWriterRoutes {
    public const NAMESPACE = 'hostinger-ai-assistant/v1';
    public const GENERATE  = '/content-writer/generate';
    public const POSTS     = '/content-writer/posts';

    public const VOICE_TONES     = array( 'neutral', 'formal', 'trustworthy', 'friendly', 'witty' );
    public const CONTENT_LENGTHS = array( 'short', 'medium', 'long' );
    public const POST_STATUSES   = array( 'draft', 'publish' );

    private const LOCATION = 'ai_assistant_ui';

    private const DEFAULT_VOICE_TONE = 'neutral';
    private const LIST_SEPARATOR     = ',';
    private const ACTION_DRAFT       = 'create';
    private const ACTION_PUBLISH     = 'publish';

    private Hostinger_Ai_Assistant_Requests $requests;
    private Hostinger_Ai_Assistant_Content_Generation $content_generation;

    public function __construct( Hostinger_Ai_Assistant_Requests $requests, Hostinger_Ai_Assistant_Content_Generation $content_generation ) {
        $this->requests           = $requests;
        $this->content_generation = $content_generation;
    }

    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            self::GENERATE,
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'generate' ),
                'permission_callback' => array( $this, 'can_manage' ),
                'args'                => $this->get_generate_args(),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            self::POSTS,
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_post' ),
                'permission_callback' => array( $this, 'can_manage' ),
                'args'                => $this->get_post_args(),
            )
        );
    }

    public function can_manage(): bool {
        return current_user_can( AiSuitePage::CAPABILITY );
    }

    public function generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $voice_tones = (array) $request->get_param( 'voice_tones' );

        $generated_content = $this->requests->generate_from_description(
            array(
                'description'    => $request->get_param( 'description' ),
                'correlation_id' => $request->get_param( 'correlation_id' ),
                'post_type'      => $request->get_param( 'post_type' ),
                'voice_tone'     => empty( $voice_tones ) ? self::DEFAULT_VOICE_TONE : implode( self::LIST_SEPARATOR, $voice_tones ),
                'focus_keywords' => implode( self::LIST_SEPARATOR, (array) $request->get_param( 'focus_keywords' ) ),
                'content_length' => $request->get_param( 'content_length' ),
                'location'       => self::LOCATION,
            )
        );

        if ( is_wp_error( $generated_content ) ) {
            return $generated_content;
        }

        return rest_ensure_response( array( 'data' => $generated_content ) );
    }

    public function create_post( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $action = $request->get_param( 'status' ) === 'publish' ? self::ACTION_PUBLISH : self::ACTION_DRAFT;

        $post_id = $this->content_generation->save_generated_post(
            array(
                'title'           => $request->get_param( 'title' ),
                'content'         => $request->get_param( 'content' ),
                'post_type'       => $this->content_generation->resolve_post_type( $request->get_param( 'post_type' ) ),
                'category'        => $request->get_param( 'category' ),
                'tags'            => implode( self::LIST_SEPARATOR, (array) $request->get_param( 'tags' ) ),
                'thumbnail_image' => (bool) $request->get_param( 'thumbnail_image' ),
                'content_image'   => (bool) $request->get_param( 'content_image' ),
                'image_url'       => $request->get_param( 'image_url' ),
                'seo_keywords'    => implode( self::LIST_SEPARATOR, (array) $request->get_param( 'seo_keywords' ) ),
                'seo_description' => $request->get_param( 'seo_description' ),
                'correlation_id'  => $request->get_param( 'correlation_id' ),
            ),
            $action,
            self::LOCATION
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return rest_ensure_response(
            array(
                'data' => array(
                    'post_id' => $post_id,
                    'url'     => $this->content_generation->get_saved_post_url( $post_id, $action ),
                ),
            )
        );
    }

    private function get_generate_args(): array {
        return array(
            'description'    => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'post_type'      => array(
                'type'              => 'string',
                'default'           => 'post',
                'sanitize_callback' => 'sanitize_key',
            ),
            'voice_tones'    => array(
                'type'    => 'array',
                'default' => array( self::DEFAULT_VOICE_TONE ),
                'items'   => array(
                    'type' => 'string',
                    'enum' => self::VOICE_TONES,
                ),
            ),
            'content_length' => array(
                'type'    => 'string',
                'default' => 'short',
                'enum'    => self::CONTENT_LENGTHS,
            ),
            'focus_keywords' => $this->get_string_list_arg(),
            'correlation_id' => $this->get_text_arg(),
        );
    }

    private function get_post_args(): array {
        return array(
            'status'          => array(
                'type'     => 'string',
                'required' => true,
                'enum'     => self::POST_STATUSES,
            ),
            'title'           => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'content'         => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'wp_kses_post',
            ),
            'post_type'       => array(
                'type'              => 'string',
                'default'           => 'post',
                'sanitize_callback' => 'sanitize_key',
            ),
            'category'        => $this->get_text_arg(),
            'tags'            => $this->get_string_list_arg(),
            'thumbnail_image' => array(
                'type'    => 'boolean',
                'default' => false,
            ),
            'content_image'   => array(
                'type'    => 'boolean',
                'default' => false,
            ),
            'image_url'       => array(
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'esc_url_raw',
            ),
            'seo_keywords'    => $this->get_string_list_arg(),
            'seo_description' => $this->get_text_arg(),
            'correlation_id'  => $this->get_text_arg(),
        );
    }

    private function get_text_arg(): array {
        return array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        );
    }

    private function get_string_list_arg(): array {
        return array(
            'type'              => 'array',
            'default'           => array(),
            'items'             => array( 'type' => 'string' ),
            'sanitize_callback' => static fn( $values ): array => array_values( array_filter( array_map( 'sanitize_text_field', (array) $values ) ) ),
        );
    }
}
