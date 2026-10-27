<?php

/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved.
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class UELM_EmailTestView{

	/**
	 * Displays the view.
	 *
	 * @return void
	 */
	public function display(){

		$this->displayHeader();

		?>
		<form method="post">
			<?php $this->displayHiddenFields(); ?>
			<?php $this->displayFormFields(); ?>
		</form>
		<?php


		$this->displayFooter();
	}

	/**
	 * Display the header.
	 *
	 * @return void
	 */
	private function displayHeader(){

		$headerTitle = __("Email Test", "unlimited-elements-for-elementor");

		require HelperUC::getPathTemplate("header");
	}

	/**
	 * Display the hidden fields.
	 *
	 * @return void
	 */
	private function displayHiddenFields(){

		$page = isset($_REQUEST["page"]) ? sanitize_key(wp_unslash($_REQUEST["page"])) : ""; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen page query arg, copied into a hidden field.

		echo '<input type="hidden" name="page" value="' . esc_attr($page) . '" />';

		if(empty($_REQUEST["view"]) === false) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen view query arg, copied into a hidden field.
			echo '<input type="hidden" name="view" value="' . esc_attr(sanitize_key(wp_unslash($_REQUEST["view"]))) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen view query arg, copied into a hidden field.
	}

	/**
	 * Display the form fields.
	 *
	 * @return void
	 */
	private function displayFormFields(){

		$email = UniteFunctionsUC::getPostVariable("email", "", UniteFunctionsUC::SANITIZE_TEXT_FIELD);
		$user = wp_get_current_user();

		?>
		<div>
			<label for="email">
				<?php esc_html_e("Send To", "unlimited-elements-for-elementor"); ?>
			</label>
			<input
				id="email"
				type="email"
				name="email"
				placeholder="user@example.com"
				value="<?php echo esc_attr($email ?: $user->user_email); ?>"
			/>
			<?php submit_button(__("Send Email", "unlimited-elements-for-elementor"), "", "", false); ?>
		</div>
		<?php

		if(empty($email) === true)
			return;

		try{
			$validEmail = UniteFunctionsUC::isEmailValid($email);

			if($validEmail === false)
				UniteFunctionsUC::throwError(__("Invalid email address.", "unlimited-elements-for-elementor"));

			$subject = __("Unlimited Elements Test Email", "unlimited-elements-for-elementor");
			$message = __("Congratulations, the test email has been successfully sent.", "unlimited-elements-for-elementor");

			$emailSent = wp_mail($email, $subject, $message);

			if($emailSent === false)
				UniteFunctionsUC::throwError(__("Unable to send the test email.", "unlimited-elements-for-elementor"));

			?>
			<div style="color: green; margin-top: 5px;">
				<?php esc_html_e("Test email has been successfully sent.", "unlimited-elements-for-elementor"); ?>
			</div>
			<?php
		}catch(Exception $exception){
			?>
			<div style="color: red; margin-top: 5px;">
				<?php 
				// translators: %s is a string
				echo esc_attr(sprintf(__("Error: %s", "unlimited-elements-for-elementor"), $exception->getMessage())); 
				?>
			</div>
			<?php
		}
	}

	/**
	 * Display the footer.
	 *
	 * @return void
	 */
	private function displayFooter(){

		$url = HelperUC::getViewUrl(GlobalsUnlimitedElements::VIEW_SETTINGS_ELEMENTOR, "#tab=forms");

		?>
		<div style="margin-top: 20px;">
			<a class="button" href="<?php echo esc_url($url); ?>">
				<?php echo esc_html__("Back to Settings", "unlimited-elements-for-elementor"); ?>
			</a>
		</div>
		<?php
	}

}

$uelm_emailTest = new UELM_EmailTestView();
$uelm_emailTest->display();

class_alias( UELM_EmailTestView::class, 'UCEmailTestView' );
