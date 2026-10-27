<?php

/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$uelm_isProVersion = GlobalsUC::$isProVersion;

$uelm_showFreeVersion = UniteFunctionsUC::getGetVar("showfreeversion", "", UniteFunctionsUC::SANITIZE_TEXT_FIELD);
$uelm_showFreeVersion = UniteFunctionsUC::strToBool($uelm_showFreeVersion);

if($uelm_showFreeVersion === true)
	$uelm_isProVersion = false;


$uelm_logoImage = ($uelm_isProVersion === true)
	? GlobalsUC::$urlPluginImages . "logo_unlimited-pro.svg"
	: GlobalsUC::$urlPluginImages . "logo_unlimited.svg";

$uelm_logoUrl = GlobalsUC::URL_SITE;
$uelm_logoTitle = GlobalsUC::URL_SITE;

$uelm_isBFMode = GlobalsUnlimitedElements::$blackFridayMode;

if($uelm_isProVersion == true)
	$uelm_isBFMode = false;

$uelm_headAddClass = "";

$uelm_buyButtonText = __("Go Pro", "unlimited-elements-for-elementor");


// determine versions of plugin activated
$uelm_verFlags = HelperUC::getActivePluginVersions();
if($uelm_verFlags[GlobalsUC::VERSION_ELEMENTOR] && $uelm_verFlags[GlobalsUC::VERSION_GUTENBERG]) {
	$uelm_plugin_ver_name = 'for Elementor and Gutenberg';
	$uelm_logoImage = GlobalsUC::$urlPluginImages . 'logo_unlimited' . ($uelm_isProVersion ? '-pro' : '') . '-elementor-gutenberg-new.svg';
} elseif($uelm_verFlags[GlobalsUC::VERSION_ELEMENTOR]) {
	$uelm_plugin_ver_name = 'for Elementor';
	$uelm_logoImage = GlobalsUC::$urlPluginImages . 'logo_unlimited' . ($uelm_isProVersion ? '-pro' : '') . '-elementor-new.svg';
} else {
	$uelm_plugin_ver_name = 'for Gutenberg'; 
	$uelm_logoImage = GlobalsUC::$urlPluginImages . 'logo_unlimited' . ($uelm_isProVersion ? '-pro' : '') . '-gutenberg-new.svg';
}

if($uelm_isBFMode == true){
	
	$uelm_headAddClass = "ue-header__bf";
	$uelm_logoImage = GlobalsUC::$urlPluginImages."logo_unlimited-white.svg";
	
	$uelm_buyButtonText = __("Get Deal Now!", "unlimited-elements-for-elementor");
	
	$uelm_urlHeaderImage = GlobalsUC::$urlPluginImages."banners/bf-banner-header.png";
}


?>

<div class="ue-root ue-header <?php echo esc_attr($uelm_headAddClass) ?>">
	
	<?php if($uelm_isBFMode == true):?>
	<div class="ue-header__inner">
	<?php endif?>
	
	<div class="ue-header__brand">
		<a href="<?php echo esc_url($uelm_logoUrl); ?>" title="<?php echo esc_attr($uelm_logoTitle); ?>" class="ue-header__logo">
			<img class="ue-header-logo" src="<?php echo esc_url($uelm_logoImage); ?>" alt="" />
		</a>
		<?php if($uelm_isProVersion === true && GlobalsUC::$isProductActive == false): ?>
			<span class="ue-header__activate-msg">
				<?php
				$uelm_urlAccount = GlobalsUnlimitedElements::$urlAccount;
				$uelm_linkAccount = '<a href="' . esc_url($uelm_urlAccount) . '">' . esc_html__("here", "unlimited-elements-for-elementor") . '</a>';

				echo wp_kses(
					sprintf(
						/* translators: %s: link to the Unlimited Elements account page */
						__("Activate the license for pro version %s", "unlimited-elements-for-elementor"),
						$uelm_linkAccount
					),
					array(
						"a" => array(
							"href" => array(),
						),
					)
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<?php if($uelm_isBFMode == true):
		//Black Friday Inner Elements
	?>
		<img class="uc-bf-banner__header" src="<?php echo esc_url($uelm_urlHeaderImage)?>">
		
		<img class="uc-bf-banner__counter" src="<?php echo esc_url('http://i.countdownmail.com/2mgwko.gif')?>" style="display:inline-block!important;width:100%!important;max-width:272px!important;" border="0" alt="countdownmail.com"/>
			
	<?php endif?>

		
	<div class="ue-header-buttons">
		<a class="ue-btn ue-flex-center ue-view-demo-btn"
			href="<?php echo esc_url(GlobalsUC::URL_WIDGETS); ?>"
			target="_blank">
			<?php echo esc_html__("View Demos", "unlimited-elements-for-elementor"); ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
				<path d="M8.333 2.5v1.667H4.167v11.666h11.666v-4.166H17.5v5c0 .46-.373.833-.833.833H3.333a.833.833 0 0 1-.833-.833V3.333c0-.46.373-.833.833-.833h5Zm6.322 1.667h-3.822V2.5H17.5v6.667h-1.667V5.345L10 11.178 8.822 10l5.833-5.833Z" />
			</svg>
		</a>
		<?php if($uelm_isProVersion === false): ?>
			<a class="ue-btn ue-flex-center ue-go-pro-btn" href="<?php echo esc_url(GlobalsUC::$url_buy_platform); ?>" target="_blank">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
					<path d="M19.048 5.952a.944.944 0 0 0-1.042-.133l-3.923 1.953-3.275-5.44a.946.946 0 0 0-1.62 0L5.913 7.775 1.992 5.822a.946.946 0 0 0-1.32 1.14l2.89 8.856a.624.624 0 0 0 .913.344C4.495 16.151 6.492 15 9.998 15s5.504 1.15 5.522 1.161a.625.625 0 0 0 .915-.343l2.89-8.853a.942.942 0 0 0-.277-1.013Zm-5.312 6.298a.625.625 0 0 1-.725.507 17.828 17.828 0 0 0-6.032 0 .624.624 0 0 1-.621-.974.625.625 0 0 1 .403-.258 19.09 19.09 0 0 1 6.468 0 .623.623 0 0 1 .51.725h-.003Z" />
				</svg>
				<?php 
				uelm_echo( $uelm_buyButtonText ); ?>
			</a>
		<?php endif; ?>
	</div>
	
	<?php if($uelm_isBFMode == true):?>
	</div> <!-- inner -->
	<?php endif?>

</div>
