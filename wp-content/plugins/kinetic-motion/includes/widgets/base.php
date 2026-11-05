<?php
/**
 * Shared base for Kinetic Motion widgets.
 */

defined( 'ABSPATH' ) || exit;

abstract class KMO_Widget_Base extends \Elementor\Widget_Base {

	public function get_categories() {
		return array( 'kinetic-motion' );
	}

	public function get_script_depends() {
		return array( 'kmo-motion' );
	}

	public function get_style_depends() {
		return array( 'kmo-frontend' );
	}

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Settings for render(), with safe fallbacks. Elementor leaves out settings whose
	 * section/condition is inactive, and style-only controls on the front end.
	 */
	protected function settings( array $defaults ) {
		$s = $this->get_settings_for_display();
		foreach ( $defaults as $k => $v ) {
			if ( ! isset( $s[ $k ] ) || null === $s[ $k ] ) {
				$s[ $k ] = $v;
			}
		}
		return $s;
	}

	/**
	 * Slider value helper.
	 */
	protected function size( $settings, $key, $default ) {
		if ( isset( $settings[ $key ]['size'] ) && '' !== $settings[ $key ]['size'] ) {
			return (float) $settings[ $key ]['size'];
		}
		return $default;
	}

	/**
	 * Print the JSON config the front-end script reads.
	 */
	protected function data_attr( array $data ) {
		return ' data-kmo="' . esc_attr( wp_json_encode( $data ) ) . '"';
	}

	protected function ease_options() {
		return KMO_Extension::eases();
	}
}
