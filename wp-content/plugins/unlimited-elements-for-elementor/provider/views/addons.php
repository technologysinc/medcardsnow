<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// no direct access
defined('UNLIMITED_ELEMENTS_INC') or die;

class UELM_CreatorAddonsViewProvider extends UELM_CreatorAddonsView{
	
	protected $showButtons = true;
	protected $showHeader = false;
	
}

class_alias( UELM_CreatorAddonsViewProvider::class, 'UniteCreatorAddonsViewProvider' );
