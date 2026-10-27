<?php

if ( ! defined( 'ABSPATH' ) ) exit;


class UELM_ProviderCoreFrontUC_Elementor extends UELM_ProviderFront{
	
	private $objFiltersProcess;

	
	/**
	 *
	 * the constructor
	 */
	public function __construct(){
		
		HelperProviderCoreUC_EL::globalInit();
		
		//run front filters process
		
		$this->objFiltersProcess = new UniteCreatorFiltersProcess();
		$this->objFiltersProcess->initWPFrontFilters();
		
		
		/*
		$disableFilters = HelperProviderCoreUC_EL::getGeneralSetting("disable_autop_filters");
		$disableFilters = UniteFunctionsUC::strToBool($disableFilters);
		
		if($disableFilters == true)
			$this->disableWpFilters();
		*/
		
		parent::__construct();
						
	}

}

class_alias( UELM_ProviderCoreFrontUC_Elementor::class, 'UniteProviderCoreFrontUC_Elementor' );
