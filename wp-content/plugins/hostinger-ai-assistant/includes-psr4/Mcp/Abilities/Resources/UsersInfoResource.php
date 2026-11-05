<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class UsersInfoResource {
    private const RESOURCE_URI = 'wordpress://users-info';

    public function register(): void {
        wp_register_ability(
            'hostinger-ai/users-info',
            array(
                'label'               => __( 'Users Information', 'hostinger-ai-assistant' ),
                'description'         => __( 'Provides detailed information about registered WordPress users and their roles', 'hostinger-ai-assistant' ),
                'category'            => 'hostinger-ai-assistant',
                'execute_callback'    => array( $this, 'get_users_info' ),
                'permission_callback' => function () {
                    return current_user_can( 'list_users' );
                },
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'resource',
                        'uri'    => self::RESOURCE_URI,
                    ),
                ),
            )
        );
    }

    public function get_users_info(): array {
        $users       = get_users();
        $users_count = count_users();
        $users_data  = array();

        foreach ( $users as $user ) {
            $users_data[] = array(
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'email'        => $user->user_email,
                'display_name' => $user->display_name,
                'roles'        => $user->roles,
                'registered'   => $user->user_registered,
            );
        }

        $data = array(
            'total_users' => $users_count['total_users'],
            'users'       => $users_data,
            'roles'       => $users_count['avail_roles'],
        );

        return array(
            array(
                'uri'      => self::RESOURCE_URI,
                'mimeType' => 'application/json',
                'text'     => wp_json_encode( $data ),
            ),
        );
    }
}
