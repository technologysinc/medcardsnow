<?php
if ( ! defined( 'ABSPATH' ) ) exit;

defined('UNLIMITED_ELEMENTS_INC') or die;

class UELM_CreatorLayoutPreviewProvider extends UELM_CreatorLayoutPreview{


	/**
	 * constructor
	 */
	public function __construct(){

		$this->showHeader = true;
		
		parent::__construct();
				
		$this->display();
	}
	
}

class_alias( UELM_CreatorLayoutPreviewProvider::class, 'UniteCreatorLayoutPreviewProvider' );
