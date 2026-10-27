<?php

/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if(isset($uelm_headerTitle) == false && isset($headerTitle))
	$uelm_headerTitle = $headerTitle;

if(isset($uelm_headerTitle) == false)
	UniteFunctionsUC::throwError("header template error: header title variable not defined");

$uelm_headerPrefix = HelperUC::getText("addon_library");

if(!empty(GlobalsUC::$alterViewHeaderPrefix))
	$uelm_headerPrefix = GlobalsUC::$alterViewHeaderPrefix;

$uelm_adminPageTitle = $uelm_headerTitle . " - " . $uelm_headerPrefix;

UniteProviderFunctionsUC::setAdminPageTitle($uelm_adminPageTitle);

?>

<div class="unite_header_wrapper">
	<div class="title_line">
		<div class="title_line_text">
			<?php 
			uelm_echo( $uelm_headerTitle ); ?>
		</div>
		<?php if(isset($headerAddHtml)): ?>
			<div class="title_line_add_html"><?php echo esc_html($headerAddHtml); ?></div>
		<?php endif ?>
	</div>
	<div class="unite-clear"></div>
</div>

<?php HelperHtmlUC::putHtmlAdminNotices() ?>
