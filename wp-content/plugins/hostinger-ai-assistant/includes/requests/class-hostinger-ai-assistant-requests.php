<?php

use Alley\WP\Block_Converter\Block_Converter;
use Hostinger\Amplitude\AmplitudeManager;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;

class Hostinger_Ai_Assistant_Requests {
    private const WP_PROXY_AI_PRODUCT_DESCRIPTION = '/installations/{software_id}/content/product/description';
    private const GENERATE_CONTENT_ACTION         = '/v3/wordpress/plugin/generate-content';
    private const GET_CONTENT_IMAGES_URLS         = '/v3/wordpress/plugin/search-images';
    private const WOO_DESCRIPTION_CREATED         = 'hts_woo_product_description_created';

    private Hostinger_Ai_Assistant_Requests_Client $client;
    private Hostinger_Ai_Assistant_Requests_Proxy $proxy;
    private Hostinger_Ai_Assistant_Amplitude $amplitude;
    private Hostinger_Ai_Assistant_Content_Generation $generate_content;
    private Hostinger_Ai_Assistant_Config $config_handler;
    private Hostinger_Ai_Assistant_Errors $error_handler;
    private Hostinger_Ai_Assistant_Helper $helper;

    public function __construct() {
        $this->helper         = new Hostinger_Ai_Assistant_Helper();
        $this->config_handler = new Hostinger_Ai_Assistant_Config();
        $this->error_handler  = new Hostinger_Ai_Assistant_Errors();

        $default_headers = array(
            Hostinger_Ai_Assistant_Config::TOKEN_HEADER  => $this->helper::get_api_token(),
            Hostinger_Ai_Assistant_Config::DOMAIN_HEADER => $this->helper->get_host_info(),
        );

        $this->client = new Hostinger_Ai_Assistant_Requests_Client(
            $this->config_handler->get_config_value( 'base_rest_uri', HOSTINGER_AI_ASSISTANT_REST_URI ),
            $default_headers
        );

        $proxy_client = new Hostinger_Ai_Assistant_Requests_Client(
            $this->config_handler->get_config_value( 'wp_proxy_ai_rest_uri', HOSTINGER_AI_ASSISTANT_WP_API_PROXY_URI ),
            $default_headers
        );

        $this->proxy = new Hostinger_Ai_Assistant_Requests_Proxy(
            $proxy_client,
            $default_headers
        );

        $helper = new Helper();
        $config = new Config();
        $client = new Client(
            $config->getConfigValue( 'base_rest_uri', Constants::HOSTINGER_REST_URI ),
            array(
                Config::TOKEN_HEADER  => $helper->getApiToken(),
                Config::DOMAIN_HEADER => $helper->getHostInfo(),
            )
        );

        $amplitude_manager      = new AmplitudeManager( $helper, $config, $client );
        $this->amplitude        = new Hostinger_Ai_Assistant_Amplitude( $amplitude_manager );
        $this->generate_content = new Hostinger_Ai_Assistant_Content_Generation();
    }

    public function init(): void {
        add_action( 'init', array( $this, 'define_ajax_events' ), 0 );
    }

    public function define_ajax_events(): void {
        if ( ! current_user_can( 'publish_posts' ) ) {
            return;
        }

        $events = array(
            'get_content_from_description',
            'redirect_to_post_editor_with_content',
            'redirect_to_published_post',
            'woo_product_description_create',
            'upload_unsplash_image',
        );

        foreach ( $events as $event ) {
            $ajax_event = 'hts_' . $event;
            add_action( 'wp_ajax_' . $ajax_event, array( $this, $event ) );
        }
    }

    public function woo_product_description_create(): void {
        $nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        $post_id   = isset( $_POST['post_id'] ) ? sanitize_text_field( $_POST['post_id'] ) : 1;
        $error_msg = $this->error_handler->get_error_message( 'action_failed' );
        $post_type = get_post_type( $post_id ) ?? 'product';

        if ( ! wp_verify_nonce( $nonce, 'generate_content' ) ) {
            $this->helper->ajax_error_message( $error_msg, $error_msg );
        }

        $this->amplitude->ai_content_saved( $post_type, $post_id, 'woocommerce_ui' );
        $existing_values   = get_option( self::WOO_DESCRIPTION_CREATED, array() );
        $existing_values[] = $post_id;
        update_option( self::WOO_DESCRIPTION_CREATED, $existing_values );
    }

    public function get_content_from_description(): void {
        $error_msg = $this->error_handler->get_error_message( 'action_failed' );
        $nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

        if ( ! wp_verify_nonce( $nonce, 'generate_content' ) ) {
            $this->helper->ajax_error_message( $error_msg, $error_msg );
        }

        $generated_content = $this->generate_from_description(
            array(
                'description'    => isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : '',
                'correlation_id' => isset( $_POST['correlation_id'] ) ? sanitize_text_field( $_POST['correlation_id'] ) : '',
                'post_type'      => isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'blog_post',
                'voice_tone'     => isset( $_POST['voice_tone'] ) ? sanitize_text_field( $_POST['voice_tone'] ) : 'neutral',
                'focus_keywords' => isset( $_POST['focus_keywords'] ) ? sanitize_text_field( $_POST['focus_keywords'] ) : '',
                'content_length' => isset( $_POST['content_length'] ) ? sanitize_text_field( $_POST['content_length'] ) : '150-300',
                'location'       => isset( $_POST['location'] ) ? sanitize_text_field( $_POST['location'] ) : 'ai_assistant_ui',
            )
        );

        if ( is_wp_error( $generated_content ) ) {
            $this->helper->ajax_error_message( $this->get_error_log_message( $generated_content ), $generated_content->get_error_message() );
        }

        wp_send_json_success( $generated_content );
    }

    public function generate_from_description( array $args ): stdClass|WP_Error {
        $unexpected_error  = $this->error_handler->get_error_message( 'unexpected_error' );
        $server_error      = $this->error_handler->get_error_message( 'server_error' );
        $empty_description = $this->error_handler->get_error_message( 'empty_description' );

        $description    = $args['description'] ?? '';
        $correlation_id = $args['correlation_id'] ?? '';
        $voice_tone     = $args['voice_tone'] ?? 'neutral';
        $focus_keywords = $args['focus_keywords'] ?? '';
        $content_length = $args['content_length'] ?? '150-300';
        $location       = $args['location'] ?? 'ai_assistant_ui';

        if ( empty( $description ) ) {
            return $this->create_error( $empty_description, $empty_description, 400 );
        }

        try {
            $validated_post_type      = $this->generate_content->validate_post_type( $args['post_type'] ?? 'blog_post' );
            $post_type                = $this->generate_content->map_post_type( $validated_post_type );
            $content_length_validated = $this->generate_content->validate_content_length( $content_length );

            $is_product_description = $post_type === 'product_description';

            $data = array(
                'post_type'   => $post_type,
                'tone'        => $voice_tone,
                'length'      => $content_length_validated,
                'description' => $description,
            );

            if ( ! empty( $focus_keywords ) ) {
                $data['focus_keyword'] = $focus_keywords;
            }

            if ( $is_product_description ) {
                $params = array(
                    'description' => $description,
                    'length'      => $this->generate_content->validate_content_length( $content_length, true ),
                    'tone'        => $voice_tone,
                );

                if ( isset( $data['focus_keyword'] ) ) {
                    $params['keywords'] = $data['focus_keyword'];
                }
                $response = $this->proxy->post( self::WP_PROXY_AI_PRODUCT_DESCRIPTION, $params, array( 'X-Correlation-ID' => $correlation_id ) );
            } else {
                $response = $this->client->get( self::GENERATE_CONTENT_ACTION, $data, array( 'X-Correlation-ID' => $correlation_id ) );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );

            if ( is_wp_error( $response ) || $response_code !== 200 ) {
                $error_message = isset( json_decode( $response_body )->error->message )
                    ? json_decode( $response_body )->error->message
                    : $unexpected_error;

                return $this->create_error( $error_message, $server_error, 502 );
            }

            $decoded = json_decode( $response['body'] );
            $data    = $decoded->data ?? null;

            if ( $is_product_description ) {
                $generated_content = $data;
            } else {
                $generated_content = is_array( $data ) ? reset( $data ) : null;
            }

            if ( ! is_object( $generated_content ) ) {
                return $this->create_error( 'Error: empty content returned by the API', $server_error, 502 );
            }

            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            if ( isset( $generated_content->seoKeywords ) ) {
                // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
                $generated_content->seo_keywords = $generated_content->seoKeywords;
            }

            // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
            if ( isset( $generated_content->metaDescription ) ) {
                // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
                $generated_content->meta_description = $generated_content->metaDescription;
            }

            if ( isset( $generated_content->tags[0] ) && $generated_content->tags[0] !== '' ) {
                $ai_image_data = $this->get_ai_image_data( $description );

                if ( is_wp_error( $ai_image_data ) ) {
                    return $ai_image_data;
                }

                if ( ! empty( $ai_image_data->photo_image_url ) ) {
                    $generated_content->image = esc_url_raw( $ai_image_data->photo_image_url );
                }
            }

            $generated_content = $this->sanitize_generated_content( $generated_content );

            if ( ! empty( $generated_content->content ) ) {
                $converter = new Block_Converter( $generated_content->content );

                $generated_content->content_blocks = $converter->convert();
            }

            $this->amplitude->ai_content_created( $post_type, $location, array( 'correlation_id' => $correlation_id ) );

            return $generated_content;
        } catch ( Exception $exception ) {
            return $this->create_error( 'Error: ' . $exception->getMessage(), $server_error, 500 );
        }
    }

    public function redirect_to_post_editor_with_content(): void {
        $this->generate_content->process_post_action( 'create' );
    }

    public function redirect_to_published_post(): void {
        $this->generate_content->process_post_action( 'publish' );
    }

    public function get_ai_image_data( string $description ): stdClass|WP_Error {
        $unexpected_error = $this->error_handler->get_error_message( 'unexpected_error' );

        if ( $description === '' ) {
            return $this->create_error( $unexpected_error, $unexpected_error, 400 );
        }

        $domain = parse_url( get_site_url(), PHP_URL_HOST );

        try {
            $response = $this->client->post(
                self::GET_CONTENT_IMAGES_URLS,
                array(
                    'domain'      => $domain,
                    'description' => $description,
                    'limit'       => 1,
                )
            );

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body )->data->list ?? array();

            if ( empty( $response_data ) ) {
                return new stdClass();
            }

            if ( is_wp_error( $response ) || $response_code !== 200 ) {
                $error_message = isset( json_decode( $response_body )->error->message ) ? json_decode( $response_body )->error->message : $unexpected_error;

                return $this->create_error( $error_message, $unexpected_error, 502 );
            }

            return reset( $response_data );
        } catch ( Exception $exception ) {
            return $this->create_error( $exception->getMessage(), $unexpected_error, 500 );
        }
    }

    private function sanitize_generated_content( object $generated_content ): object {
        if ( isset( $generated_content->title ) ) {
            $generated_content->title = sanitize_text_field( $generated_content->title );
        }

        if ( isset( $generated_content->content ) ) {
            $generated_content->content = wp_kses_post( $generated_content->content );
        }

        return $generated_content;
    }

    private function create_error( string $log_message, string $display_message, int $status ): WP_Error {
        return new WP_Error(
            'hostinger_ai_content_generation_failed',
            $display_message,
            array(
                'status' => $status,
                'log'    => $log_message,
            )
        );
    }

    private function get_error_log_message( WP_Error $error ): string {
        $data = $error->get_error_data();

        return is_array( $data ) && isset( $data['log'] ) ? $data['log'] : $error->get_error_message();
    }
}

( new Hostinger_Ai_Assistant_Requests() )->init();
