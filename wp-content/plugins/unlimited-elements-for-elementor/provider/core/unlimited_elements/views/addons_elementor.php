<?php
if ( ! defined( 'ABSPATH' ) ) exit;

//no direct accees
defined ('UNLIMITED_ELEMENTS_INC') or die ('restricted aceess');

require HelperUC::getPathViewObject("addons_view.class");


class UELM_CreatorAddonsElementorView extends UELM_CreatorAddonsView{

	protected $showButtons = true;
	protected $showHeader = false;
	protected $pluginTitle = null;


	/**
	 * get header text
	 * @return unknown
	 */
	protected function getHeaderText(){

		$headerTitle = esc_html__("Manage Widgets for Elementor", "unlimited-elements-for-elementor");

		return($headerTitle);
	}


	/**
	 * addons view provider
	 */
	public function __construct(){

		$this->addonType = GlobalsUnlimitedElements::ADDONSTYPE_ELEMENTOR;
		$this->product = GlobalsUnlimitedElements::PLUGIN_NAME;
		$this->pluginTitle = GlobalsUnlimitedElements::$pluginTitleCurrent;
		$this->headerTextInner = __("Widgets", "unlimited-elements-for-elementor");
		
		if(GlobalsUnlimitedElements::$isGutenbergOnly == true)
			$this->headerTextInner = __("Gutenberg Blocks", "unlimited-elements-for-elementor");
		
		parent::__construct();
	}


}


new UELM_CreatorAddonsElementorView();

class_alias( UELM_CreatorAddonsElementorView::class, 'UniteCreatorAddonsElementorView' );
