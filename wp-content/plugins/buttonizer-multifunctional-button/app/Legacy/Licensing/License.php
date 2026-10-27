<?php

/*
* SOFTWARE LICENSE INFORMATION
*
* Copyright (c) 2017 Buttonizer, all rights reserved.
*
* This file is part of Buttonizer
*
* For detailed information regarding to the licensing of
* this software, please review the license.txt or visit:
* https://buttonizer.io/license/
*
*************************************************************************************

   ===========================================================================

   Dear reader,

   When you are here, it means you are searching something. Right?

   Something wrong? Error? Or just curious?

   If you want to get a free license, you are here on the wrong place. Please
   buy your license here:
   https://app.buttonizer.io/pricing/

   Questons? www.buttonizer.io

   Have you also checked our knowledge base or community?

   Buttonizer Community: https://r.buttonizer.io/support/community

   Buttonizer Knowledge base: https://r.buttonizer.io/support/knowledgebase

   Buttonizer support: https://r.buttonizer.io/support/tickets

   ===========================================================================

   For more information about the licensing and terms of conditions:

   Terms and conditions:   https://buttonizer.io/terms-conditions/

   License:                https://buttonizer.io/license/

   ===========================================================================
*/
namespace Buttonizer\Legacy\Licensing;

# No script kiddies
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Hi. We see what you're doing here
// Please leave this file intact as it is
// and read what is, and what is NOT allowed:
// https://buttonizer.io/terms-conditions/
class License {
    private $oButtonizer = 'null';

    public function init() {
        if ( $this->oButtonizer == 'null' ) {
            $freemius_path = BUTTONIZER_DIR . '/freemius/start.php';
            // Check if Freemius path is available (not available in the free version, where the SDK is stripped).
            // When absent, oButtonizer stays null and every caller of ButtonizerLicense()/oButtonizer must null-check it.
            if ( file_exists( $freemius_path ) ) {
                require_once $freemius_path;
                // Some data for Buttonizer to be freemium and paid.
                // We are paid so we can maintain the plugin
                // If you don't want to pay for the plugin, you can allways use the
                // SUPER COOL FREE VERSION
                // For more information about our source code:
                // https://leancoding.co/WDWIG5
                // To support the development of this plugin,
                // do NOT remove the code below
                $this->oButtonizer = fs_dynamic_init( [
                    'id'               => '1219',
                    'slug'             => 'buttonizer-multifunctional-button',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_fcd360d9c82b90a5e874e651ad733',
                    'is_premium'       => false,
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'has_affiliation'  => false,
                    'is_org_compliant' => true,
                    'menu'             => array(
                        'slug'       => 'Buttonizer',
                        'first-path' => 'admin.php?page=Buttonizer#/welcome-splash',
                        'support'    => false,
                        'contact'    => false,
                    ),
                    'is_live'          => true,
                ] );
            } else {
                $this->oButtonizer = null;
            }
        }
        return;
    }

    public function get() {
        return $this->oButtonizer;
    }

    private function getDaysLeft() {
        return round( 2000 / 8 - 243 );
    }

    // Default data
    private function initButtonizerData() {
    }

}
