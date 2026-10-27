<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

use Objectiv\Plugins\Checkout\Features\GoogleAddressAutocomplete;
use Objectiv\Plugins\Checkout\Features\Turnstile;
use Objectiv\Plugins\Checkout\Managers\PlanManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\Managers\UpdatesManager;

/**
 * @link checkoutwc.com
 * @since 5.0.0
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class Integrations extends PageAbstract {
	public function __construct() {
		parent::__construct( __( 'Integrations', 'checkout-wc' ), 'cfw_manage_integrations', 'integrations' );
	}

	public function init() {
		$integrations = cfw_apply_filters( 'cfw_admin_integrations_checkbox_fields', [] );

		if ( ! defined( 'CFW_PREMIUM_PLAN_IDS' ) && count( $integrations ) === 0 ) {
			return;
		}

		parent::init();
	}

	public function output() {
		?>
		<div id="cfw-admin-pages-integrations"></div>
		<?php
	}

	public function maybe_set_script_data() {
		if ( ! $this->is_current_page() ) {
			return;
		}

		// The Lite build strips includes/Features, so these classes are absent there while this page still loads to host third party integration settings.
		$turnstile_available    = class_exists( Turnstile::class );
		$autocomplete_available = class_exists( GoogleAddressAutocomplete::class );

		$this->set_script_data(
			[
				'settings'     => [
					'google_places_api_key'     => SettingsManager::instance()->get_setting( 'google_places_api_key' ),
					// Normalized so the field shows the version the checkout will actually use, even if the setting is missing or unrecognized.
					// Lite falls back to the raw setting: the class that normalizes it, and the autocomplete it feeds, are both absent from that build.
					'google_places_api_version' => $autocomplete_available
						? GoogleAddressAutocomplete::normalize_api_version( SettingsManager::instance()->get_setting( 'google_places_api_version' ) )
						: SettingsManager::instance()->get_setting( 'google_places_api_version' ),

					// Turnstile settings
					'turnstile_enabled'                    => SettingsManager::instance()->get_setting( 'turnstile_enabled' ) === 'yes',
					'turnstile_site_key'                   => SettingsManager::instance()->get_setting( 'turnstile_site_key' ),
					'turnstile_secret_key'                 => SettingsManager::instance()->get_setting( 'turnstile_secret_key' ),
					'turnstile_checkout_enabled'           => SettingsManager::instance()->get_setting( 'turnstile_checkout_enabled' ) === 'yes',
					'turnstile_order_pay_enabled'          => SettingsManager::instance()->get_setting( 'turnstile_order_pay_enabled' ) === 'yes',
					'turnstile_login_enabled'              => SettingsManager::instance()->get_setting( 'turnstile_login_enabled' ) === 'yes',
					'turnstile_register_enabled'           => SettingsManager::instance()->get_setting( 'turnstile_register_enabled' ) === 'yes',
					'turnstile_position'                   => SettingsManager::instance()->get_setting( 'turnstile_position' ),
					'turnstile_theme'                      => SettingsManager::instance()->get_setting( 'turnstile_theme' ),
					'turnstile_size'                       => SettingsManager::instance()->get_setting( 'turnstile_size' ),
					'turnstile_guest_only'                 => SettingsManager::instance()->get_setting( 'turnstile_guest_only' ) === 'yes',
					'turnstile_has_conflict'               => $turnstile_available && Turnstile::has_conflict(),
					'turnstile_conflict_notice'            => $turnstile_available ? Turnstile::get_conflict_notice() : '',
				],
				/**
				 * Filters third party checkboxes here:  WP Admin > CheckoutWC > Advanced > Integrations
				 *
				 * Use to add additional integration settings
				 *
				 * @param array $integrations The integrations admin page class
				 * @since 9.0.0
				 */
				'integrations' => apply_filters( 'cfw_admin_integrations_checkbox_fields', [] ),
				'plan'         => $this->get_plan_data(),

				/*
				 * Deliberately outside 'settings': that array is spread into the form's initial values and posted
				 * back on save, which would rewrite this yes/no setting as a boolean. The key check only reads it.
				 */
				'google_key_features'  => [
					'autocomplete' => [
						'enabled'          => SettingsManager::instance()->get_setting( 'enable_address_autocomplete' ) === 'yes',
						'separate_fields'  => SettingsManager::instance()->get_setting( 'enable_discreet_address_1_fields' ) === 'yes',
						'fetchify_enabled' => SettingsManager::instance()->get_setting( 'enable_fetchify_address_autocomplete' ) === 'yes',
						'settings_url'     => add_query_arg( [ 'subpage' => 'checkout' ], AdminPagesRegistry::get( 'woocommerce_pages' )->get_url() ),
					],
					'map_embed'    => [
						'enabled'           => SettingsManager::instance()->get_setting( 'enable_map_embed' ) === 'yes',
						// The map setting is only offered once the thank you page template is on, so without that there is nothing for a merchant to switch on.
						'thank_you_enabled' => SettingsManager::instance()->get_setting( 'enable_thank_you_page' ) === 'yes',
						'settings_url'      => add_query_arg( [ 'subpage' => 'thankyou' ], AdminPagesRegistry::get( 'woocommerce_pages' )->get_url() ),
					],
				],
			]
		);
	}
}
