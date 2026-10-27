<?php
if ( ! defined( 'ABSPATH' ) ) exit;

defined('UNLIMITED_ELEMENTS_INC') or die;

class UELM_AddonLibraryViewLayoutProvider extends UELM_AddonLibraryViewLayout{
	
	
	/**
	 * add toolbar
	 */
	function __construct(){
		parent::__construct();
		
		$this->shortcodeWrappers = "wp";
		$this->shortcode = "blox_layout";
				
		$this->display();
	}
	
	
}

class_alias( UELM_AddonLibraryViewLayoutProvider::class, 'AddonLibraryViewLayoutProvider' );
