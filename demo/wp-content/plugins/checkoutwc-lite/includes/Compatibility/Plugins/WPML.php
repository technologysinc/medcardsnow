<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Compatibility\Traits\TranslatesStorePoliciesTrait;

class WPML extends CompatibilityAbstract {
	use TranslatesStorePoliciesTrait;

	public function is_available(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' );
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
		$translated_id = cfw_apply_filters( 'wpml_object_id', $page_id, 'page', true );

		return empty( $translated_id ) ? $page_id : (int) $translated_id;
	}

	public function run() {
		add_filter( 'cfw_acr_cart_meta', [ $this, 'add_site_language_meta' ], 10, 1 );
		add_action( 'cfw_acr_handle_meta', [ $this, 'set_site_language' ], 10, 1 );
	}

	public function add_site_language_meta( $meta ) {
		$language = cfw_apply_filters( 'wpml_current_language', null );

		cfw_debug_log( 'CheckoutWC WPML: Current language: ' . $language );

		if ( ! $language ) {
			return $meta;
		}

		$meta['wpml_site_language'] = $language;

		return $meta;
	}

	public function set_site_language( $meta ) {
		global $sitepress;

		if ( ! isset( $meta['wpml_site_language'] ) ) {
			cfw_debug_log( 'CheckoutWC WPML: Cannot set language (no wpml_site_language meta)' );
			return;
		}

		$language = $meta['wpml_site_language'];

		if ( empty( $language ) ) {
			cfw_debug_log( 'CheckoutWC WPML: Cannot set language (empty language)' );
			return;
		}

		if ( ! method_exists( $sitepress, 'switch_lang' ) ) {
			cfw_debug_log( 'CheckoutWC WPML: Cannot set language (no switch_lang function)' );
			return;
		}

		cfw_debug_log( 'CheckoutWC WPML: Before set current language: ' . $language );

		$sitepress->switch_lang( $language );
	}
}
