<?php

namespace Hostinger\AiAssistant\AiSuite;

use Hostinger\AiAssistant\ContentWriter\ContentWriterOptions;
use Hostinger\AiAssistant\Hosting\HpanelUrlBuilder;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AiSuiteAssets {
    public const HANDLE           = 'hostinger_ai_suite';
    public const LOCALIZED_OBJECT = 'hostingerAiSuite';

    private const DASHBOARD_SECTION = 'website';

    private AiSuitePage $page;
    private AiSuiteTranslations $translations;
    private HpanelUrlBuilder $hpanel_url_builder;
    private ContentWriterOptions $content_writer_options;

    public function __construct(
        AiSuitePage $page,
        AiSuiteTranslations $translations,
        HpanelUrlBuilder $hpanel_url_builder,
        ContentWriterOptions $content_writer_options
    ) {
        $this->page                   = $page;
        $this->translations           = $translations;
        $this->hpanel_url_builder     = $hpanel_url_builder;
        $this->content_writer_options = $content_writer_options;
    }

    public function init(): void {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 2 );
    }

    public function enqueue_assets(): void {
        if ( ! $this->page->is_current_page() ) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE,
            HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/css/hostinger-ai-suite.min.css',
            array(),
            HOSTINGER_AI_PLUGIN_VERSION
        );
        wp_add_inline_style( self::HANDLE, '.notice { display: none !important; }' );

        wp_enqueue_script(
            self::HANDLE,
            HOSTINGER_AI_ASSISTANT_ASSETS_URL . '/js/hostinger-ai-suite.min.js',
            array(),
            HOSTINGER_AI_PLUGIN_VERSION,
            array( 'in_footer' => true )
        );
        wp_localize_script( self::HANDLE, self::LOCALIZED_OBJECT, $this->get_localized_data() );
    }

    public function add_module_type( string $tag, string $handle ): string {
        if ( $handle !== self::HANDLE ) {
            return $tag;
        }

        return str_replace( '<script ', '<script type="module" ', $tag );
    }

    public function get_localized_data(): array {
        return array(
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'rest_url'       => esc_url_raw( rest_url() ),
            'site_url'       => esc_url_raw( home_url() ),
            'dashboard_url'  => esc_url_raw( $this->hpanel_url_builder->build_login_url( array( 'section' => self::DASHBOARD_SECTION ) ) ),
            'plugin_version' => HOSTINGER_AI_PLUGIN_VERSION,
            'user_locale'    => get_user_locale(),
            'translations'   => $this->translations->get_values(),
            'content_writer' => array(
                'content_types' => $this->content_writer_options->get_content_types(),
            ),
        );
    }
}
