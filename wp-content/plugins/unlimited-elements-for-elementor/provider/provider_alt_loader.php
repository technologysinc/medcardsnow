<?php


/**
 * return if addon creator plugin exists and active
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function uelm_isAddonLibraryPluginExists(){
	
	$alPlugin = "addon-library/addonlibrary.php";
	$alPlugin2 = "unlimited-addons-for-wpbakery-page-builder/unlimited_addons.php";
	
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	$arrPlugins = get_plugins();
	
	if(isset($arrPlugins[$alPlugin]) == true){
		$isActive = is_plugin_active($alPlugin);
		
		return($isActive);
	}
	
	if(isset($arrPlugins[$alPlugin2]) == true)
		return(true);
		
	$isActive = is_plugin_active($alPlugin);
	
	return(false);
}


if(uelm_isAddonLibraryPluginExists()){
	
	require_once dirname(__FILE__)."/views/compatability_message.php";
	
}else{

	
	try{
		
		require_once $uelm_currentFolder.'/includes.php';
		
		HelperUC::validatePluginStartup();
		
		require_once  GlobalsUC::$pathProvider."core/provider_main_file.php";
				
	}catch(Exception $e){
		
		$uelm_ucStandAloneErrorMessage = $e->getMessage();
		$uelm_filePathViewStandAlone = dirname(__FILE__)."/views/stand_alone_broken_error.php";
		
		require $uelm_filePathViewStandAlone;	
		
	}
	
}

