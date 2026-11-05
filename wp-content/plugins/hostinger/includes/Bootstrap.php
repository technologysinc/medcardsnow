<?php

namespace Hostinger;


use Hostinger\Admin\PluginSettings;
use Hostinger\Admin\Jobs\JobInitializer;
use Hostinger\Admin\Proxy;
use Hostinger\Cdn\CacheClient;
use Hostinger\Cdn\CachePurger;
use Hostinger\Cdn\LiteSpeedHooks;
use Hostinger\Cdn\SoftwareIdResolver;
use Hostinger\LlmsTxtGenerator\LlmsTxtFileHelper;
use Hostinger\LlmsTxtGenerator\LlmsTxtHeadLink;
use Hostinger\LlmsTxtGenerator\LlmsTxtParser;
use Hostinger\LlmsTxtGenerator\LlmsTxtSummaryProvider;
use Hostinger\Rest\CacheRoutes;
use Hostinger\Rest\Routes;
use Hostinger\Rest\SettingsRoutes;
use Hostinger\Admin\Assets as AdminAssets;
use Hostinger\Admin\Hooks as AdminHooks;
use Hostinger\Admin\Menu as AdminMenu;
use Hostinger\Admin\Redirects as AdminRedirects;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;
use Hostinger\LlmsTxtGenerator\LlmsTxtGenerator;

defined( 'ABSPATH' ) || exit;

class Bootstrap {

    protected Loader $loader;
    protected Utils $utils;
    protected Config $config;

    public function __construct() {
        $this->loader = new Loader();
        $this->utils  = new Utils();
        $this->config = new Config();
    }

    public function run(): void {
        $this->load_dependencies();
        $this->set_locale();
        $this->loader->run();
    }

    private function load_dependencies(): void {
        $this->load_public_dependencies();

        if ( is_admin() ) {
            $this->load_admin_dependencies();
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            new Cli();
        }

        $plugin_settings = new PluginSettings();
        $plugin_options  = $plugin_settings->get_plugin_settings();

        if ( $plugin_options->get_maintenance_mode() ) {
            require_once HOSTINGER_ABSPATH . 'includes/ComingSoon.php';
        }
    }

    private function set_locale() {
        $plugin_i18n = new I18n();
        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }

    private function load_admin_dependencies(): void {
        new AdminAssets();
        new AdminHooks( $this->utils );
        new AdminMenu();
        new AdminRedirects();
        new AdminRedirects();
    }

    private function load_public_dependencies(): void {

        $client = new Client(
            'https://' . HOSTINGER_PROXY_API_HOST,
            array(
                Config::TOKEN_HEADER  => $this->utils->getApiToken(),
                Config::DOMAIN_HEADER => $this->utils->getHostInfo(),
            )
        );

        $llms_txt_parser = new LlmsTxtParser( new LlmsTxtSummaryProvider() );

        new JobInitializer( new Proxy( $client, $this->utils ), $llms_txt_parser );
        new Hooks();

        $plugin_settings      = new PluginSettings();
        $llms_txt_file_helper = new LlmsTxtFileHelper();

        $llms_txt_generator = new LlmsTxtGenerator( $plugin_settings, $llms_txt_file_helper, $llms_txt_parser );
        $this->loader->add_action( 'wp_loaded', $llms_txt_generator, 'maybe_regenerate_after_update' );

        $llms_txt_head_link = new LlmsTxtHeadLink( $plugin_settings, $llms_txt_file_helper );
        $this->loader->add_action( 'wp_head', $llms_txt_head_link, 'render' );

        $cache_purger = $this->build_cache_purger( $client );

        new LiteSpeedHooks( $cache_purger, $this->utils );

        $settings_routes = new SettingsRoutes( $plugin_settings );
        $cache_routes    = new CacheRoutes( $cache_purger );
        $routes          = new Routes( $settings_routes, $cache_routes );
        $routes->init();
    }

    private function build_cache_purger( Client $client ): CachePurger {
        return new CachePurger(
            new CacheClient( $client ),
            new SoftwareIdResolver( $client ),
            $this->utils
        );
    }
}
