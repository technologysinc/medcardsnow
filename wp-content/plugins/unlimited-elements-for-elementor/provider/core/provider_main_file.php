<?php
if ( ! defined( 'ABSPATH' ) ) exit;

try{
	
	//-------------------------------------------------------------
	
	//load core plugins
	
	$uelm_pathCorePlugins = dirname(__FILE__)."/";
	
	$uelm_pathUnlimitedElementsPlugin = $uelm_pathCorePlugins."unlimited_elements/plugin.php";
		require_once $uelm_pathUnlimitedElementsPlugin;
	
	$uelm_pathCreateAddonsPlugin = $uelm_pathCorePlugins."create_addons/plugin.php";
		require_once $uelm_pathCreateAddonsPlugin;
	
	if(is_admin() || (defined('WP_CLI') && WP_CLI) ){		//load admin part
		
		do_action(GlobalsProviderUC::ACTION_RUN_ADMIN); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Constant value is unitecreator_run_admin.
		
		
	}else{		//load front part
		
		do_action(GlobalsProviderUC::ACTION_RUN_FRONT); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Constant value is unitecreator_run_front.
		
	}

	
	}catch(Exception $e){
		$uelm_message = $e->getMessage();
		$uelm_trace = $e->getTraceAsString();
		uelm_echo( "Error: <b>".$uelm_message."</b>");
		
		if(GlobalsUC::$SHOW_TRACE == true)
			dmp($uelm_trace);
	}