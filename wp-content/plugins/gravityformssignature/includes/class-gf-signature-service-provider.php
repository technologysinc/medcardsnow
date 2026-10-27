<?php
/**
 * Service Provider for the Gravity Forms Signature Add-On.
 *
 * @package     Gravity_forms\Gravity_Forms_Signature
 *
 * @since 4.6.2
 */
namespace Gravity_forms\Gravity_Forms_Signature;

use Gravity_Forms\Gravity_Forms\GF_Service_Container;
use Gravity_Forms\Gravity_Forms\GF_Service_Provider;
use Gravity_Forms\Gravity_Forms\Config\GF_Config_Service_Provider;
use Gravity_forms\Gravity_Forms_Signature\Config\GF_Signature_Config;

/**
 * Class GF_Signature_Service_Provider
 *
 * Service Provider for the Gravity Forms Signature Add-On.
 */
class GF_Signature_Service_Provider extends GF_Service_Provider
{
	const SIGNATURE_CONFIG = 'gf_signature_config';

	public function register( GF_Service_Container $container ) {

		require_once plugin_dir_path( __FILE__ ) . 'config/class-gf-signature-config.php';

		$container->add(
			self::SIGNATURE_CONFIG,
			function () use ( $container ) {
				return new GF_Signature_Config( $container->get( GF_Config_Service_Provider::DATA_PARSER ) );
			}
		);
		$container->get( GF_Config_Service_Provider::CONFIG_COLLECTION )->add_config( $container->get( self::SIGNATURE_CONFIG ) );
	}
}
