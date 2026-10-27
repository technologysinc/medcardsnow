<?php

namespace Envato_Template_Kit_Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Confirms that a stored "envato_tk_folder_name" value is one we generated ourselves.
 *
 * The only writer of this meta is Importer::install_template_kit_zip_to_db(), which always
 * stores md5( mt_rand() . NONCE_SALT ) - i.e. exactly 32 lowercase hex characters. Anything
 * else did not come from us, so it is not a folder we may read from or delete.
 *
 * @param mixed $folder_name The raw meta value.
 *
 * @return bool
 */
function envato_template_kit_import_is_valid_kit_folder_name( $folder_name ) {
	return is_string( $folder_name ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $folder_name );
}

/**
 * Resolves a kit folder name to a real, verified path inside the uploads template-kits directory.
 *
 * Returns null unless the folder name is one of ours AND the fully resolved path sits inside our
 * own upload folder. realpath() is used so the comparison is made against the final location on
 * disk rather than against the string that was passed in.
 *
 * @param mixed $folder_name The raw "envato_tk_folder_name" meta value.
 *
 * @return string|null Trailing-slashed absolute path, or null when it cannot be trusted.
 */
function envato_template_kit_import_get_kit_folder_path( $folder_name ) {
	if ( ! envato_template_kit_import_is_valid_kit_folder_name( $folder_name ) ) {
		return null;
	}

	$wp_upload_dir = wp_upload_dir();
	if ( empty( $wp_upload_dir['basedir'] ) ) {
		return null;
	}

	$base_path = trailingslashit( $wp_upload_dir['basedir'] ) . 'template-kits/';
	$real_base = realpath( $base_path );
	$real_path = realpath( $base_path . $folder_name );

	if ( ! $real_base || ! $real_path || ! is_dir( $real_path ) ) {
		return null;
	}

	// Compare separator-terminated so a sibling folder sharing our prefix is not treated as a match.
	if ( 0 !== strpos( $real_path . DIRECTORY_SEPARATOR, $real_base . DIRECTORY_SEPARATOR ) ) {
		return null;
	}

	return trailingslashit( $real_path );
}

/**
 * @param $template_kit_id
 *
 * @return bool|Builder_Elementor|Builder_Gutenberg|Builder_Elementor_Kit
 */
function envato_template_kit_import_get_builder( $template_kit_id ) {
	// Grab out the uploaded template kit from the CPT.
	$post = $template_kit_id ? get_post( $template_kit_id ) : false;
	if ( $post && CPT_Kits::get_instance()->cpt_slug === $post->post_type ) {
		// Confirmed that the required ID is in fact one of our uploaded template kits.
		$builder = get_post_meta( $post->ID, 'envato_tk_builder', true );
		if ( 'elementor' === $builder ) {
			$builder_class = new Builder_Elementor();
			$builder_class->load_kit( $post->ID );
			return $builder_class;
		} elseif ( 'gutenberg' === $builder ) {
			$builder_class = new Builder_Gutenberg();
			$builder_class->load_kit( $post->ID );
			return $builder_class;
		}elseif ( ENVATO_TEMPLATE_KIT_IMPORT_TYPE_ELEMENTOR === $builder ) {
			$builder_class = new Builder_Elementor_Kit();
			$builder_class->load_kit( $post->ID );
			return $builder_class;
		}
	}

	return false;
}
