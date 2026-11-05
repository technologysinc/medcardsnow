<?php

namespace Hostinger\AiAssistant\AiSuite;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AiSuitePage {
    public const MENU_SLUG  = 'hostinger-ai-suite';
    public const CAPABILITY = 'manage_options';
    public const MOUNT_ID   = 'hostinger-ai-suite-app';

    /**
     * A parent slug that is never registered keeps the page reachable via admin.php?page=… but out of the menu.
     */
    public const HIDDEN_PARENT_SLUG = 'hts';

    public function init(): void {
        add_action( 'admin_menu', array( $this, 'register_page' ) );
    }

    public function register_page(): void {
        add_submenu_page(
            self::HIDDEN_PARENT_SLUG,
            __( 'AI Suite', 'hostinger-ai-assistant' ),
            __( 'AI Suite', 'hostinger-ai-assistant' ),
            self::CAPABILITY,
            self::MENU_SLUG,
            array( $this, 'render' )
        );
    }

    public function render(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'hostinger-ai-assistant' ), '', array( 'response' => 403 ) );
        }

        echo '<div id="' . esc_attr( self::MOUNT_ID ) . '"></div>';
    }

    public function is_current_page(): bool {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        return $page === self::MENU_SLUG;
    }
}
