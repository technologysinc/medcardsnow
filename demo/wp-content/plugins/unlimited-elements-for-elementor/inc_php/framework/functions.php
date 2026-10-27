<?php
/**
 * @package Unlimited Elements
 * @author unlimited-elements.com
 * @copyright (C) 2021 Unlimited Elements, All Rights Reserved. 
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * */
if ( ! defined( 'ABSPATH' ) ) exit;

//---------------------------------------------------------------------------------------------------------------------	
	
	if(!function_exists("uelm_html_debug")){
		function uelm_html_debug($value){

			if(is_array($value) || is_object($value))
				$value = wp_json_encode($value, JSON_PRETTY_PRINT);

			return esc_html((string) $value);
		}
	}

	if(!function_exists("dmp")){
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Debug helper kept under its existing name.
		function dmp($str){

			$html = "<div align='left' style='direction:ltr;color:black;'><pre>" . uelm_html_debug($str) . "</pre></div>";

			if(class_exists("HelperHtmlUC")){
				HelperHtmlUC::putHtml($html);
				return;
			}

			echo wp_kses($html, array(
				"div" => array(
					"align" => true,
					"style" => true,
				),
				"pre" => array(),
			));
		}
	}
	
	if(!function_exists("dmpHtml")){
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Debug helper kept under its existing name.
		function dmpHtml($str){
			dmp($str);
		}
	}
	 
	if(!function_exists("dmpGet")){
		
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Debug helper kept under its existing name.
		function dmpGet($str){
			
			$html = "";
			
			$html .= "<div align='left' style='direction:ltr;color:black;'>";
			
			$html .= "<pre>";
			$html .= uelm_html_debug($str);
			$html .= "</pre>";
			$html .= "</div>";
			
			return($html);
		}
		
	}
	
	if(!function_exists("uelm_echo")){
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Kept under its existing name.
		function uelm_echo($str) {
			if( is_array($str) || is_object($str) ) {
				return;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo($str);
		}
	}

	if (!function_exists("uelm_date")) {
		
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Kept under its existing name.
		function uelm_date($format, $time = null) {
			
			if(empty($time))
				$time = time();
			
			$timezone = new DateTimeZone(date_default_timezone_get());

			$datetime = new DateTime('@' . $time);
			$datetime->setTimezone($timezone);			
			$formatted = $datetime->format($format);
			
			return $formatted;
		}
		
	}




	
	
	 
	
	
