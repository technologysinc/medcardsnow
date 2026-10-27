<?php

namespace Objectiv\Plugins\Checkout\API;

use Objectiv\Plugins\Checkout\Admin\Pages\CheckoutEditor;
use Objectiv\Plugins\Checkout\Features\CustomFields\PercentageBasis;
use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Managers\SlotManager;
use Objectiv\Plugins\Checkout\Model\CustomField;
use WC_Tax;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API endpoints for the Checkout Editor slot system.
 *
 * GET  /checkoutwc/v1/slots  — returns slot definitions, current assignments,
 *                              custom HTML blocks, and available items.
 * POST /checkoutwc/v1/slots  — saves slot assignments and custom HTML blocks.
 *
 * @link checkoutwc.com
 * @since 11.2.0
 * @package Objectiv\Plugins\Checkout\API
 */
class SlotsAPI {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'checkoutwc/v1',
			'slots',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_slots' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'save_slots' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'assignments'        => [
							'type'     => 'object',
							'required' => true,
						],
						'custom_html_blocks' => [
							'type'     => 'object',
							'required' => false,
							'default'  => [],
						],
						'custom_fields'      => [
							'type'     => 'object',
							'required' => false,
							'default'  => null,
						],
					],
				],
			]
		);
	}

	/**
	 * GET /checkoutwc/v1/slots
	 *
	 * Returns slot definitions, the current assignments, available items for
	 * the editor picker, and all custom HTML blocks.
	 *
	 * @return WP_REST_Response
	 */
	public function get_slots(): WP_REST_Response {
		$manager = SlotManager::instance();
		$fields  = CustomFieldManager::instance();

		return rest_ensure_response(
			[
				'slot_definitions'   => SlotManager::get_slot_hook_map(),
				'assignments'        => $manager->get_slots(),
				'custom_html_blocks' => $manager->get_custom_html_blocks(),
				'custom_fields'      => $fields->get_definitions(),
				'custom_fields_revision' => $fields->get_revision(),
				'available_items'  => $manager->get_available_items(),
				// Re-sent on every read so the editor's "can I create another bump?" guard
				// keeps up with bumps published or trashed in the bump editor modal.
				'bump_editor'        => CheckoutEditor::get_bump_editor_data(),
			]
		);
	}

	/**
	 * POST /checkoutwc/v1/slots
	 *
	 * Accepts `assignments` (slot ID → list of assignment items) and
	 * `custom_html_blocks` (UUID → {name, content}), validates them lightly,
	 * then persists via SlotManager.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function save_slots( WP_REST_Request $request ): WP_REST_Response {
		$manager     = SlotManager::instance();
		$assignments = $request->get_param( 'assignments' );
		$html_blocks = $request->get_param( 'custom_html_blocks' ) ?? [];

		// Sanitise assignments.
		$clean_assignments = [];
		$valid_slot_ids    = array_keys( SlotManager::get_slot_hook_map() );

		foreach ( (array) $assignments as $slot_id => $items ) {
			if ( ! in_array( $slot_id, $valid_slot_ids, true ) ) {
				continue;
			}

			$clean_items = [];
			foreach ( (array) $items as $item ) {
				$type = sanitize_text_field( $item['type'] ?? '' );

				if ( ! in_array( $type, [ 'order_bump', 'trust_badges', 'review_badges', 'custom_html', 'heading', 'custom_field' ], true ) ) {
					continue;
				}

				$clean_item = [
					'type'       => $type,
					'sort_order' => (int) ( $item['sort_order'] ?? 0 ),
				];

				if ( 'order_bump' === $type ) {
					$clean_item['id'] = (int) ( $item['id'] ?? 0 );
					if ( ! $clean_item['id'] ) {
						continue;
					}
				}

				if ( 'custom_html' === $type ) {
					$clean_item['id'] = sanitize_text_field( $item['id'] ?? '' );
					if ( ! $clean_item['id'] ) {
						continue;
					}
				}

				// Custom fields are referenced by their slug ID; the definition lives in its own store.
				if ( 'custom_field' === $type ) {
					$clean_item['id'] = sanitize_key( $item['id'] ?? '' );
					if ( ! $clean_item['id'] ) {
						continue;
					}
				}

				// Each trust badge is placed individually by its ID (e.g. "tb-0").
				if ( 'trust_badges' === $type ) {
					$clean_item['id'] = sanitize_text_field( $item['id'] ?? '' );
					if ( ! $clean_item['id'] ) {
						continue;
					}
				}

				// Heading blocks store text/level inline on the assignment.
				if ( 'heading' === $type ) {
					$clean_item['text']  = sanitize_text_field( $item['text'] ?? '' );
					$clean_item['level'] = max( 1, min( 6, (int) ( $item['level'] ?? 4 ) ) );
				}

				// Optional margin override — allow only CSS-safe characters.
				$raw_margin = sanitize_text_field( $item['margin'] ?? '' );
				if ( $raw_margin !== '' ) {
					$clean_margin = preg_replace( '/[^0-9a-zA-Z .%\s\-]/', '', $raw_margin );
					if ( $clean_margin !== '' ) {
						$clean_item['margin'] = $clean_margin;
					}
				}

				$clean_items[] = $clean_item;
			}

			$clean_assignments[ $slot_id ] = $clean_items;
		}

		// Sanitise custom HTML blocks.
		$clean_html_blocks = [];
		foreach ( (array) $html_blocks as $uuid => $block ) {
			$uuid = sanitize_text_field( $uuid );
			if ( ! $uuid ) {
				continue;
			}
			$clean_html_blocks[ $uuid ] = [
				'name'    => sanitize_text_field( $block['name'] ?? '' ),
				'content' => wp_kses_post( $block['content'] ?? '' ),
			];
		}

		// A payload with no items at all, when slots already hold some, means the client sent nothing
		// rather than the merchant having emptied every slot by hand. Writing it replaces the whole
		// registry with nothing, which is how an entire slot configuration disappears with no way back.
		// The cost of the guard is that genuinely clearing every slot at once does not stick; the
		// merchant sees the items return on reload, which is recoverable in a way that data loss is not.
		if ( $this->has_no_items( $clean_assignments ) && ! $this->has_no_items( $manager->get_slots() ) ) {
			return rest_ensure_response(
				[
					'success' => false,
					'message' => __( 'No slot assignments were received, so the existing ones were left in place.', 'checkout-wc' ),
				]
			);
		}

		$manager->save_slots( $clean_assignments );
		$manager->save_custom_html_blocks( $clean_html_blocks );
		$manager->sync_bump_location_meta( $clean_assignments );

		// A client that does not send custom_fields at all leaves the store untouched.
		$custom_fields = $request->get_param( 'custom_fields' );

		if ( null !== $custom_fields ) {
			$this->save_custom_fields( (array) $custom_fields, $clean_assignments );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Returns whether a set of slot assignments contains no items whatsoever.
	 *
	 * @since 11.4.0
	 *
	 * @param array<string, mixed> $assignments
	 *
	 * @return bool
	 */
	private function has_no_items( array $assignments ): bool {
		foreach ( $assignments as $items ) {
			if ( ! empty( $items ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Persists custom field definitions exactly as the editor sends them.
	 *
	 * Definitions are deliberately not pruned to what the slots reference. Taking a field out of a
	 * slot is not the same as deleting it — the definition stays available in the editor's "+" picker
	 * so it can be placed again, keeping its options, pricing and display conditions. Only an
	 * explicit delete removes one, which the editor performs by omitting it from this payload.
	 *
	 * That makes the editor authoritative about which fields exist, so an empty set is treated with
	 * suspicion where it contradicts the request: a client that omits the key entirely is handled by
	 * the caller, and one that posts an empty set while its own assignments still reference fields is
	 * incoherent, so it is skipped rather than destroying the configuration behind those items. An
	 * empty set with nothing referencing a field is legitimate — that is a deleted last field.
	 *
	 * @since 11.4.0
	 *
	 * @param array<string, mixed> $custom_fields Raw definitions keyed by field ID.
	 * @param array<string, mixed> $assignments   Sanitised slot assignments from this same request.
	 *
	 * @return void
	 */
	private function save_custom_fields( array $custom_fields, array $assignments ): void {
		$manager  = CustomFieldManager::instance();
		$existing = $manager->get_definitions();

		$referenced = [];

		foreach ( $assignments as $items ) {
			foreach ( (array) $items as $item ) {
				if ( 'custom_field' === ( $item['type'] ?? '' ) && ! empty( $item['id'] ) ) {
					$referenced[ (string) $item['id'] ] = true;
				}
			}
		}

		if ( empty( $custom_fields ) && ! empty( $existing ) && ! empty( $referenced ) ) {
			return;
		}

		$manager->save_fields( $this->sanitize_custom_fields( $custom_fields ) );
	}

	/**
	 * Sanitises merchant-authored custom field definitions.
	 *
	 * @since 11.4.0
	 *
	 * @param array<string, mixed> $fields Raw definitions keyed by field ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function sanitize_custom_fields( array $fields ): array {
		$stored = CustomFieldManager::instance()->get_definitions();
		$clean  = [];

		foreach ( $fields as $field_id => $definition ) {
			$field_id = sanitize_key( $field_id );

			if ( ! $field_id || ! is_array( $definition ) ) {
				continue;
			}

			// A stored field whose type the plan no longer covers is read-only: it is kept exactly
			// as stored, whatever the payload says. A lapsed licence must not quietly rewrite a
			// configuration the merchant cannot see in full, and it must not let one be recovered by
			// editing around the gate. Deleting it is still possible — that is expressed by leaving
			// the field out of the payload entirely, which never reaches this branch.
			$stored_type = isset( $stored[ $field_id ]['type'] ) ? (string) $stored[ $field_id ]['type'] : null;

			if ( null !== $stored_type && ! CustomFieldManager::type_is_editable( $stored_type ) ) {
				$clean[ $field_id ] = $stored[ $field_id ];
				continue;
			}

			$type = sanitize_key( $definition['type'] ?? 'text' );

			if ( ! in_array( $type, CustomField::SUPPORTED_TYPES, true ) ) {
				$type = 'text';
			}

			// A locked type cannot be introduced from below the plan that owns it, whether on a new
			// field or by switching an existing one over. An existing field keeps what it had; a new
			// one is dropped rather than silently downgraded to text, which would put a field on the
			// checkout the merchant did not ask for.
			if ( ! CustomFieldManager::type_is_editable( $type ) ) {
				if ( isset( $stored[ $field_id ] ) ) {
					$clean[ $field_id ] = $stored[ $field_id ];
				}

				continue;
			}

			$clean[ $field_id ] = [
				'id'          => $field_id,
				'type'        => $type,
				'label'       => sanitize_text_field( $definition['label'] ?? '' ),
				'placeholder' => sanitize_text_field( $definition['placeholder'] ?? '' ),
				'required'    => ! empty( $definition['required'] ),
				'max_length'  => in_array( $type, CustomField::LENGTH_LIMITED_TYPES, true ) ? absint( $definition['max_length'] ?? 0 ) : 0,
				'options'     => $this->sanitize_custom_field_options( $definition['options'] ?? [] ),
				'fee'         => $this->sanitize_custom_field_fee( $definition['fee'] ?? [] ),
				'rules'       => is_array( $definition['rules'] ?? null ) ? $definition['rules'] : [],
			];
		}

		// Returned as sent, not merged over what is stored. Deleting a field is expressed by leaving
		// it out of the payload, so anything union-ed back in here would be impossible to remove. The
		// editor always posts the full set, and save_custom_fields() refuses a payload that empties
		// the store while slots still reference fields, which covers a truncated one.
		return $clean;
	}

	/**
	 * Sanitises the option list for a select or radio field.
	 *
	 * @since 11.4.0
	 *
	 * @param mixed $options
	 *
	 * @return array<int, array{value: string, label: string, fee_amount: float}>
	 */
	private function sanitize_custom_field_options( $options ): array {
		if ( ! is_array( $options ) ) {
			return [];
		}

		$clean = [];

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$label = sanitize_text_field( $option['label'] ?? '' );
			$value = sanitize_text_field( $option['value'] ?? '' );

			// Derive the stored value from the label when none was supplied, rather than dropping the
			// option — a select or radio with no options cannot be answered at all.
			if ( '' === $value ) {
				$value = sanitize_title( $label );
			}

			if ( '' === $value ) {
				continue;
			}

			$clean[] = [
				'value'      => $value,
				'label'      => '' !== $label ? $label : $value,
				'fee_amount' => max( 0, (float) ( $option['fee_amount'] ?? 0 ) ),
			];
		}

		return $clean;
	}

	/**
	 * Sanitises a field's price adjustment configuration.
	 *
	 * Kept whatever the field's type, so a fee survives a merchant switching a priced dropdown to a
	 * text field. A select or radio prices per option and ignores the field-level amount, so the
	 * amount kept here cannot charge anything on its own.
	 *
	 * @since 11.4.0
	 *
	 * @param mixed $fee
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_custom_field_fee( $fee ): array {
		$disabled = [ 'enabled' => false ];

		if ( ! is_array( $fee ) ) {
			return $disabled;
		}

		if ( empty( $fee['enabled'] ) ) {
			return $disabled;
		}

		$mode = 'percent' === ( $fee['mode'] ?? 'flat' ) ? 'percent' : 'flat';

		// Tax class slugs are validated against WooCommerce's own list; an unknown value silently
		// bills at the standard rate, so fall back to it explicitly instead.
		$tax_class = sanitize_text_field( $fee['tax_class'] ?? '' );

		if ( '' !== $tax_class && ! in_array( $tax_class, WC_Tax::get_tax_class_slugs(), true ) ) {
			$tax_class = '';
		}

		// Normalised rather than stored raw, so a superseded basis key is upgraded on save instead of
		// lingering. Taxable and tax class are kept as given even when tax is switched off in
		// WooCommerce: the controls are hidden in that state, and discarding the values would lose the
		// merchant's intent if they turn tax back on.
		$normalised = PercentageBasis::normalise(
			sanitize_key( $fee['basis'] ?? '' ),
			! empty( $fee['basis_include_tax'] )
		);

		return [
			'enabled'           => true,
			// Absent means on: the default is to show the customer what the fee will cost them.
			'show_in_label'     => ! array_key_exists( 'show_in_label', $fee ) || ! empty( $fee['show_in_label'] ),
			'mode'              => $mode,
			'amount'            => max( 0, (float) ( $fee['amount'] ?? 0 ) ),
			'basis'             => $normalised['basis'],
			'basis_include_tax' => $normalised['include_tax'],
			'taxable'           => ! empty( $fee['taxable'] ),
			'tax_class'         => $tax_class,
			'label'             => sanitize_text_field( $fee['label'] ?? '' ),
		];
	}

	/**
	 * Permission check — mirrors the SettingsAPI capability.
	 *
	 * @return bool
	 */
	public function can_manage(): bool {
		return current_user_can( 'cfw_manage_pages' );
	}
}
