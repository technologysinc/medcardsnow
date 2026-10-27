<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


require HelperUC::getPathViewObject("settings_view.class");

class UELM_CreatorViewElementorSettings extends UELM_CreatorSettingsView{


	/**
	 * modify custom settings - function for override
	 */
	protected function modifyCustomSettings($objSettings){

		$objSettings = HelperProviderUC::modifyGeneralSettings_memoryLimit($objSettings);

		if(GlobalsUnlimitedElements::$enableGoogleAPI == false){
			
			$objSettings->hideSetting("google_connect_heading");
			$objSettings->hideSetting("google_connect_desc");
			$objSettings->hideSetting("google_connect_integration");
			
			$objSettings->hideSetting("google_api_heading");
			$objSettings->hideSetting("google_api_key");
		}

		if(GlobalsUnlimitedElements::$enableWeatherAPI == false){
			$objSettings->hideSetting("openweather_api_heading");
			$objSettings->hideSetting("openweather_api_key");
		}

		if(GlobalsUnlimitedElements::$enableCurrencyAPI == false){
			$objSettings->hideSetting("exchangerate_api_heading");
			$objSettings->hideSetting("exchangerate_api_key");
		}

		$isWpmlExists = UniteCreatorWpmlIntegrate::isWpmlExists();

		//enable wpml integration settings
		if($isWpmlExists == true){

			$objSettings->updateSettingProperty("wpml_heading", "hidden", "false");
			$objSettings->updateSettingProperty("wpml_button", "hidden", "false");

		}

		if(GlobalsUC::$isProVersion == false || GlobalsUnlimitedElements::$enableLimitProFunctionality == false)
			$objSettings->hideSetting("edit_pro_settings");

		$ablySetting = $objSettings->getSettingByName("ably_api_key");
		$ablyKey = UniteFunctionsUC::getVal($ablySetting, "value");

		//the mask is not the key; the real value stays in the database and is not printed
		if(empty($ablyKey) == false){
			$objSettings->updateSettingProperty("ably_api_key", "description", "A key is saved. The dots are only a marker. Create a key at <a href='https://ably.com/' target='_blank'>ably.com</a>. Paste a new key to replace it.");
			$objSettings->updateSettingProperty("ably_api_key", "value", "********");
		}

		return($objSettings);
	}


	/**
	 * constructor
	 */
	public function __construct(){

		$this->headerTitle = esc_html__("General Settings", "unlimited-elements-for-elementor");
		$this->isModeCustomSettings = true;
		$this->customSettingsXmlFile = HelperProviderCoreUC_EL::$filepathGeneralSettings;
		$this->customSettingsKey = "unlimited_elements_general_settings";


		//set settings
		$this->display();
	}


}

new UELM_CreatorViewElementorSettings();

class_alias( UELM_CreatorViewElementorSettings::class, 'UniteCreatorViewElementorSettings' );
