<?php

namespace Smush\Core\Smush;

use Smush\Core\Api\Backoff;
use Smush\Core\Api\Request_Multiple;
use Smush\Core\Array_Utils;
use Smush\Core\File_System;
use Smush\Core\Helper;
use Smush\Core\Product_Analytics;
use Smush\Core\Settings;
use Smush\Core\Timer;
use Smush\Core\Upload_Dir;
use Smush_Vendor\GuzzleHttp\Client;
use Smush_Vendor\GuzzleHttp\Exception\GuzzleException;
use WP_Error;
use WP_Smush;

/**
 * Takes raw image file paths and processes them through the Smush API.
 */
class Smusher {
	const ERROR_SSL_CERT = 'ssl_cert_error';
	const IMAGE_NOT_SAVED_FROM_URL = 'image_not_saved_from_url';
	/**
	 * @var Settings
	 */
	private $settings;
	/**
	 * @var Request_Multiple
	 */
	private $request_multiple;
	/**
	 * @var Backoff
	 */
	private $backoff;
	/**
	 * @var \WDEV_Logger|null
	 */
	private $logger;
	/**
	 * @var int
	 */
	private $retry_attempts;
	/**
	 * @var int
	 */
	private $retry_wait;
	/**
	 * @var int
	 */
	private $timeout;
	/**
	 * @var string
	 */
	private $user_agent;
	/**
	 * @var int
	 */
	private $connect_timeout;
	/**
	 * @var boolean
	 */
	private $smush_parallel;
	/**
	 * @var WP_Error
	 */
	private $errors;
	/**
	 * @var WP_Error
	 */
	private $warnings;
	/**
	 * @var File_System
	 */
	private $fs;
	/**
	 * @var Upload_Dir
	 */
	private $upload_dir;
	/**
	 * @var Array_Utils
	 */
	private $array_utils;

	private $streaming_enabled;

	const DEFAULT_CHUNK_SIZE = 5 * 1024 * 1024;
	/**
	 * @var Product_Analytics
	 */
	private $product_analytics;

	private $file_sizes_cache = array();

	public function __construct() {
		$this->retry_attempts    = WP_SMUSH_RETRY_ATTEMPTS;
		$this->retry_wait        = WP_SMUSH_RETRY_WAIT;
		$this->user_agent        = WP_SMUSH_UA;
		$this->smush_parallel    = WP_SMUSH_PARALLEL;
		$this->timeout           = WP_SMUSH_TIMEOUT;
		$this->streaming_enabled = WP_SMUSH_USE_STREAMS;
		$this->connect_timeout   = 5;

		$this->settings          = Settings::get_instance();
		$this->logger            = Helper::logger();
		$this->request_multiple  = new Request_Multiple();
		$this->backoff           = new Backoff();
		$this->errors            = new WP_Error();
		$this->warnings          = new WP_Error();
		$this->fs                = new File_System();
		$this->upload_dir        = new Upload_Dir();
		$this->array_utils       = new Array_Utils();
		$this->product_analytics = Product_Analytics::get_instance();
	}

	/**
	 * @param $files_data string[]|array[]
	 *
	 * @return boolean[]|object[]
	 */
	public function smush( $files_data ) {
		$this->set_errors( new WP_Error() );

		if (
			$this->smush_parallel
			&& $this->parallel_available_on_server()
		) {
			return $this->smush_parallel( $files_data );
		} else {
			return $this->smush_sequential( $files_data );
		}
	}

	/**
	 * @param $files_data string[]|array[]
	 *
	 * @return boolean[]|object[]
	 */
	private function smush_parallel( $files_data ) {
		$retry    = array();
		$requests = array();
		foreach ( $files_data as $size_key => $size_file_data ) {
			list( $size_file_path, $size_file_url ) = $this->get_file_path_and_url( $size_file_data );
			$requests[ $size_key ] = $this->get_parallel_request_args( $size_file_path, $size_file_url );
		}

		// Send off the valid paths to the API
		$responses = array();
		$this->request_multiple->do_requests( $requests, array(
			'timeout'         => $this->timeout,
			'connect_timeout' => $this->connect_timeout,
			'user-agent'      => $this->user_agent,
			'complete'        => function ( $response, $response_size_key ) use ( &$requests, &$responses, &$retry, $files_data ) {
				// Free up memory
				$requests[ $response_size_key ] = null;
				$size_file_data                 = $files_data[ $response_size_key ];
				list( $size_file_path ) = $this->get_file_path_and_url( $size_file_data );

				if ( $this->is_network_error( $response ) ) {
					$retry[ $response_size_key ] = $size_file_data;

					$this->add_warnings( $response, $response_size_key );
				} else {
					$is_success_response = $this->handle_response( $response, $response_size_key, $size_file_path );
					// If the network request was successful, there are still some cases where it's best to retry
					if ( ! $is_success_response && $this->has_error_worth_retrying() ) {
						$retry[ $response_size_key ] = $size_file_data;
					} else {
						$responses[ $response_size_key ] = $is_success_response;
					}
				}
			},
		) );

		$this->maybe_track_api_fetch_error();
		$this->maybe_track_image_url_error();

		// Retry failures with exponential backoff
		foreach ( $retry as $retry_size_key => $retry_size_file ) {
			list( $retry_file_path ) = $this->get_file_path_and_url( $retry_size_file );
			// Note that we are not sending a file URL because we want the retry to happen using the traditional approach
			// This is designed to prevent issues when a firewall is blocking the callback
			$responses[ $retry_size_key ] = $this->smush_file( $retry_file_path, $retry_size_key );
		}

		return $responses;
	}

	/**
	 * @param $files_data string[]|array[]
	 *
	 * @return boolean[]|object[]
	 */
	private function smush_sequential( $files_data ) {
		$responses = array();
		foreach ( $files_data as $size_key => $size_file_data ) {
			list( $size_file_path, $size_file_url ) = $this->get_file_path_and_url( $size_file_data );
			$responses[ $size_key ] = $this->smush_file( $size_file_path, $size_key, $size_file_url );
		}

		return $responses;
	}

	/**
	 * @param $file_path string
	 * @param $size_key string
	 *
	 * @return bool|object
	 */
	public function smush_file( $file_path, $size_key = '', $file_url = '' ) {
		$response = $this->backoff->set_wait( $this->retry_wait )
		                          ->set_max_attempts( $this->retry_attempts )
		                          ->enable_jitter()
		                          ->set_decider( array( $this, 'is_network_error' ) )
		                          ->run( function () use ( $file_path, $file_url ) {
			                          return $this->make_post_request( $file_path, $file_url );
		                          } );

		return $this->handle_response( $response, $size_key, $file_path );
	}

	private function make_post_request( $file_path, $file_url ) {
		return wp_remote_post(
			$this->get_api_url(),
			$this->get_api_request_args( $file_path, $file_url )
		);
	}

	private function get_api_request_args( $file_path, $file_url ) {
		$request_body = $this->prepare_request_body_for_streaming( $file_url );
		if ( empty( $request_body ) ) {
			// Temporary increase the limit because we are about to read a full file into memory.
			wp_raise_memory_limit( 'image' );
			$request_body = $this->fs->file_get_contents( $file_path );
		}

		return array(
			'headers'    => $this->get_api_request_headers( $file_path, $file_url ),
			'body'       => $request_body,
			'timeout'    => $this->timeout,
			'user-agent' => $this->user_agent,
		);
	}

	/**
	 * @param $response
	 * @param $size_key string
	 * @param $file_path string
	 *
	 * @return bool|object
	 */
	private function handle_response( $response, $size_key, $file_path ) {
		$data = $this->parse_response( $response, $size_key, $file_path );

		if ( ! $data ) {
			if ( $this->has_error( self::ERROR_SSL_CERT ) ) {
				// Switch to http protocol.
				$this->settings->set_setting( 'wp-smush-use_http', 1 );
			}

			return false;
		}

		if ( $data->bytes_saved > 0 ) {
			if ( ! empty( $data->image_url ) ) {
				$saved_from_image_url = $this->save_from_image_url( $data->image_url, $file_path, $data->image_md5 );
				if ( is_wp_error( $saved_from_image_url ) ) {
					$this->add_error(
						$size_key,
						self::IMAGE_NOT_SAVED_FROM_URL,
						/* translators: %s: Error message. */
						sprintf( __( 'Smush was successful but we were unable to save from URL: %s.', 'wp-smushit' ), $saved_from_image_url->get_error_message() ),
						array(
							'original_code'    => $saved_from_image_url->get_error_code(),
							'original_message' => $saved_from_image_url->get_error_message(),
						)
					);

					return false;
				}
			} else {
				$optimized_image_saved = $this->save_smushed_image_file( $file_path, $data->image );
				if ( ! $optimized_image_saved ) {
					$this->add_error(
						$size_key,
						'image_not_saved',
						/* translators: %s: File path. */
						sprintf( __( 'Smush was successful but we were unable to save the file due to a file system error: [%s].', 'wp-smushit' ), $this->upload_dir->get_human_readable_path( $file_path ) )
					);

					return false;
				}
			}
		}

		// No need to pass image data any further
		$data->image     = null;
		$data->image_md5 = null;

		// Check for API message and store in db.
		if ( ! empty( $data->api_message ) ) {
			$this->add_api_message( (array) $data->api_message );
		}

		return $data;
	}

	/**
	 * @param $input_stream resource
	 * @param $target_file_path
	 * @param $file_md5
	 * @param $chunk_size
	 *
	 * @return true|WP_Error
	 */
	protected function save_from_resource( $input_stream, $target_file_path, $file_md5, $chunk_size ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$timer = new Timer();
		$timer->start();

		$error     = false;
		$temp_name = wp_tempnam();
		do {
			if ( empty( $temp_name ) ) {
				$error = new WP_Error( 'temp-file-creation-error', 'Error creating temporary file' );
				break;
			}

			$output_stream = fopen( $temp_name, "wb" );
			do {
				$chunk_copied_successfully = stream_copy_to_stream( $input_stream, $output_stream, $chunk_size );
				if ( $chunk_copied_successfully === false ) {
					break;
				}
			} while ( ! feof( $input_stream ) );

			// Close the input and output streams
			fclose( $input_stream );
			fclose( $output_stream );

			if ( $chunk_copied_successfully === false ) {
				$error = new WP_Error( 'temp-file-save-error', 'Error saving temp file' );
				break;
			}

			$hash_equals = hash_equals( $file_md5, md5_file( $temp_name ) );
			if ( ! $hash_equals ) {
				$error = new WP_Error( 'file-hash-mismatch', 'File hash mismatch' );
				break;
			}

			$target_file_name = basename( $target_file_path );
			$type             = wp_get_image_mime( $temp_name );
			if ( ! str_starts_with( $type, 'image/' ) ) {
				$error = new WP_Error(
					'invalid-file-type',
					sprintf( 'Invalid file type. Calculated type for file named %s at %s is %s', $target_file_name, $temp_name, $type )
				);
				break;
			}

			$file_copied = copy( $temp_name, $target_file_path );
			if ( ! $file_copied ) {
				$error = new WP_Error( 'error-moving-file', 'Error moving file' );
				break;
			}

			$permissions = $this->get_permissions_for_image( $target_file_path );
			chmod( $target_file_path, $permissions );
		} while ( 0 );

		@unlink( $temp_name );

		$time = $timer->end();
		if ( $error ) {
			$this->logger->notice( sprintf( 'File could not be saved: %s', $error->get_error_message() ) );
			return $error;
		} else {
			$this->logger->notice( sprintf( 'File saved successfully in %s seconds', $time ) );
			return true;
		}
	}

	public function save_from_image_url( $image_url, $target_file_path, $file_md5, $chunk_size = self::DEFAULT_CHUNK_SIZE ) {
		try {
			$client       = new Client();
			$response     = $client->get( $image_url, [
				'stream' => true,
			] );
			$input_stream = $response->getBody()->detach();

			return $this->save_from_resource( $input_stream, $target_file_path, $file_md5, $chunk_size );
		} catch ( GuzzleException $exception ) {
			$this->logger->error( sprintf( 'Error fetching image from URL: %s', $exception->getMessage() ) );

			$code = $exception->getCode();
			$code = empty( $code ) ? 'timeout' : $code;

			return new WP_Error( $code, 'Error fetching image from URL' );
		}
	}

	protected function save_smushed_image_file( $file_path, $image ) {
		$pre = apply_filters( 'wp_smush_pre_image_write', false, $file_path, $image );
		if ( $pre !== false ) {
			$this->logger->notice( 'Another plugin/theme short circuited the image write operation using the wp_smush_pre_image_write filter.' );

			// Assume that the plugin/theme responsible took care of it
			return true;
		}

		$permissions = $this->get_permissions_for_image( $file_path );

		// Save the new file
		$success = $this->put_smushed_image_file( $file_path, $image );

		chmod( $file_path, $permissions );

		return $success;
	}

	private function put_smushed_image_file( $file_path, $image ) {
		$temp_file = $file_path . '.tmp';

		$success = $this->put_image_using_temp_file( $file_path, $image, $temp_file );

		// Clean up
		if ( $this->fs->file_exists( $temp_file ) ) {
			$this->fs->unlink( $temp_file );
		}

		return $success;
	}

	private function put_image_using_temp_file( $file_path, $image, $temp_file ) {
		$file_written = file_put_contents( $temp_file, $image );
		if ( ! $file_written ) {
			return false;
		}

		$renamed = rename( $temp_file, $file_path );
		if ( $renamed ) {
			return true;
		}

		$copied = $this->fs->copy( $temp_file, $file_path );
		if ( $copied ) {
			return true;
		}

		return false;
	}

	private function add_api_message( $api_message = array() ) {
		if ( empty( $api_message ) || ! count( $api_message ) || empty( $api_message['timestamp'] ) || empty( $api_message['message'] ) ) {
			return;
		}
		$o_api_message = get_site_option( 'wp-smush-api_message', array() );
		if ( array_key_exists( $api_message['timestamp'], $o_api_message ) ) {
			return;
		}

		$message                              = array();
		$message[ $api_message['timestamp'] ] = array(
			'message' => sanitize_text_field( $api_message['message'] ),
			'type'    => sanitize_text_field( $api_message['type'] ),
			'status'  => 'show',
		);
		update_site_option( 'wp-smush-api_message', $message );
	}

	/**
	 * @param $response
	 * @param $size_key string
	 * @param $file_path string
	 *
	 * @return object|false
	 */
	private function parse_response( $response, $size_key, $file_path ) {
		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();

			if ( strpos( $error, 'SSL CA cert' ) !== false ) {
				$this->add_error( $size_key, self::ERROR_SSL_CERT, $error );

				return false;
			} else if ( strpos( $error, 'timed out' ) !== false ) {
				$this->add_error(
					$size_key,
					'time_out',
					esc_html__( "Skipped due to a timeout error. You can increase the request timeout to make sure Smush has enough time to process larger files. define('WP_SMUSH_TIMEOUT', 150);", 'wp-smushit' )
				);

				return false;
			} else {
				$this->add_error(
					$size_key,
					'error_posting_to_api',
					/* translators: %s: Error message. */
					sprintf( __( 'Error posting to API: %s', 'wp-smushit' ), $error )
				);

				return false;
			}
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$non_200_body = wp_remote_retrieve_body( $response );
			$non_200_json = $non_200_body ? json_decode( $non_200_body ) : null;
			if ( ! empty( $non_200_json->data ) ) {
				// We got a pre-formatted error from the API
				$error = $non_200_json->data;
			} else {
				// Make an error from the response message
				$error = sprintf(
				/* translators: 1: Error code, 2: Error message. */
					__( 'Error posting to API: %1$s %2$s', 'wp-smushit' ),
					wp_remote_retrieve_response_code( $response ),
					wp_remote_retrieve_response_message( $response )
				);
			}

			$this->add_error( $size_key, 'non_200_response', $error );

			return false;
		}

		$json = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $json->success ) ) {
			$error = ! empty( $json->data )
				? $json->data
				: __( "Image couldn't be smushed", 'wp-smushit' );

			$this->add_error( $size_key, 'unsuccessful_smush', $error );

			return false;
		}

		if (
			empty( $json->data )
			|| empty( $json->data->before_size )
			|| empty( $json->data->after_size )
		) {
			$this->add_error( $size_key, 'no_data', __( 'Unknown API error', 'wp-smushit' ) );

			return false;
		}

		$data                   = $json->data;
		$data->bytes_saved      = isset( $data->bytes_saved ) ? (int) $data->bytes_saved : 0;
		$optimized_image_larger = $data->after_size > $data->before_size;
		if ( $optimized_image_larger ) {
			$this->add_error(
				$size_key,
				'optimized_image_larger',
				/* translators: 1: File path, 2: Savings bytes. */
				sprintf( 'The smushed image is larger than the original image [%s] (bytes saved %d), keep original image.', $this->upload_dir->get_human_readable_path( $file_path ), $data->bytes_saved )
			);

			return false;
		}

		if ( empty( $data->image_url ) ) {
			$image = empty( $data->image ) ? '' : $data->image;
			if ( $data->bytes_saved > 0 ) {
				// Because of the API response structure, the following should only be done when there are some bytes_saved.

				if ( $data->image_md5 !== md5( $image ) ) {
					$error = __( 'Smush data corrupted, try again.', 'wp-smushit' );
					$this->add_error( $size_key, 'data_corrupted', $error );

					return false;
				}

				if ( ! empty( $image ) ) {
					$data->image = base64_decode( $data->image );
				}
			}
		}

		return $data;
	}

	public function is_network_error( $response ) {
		return $this->retry_attempts > 0 && (
				is_wp_error( $response )
				|| 200 !== wp_remote_retrieve_response_code( $response )
			);
	}

	private function get_parallel_request_args( $file_path, $file_url = '' ) {
		$data = $this->prepare_request_body_for_streaming( $file_url );
		if ( empty( $data ) ) {
			$data = $this->fs->file_get_contents( $file_path );
		}

		return array(
			'url'     => $this->get_api_url(),
			'headers' => $this->get_api_request_headers( $file_path, $file_url ),
			'data'    => $data,
			'type'    => 'POST',
		);
	}

	/**
	 * @return string
	 */
	private function get_api_url() {
		return defined( 'WP_SMUSH_API_HTTP' ) ? WP_SMUSH_API_HTTP : WP_SMUSH_API;
	}

	/**
	 * @return string[]
	 */
	protected function get_api_request_headers( $file_path, $file_url ) {
		$headers = array(
			'accept' => 'application/json', // The API returns JSON.
			'exif'   => $this->settings->get( 'strip_exif' ) ? 'false' : 'true',
		);

		if ( empty( $file_url ) ) {
			$headers['content-type'] = 'application/binary';
		}

		$headers['lossy'] = $this->settings->get_lossy_level_setting();

		// Check if premium member, add API key.
		$api_key = Helper::get_wpmudev_apikey();
		if ( ! empty( $api_key ) && WP_Smush::is_pro() ) {
			$headers['apikey'] = $api_key;

			$is_large_file = $this->is_large_file( $file_path );
			if ( $is_large_file ) {
				$headers['islarge'] = 1;
			}
		}

		return $headers;
	}

	private function is_large_file( $file_path ) {
		$file_size = $this->get_file_size( $file_path );
		$cut_off   = $this->settings->get_large_file_cutoff();

		return $file_size > $cut_off;
	}

	private function get_file_size( $file_path ) {
		if ( ! isset( $this->file_sizes_cache[ $file_path ] ) ) {
			$this->file_sizes_cache[ $file_path ] = file_exists( $file_path ) ? filesize( $file_path ) : 0;
		}

		return $this->file_sizes_cache[ $file_path ];
	}

	/**
	 * @return bool
	 */
	public function parallel_available_on_server() {
		return $this->curl_multi_exec_available();
	}

	/**
	 * @return bool
	 */
	public function curl_multi_exec_available() {
		if ( ! function_exists( 'curl_multi_exec' ) ) {
			return false;
		}

		$disabled_functions = explode( ',', ini_get( 'disable_functions' ) );
		if ( in_array( 'curl_multi_exec', $disabled_functions ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int $retry_attempts
	 *
	 * @return Smusher
	 */
	public function set_retry_attempts( $retry_attempts ) {
		$this->retry_attempts = $retry_attempts;

		return $this;
	}

	/**
	 * @param int $timeout
	 */
	public function set_timeout( $timeout ) {
		$this->timeout = $timeout;
	}

	/**
	 * @param bool $smush_parallel
	 *
	 * @return Smusher
	 */
	public function set_smush_parallel( $smush_parallel ) {
		$this->smush_parallel = $smush_parallel;

		return $this;
	}

	/**
	 * @param Request_Multiple $request_multiple
	 *
	 * @return Smusher
	 */
	public function set_request_multiple( $request_multiple ) {
		$this->request_multiple = $request_multiple;

		return $this;
	}

	public function get_errors() {
		return $this->errors;
	}

	/**
	 * @param $errors WP_Error
	 *
	 * @return void
	 */
	private function set_errors( $errors ) {
		$this->errors = $errors;
	}

	/**
	 * @param $size_key string
	 * @param $code string
	 * @param $message string
	 *
	 * @return void
	 */
	private function add_error( $size_key, $code, $message, $data = array() ) {
		// Log the error
		$this->logger->error( "[$size_key] $message" );
		// Add the error
		$this->errors->add( $code, "[$size_key] $message" );

		if ( ! empty( $data ) ) {
			$this->errors->add_data( $data, $code );
		}
	}

	/**
	 * @param $size_key string
	 * @param $code string
	 * @param $message string
	 *
	 * @return void
	 */
	private function add_warning( $size_key, $code, $message ) {
		// Log the warning
		$this->logger->warning( "[$size_key] $message" );
		// Add the warning
		$this->warnings->add( $code, "[$size_key] $message" );
	}

	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * @param $code string
	 *
	 * @return bool
	 */
	private function has_error( $code ) {
		return ! empty( $this->errors->get_error_message( $code ) );
	}

	/**
	 * @param $file_url
	 *
	 * @return array|false
	 */
	private function prepare_request_body_for_streaming( $file_url ) {
		if ( empty( $file_url ) || ! $this->streaming_enabled() ) {
			return false;
		}

		return array( 'file_url' => $file_url );
	}

	/**
	 * @param $file_data string|array
	 *
	 * @return array
	 */
	private function get_file_path_and_url( $file_data ): array {
		if ( is_string( $file_data ) ) {
			$file_path = $file_data;
			$file_url  = '';
		} else {
			$file_path = $this->array_utils->get_array_value( $file_data, 'path' );
			$file_url  = $this->array_utils->get_array_value( $file_data, 'url' );
		}
		return array( $file_path, $file_url );
	}

	private function get_permissions_for_image( $file_path ) {
		clearstatcache();
		$perms = fileperms( $file_path ) & 0777;
		// Some servers are having issue with file permission, this should fix it.
		if ( empty( $perms ) ) {
			// Source: WordPress Core.
			$stat  = stat( dirname( $file_path ) );
			$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		}

		return $perms;
	}

	private function streaming_enabled() {
		return $this->streaming_enabled;
	}

	public function set_streaming_enabled( $streaming_enabled ) {
		$this->streaming_enabled = $streaming_enabled;
	}

	private function maybe_track_api_fetch_error() {
		$fetch_error_message = $this->warnings->get_error_message( 'response_code_422' );
		if ( $fetch_error_message ) {
			$this->product_analytics->maybe_track_error(
				'api_error',
				422,
				$fetch_error_message,
				array(
					'Smush Type' => $this->get_type_label(),
				)
			);
		}
	}

	private function maybe_track_image_url_error() {
		if ( $this->has_error( self::IMAGE_NOT_SAVED_FROM_URL ) ) {
			$error_data       = $this->errors->get_error_data( self::IMAGE_NOT_SAVED_FROM_URL );
			$original_code    = $this->array_utils->get_array_value( $error_data, 'original_code' );
			$original_message = $this->array_utils->get_array_value( $error_data, 'original_message' );

			if ( $original_code && $original_message ) {
				$this->product_analytics->maybe_track_error(
					self::IMAGE_NOT_SAVED_FROM_URL,
					$original_code,
					$original_message,
					array(
						'Smush Type' => $this->get_type_label(),
					)
				);
			}
		}
	}

	/**
	 * @return bool
	 */
	private function has_error_worth_retrying() {
		$errors_that_should_be_retried = array(
			self::IMAGE_NOT_SAVED_FROM_URL,
		);

		foreach ( $errors_that_should_be_retried as $error_code ) {
			if ( $this->has_error( $error_code ) ) {
				return true;
			}
		}

		return false;
	}

	protected function get_type_label() {
		return 'Classic';
	}

	private function add_warnings( $response, $size_key ) {
		if ( is_wp_error( $response ) ) {
			/**
			 * @var WP_Error $error
			 */
			$error = $response;
			$this->add_warning( $size_key, $error->get_error_code(), $error->get_error_message() );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( ! empty( $body ) ) {
			$json = json_decode( $body );
			if ( empty( $json->success ) && ! empty( $json->data ) && is_string( $json->data ) ) {
				$message = $json->data;
			}
		}

		$this->add_warning( $size_key, "response_code_$code", $message );
	}
}