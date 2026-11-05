<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class TemplatePartsTools extends RestEndpointTool {
    public const ASSIGN_NAVIGATION_TOOL = 'hostinger-ai/template-part-assign-navigation';
    private const ROUTE                 = '/wp/v2/template-parts';

    public function register(): void {
        $id_schema = array(
            'properties' => array(
                'id' => array(
                    'type'        => 'string',
                    'description' => __( 'Template part ID in the theme//slug format (e.g. twentytwentyfive//header), as returned by template-parts-search.', 'hostinger-ai-assistant' ),
                ),
            ),
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai/template-parts-search',
                    'label'       => __( 'List Template Parts', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of template parts.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'         => 'List Template Parts',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'                  => 'hostinger-ai/template-part-get',
                    'label'                      => __( 'Get Template Part', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Get a single template part by ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => $id_schema,
                    'meta'                       => array(
                        'annotations' => array(
                            'title'         => 'Get Template Part',
                            'readOnlyHint'  => true,
                            'openWorldHint' => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'                  => 'hostinger-ai/template-part-update',
                    'label'                      => __( 'Update Template Part', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Update a template part content or title.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => $id_schema,
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Update Template Part',
                            'readOnlyHint'    => false,
                            'destructiveHint' => false,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'                  => 'hostinger-ai/template-part-delete',
                    'label'                      => __( 'Delete Template Part', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Delete a template part by ID. For template parts provided by the theme, this removes the customizations and restores the theme version.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => $id_schema,
                    'meta'                       => array(
                        'annotations' => array(
                            'title'           => 'Delete Template Part',
                            'readOnlyHint'    => false,
                            'destructiveHint' => true,
                            'idempotentHint'  => true,
                            'openWorldHint'   => false,
                        ),
                    ),
                ),
            ),
            'WP_REST_Templates_Controller',
            self::ROUTE,
            'wp_template_part'
        );

        $this->register_assign_navigation();
    }

    public function assign_navigation( array $input ): WP_Error|array {
        $template_part_id = $this->format_route_id( $input['template_part_id'] ?? '' );
        $navigation_id    = intval( $input['navigation_id'] ?? 0 );
        $navigation       = get_post( $navigation_id );

        if ( ! $navigation || $navigation->post_type !== 'wp_navigation' || $navigation->post_status === 'trash' ) {
            return new WP_Error(
                'navigation_not_found',
                __( 'Navigation not found.', 'hostinger-ai-assistant' ),
                array( 'status' => 404 )
            );
        }

        $request = new WP_REST_Request( 'GET', self::ROUTE . '/' . $template_part_id );
        $request->set_param( 'context', 'edit' );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            return $response->as_error();
        }

        $template_part = $response->get_data();
        $blocks        = $this->set_navigation_ref( parse_blocks( $template_part['content']['raw'] ?? '' ), $navigation_id );

        if ( $blocks === null ) {
            return new WP_Error(
                'navigation_block_not_found',
                __( 'The template part does not contain a Navigation block.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $request = new WP_REST_Request( 'PUT', self::ROUTE . '/' . $template_part_id );
        $request->set_param( 'content', serialize_blocks( $blocks ) );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            return $response->as_error();
        }

        return $response->get_data();
    }

    protected function format_route_id( mixed $id ): string {
        return is_scalar( $id ) ? preg_replace( '/[^\w.\-\/]/', '', (string) $id ) : '';
    }

    private function register_assign_navigation(): void {
        wp_register_ability(
            self::ASSIGN_NAVIGATION_TOOL,
            array(
                'label'               => __( 'Assign Navigation To Template Part', 'hostinger-ai-assistant' ),
                'description'         => __( 'Assign a navigation to the first Navigation block of a template part by providing template_part_id and navigation_id.', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'template_part_id' => array(
                            'type'        => 'string',
                            'description' => __( 'Template part ID in the theme//slug format (e.g. twentytwentyfive//header), as returned by template-parts-search.', 'hostinger-ai-assistant' ),
                        ),
                        'navigation_id'    => array(
                            'type'        => 'integer',
                            'description' => __( 'Navigation (wp_navigation) post ID.', 'hostinger-ai-assistant' ),
                        ),
                    ),
                    'required'   => array( 'template_part_id', 'navigation_id' ),
                ),
                'execute_callback'    => function ( $input ) {
                    return $this->assign_navigation( is_array( $input ) ? $input : array() );
                },
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => $this->type,
                    ),
                    'annotations'  => array(
                        'title'           => 'Assign Navigation',
                        'readOnlyHint'    => false,
                        'destructiveHint' => false,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    private function set_navigation_ref( array $blocks, int $navigation_id ): ?array {
        foreach ( $blocks as $index => $block ) {
            if ( ( $block['blockName'] ?? '' ) === 'core/navigation' ) {
                $blocks[ $index ]['attrs']['ref'] = $navigation_id;
                $blocks[ $index ]['innerBlocks']  = array();
                $blocks[ $index ]['innerContent'] = array();
                $blocks[ $index ]['innerHTML']    = '';

                return $blocks;
            }

            if ( empty( $block['innerBlocks'] ) ) {
                continue;
            }

            $inner_blocks = $this->set_navigation_ref( $block['innerBlocks'], $navigation_id );

            if ( $inner_blocks !== null ) {
                $blocks[ $index ]['innerBlocks'] = $inner_blocks;

                return $blocks;
            }
        }

        return null;
    }
}
