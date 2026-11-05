<?php

class GFUserSignups {

	public static function create_signups_table() {
		global $wpdb;

		self::add_signups_to_wpdb();

		$table_exists = (bool) $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->signups}'" );

		// Upgrade verions prior to 3.7
		if ( $table_exists ) {

			$column_exists = $wpdb->query( "SHOW COLUMNS FROM {$wpdb->signups} LIKE 'signup_id'" );

			if ( empty( $column_exists ) ) {

				// New primary key for signups.
				$wpdb->query( "ALTER TABLE $wpdb->signups ADD signup_id BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST" );
				$wpdb->query( "ALTER TABLE $wpdb->signups DROP INDEX domain" );

			}

		}

		self::install_signups();

	}

	private static function install_signups() {
		global $wpdb;

		// Signups is not there and we need it so let's create it
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Use WP's core CREATE TABLE query
		$create_queries = wp_get_db_schema( 'ms_global' );
		if ( ! is_array( $create_queries ) ) {
			$create_queries = explode( ';', $create_queries );
			$create_queries = array_filter( $create_queries );
		}

		// Filter out all the queries except wp_signups
		foreach ( $create_queries as $key => $query ) {
			if ( preg_match( "|CREATE TABLE ([^ ]*)|", $query, $matches ) ) {
				if ( trim( $matches[1], '`' ) !== $wpdb->signups ) {
					unset( $create_queries[ $key ] );
				}
			}
		}

		// Run WordPress's database upgrader
		if ( ! empty( $create_queries ) ) {
			$result = dbDelta( $create_queries );
		}

	}

	/**
	 * Add signups property to $wpdb object. Used by several MS functions.
	 */
	private static function add_signups_to_wpdb() {
		global $wpdb;
		$wpdb->signups = $wpdb->base_prefix . 'signups';
	}

	public static function prep_signups_functionality() {

		if ( ! is_multisite() ) {

			// require MS functions
			require_once( ABSPATH . 'wp-includes/ms-functions.php' );

			// add $wpdb->signups property (accessed in various MS functions)
			self::add_signups_to_wpdb();

			// remove filter which checks for Network setting (not active on non-ms install)
			remove_filter( 'option_users_can_register', 'users_can_register_signup_filter' );

		}

		// Write the activation key to the entry before the notification runs.
		// Manual activation calls GFSignup::get() from that notification, and get()
		// requires the key to already be stored on this site's entry.
		add_action( 'after_signup_user', array( 'GFUserSignups', 'persist_user_activation_key' ), 1, 4 );
		add_action( 'after_signup_site', array( 'GFUserSignups', 'persist_site_activation_key' ), 1, 7 );

		// signup: update the signup URL to GF's custom activation page
		add_filter( 'wpmu_signup_user_notification_email', array( 'GFUserSignups', 'modify_signup_user_notification_message' ), 10, 4 );
		add_filter( 'wpmu_signup_blog_notification_email', array( 'GFUserSignups', 'modify_signup_blog_notification_message' ), 10, 7 );

		// disable activation email for manual activation feeds
		add_filter( 'wpmu_signup_user_notification', array( 'GFUserSignups', 'maybe_suppress_signup_user_notification' ), 10, 3 );
		add_filter( 'wpmu_signup_blog_notification', array( 'GFUserSignups', 'maybe_suppress_signup_blog_notification' ), 10, 6 );

		add_filter( 'wpmu_signup_user_notification', array( __class__, 'add_site_name_filter' ) );
		add_filter( 'wpmu_signup_user_notification_subject', array( __class__, 'remove_site_name_filter' ) );

		// signup: BP cancels default MS signup notification and replaces with its own; hook up to BP's custom notification hook
		if ( gf_user_registration()->is_bp_active() ) {
			add_filter( 'bp_core_activation_signup_user_notification_message', array( 'GFUserSignups', 'modify_signup_user_notification_message' ), 10, 4 );
			add_filter( 'bp_core_activation_signup_blog_notification_message', array( 'GFUserSignups', 'modify_signup_blog_notification_message' ), 10, 7 );
		}

	}

	public static function maybe_suppress_signup_user_notification( $user, $user_email, $key ) {
		return self::is_manual_activation( $key ) ? false : $user;
	}

	public static function maybe_suppress_signup_blog_notification( $domain, $path, $title, $user, $user_email, $key ) {
		return self::is_manual_activation( $key ) ? false : $user;
	}

	public static function is_manual_activation( $key ) {
		$signup = GFSignup::get( $key );

		return ! is_wp_error( $signup ) && $signup->get_activation_type() == 'manual';
	}

	public static function modify_signup_user_notification_message( $message, $user, $user_email, $key ) {

		// don't send activation email for manual activations
		if ( self::is_manual_activation( $key ) ) {
			return false;
		}

		$url = gf_user_registration()->get_activation_url( $key );

		// BP replaces URL before passing the message, get the BP activation URL and replace
		if ( gf_user_registration()->is_bp_active() ) {
			$activate_url = esc_url_raw( sprintf( '%s?key=%s', bp_get_activation_page(), $key ) );
			$message      = str_replace( $activate_url, '%s', $message );
		}

		return sprintf( $message, esc_url_raw( $url ) );
	}

	public static function modify_signup_blog_notification_message( $message, $domain, $path, $title, $user, $user_email, $key ) {

		// don't send activation email for manual activations
		if ( self::is_manual_activation( $key ) ) {
			return false;
		}

		$url = gf_user_registration()->get_activation_url( $key );

		// BP replaces URL before passing the message, get the BP activation URL and replace
		if ( gf_user_registration()->is_bp_active() ) {
			$activate_url = esc_url( bp_get_activation_page() . "?key=$key" );
			$message      = str_replace( $activate_url, '%s', $message );
		}

		return sprintf( $message, esc_url_raw( $url ), esc_url( "http://{$domain}{$path}" ), $key );
	}

	public static function add_site_name_filter( $return ) {
		add_filter( 'site_option_site_name', array( __class__, 'modify_site_name' ) );

		return $return;
	}

	public static function remove_site_name_filter( $return ) {
		remove_filter( 'site_option_site_name', array( __class__, 'modify_site_name' ) );

		return $return;
	}

	public static function modify_site_name( $site_name ) {

		if ( ! $site_name ) {
			$site_name = get_site_option( 'blogname' );
		}

		return $site_name;
	}

	public static function add_signup_meta( $lead_id, $activation_key ) {
		gform_update_meta( $lead_id, 'activation_key', $activation_key );
	}

	public static function get_lead_activation_key( $lead_id ) {
		return gform_get_meta( $lead_id, 'activation_key' );
	}

	/**
	 * Store the activation key on the entry for a user signup.
	 *
	 * Runs before the signup notification so GFSignup::get() can confirm the key
	 * belongs to this site while manual activation is being detected.
	 *
	 * @since 5.5.3
	 *
	 * @param string $user       Requested user login.
	 * @param string $user_email Requested email address.
	 * @param string $key        Activation key.
	 * @param array  $meta       Signup meta.
	 */
	public static function persist_user_activation_key( $user, $user_email, $key, $meta ) {
		self::persist_activation_key( $meta, $key );
	}

	/**
	 * Store the activation key on the entry for a site signup.
	 *
	 * @since 5.5.3
	 *
	 * @param string $domain     Requested domain.
	 * @param string $path       Requested path.
	 * @param string $title      Requested site title.
	 * @param string $user       Requested user login.
	 * @param string $user_email Requested email address.
	 * @param string $key        Activation key.
	 * @param array  $meta       Signup meta.
	 */
	public static function persist_site_activation_key( $domain, $path, $title, $user, $user_email, $key, $meta ) {
		self::persist_activation_key( $meta, $key );
	}

	/**
	 * Whether a signup may be activated or deleted on the current site.
	 *
	 * The signups table is shared by the whole network. A key is accepted only
	 * when this site's entry meta stores that same key. Signups created on
	 * multisite also record the originating blog ID, which must match.
	 *
	 * @since 5.5.3
	 *
	 * @param string $key         Activation key.
	 * @param array  $signup_meta Signup meta.
	 *
	 * @return bool
	 */
	public static function signup_belongs_to_current_site( $key, $signup_meta ) {
		if ( ! is_array( $signup_meta ) ) {
			return false;
		}

		if ( is_multisite() ) {
			$signup_blog_id = absint( rgar( $signup_meta, 'blog_id' ) );

			// Older signups have no blog ID. The activation key check below still applies.
			if ( $signup_blog_id && $signup_blog_id !== get_current_blog_id() ) {
				return false;
			}
		}

		$lead_id = rgar( $signup_meta, 'lead_id' );
		if ( ! $lead_id ) {
			$lead_id = rgar( $signup_meta, 'entry_id' );
		}

		return self::activation_key_matches_lead( $lead_id, $key );
	}

	/**
	 * Whether the current site's entry stores this activation key.
	 *
	 * @since 5.5.3
	 *
	 * @param int    $lead_id Entry ID from the signup meta.
	 * @param string $key     Activation key.
	 *
	 * @return bool
	 */
	public static function activation_key_matches_lead( $lead_id, $key ) {
		if ( empty( $lead_id ) || ! is_string( $key ) || '' === $key ) {
			return false;
		}

		$stored_key = self::get_lead_activation_key( $lead_id );

		if ( ! is_string( $stored_key ) || '' === $stored_key ) {
			return false;
		}

		return hash_equals( $stored_key, $key );
	}

	/**
	 * Write the activation key to the entry that created the signup.
	 *
	 * @since 5.5.3
	 *
	 * @param mixed  $meta Signup meta.
	 * @param string $key  Activation key.
	 */
	private static function persist_activation_key( $meta, $key ) {
		if ( ! is_array( $meta ) ) {
			return;
		}

		$lead_id = rgar( $meta, 'lead_id' );
		if ( ! $lead_id ) {
			$lead_id = rgar( $meta, 'entry_id' );
		}

		if ( $lead_id && is_string( $key ) && '' !== $key ) {
			self::add_signup_meta( $lead_id, $key );
		}
	}

	/**
	 * Safely unserializes signup meta without instantiating PHP objects.
	 *
	 * @since 5.5.0
	 *
	 * @param mixed $meta The signup meta value.
	 *
	 * @return array
	 */
	public static function maybe_unserialize_signup_meta( $meta ) {
		$meta = GF_User_Registration::safe_unserialize( $meta );

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Activate a signup.
	 *
	 */
	public static function activate_signup( $key ) {
		global $wpdb, $current_site;

		$blog_id = is_object( $current_site ) ? $current_site->id : false;
		$signup  = GFSignup::get( $key );

		if ( is_wp_error( $signup ) ) {
			return $signup;
		}

		$user_id = username_exists( $signup->user_login );
		if ( $user_id ) {
			//username already exists, go ahead and mark signup activated and return error message
			$signup->set_as_activated();

			return new WP_Error( 'user_already_exists', __( 'That username is already activated.' ), $signup );
		}

		if ( gf_user_registration()->activation_email_exists( $signup->user_email ) ) {
			// Email address already exists, return error message.
			return new WP_Error( 'email_already_exists', __( 'Sorry, that email address is already used!' ), $signup );
		}

		// unbind site creation from gform_user_registered hook, run it manually below
		if ( is_multisite() ) {
			remove_action( 'gform_user_registered', array( gf_user_registration(), 'create_site' ) );
		}

		gf_user_registration()->log( "Activating signup for username: {$signup->user_login} - entry: {$signup->lead['id']}" );

		$user_data = gf_user_registration()->create_user( $signup->lead, $signup->form, $signup->config, '', $signup->meta['password_hash'] );
		$user_id   = is_wp_error( $user_data ) ? null : rgar( $user_data, 'user_id' );

		if ( ! $user_id ) {
			return new WP_Error( 'create_user', __( 'Could not create user' ), $signup );
		}

		$signup->set_as_activated();

		// Send notifications
		GFAPI::send_notifications( $signup->form, $signup->lead, 'gfur_user_activated' );

		do_action( 'gform_activate_user', $user_id, $user_data, $signup->meta );

		if ( is_multisite() && rgars( $signup->config, 'meta/createSite' ) ) {
			$blog_id = gf_user_registration()->create_site( $user_id, $signup->config, $signup->lead, $user_data['password'] );
		}

		return array( 'user_id' => $user_id, 'password' => $user_data['password'], 'password_hash' => $user_data['password_hash'], 'blog_id' => $blog_id );
	}

	public static function delete_signup( $key ) {
		$signup = GFSignup::get( $key );

		if ( is_wp_error( $signup ) ) {
			return $signup;
		}

		do_action( 'gform_userregistration_delete_signup', $signup );

		$result = $signup->delete();

		if ( $result ) {
			GFAPI::send_notifications( $signup->form, $signup->lead, 'gfur_user_activation_deleted' );
		}

		return $result;
	}

}

/**
 * Create a signup object from a signup key.
 */
class GFSignup {

	public $meta;
	public $lead;
	public $form;
	public $config;

	public $activation_key;
	public $user_login;
	public $user_email;

	private $error;

	function __construct( $signup ) {

		foreach ( $signup as $key => $value ) {
			$this->$key = $value;
		}

		$this->meta   = GFUserSignups::maybe_unserialize_signup_meta( $signup->meta );
		$this->lead   = GFFormsModel::get_lead( rgar( $this->meta, 'lead_id' ) );
		$this->form   = GFFormsModel::get_form_meta( rgar( $this->lead, 'form_id' ) );
		$this->config = gf_user_registration()->get_filtered_single_submission_feed( $this->lead, $this->form );

	}

	public static function get( $key ) {
		global $wpdb;

		if ( ! is_string( $key ) || '' === $key ) {
			return new WP_Error( 'invalid_key', __( 'Invalid activation key.' ) );
		}

		$signup = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE activation_key = %s", $key ) );

		if ( empty( $signup ) ) {
			return new WP_Error( 'invalid_key', __( 'Invalid activation key.' ) );
		}

		$signup_meta = GFUserSignups::maybe_unserialize_signup_meta( $signup->meta );
		$lead_id     = rgar( $signup_meta, 'lead_id' );

		if ( empty( $lead_id ) ) {
			return new WP_Error( 'invalid_lead', __( 'Activation key is not valid.' ), $signup );
		}

		// Entry IDs collide across subsites. Do not load or act on this signup
		// unless the key belongs to an entry on the site we are currently on.
		if ( ! GFUserSignups::signup_belongs_to_current_site( $key, $signup_meta ) ) {
			return new WP_Error( 'invalid_key', __( 'Invalid activation key.' ) );
		}

		if ( $signup->active ) {
			return new WP_Error( 'already_active', __( 'The user is already active.' ), $signup );
		}

		$lead = GFFormsModel::get_lead( $lead_id );

		if ( $lead === false ) {
			return new WP_Error( 'invalid_lead', __( 'Activation key is not valid.' ), $signup );
		}

		return new GFSignup( $signup );
	}

	function get_activation_type() {
		return rgars( $this->config, 'meta/userActivationValue' );
	}

	function set_as_activated() {
		global $wpdb;

		// Remove password for security.
		$this->meta['password'] = '';

		$now    = current_time( 'mysql', true );
		$result = $wpdb->update( $wpdb->signups, array(
			'active'    => 1,
			'activated' => $now,
			'meta'      => serialize( $this->meta ),
		), array( 'activation_key' => $this->activation_key ) );

		return $result;
	}

	function delete() {
		global $wpdb;

		return $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->signups WHERE activation_key = %s", $this->activation_key ) );
	}

	/**
	 * Magic methods to allow setting dynamic properties without deprecation notices in PHP 8.2.
	 */
	public function __set($name, $value) { }

}
