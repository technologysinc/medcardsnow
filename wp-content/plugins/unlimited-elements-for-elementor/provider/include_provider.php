<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$uelm_pathProvider = dirname(__FILE__) . '/';

require_once $uelm_pathProvider . 'provider_globals.class.php';
require_once $uelm_pathProvider . 'provider_db.class.php';
require_once $uelm_pathProvider . 'functions_wordpress.class.php';
require_once $uelm_pathProvider . 'provider_functions.class.php';
require_once $uelm_pathProvider . 'provider_helper.class.php';
require_once $uelm_pathProvider . 'provider_admin_plugin_base.class.php';
require_once $uelm_pathProvider . 'acf_integrate.class.php';
require_once $uelm_pathProvider . 'wpml_integrate.class.php';
require_once $uelm_pathProvider . 'pods_integrate.class.php';
require_once $uelm_pathProvider . 'toolset_integrate.class.php';
require_once $uelm_pathProvider . 'woocommerce_integrate.class.php';
require_once $uelm_pathProvider . 'admin_notices/includes.php';
