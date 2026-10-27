<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Gateways;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;
use Objectiv\Plugins\Checkout\Model\DetectedPaymentGateway;
use Objectiv\Plugins\Checkout\Model\GatewaySupport;

class StripeWooCommerce extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\WC_Stripe_Manager' );
	}

	public function pre_init() {
		// Treat Payment Plugins for Stripe checkout REST calls (e.g. the Apple Pay express
		// form-validation request) as a checkout page so cfw_is_checkout() returns true.
		// Required for local pickup shipping field suppression during express checkout.
		add_filter( 'cfw_is_checkout', [ $this, 'is_checkout' ] );

		if ( ! $this->is_available() ) {
			return;
		}

		add_filter(
			'cfw_detected_gateways',
			function ( $gateways ) {
				$gateways[] = new DetectedPaymentGateway(
					'Payment Plugins for Stripe WooCommerce',
					GatewaySupport::FULLY_SUPPORTED
				);

				return $gateways;
			}
		);
	}

	/**
	 * Treat Payment Plugins for Stripe checkout REST requests as the checkout page.
	 *
	 * Payment Plugins routes its frontend REST calls through the wc_stripe_frontend_request
	 * AJAX proxy with a `path` parameter. The Apple Pay / express checkout flow validates and
	 * creates the order via /wc-stripe/v1/checkout/* on that proxy, where WooCommerce's
	 * is_checkout() is false. Without this, LocalPickup::pickup_is_selected() bails and the
	 * shipping fields are wrongly required for pickup orders.
	 *
	 * @param bool $is_checkout Whether we are on the checkout page.
	 * @return bool
	 */
	public function is_checkout( bool $is_checkout ): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['wc-ajax'] ) && 'wc_stripe_frontend_request' === $_GET['wc-ajax'] ) {
			$path = isset( $_GET['path'] ) ? wc_clean( wp_unslash( $_GET['path'] ) ) : '';

			if ( 0 === strpos( $path, '/wc-stripe/v1/checkout/' ) ) {
				return true;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $is_checkout;
	}

	public function run() {
		if ( ! class_exists( '\\WC_Stripe_Field_Manager' ) ) {
			return;
		}

		$payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		foreach ( $payment_gateways as $payment_gateway ) {
			if ( ! in_array( $payment_gateway->id, [ 'stripe_applepay', 'stripe_payment_request', 'stripe_googlepay', 'stripe_amazonpay' ], true ) ) {
				continue;
			}

			if ( ! isset( $payment_gateway->settings ) ) {
				continue;
			}

			$payment_gateway->settings = $this->adjust_button_height( $payment_gateway->settings );
		}

		// Remove theirs
		remove_action( 'woocommerce_checkout_before_customer_details', [ \WC_Stripe_Field_Manager::class, 'output_banner_checkout_fields' ] );

		// Add our own stripe requests
		add_action( 'cfw_payment_request_buttons', [ '\\WC_Stripe_Field_Manager', 'output_banner_checkout_fields' ], 1 );
	}
}
