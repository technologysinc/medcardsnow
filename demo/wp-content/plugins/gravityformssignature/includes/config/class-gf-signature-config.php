<?php
namespace Gravity_forms\Gravity_Forms_Signature\Config;

use Gravity_Forms\Gravity_Forms\Config\GF_Config;
use Gravity_Forms\Gravity_Forms\GF_Service_Container;
use Gravity_Forms\Gravity_Forms\GF_Service_Provider;
use Gravity_Forms\Gravity_Forms\Config\GF_Config_Data_Parser;
class GF_Signature_Config extends GF_Config {
	protected $name               = 'gform_theme_config';
	protected $script_to_localize = 'gform_gravityforms_theme';

	/**
	 * Config data.
	 *
	 * @return array[]
	 */
	public function data() {


		if ( ! isset( $this->args ) || ! rgar( $this->args, 'form_ids' ) ) {
			return array();
		}

		$addon = \GFSignature::get_instance();

		$data = array();
		foreach ( $this->args['form_ids'] as $form_id ) {
			$form = \GFAPI::get_form($form_id);

			if ( ! $addon::has_signature_field( $form ) ) {
				continue;
			}
			$data[ $form_id ] = array();

			// Add every instance of the signature field to the data array, including those nested within repeater fields.
			$add_signature_fields = function( $fields ) use ( &$add_signature_fields, &$data, $form_id, $form ) {
				foreach ( $fields as $field ) {
					if ( $field->type == 'signature' ) {
						$data[ $form_id ][ $field->id ] = $field->get_signaturepad_init_options( $form );
					} elseif ( $field->type == 'repeater' && ! empty( $field->fields ) ) {
						$add_signature_fields( $field->fields );
					}
				}
			};

			$add_signature_fields( $form['fields'] );
		}

		return array(
			'addon' => array(
				'signature' => $data,
			),
		);
	}

	/**
	 * Enable ajax loading for the "gform_theme_config/addon/stripe/elements" config path.
	 *
	 * @since 5.8.0
	 *
	 * @param string $config_path The full path to the config item when stored in the browser's window object, for example: "gform_theme_config/common/form/product_meta"
	 * @param array  $args        The args used to load the config data. This will be empty for generic config items. For form specific items will be in the format: array( 'form_ids' => array(123,222) ).
	 *
	 * @return bool Return true if the provided $config_path is the product_meta path. Return false otherwise.
	 */
	public function enable_ajax( $config_path, $args ) {
		if ( str_starts_with( $config_path, 'gform_theme_config/addon/signature' ) ) {
			return true;
		}
		return false;
	}
}
