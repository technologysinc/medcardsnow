<?php
/**
 * Template Kit Import:
 *
 * This starts things up. Registers the SPL and starts up some classes.
 *
 * @package Envato/Envato_Template_Kit_Import
 * @since 0.0.2
 */

namespace Envato_Template_Kit_Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Collection registration and management.
 *
 * @since 0.0.2
 */
class CPT_Kits extends CPT {

	/**
	 * Core custom post name for these templates.
	 *
	 * @var string
	 */
	public $cpt_name = 'Imported Kit';

	/**
	 * Core custom post name for these templates.
	 *
	 * @var string
	 */
	public $cpt_slug = 'envato_tk_import';

	/**
	 * Post meta keys describing where a kit lives on disk and what it contains.
	 *
	 * These are maintained exclusively by our own import code and are read back as filesystem
	 * paths, so they are treated as internal state rather than editable post metadata.
	 *
	 * @var string[]
	 */
	private $protected_meta_keys = array(
		'envato_tk_folder_name',
		'envato_tk_manifest',
		'envato_tk_source_zip_url',
	);

	public function __construct() {
		parent::__construct();

		add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'wpseo_sitemap_exclude_post_type' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'custom_cpt_links' ), 10, 2 );

		/*
		 * map_meta_cap() applies auth_post_meta_{$meta_key} whenever the filter exists, so this is
		 * sufficient on its own and needs no register_meta() call. Our own update_post_meta() calls
		 * are unaffected, as they do not go through a capability check.
		 */
		foreach ( $this->protected_meta_keys as $protected_meta_key ) {
			add_filter( 'auth_post_meta_' . $protected_meta_key, '__return_false' );
		}
	}

	/**
	 * We need to manually exclude this post type from Yoast because it doesn't behave nicely.
	 *
	 * @param $exclude
	 * @param $post_type
	 *
	 * @return bool
	 *
	 * @since 0.0.9
	 */
	public function wpseo_sitemap_exclude_post_type( $exclude, $post_type ) {
		if ( $post_type === $this->cpt_slug ) {
			return true;
		}

		return $exclude;
	}

	public function get_all_uploaded_kits() {
		return get_posts(
			array(
				'posts_per_page' => - 1,
				'post_type'      => $this->cpt_slug,
			)
		);
	}

	public function custom_cpt_links( $actions, $post ) {
		if ( $post->post_type === $this->cpt_slug ) {
			$actions['review'] = '<a href="' . esc_url( admin_url( 'admin.php?page=template-kit-review&template_kit_id=' . $post->ID ) ) . '">Review</a>';
		}

		return $actions;
	}

}
