<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

class InvalidLicenseKeyNotice extends NoticeAbstract {
	public function maybe_add( string $id, string $title, string $message, array $options = [] ) {
		$key_status  = UpdatesManager::instance()->get_field_value( 'key_status' );
		$license_key = UpdatesManager::instance()->get_field_value( 'license_key' );

		if ( 'expired' === $key_status ) {
			$title   = __( 'CheckoutWC license expired', 'checkout-wc' );
			$message = sprintf(
				/* translators: %1$s: URL to pricing page, %2$s: "Renew your license" link text, %3$s: Support email URL, %4$s: "contact support" link text */
				__( 'Premium features are off because your license has expired. <a href="%1$s" target="_blank">%2$s</a> to turn them back on. If you think this is a mistake, <a href="%3$s">%4$s</a>.', 'checkout-wc' ),
				'https://www.checkoutwc.com/pricing',
				__( 'Renew your license', 'checkout-wc' ),
				'mailto:support@checkoutwc.com?subject=Problem%20With%20License%20Expiration&body=License%20Key%3A%20' . $license_key,
				__( 'contact support', 'checkout-wc' )
			);
		}

		parent::maybe_add( $id, $title, $message, $options );
	}

	protected function should_add(): bool {
		$key_status  = UpdatesManager::instance()->get_field_value( 'key_status' );
		$license_key = UpdatesManager::instance()->get_field_value( 'license_key' );

		if ( ! empty( $_GET['page'] ) && 'cfw-settings' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( ! empty( $license_key ) && 'valid' === $key_status ) {
			return false;
		}

		// Not-activated keys get InactiveLicenseNotice instead.
		if ( ! empty( $license_key ) && in_array( $key_status, [ 'inactive', 'site_inactive' ], true ) ) {
			return false;
		}

		return true;
	}
}
