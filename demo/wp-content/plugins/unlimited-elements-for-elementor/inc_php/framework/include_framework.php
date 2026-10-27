<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

$uelm_folderIncludes = dirname(__FILE__)."/";
$uelm_folderCreatorIncludes = $uelm_folderIncludes."../";
$uelm_folderProvider = $uelm_folderIncludes."../../provider/";

//include provider classes
require_once $uelm_folderIncludes . 'functions.php';
require_once $uelm_folderIncludes . 'functions.class.php';
require_once $uelm_folderIncludes . 'html_output_base.class.php';

require_once $uelm_folderProvider."include_provider.php";

require_once $uelm_folderIncludes . 'http/includes.php';
require_once $uelm_folderIncludes . 'db.class.php';
require_once $uelm_folderIncludes . 'params_manager.class.php';
require_once $uelm_folderIncludes . 'settings.class.php';
require_once $uelm_folderIncludes . 'cssparser.class.php';
require_once $uelm_folderIncludes . 'settings_advances.class.php';
require_once $uelm_folderIncludes . 'settings_output.class.php';
require_once $uelm_folderProvider . 'provider_settings_output.class.php';
require_once $uelm_folderCreatorIncludes	. 'unitecreator_settings_output.class.php';

require_once $uelm_folderIncludes . 'settings_output_wide.class.php';
require_once $uelm_folderIncludes . 'settings_output_inline.class.php';
require_once $uelm_folderIncludes . 'settings_output_sidebar.class.php';

require_once $uelm_folderIncludes . 'image_proccess.class.php';
require_once $uelm_folderIncludes . 'zip.class.php';

require_once $uelm_folderIncludes . 'base_admin.class.php';

require_once $uelm_folderIncludes . 'elements_base.class.php';
require_once $uelm_folderIncludes . 'base_output.class.php';
require_once $uelm_folderIncludes . 'helper_base.class.php';
require_once $uelm_folderIncludes . 'table.class.php';
require_once $uelm_folderIncludes . 'font_manager.class.php';
require_once $uelm_folderIncludes . 'services.class.php';

//include composer - twig
$uelm_isTwigExists = interface_exists("Twig\\Loader\\LoaderInterface");

if($uelm_isTwigExists == false){
	require $uelm_folderIncludes."../../vendor/autoload.php";
}
