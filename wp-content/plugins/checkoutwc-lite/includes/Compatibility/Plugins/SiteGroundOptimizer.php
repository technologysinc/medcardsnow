<?php
namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use WP_Dependencies;

/**
 * SiteGround Speed Optimizer compatibility.
 *
 * Speed Optimizer names every minified copy after the asset's registered handle, not its source file:
 * styles land in `dirname( source )/{$handle}.min.css` and scripts in a single site-wide
 * `uploads/siteground-optimizer-assets/{$handle}.min.js`. Regeneration is gated only on the source and
 * the copy sharing an mtime, so whichever source is minified first owns that filename indefinitely.
 *
 * We deliberately reuse handles - `cfw_front` for all three page stylesheets, and WooCommerce's own
 * `woocommerce`, `selectWoo` and `wc-checkout` for our bundles - so several different sources compete for
 * one filename. A shop page can leave WooCommerce's `woocommerce.js` cached as `woocommerce.min.js`, which
 * the checkout then serves in place of our bundle: valid JavaScript, no console error, and a checkout that
 * never removes `cfw-preload` and so renders nothing below the breadcrumb.
 *
 * Excluding our handles keeps Speed Optimizer away from the collision. Our assets ship minified already, so
 * nothing is lost by opting out.
 *
 * @since 11.4.0
 */
class SiteGroundOptimizer extends CompatibilityAbstract {
	/**
	 * Registered script handles pointing at our own files, or null before they have been resolved.
	 *
	 * @var array|null
	 */
	private $script_handles = null;

	/**
	 * Registered style handles pointing at our own files, or null before they have been resolved.
	 *
	 * @var array|null
	 */
	private $style_handles = null;

	/**
	 * Determines whether Speed Optimizer is active.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return defined( 'SiteGround_Optimizer\VERSION' );
	}

	/**
	 * Registers the asset exclusion filters.
	 *
	 * @return void
	 */
	public function pre_init() {
		if ( ! $this->is_available() ) {
			return;
		}

		add_filter( 'sgo_css_minify_exclude', [ $this, 'exclude_styles' ] );
		add_filter( 'sgo_css_combine_exclude', [ $this, 'exclude_styles' ] );
		add_filter( 'sgo_js_minify_exclude', [ $this, 'exclude_scripts' ] );
		add_filter( 'sgo_javascript_combine_exclude', [ $this, 'exclude_scripts' ] );
		add_filter( 'sgo_js_async_exclude', [ $this, 'exclude_scripts' ] );
	}

	/**
	 * Adds our style handles to one of Speed Optimizer's exclusion lists.
	 *
	 * @param array $excluded Handles Speed Optimizer will skip.
	 *
	 * @return array
	 */
	public function exclude_styles( $excluded ): array {
		if ( is_null( $this->style_handles ) ) {
			$this->style_handles = $this->find_our_handles( wp_styles() );
		}

		return array_values( array_unique( array_merge( (array) $excluded, $this->style_handles ) ) );
	}

	/**
	 * Adds our script handles to one of Speed Optimizer's exclusion lists.
	 *
	 * @param array $excluded Handles Speed Optimizer will skip.
	 *
	 * @return array
	 */
	public function exclude_scripts( $excluded ): array {
		if ( is_null( $this->script_handles ) ) {
			$this->script_handles = $this->find_our_handles( wp_scripts() );
		}

		return array_values( array_unique( array_merge( (array) $excluded, $this->script_handles ) ) );
	}

	/**
	 * Collects the handles in a dependency queue whose source file belongs to this plugin.
	 *
	 * Matching on the source rather than a fixed handle list keeps WooCommerce's own scripts minified on
	 * non-checkout pages, where `woocommerce` and friends still point at WooCommerce's files, and covers both
	 * the Lite and paid directory slugs plus the active template's assets.
	 *
	 * @param WP_Dependencies|null $dependencies Queue to search.
	 *
	 * @return array
	 */
	private function find_our_handles( $dependencies ): array {
		if ( ! $dependencies instanceof WP_Dependencies ) {
			return [];
		}

		$plugin_path = wp_parse_url( CFW_PATH_URL_BASE, PHP_URL_PATH );

		if ( empty( $plugin_path ) ) {
			return [];
		}

		$handles = [];

		foreach ( $dependencies->registered as $handle => $dependency ) {
			if ( empty( $dependency->src ) || ! is_string( $dependency->src ) ) {
				continue;
			}

			if ( strpos( $dependency->src, $plugin_path ) !== false ) {
				$handles[] = $handle;
			}
		}

		return $handles;
	}
}
