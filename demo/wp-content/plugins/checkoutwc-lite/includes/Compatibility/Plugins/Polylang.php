<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Compatibility\Traits\TranslatesStorePoliciesTrait;

class Polylang extends CompatibilityAbstract {
	use TranslatesStorePoliciesTrait;

	public function is_available(): bool {
		return defined( 'POLYLANG_VERSION' );
	}

	/**
	 * Register front end filters
	 */
	public function run_immediately() {
		add_filter( 'cfw_event_object', [ $this, 'translate_store_policies' ] );
	}

	/**
	 * Get the ID of the page in the active language
	 *
	 * @param int $page_id The configured page ID.
	 * @return int
	 */
	protected function get_translated_page_id( int $page_id ): int {
		if ( ! function_exists( 'pll_get_post' ) ) {
			return $page_id;
		}

		$translated_id = pll_get_post( $page_id );

		return empty( $translated_id ) ? $page_id : (int) $translated_id;
	}

	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter( 'cfw_restricted_post_types_count_args', [ $this, 'add_language_arg' ] );
		add_filter( 'cfw_restricted_post_types_publish_override', [ $this, 'maybe_allow_publish' ], 10, 2 );
	}

	public function add_site_language_meta( $meta ) {
		$language = function_exists( 'pll_current_language' ) ? pll_current_language() : null;

		cfw_debug_log( 'CheckoutWC Polylang: Current language: ' . $language );

		if ( ! $language ) {
			return $meta;
		}

		$meta['polylang_site_language'] = $language;

		return $meta;
	}

	public function set_site_language( $meta ) {
		if ( ! isset( $meta['polylang_site_language'] ) ) {
			return;
		}

		$language = $meta['polylang_site_language'];

		if ( empty( $language ) || ! function_exists( 'PLL' ) ) {
			return;
		}

		cfw_debug_log( 'CheckoutWC Polylang: Before set current language: ' . $language );

		PLL()->curlang = PLL()->model->get_language( $language );
	}

	public function run() {
		add_filter(
			'cfw_header_home_url',
			function ( $url ) {
				return function_exists( 'pll_home_url' ) ? pll_home_url() : $url;
			}
		);

		add_filter( 'cfw_acr_cart_meta', [ $this, 'add_site_language_meta' ], 10, 1 );
		add_action( 'cfw_acr_handle_meta', [ $this, 'set_site_language' ], 10, 1 );
	}

	public function run_on_thankyou() {
		$this->run();
	}

	public function add_language_arg( $args ) {
		$args['lang'] = pll_default_language();

		return $args;
	}

	public function maybe_allow_publish( $override, $post ): bool {
		if ( isset( $_GET['new_lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( ! isset( $post->ID ) ) {
			return $override;
		}

		if ( pll_get_post_language( $post->ID ) !== pll_default_language() ) {
			return true;
		}

		return $override;
	}
}
