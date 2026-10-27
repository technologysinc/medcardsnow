<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Plugins;

use Objectiv\Plugins\Checkout\Compatibility\CompatibilityAbstract;

class YITHDeliveryDate extends CompatibilityAbstract {
	public function is_available(): bool {
		return class_exists( '\\YITH_Delivery_Date_Shipping_Manager' );
	}

	public function run() {
		$YITH_Delivery_Date_Shipping_Manager = \YITH_Delivery_Date_Shipping_Manager::get_instance();

		add_action( 'cfw_after_shipping_packages', [ $YITH_Delivery_Date_Shipping_Manager, 'print_delivery_from' ], 16 );
	}

	public function typescript_class_and_params( array $compatibility ): array {
		$compatibility[] = [
			'class'  => 'YITHDeliveryDate',
			'params' => [],
		];

		return $compatibility;
	}
}
