<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

	
	$uelm_filepathPickerObject = GlobalsUC::$pathViewsObjects."mappicker_view.class.php";
	require $uelm_filepathPickerObject;
	
	$uelm_objView = new UELM_CreatorMappickerView();
	
	
	$uelm_objView->putHtml();

