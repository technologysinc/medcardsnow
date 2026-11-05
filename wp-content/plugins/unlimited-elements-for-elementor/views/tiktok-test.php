<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h1><?php esc_html_e("Unlimited Elements - TikTok Feed", "unlimited-elements-for-elementor"); ?></h1>

<br>

<?php

$objServices = new UniteServicesUC();
$objServices->includeTikTokAPI();

try{
	UELM_TikTokFeed::render(12);
}catch(Exception $e){
	HelperHtmlUC::outputException($e);
}
