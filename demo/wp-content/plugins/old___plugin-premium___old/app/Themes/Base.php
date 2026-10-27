<?php
namespace PLUGIN\Themes;

use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Base {
    
    public function setup() {
        if ( !current_user_can( 'switch_themes' ) ) return;
        // enqueue js and css
        add_action( 'admin_enqueue_scripts', array( $this, 'plugin_load_theme_admin_scripts' ) );
        add_action( 'admin_init', array( $this, 'plugin_theme_download' ) );
    }

    public function plugin_load_theme_admin_scripts() {
        global $pagenow;
        if ( $pagenow == 'themes.php' ) {
            wp_register_style( 'dtwap-admin-style', PLUGIN_PREMIUM_URL.'/assets/css/dtwap-admin.css', array(), PLUGIN_PREMIUM_VERSION );
            wp_enqueue_style( 'dtwap-admin-style' );
            wp_register_script( 'dtwap-admin-script', PLUGIN_PREMIUM_URL.'/assets/js/dtwap-admin.js', array('jquery'), PLUGIN_PREMIUM_VERSION, true );
            wp_enqueue_script( 'dtwap-admin-script' );
            wp_localize_script( 'dtwap-admin-script', 'dtwap', array( 'download_title' => __( 'Download', 'plugin'), 'dtwap_nonce'=> wp_create_nonce('dtwap-themes') ) );
        }
    }

    /**
     * Download theme zip
     * 
     * @package Download Theme
     * @since 1.0.0
     */
    public function plugin_theme_download(){
            if(!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'dtwap-themes')){
                return;
            }
	    $themes = wp_get_themes();
		if( is_user_logged_in() && current_user_can( 'switch_themes' ) && isset( $_GET['dtwap_download'] ) && !empty( $_GET['dtwap_download'] ) && array_key_exists( $_GET['dtwap_download'], $themes ) ) {
            $dtwap_download = sanitize_text_field( $_GET['dtwap_download'] );
            $folder_path    = get_theme_root( $dtwap_download ).'/'.$dtwap_download;
            $root_path      = realpath( $folder_path );
            $zip = new ZipArchive();
            $zip->open( $folder_path.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE );
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root_path),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
		    foreach ( $files as $name => $file ) {
		        if ( !$file->isDir() ) {
		        	$file_path	   = $file->getRealPath();
		            $relative_path = substr( $file_path, strlen( $root_path ) + 1 );
		            $zip->addFile( $file_path, $relative_path );
		        }
		    }		
		    $zip->close();		
		    // Download Zip
		    $zip_file = $folder_path.'.zip';
		    if ( file_exists( $zip_file ) ) {
                header( 'Content-Description: File Transfer' );
                header( 'Content-Type: application/octet-stream' );
                header( 'Content-Disposition: attachment; filename="'.basename($zip_file).'"' );
                header( 'Expires: 0' );
                header( 'Cache-Control: must-revalidate' );
                header( 'Pragma: public' );
                header( 'Content-Length: ' . filesize($zip_file) );
                header( 'Set-Cookie:fileLoading=true' );
                readfile( $zip_file );
                unlink( $zip_file );
                exit;
            }
        }	
    }
}