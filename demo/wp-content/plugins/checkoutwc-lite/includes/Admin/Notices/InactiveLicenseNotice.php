<?php

namespace Objectiv\Plugins\Checkout\Admin\Notices;

use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

class InactiveLicenseNotice extends NoticeAbstract {
	public function add( string $url ) {
		$message = sprintf(
			/* translators: %1$s: URL to activation page, %2$s: "Activate your license" link text, %3$s: Pricing URL, %4$s: "buy a license" link text */
			__( 'Premium features are off because your license isn\'t activated on this site. <a href="%1$s">%2$s</a> or <a href="%3$s" target="_blank">%4$s</a> for this site.', 'checkout-wc' ),
			$url,
			__( 'Activate your license', 'checkout-wc' ),
			'https://www.checkoutwc.com/pricing',
			__( 'buy a license', 'checkout-wc' )
		);

		parent::maybe_add(
			'cfw_inactive_license',
			__( 'CheckoutWC license not activated', 'checkout-wc' ),
			$message,
			[
				'type'        => 'error',
				'dismissible' => false,
			]
		);
	}

	protected function should_add(): bool {
		$key_status  = UpdatesManager::instance()->get_field_value( 'key_status' );
		$license_key = UpdatesManager::instance()->get_field_value( 'license_key' );

		if ( empty( $license_key ) || ! in_array( $key_status, [ 'inactive', 'site_inactive' ], true ) ) {
			return false;
		}

		return true;
	}
}
