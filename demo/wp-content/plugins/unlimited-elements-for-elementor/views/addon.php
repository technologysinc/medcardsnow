<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

require_once GlobalsUC::$pathViewsObjects."addon_view.class.php";

$uelm_pathProviderAddon = GlobalsUC::$pathProvider."views/addon.php";

if(file_exists($uelm_pathProviderAddon) == true){
	require_once $uelm_pathProviderAddon;
	$uelm_objAddonView = new UELM_CreatorAddonViewProvider();
}
else{
	$uelm_objAddonView = new UELM_CreatorAddonView();
}

$uelm_objAddonView->runView();
