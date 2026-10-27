<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Model\AlternativePlugin;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;
use WC_Stripe_Feature_Flags;
use WC_Stripe_Helper;

class Stripe extends CompatibilityAbstract {

	/**
	 * Whether the Stripe gateway is present and has actually loaded.
	 *
	 * WC_STRIPE_VERSION is defined at file scope, before Stripe checks its own WooCommerce version
	 * floor, so it stays defined even when the gateway bails out and loads none of its classes.
	 * The class check is what tells the two apart.
	 *
	 * Stripe loads its classes on plugins_loaded at priority 10, so this only gives a meaningful
	 * answer from that point on - callers running earlier must defer the check.
	 */
	public function is_available(): bool {
		return defined( 'WC_STRIPE_VERSION' ) && version_compare( WC_STRIPE_VERSION, '4.0.0' ) >= 0
			&& class_exists( 'WC_Stripe_Helper' );
	}

	public function pre_init() {
		/**
		 * Filters whether to override Stripe payment request button heights
		 *
		 * @since 4.3.3
		 *
		 * @param bool $allow Whether to ignore shipping phone requirement during payment requests
		 */
		if ( apply_filters( 'cfw_stripe_payment_requests_ignore_shipping_phone', true ) ) {
			add_action( 'wc_ajax_wc_stripe_create_order', [ $this, 'process_payment_request_ajax_checkout' ], 1 );
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				// Checked here rather than up front: pre_init() runs on plugins_loaded before Stripe
				// has loaded its classes, whereas this filter only runs on admin screens.
				if ( ! $this->is_available() ) {
					return $gateways;
				}

				$gateways[] = new DetectedPaymentGateway(
				'WooCommerce Stripe Gateway',
				GatewaySupport::FULLY_SUPPORTED,
				'Fully supported, but we recommend switching to <a class="text-blue-600 underline" target="_blank" href="https://wordpress.org/plugins/woo-stripe-payment/">Payment Plugins for Stripe WooCommerce</a>. Their plugin is designed to work with CheckoutWC so there are fewer unexpected issues with updates.',
				new AlternativePlugin(
					'woo-stripe-payment',
					'Payment Plugins for Stripe WooCommerce'
				)
				);

				return $gateways;
			}
		);
	}

	public function run() {
		// Check if express checkout should be shown
		if ( ! $this->should_show_express_checkout() ) {
			return;
		}

		$this->add_payment_request_buttons_ece();
	}

	public function add_payment_request_buttons_ece() {
		if ( ! class_exists( '\\WC_Stripe_Express_Checkout_Element' ) || ! cfw_is_checkout() ) {
			return;
		}

		$stripe_ece = \WC_Stripe_Express_Checkout_Element::instance();

		remove_action( 'woocommerce_checkout_before_customer_details', [ $stripe_ece, 'display_express_checkout_button_html' ], 1 );
		add_action( 'cfw_payment_request_buttons', [ $stripe_ece, 'display_express_checkout_button_html' ], 1 );
	}

	public function process_payment_request_ajax_checkout() {
		$payment_request_type = isset( $_POST['payment_request_type'] ) ? wc_clean( wp_unslash( $_POST['payment_request_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Disable shipping phone validation when using payment request
		if ( ! empty( $payment_request_type ) ) {
			add_filter(
				'woocommerce_checkout_fields',
				function ( $fields ) {
					if ( isset( $fields['shipping']['shipping_phone'] ) ) {
						$fields['shipping']['shipping_phone']['required'] = false;
						$fields['shipping']['shipping_phone']['validate'] = [];
					}

					if ( 'yes' === SettingsManager::instance()->get_setting( 'use_fullname_field' ) ) {
						unset( $fields['shipping']['shipping_full_name'] );
						unset( $fields['billing']['billing_full_name'] );
					}

					if ( 'yes' === SettingsManager::instance()->get_setting( 'enable_discreet_address_1_fields' ) ) {
						unset( $fields['shipping']['shipping_house_number'] );
						unset( $fields['billing']['billing_house_number'] );
						unset( $fields['shipping']['shipping_street_name'] );
						unset( $fields['billing']['billing_street_name'] );
					}

					return $fields;
				},
				1
			);
		}
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = [
			'class'  => 'Stripe',
			'params' => [],
		];

		return $compatibility;
	}

	/**
	 * Determine if express checkout should be shown on checkout page
	 *
	 * This method includes fallback logic for when Stripe's settings are empty or misconfigured
	 *
	 * @since 10.3.10
	 * @return bool
	 */
	private function should_show_express_checkout(): bool {
		$stripe_settings = WC_Stripe_Helper::get_stripe_settings();

		// Check the legacy payment_request_button_locations setting
		$prb_locations = $stripe_settings['payment_request_button_locations'] ?? [];

		// Check the new express_checkout_button_locations setting
		$ece_locations = $stripe_settings['express_checkout_button_locations'] ?? [];

		// If checkout is explicitly in either location array, respect that
		if ( in_array( 'checkout', $prb_locations, true ) || in_array( 'checkout', $ece_locations, true ) ) {
			return true;
		}

		/**
		 * Filter to force enable express checkout on checkout page
		 *
		 * Useful when Stripe settings are misconfigured or empty
		 *
		 * @since 10.3.10
		 * @param bool $force_enable Whether to force enable express checkout
		 */
		if ( apply_filters( 'cfw_stripe_force_checkout_express_checkout', false ) ) {
			return true;
		}

		// If either location array is not empty, settings have been configured - respect them
		if ( ! empty( $prb_locations ) || ! empty( $ece_locations ) ) {
			return false;
		}

		// Both arrays are empty - likely a new installation with default settings
		// Check if Express Checkout Element is available
		if ( ! class_exists( '\\WC_Stripe_Express_Checkout_Element' ) ) {
			return false;
		}

		// Check if ECE is enabled via feature flags. Guarded on the method rather than the class:
		// Stripe deprecated is_stripe_ece_enabled() in 10.0.0, where it always returns true, and
		// has it slated for removal - once it goes, treat ECE as enabled rather than fatalling.
		if (
			method_exists( 'WC_Stripe_Feature_Flags', 'is_stripe_ece_enabled' )
			&& ! WC_Stripe_Feature_Flags::is_stripe_ece_enabled()
		) {
			return false;
		}

		// ECE class exists and is enabled (or we can't check flags) with empty locations
		// Apply fallback to show on checkout (matching Stripe's intended defaults)
		return true;
	}

}
