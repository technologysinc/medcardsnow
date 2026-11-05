<?php

namespace Smush\Core\Webp;

use Smush\Core\Smush\Smusher;

class Webp_Converter extends Smusher {
	/**
	 * @var Webp_Helper
	 */
	private $webp_helper;

	public function __construct() {
		parent::__construct();

		$this->webp_helper = new Webp_Helper();
	}

	protected function get_api_request_headers( $file_path, $file_url ) {
		$headers         = parent::get_api_request_headers( $file_path, $file_url );
		$headers['webp'] = 'true';

		return $headers;
	}

	protected function save_smushed_image_file( $file_path, $image ) {
		$webp_file_path = $this->webp_helper->get_webp_file_path( $file_path, true );
		$file_saved     = file_put_contents( $webp_file_path, $image );
		if ( ! $file_saved ) {
			return false;
		}

		return $webp_file_path;
	}

	protected function save_from_resource( $input_stream, $target_file_path, $file_md5, $chunk_size ) {
		$webp_file_path = $this->webp_helper->get_webp_file_path( $target_file_path, true );

		return parent::save_from_resource( $input_stream, $webp_file_path, $file_md5, $chunk_size );
	}

	protected function get_type_label() {
		return 'WebP';
	}
}