<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

$uelm_filepathAddonSettings = GlobalsUC::$pathSettings."addon_fields.xml";

UniteFunctionsUC::validateFilepath($uelm_filepathAddonSettings);

$uelm_generalSettings = new UniteCreatorSettings();

if(isset($this->objAddon)){
    $uelm_generalSettings->setCurrentAddon($this->objAddon);
}

$uelm_generalSettings->loadXMLFile($uelm_filepathAddonSettings);