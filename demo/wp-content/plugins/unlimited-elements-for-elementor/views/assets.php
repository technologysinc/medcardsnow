<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;


$uelm_headerTitle = esc_html__("Assets Manager", "unlimited-elements-for-elementor");
require HelperUC::getPathTemplate("header");


$uelm_objAssets = new UniteCreatorAssetsWork();
$uelm_objAssets->initByKey("assets_manager");

?>
<div class="uc-assets-manager-wrapper">

	<?php 
	$uelm_objAssets->putHTML();
	?>
	
</div>

<?php

	$uelm_script = 'jQuery(document).ready(function(){
	
		var objAdmin = new UniteCreatorAdmin();
		objAdmin.initAssetsManagerView();
	
	});';

	UniteProviderFunctionsUC::printCustomScript($uelm_script, true); 
