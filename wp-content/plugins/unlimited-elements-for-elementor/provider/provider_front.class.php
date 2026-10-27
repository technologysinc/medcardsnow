<?php
/**
 * @package Unlimited Elements
* @author unlimited-elements.com
* @copyright (C) 2012 Unite CMS, All Rights Reserved.
* @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
* */

if ( ! defined( 'ABSPATH' ) ) exit;

class UELM_ProviderFront{
	
	private $t;
	const ACTION_FOOTER_SCRIPTS = "wp_print_footer_scripts";
	const ACTION_AFTER_SETUP_THEME = "after_setup_theme";
	const DEBUG_PAGE_QUERIES = false;
	
	
	/**
	 *
	 * add some wordpress action
	 */
	protected function addAction($action,$eventFunction,$priority=10){
		
		add_action( $action, array($this, $eventFunction), $priority );
	}
		
	/**
	 * add filter
	 */
	protected function addFilter($tag, $func, $priority = 10, $accepted_args = 1){
		
		add_filter($tag, array($this, $func), $priority, $accepted_args);
	}

	
	/**
	 * on script tag output. modify the output by options
	 */
	public function onScriptTagOutput($tag, $handle, $src){
		
		if(isset(GlobalsProviderUC::$arrJSHandlesModules[$handle])){
			
			
			//modify tag, change to module if needed
						
			$search = "type='text/javascript'";
			$replace = "type='module'";
			
			$tag = str_replace($search, $replace, $tag);
			
			$pos = strpos($tag, "type='module'");
			
			if($pos === false)
				$tag = str_replace('<script',"<script type='module'", $tag);
		}
				
		return($tag);
	}
	
	/**
	 * on template include
	 */
	public function onTemplateInclude($template){
		
		if(is_singular() == false)
			return($template);
		
		$renderTemplateID = UniteFunctionsUC::getGetVar("ucrendertemplate","",UniteFunctionsUC::SANITIZE_ID);
		
		if(empty($renderTemplateID))
			return($template);
					
		if(defined("ELEMENTOR_PATH") == false)
			return($template);
		
		$pathTemplate = HelperProviderCoreUC_EL::$pathCore."template.php";
		
		return($pathTemplate);
	}
	
	/**
	 * disable some services according to settings
	 */
	private function checkDisableServices(){

		//disable short pixel on render template
		$renderTemplateID = UniteFunctionsUC::getGetVar("ucrendertemplate","",UniteFunctionsUC::SANITIZE_ID);

		if(empty($renderTemplateID))
			return(false);
		
			
		//disable short pixel
					
		if(defined("SHORTPIXEL_AI_VERSION")){
			
			$isMultiple = UniteFunctionsUC::getGetVar("multiple","",UniteFunctionsUC::SANITIZE_TEXT_FIELD);
			$isMultiple = UniteFunctionsUC::strToBool($isMultiple);
			
			if(!defined("DONOTCDN") && $isMultiple == false)
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant name read by CDN plugins.
				define("DONOTCDN",true);
		}
		
		//disable doubly
		
		if(!defined("DISABLE_DOUBLY"))		
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant name read by the Doubly plugin.
			define("DISABLE_DOUBLY", true);
	}
	
	/**
	 * on plugins loaded
	 */
	public function onPluginsLoaded(){
		
		$this->checkDisableServices();
		
	}
	
	/**
	 * check schema
	 */
	public function onFooterCheckShowSchema(){
				
		$objSchema = new UniteCreatorSchema();
		$objSchema->showAddonSchema();
		
	}
	
	/**
	 *
	 * the constructor
	 */
	public function __construct(){
		
		$this->t = $this;
		
		do_action("uelm_addon_library_before_front_init");
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name kept for existing callbacks.
		do_action("addon_library_before_front_init");
		
		HelperProviderUC::globalInit();
	   	
		$this->addAction(self::ACTION_FOOTER_SCRIPTS, "onPrintFooterStyles", 1);
		$this->addAction(self::ACTION_FOOTER_SCRIPTS, "onPrintFooterScripts");
		
		$this->addAction( 'wp_head', 'onPrintHeadStyles' );
		
		//modify output <script> tag, add module to it
		$this->addFilter("script_loader_tag", 'onScriptTagOutput',10,3);
		
		//set elementor canvas accorging "GET" variable
		$this->addFilter("template_include", "onTemplateInclude",12);	//after elementor and woo
		
		$this->addAction( 'plugins_loaded', 'onPluginsLoaded' );
		
		$this->addAction( 'wp_footer', 'onFooterCheckShowSchema' );
		
		UniteFunctionsWPUC::onFrontInit();
				
	}
	
	
	/**
	 * on print head styles
	 */
	public function onPrintHeadStyles(){
	    
	  HelperProviderUC::outputCustomStyles();	  
	}
	
	/**
	 * show queries debug
	 */
	private function showDebugQueries(){
		
		HelperProviderUC::printDebugQueries(true);
		
	}
	
	/**
	 * print footer scripts
	 */
	public function onPrintFooterStyles(){
		
		if(self::DEBUG_PAGE_QUERIES == true)
			$this->showDebugQueries();
		
		HelperProviderUC::onPrintFooterScripts(true, "css");
		
	}

	/**
	 * print footer scripts
	 */
	public function onPrintFooterScripts(){
		
		HelperProviderUC::onPrintFooterScripts(true, "js");
		
	}
	
	
		
}

class_alias( UELM_ProviderFront::class, 'UniteProviderFrontUC' );
?>
