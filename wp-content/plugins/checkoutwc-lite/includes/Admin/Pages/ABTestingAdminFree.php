<?php

namespace Objectiv\Plugins\Checkout\Admin\Pages;

/**
 * @link checkoutwc.com
 * @since 11.1.3
 * @package Objectiv\Plugins\Checkout\Admin\Pages
 */
class ABTestingAdminFree extends PageAbstract {
	/**
	 * ABTestingAdminFree constructor.
	 */
	public function __construct() {
		parent::__construct( __( 'A/B Testing', 'checkout-wc' ) . ' <span class="cfw-badge cfw-premium-badge"></span>', 'cfw_manage_ab_tests', 'ab_testing' );
	}

	/**
	 * Output the locked A/B Testing teaser page.
	 *
	 * @return void
	 */
	public function output() {
		?>
		<div>
			<div class="md:grid md:grid-cols-3 md:gap-6">
				<div class="md:col-span-1">
					<div class="px-4 sm:px-0">
						<h3 class="text-lg font-medium leading-6 text-gray-900"><?php esc_html_e( 'A/B Testing', 'checkout-wc' ); ?></h3>
						<p class="mt-1 text-sm text-gray-600"><?php esc_html_e( 'Configure A/B Testing settings.', 'checkout-wc' ); ?></p>
					</div>
				</div>
				<div class="mt-5 md:mt-0 md:col-span-2" id="ab-testing_content">
					<div></div>
					<div class="shadow sm:rounded-md">
						<div class="cfw-admin-section-component-content px-4 py-5 bg-white space-y-6 sm:p-6">
							<div class="flex items-center space-x-4"><button class="bg-lime-500 relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2" id="headlessui-switch-:r0:" role="switch" type="button" tabindex="0" aria-checked="true" data-headlessui-state="checked" aria-labelledby="headlessui-label-:r1:" aria-describedby="headlessui-description-:r2:"><span class="sr-only">Use setting</span><span aria-hidden="true" class="translate-x-[1.75rem] pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span></button><span class="flex flex-grow flex-col"><span class="text-sm font-medium leading-6 text-gray-900" id="headlessui-label-:r1:"><?php esc_html_e( 'Enable A/B Testing', 'checkout-wc' ); ?></span><span class="text-sm text-gray-500" id="headlessui-description-:r2:"><?php esc_html_e( 'Allow A/B tests to be created and applied on the checkout page.', 'checkout-wc' ); ?></span></span></div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		$this->premium_lock_html( 'pro' );
	}
}
