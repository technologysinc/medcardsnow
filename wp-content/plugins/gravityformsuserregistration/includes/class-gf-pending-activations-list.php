<?php

defined( 'ABSPATH' ) || die();

require_once ABSPATH . '/wp-admin/includes/class-wp-list-table.php';
require_once gf_user_registration()->get_base_path() . '/includes/signups.php';

class GF_Pending_Activations_List extends WP_List_Table {

	var $_column_headers;
	var $_actions_added = false;

	function __construct() {

		$this->items = array();
		$this->_column_headers = array(
			array(
				'cb'         => '<input type="checkbox" />',
				'user_login' => __( 'Username', 'gravityformsuserregistration' ),
				'email'      => __( 'Email', 'gravityformsuserregistration' ),
				'date'       => __( 'Sign Up Date', 'gravityformsuserregistration' ),
			),
			array(),
			array(),
			'user_login',
		);

		parent::__construct();

	}

	function prepare_items() {

		$forms               = array();
		$per_page            = 10;
		$page                = rgget( 'paged' ) ? rgget( 'paged' ) : 1;
		$pending_activations = GF_Pending_Activations::get_pending_activations( rgget( 'id' ), array(
			'per_page' => $per_page,
			'page'     => $page
		) );
		$total_pending       = GF_Pending_Activations::get_pending_activations( rgget( 'id' ), array(
			'per_page'  => $per_page,
			'page'      => $page,
			'get_total' => true
		) );

		foreach ( $pending_activations as $pending_activation ) {

			$signup_meta = GFUserSignups::maybe_unserialize_signup_meta( $pending_activation->meta );

			// On multisite, make sure this signup belongs to the current site.
			if ( ! GFUserSignups::signup_belongs_to_current_site( $pending_activation->activation_key, $signup_meta ) ) {
				continue;
			}

			$lead = GFAPI::get_entry( rgar( $signup_meta, 'lead_id' ) );
			if ( is_wp_error( $lead ) ) {
				continue;
			}

			$form_id           = $lead['form_id'];
			$form              = rgar( $forms, $form_id ) ? rgar( $forms, $form_id ) : GFFormsModel::get_form_meta( $form_id );
			$forms[ $form_id ] = $form;

			$item               = array();
			$item['form']       = $form['title'];
			$item['user_login'] = rgar( $signup_meta, 'user_login' );
			$item['email']      = rgar( $signup_meta, 'email' );
			$item['date']       = $lead['date_created'];

			// non-columns
			$item['lead_id']        = $lead['id'];
			$item['form_id']        = $form_id;
			$item['activation_key'] = $pending_activation->activation_key;

			array_push( $this->items, $item );

		}

		$this->set_pagination_args( array(
			'total_items' => $total_pending,
			'per_page'    => $per_page
		) );

	}

	/**
	 * Display row actions for default column.
	 *
	 * @since Unknown
	 *
	 * @param array  $item        The table row item.
	 * @param string $column_name Slug of the column.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {

		$value = rgar( $item, $column_name );

		if ( $column_name === 'user_login' ) {
			$activation_key = esc_attr( $item['activation_key'] );
			$value         .= '
			<div class="row-actions">
				<span class="inline hide-if-no-js">
					<a href="#"
						data-dialog-title="' . esc_attr__( 'Activate User', 'gravityformsuserregistration' ) . '"
						data-dialog-confirm="' . esc_attr__( 'Are you sure you want to activate this user? \'Cancel\' to stop, \'OK\' to activate.', 'gravityformsuserregistration' ) . '"
						data-dialog-callback="gfPendingActivationAction"
						data-activation-action="activate"
						data-activation-key="' . $activation_key . '"
					>' . esc_html__( 'Activate User', 'gravityformsuserregistration' ) . '</a> |
				</span>
				<span class="inline hide-if-no-js">
					<a title="' . esc_attr__( 'View the entry associated with this sign up', 'gravityformsuserregistration' ) . '" href="' . admin_url( "admin.php?page=gf_entries&view=entry&id={$item['form_id']}&lid={$item['lead_id']}" ) . '">' . esc_html__( 'View Entry', 'gravityformsuserregistration' ) . '</a> |
				</span>
				<span class="inline hide-if-no-js">
					<a href="#"
						data-dialog-title="' . esc_attr__( 'Delete Registration', 'gravityformsuserregistration' ) . '"
						data-dialog-confirm="' . esc_attr__( 'Are you sure you want to delete this registration? \'Cancel\' to stop, \'OK\' to delete.', 'gravityformsuserregistration' ) . '"
						data-dialog-callback="gfPendingActivationAction"
						data-activation-action="delete"
						data-activation-key="' . $activation_key . '"
					>' . esc_html__( 'Delete', 'gravityformsuserregistration' ) . '</a>
				</span>
			</div>';
		}

		return $value;
	}

	function column_cb( $item ) {
		return '<input type="checkbox" name="items[]" value="' . $item['activation_key'] . '" />';
	}

	function column_date( $item ) {
		return GFCommon::format_date( rgar( $item, 'date' ), false );
	}

	function get_bulk_actions() {

		$actions = array(
			'activate' => __( 'Activate', 'gravityformsuserregistration' ),
			'delete'   => __( 'Delete', 'gravityformsuserregistration' )
		);

		return $actions;
	}

	function get_columns() {
		return array();
	}
}
