<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


require HelperUC::getPathViewObject("addons_view.class");

$uelm_pathProviderAddons = GlobalsUC::$pathProvider."views/addons.php";

if(file_exists($uelm_pathProviderAddons) == true){
	require_once $uelm_pathProviderAddons;
	new UELM_CreatorAddonsViewProvider();
}
else{
	new UELM_CreatorAddonsView();
}

