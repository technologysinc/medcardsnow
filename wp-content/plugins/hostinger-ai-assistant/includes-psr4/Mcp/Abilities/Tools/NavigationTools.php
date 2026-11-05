<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_REST_Posts_Controller;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class NavigationTools extends RestEndpointTool {
    public function register(): void {
        $controller_class = $this->get_controller_class();

        if ( $controller_class === null ) {
            return;
        }

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/navigation-search',
                    'label'       => __( 'List Navigations', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of Site Editor navigations.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Navigations',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai/navigation-get',
                    'label'       => __( 'Get Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single navigation by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'Get Navigation',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai/navigation-create',
                    'label'       => __( 'Create Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new Site Editor navigation.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Add Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => false,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai/navigation-update',
                    'label'       => __( 'Update Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing Site Editor navigation.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Update Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai/navigation-delete',
                    'label'       => __( 'Delete Navigation', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a Site Editor navigation by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Navigation',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            $controller_class,
            '/wp/v2/navigation',
            'wp_navigation'
        );
    }

    protected function get_controller_class(): ?string {
        if ( ! post_type_exists( 'wp_navigation' ) ) {
            return null;
        }

        if ( class_exists( 'WP_REST_Navigation_Controller' ) ) {
            return 'WP_REST_Navigation_Controller';
        }

        $post_type        = get_post_type_object( 'wp_navigation' );
        $controller_class = $post_type->rest_controller_class ?? '';

        if ( is_string( $controller_class ) && class_exists( $controller_class ) && is_subclass_of( $controller_class, 'WP_REST_Controller' ) ) {
            return $controller_class;
        }

        return WP_REST_Posts_Controller::class;
    }
}
