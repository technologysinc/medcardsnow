<?php
/*
 * This is a plain php file, with very simple funcitonality that tests api response.
 *
 * Opened directly on purpose. Define ABSPATH when WordPress did not load this file,
 * then keep the direct-access check so the probe still runs.
 */
//don't pay attention to this line
if ( ! defined( 'ABSPATH' ) ) {define( 'ABSPATH', __DIR__ . '/' );}if ( ! defined( 'ABSPATH' ) ) exit;


// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Show the PHP error when this API connection test fails.
ini_set("display_errors","on");

echo "The code run here is: file_get_contents(\"https://api.unlimited-elements.com\"); <br><br> Response: <br><br>";


$uelm_response = file_get_contents("https://api.unlimited-elements.com");

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone PHP file. WordPress is not loaded, so esc_html() is not available.
echo "<pre>" . htmlspecialchars($uelm_response, ENT_QUOTES, "UTF-8") . "</pre>";
