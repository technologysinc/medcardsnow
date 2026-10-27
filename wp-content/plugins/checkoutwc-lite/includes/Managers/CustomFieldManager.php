<?php

namespace Objectiv\Plugins\Checkout\Managers;

use Objectiv\Plugins\Checkout\Model\CustomField;
use Objectiv\Plugins\Checkout\Model\RulesProcessor;
use Objectiv\Plugins\Checkout\SingletonAbstract;

/**
 * Stores and resolves merchant-authored checkout fields.
 *
 * Definitions live in a single JSON option keyed by field ID, mirroring the custom HTML block
 * store, so the editor preview transient can overlay them wholesale. Slot assignments reference a
 * field by ID; the definition is never duplicated into the assignment.
 *
 * `get_active_fields()` is the one place that decides which fields apply to the current request.
 * Rendering, validation and fee calculation all read it, because if any of them disagreed a
 * customer could be blocked by a required field that never rendered, or charged for a field that
 * was hidden.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Managers
 */
class CustomFieldManager extends SingletonAbstract {

	/**
	 * WordPress option key for field definitions, keyed by field ID.
	 *
	 * @var string
	 */
	const FIELDS_OPTION = '_cfw_checkout_custom_fields';

	/**
	 * WordPress option key holding a counter bumped on every save.
	 *
	 * A version stamp for the field configuration, published in the checkout data payload and
	 * returned by the slots API. Nothing consumes it yet. It was added for a reload — a merchant
	 * adding a required field while a customer sits on checkout leaves that customer with a field
	 * enforced server-side and absent from their page — which was left out deliberately, because
	 * configuration only changes on Save and that window is narrow. Kept as the seam that fix would
	 * need, and as something support can compare against what a customer's page was rendered with.
	 *
	 * @var string
	 */
	const REVISION_OPTION = '_cfw_checkout_custom_fields_revision';

	/**
	 * Slots that collapse behind the "Show order summary" toggle on small screens.
	 *
	 * Fields placed here are relocated into the payment step on mobile, so they stay reachable
	 * without opening the summary.
	 *
	 * @var string[]
	 */
	const CART_SUMMARY_SLOTS = [
		'before_cart_summary_items',
		'after_cart_summary_items',
		'before_cart_summary_totals',
		'after_cart_summary_totals',
	];

	/**
	 * Slots that cannot host a form field at all.
	 *
	 * In most templates the footer renders outside the checkout form, so an input there is never
	 * submitted, never validated and never persisted by the field-persistence layer.
	 *
	 * @var string[]
	 */
	const UNSUPPORTED_SLOTS = [ 'before_footer', 'after_footer' ];

	/**
	 * Memoised rule verdicts for this request, keyed by field ID and cart state.
	 *
	 * @var array<string, bool>
	 */
	protected $eligibility_cache = [];

	/**
	 * Returns the raw field definitions keyed by ID, as stored.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_definitions(): array {
		$raw   = SlotManager::instance()->get_overlaid_json_option( self::FIELDS_OPTION );
		$clean = [];

		foreach ( $raw as $id => $definition ) {
			if ( is_array( $definition ) ) {
				$clean[ (string) $id ] = $definition;
			}
		}

		return $clean;
	}

	/**
	 * Returns every defined field, keyed by ID.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, CustomField>
	 */
	public function get_fields(): array {
		$raw    = SlotManager::instance()->get_overlaid_json_option( self::FIELDS_OPTION );
		$fields = [];

		foreach ( $raw as $id => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$definition['id']       = (string) $id;
			$fields[ (string) $id ] = new CustomField( $definition );
		}

		return $fields;
	}

	/**
	 * Returns a single field by ID.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field_id
	 *
	 * @return CustomField|null
	 */
	public function get_field( string $field_id ) {
		return $this->get_fields()[ $field_id ] ?? null;
	}

	/**
	 * Returns whether the current plan may use a field of this type.
	 *
	 * Text is free on every plan; the richer types are paid. Deliberately a check on the type
	 * rather than on the field's existence, so a plan that lapses leaves the definition intact and
	 * merely stops it reaching customers — the editor surfaces it as a warning the merchant can
	 * resolve by upgrading or by deleting the field.
	 *
	 * Takes the raw type string rather than a CustomField because the REST sanitiser has to make
	 * the same decision about an incoming definition before any model exists for it.
	 *
	 * @since 11.4.0
	 *
	 * @param string $type One of CustomField::SUPPORTED_TYPES.
	 *
	 * @return bool
	 */
	public static function type_is_available( string $type ): bool {
		if ( in_array( $type, CustomField::FREE_TYPES, true ) ) {
			return true;
		}

		return PlanManager::has_premium_plan_or_higher( CustomField::PLAN_LOCKED_TYPES_MIN_PLAN );
	}

	/**
	 * Determines whether the merchant can save a field of this type in the editor.
	 *
	 * Uses the admin plan level, so a license that isn't activated yet can still configure fields that won't render until it is.
	 *
	 * @since 11.4.1
	 *
	 * @param string $type One of CustomField::SUPPORTED_TYPES.
	 * @return bool
	 */
	public static function type_is_editable( string $type ): bool {
		if ( in_array( $type, CustomField::FREE_TYPES, true ) ) {
			return true;
		}

		return PlanManager::has_admin_plan_or_higher( CustomField::PLAN_LOCKED_TYPES_MIN_PLAN );
	}

	/**
	 * Persists field definitions and bumps the revision.
	 *
	 * @since 11.4.0
	 *
	 * @param array<string, array<string, mixed>> $fields Definitions keyed by field ID.
	 *
	 * @return void
	 */
	public function save_fields( array $fields ): void {
		update_option( self::FIELDS_OPTION, wp_json_encode( $fields ) );
		update_option( self::REVISION_OPTION, $this->get_revision() + 1 );
	}

	/**
	 * Returns the current configuration revision.
	 *
	 * @since 11.4.0
	 *
	 * @return int
	 */
	public function get_revision(): int {
		return (int) get_option( self::REVISION_OPTION, 0 );
	}

	/**
	 * Returns the slot each field is assigned to, keyed by field ID.
	 *
	 * A field placed in more than one slot resolves to its first assignment; the editor assigns a
	 * field once in practice.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, string>
	 */
	public function get_field_slot_map(): array {
		$map = [];

		foreach ( SlotManager::instance()->get_slots() as $slot_id => $items ) {
			foreach ( (array) $items as $item ) {
				if ( ! is_array( $item ) || 'custom_field' !== ( $item['type'] ?? '' ) ) {
					continue;
				}

				$field_id = (string) ( $item['id'] ?? '' );

				if ( '' === $field_id || isset( $map[ $field_id ] ) ) {
					continue;
				}

				$map[ $field_id ] = (string) $slot_id;
			}
		}

		return $map;
	}

	/**
	 * Returns the fields that are assigned to a slot which reaches the customer for this cart.
	 *
	 * This is placement eligibility only — it does not evaluate display rules. Callers that care
	 * about rules layer them on top.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, CustomField>
	 */
	public function get_active_fields(): array {
		$fields       = $this->get_fields();
		$slot_map     = $this->get_field_slot_map();
		$slot_manager = SlotManager::instance();
		$active       = [];

		foreach ( $slot_map as $field_id => $slot_id ) {
			if ( ! isset( $fields[ $field_id ] ) ) {
				continue;
			}

			// A type the plan no longer covers drops out here rather than at the render call, so
			// validation, fees and order data all agree it is not part of this checkout.
			if ( ! self::type_is_available( $fields[ $field_id ]->get_type() ) ) {
				continue;
			}

			if ( in_array( $slot_id, self::UNSUPPORTED_SLOTS, true ) ) {
				continue;
			}

			if ( ! $slot_manager->slot_is_active( $slot_id ) ) {
				continue;
			}

			$active[ $field_id ] = $fields[ $field_id ];
		}

		return $active;
	}

	/**
	 * Returns whether a field's display conditions are satisfied for the current cart.
	 *
	 * An empty rule set always passes. Results are memoised per cart state because the rule engine
	 * runs order queries for its customer-history conditions, and totals are recalculated several
	 * times per checkout refresh.
	 *
	 * @since 11.4.0
	 *
	 * @param CustomField $field
	 *
	 * @return bool
	 */
	public function field_is_eligible( CustomField $field ): bool {
		$rules = $field->get_rules();

		if ( empty( $rules ) ) {
			return true;
		}

		$cart = WC()->cart;

		if ( ! $cart ) {
			return false;
		}

		$cache_key = $field->get_id() . '|' . $cart->get_cart_hash() . '|' . (string) $cart->get_total( 'edit' );

		if ( isset( $this->eligibility_cache[ $cache_key ] ) ) {
			return $this->eligibility_cache[ $cache_key ];
		}

		$processor = new RulesProcessor( $rules, null, $cart );
		$result    = $processor->evaluate();

		if ( ! $result ) {
			cfw_debug_log( 'Custom field hidden by display conditions: ' . $field->get_id() );
		}

		$this->eligibility_cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Returns the fields that both reach the customer and pass their display conditions.
	 *
	 * This is the set a submission is validated against. Anything outside it must not be enforced:
	 * the customer either cannot see it or it does not apply to their cart.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, CustomField>
	 */
	public function get_eligible_fields(): array {
		return array_filter(
			$this->get_active_fields(),
			function ( CustomField $field ): bool {
				return $this->field_is_eligible( $field );
			}
		);
	}

	/**
	 * Returns whether express checkout should be withheld from this cart.
	 *
	 * Express gateways submit their own payload and never carry a custom field, so an order placed
	 * that way can arrive without one the merchant marked required. Permitted by default, on the
	 * grounds that a merchant who has configured express checkout should not silently lose it; the
	 * setting is there for a merchant who would rather withhold express than take an incomplete
	 * order, and the field editor spells out which of those is in force.
	 *
	 * The decision is store-level because the outcome is: express is one row of buttons for the
	 * whole page, so it is either offered or it is not. Eligibility rather than mere existence
	 * though, so a required field gated behind display conditions only withholds express from the
	 * carts it actually reaches.
	 *
	 * @since 11.4.0
	 *
	 * @return bool
	 */
	public function express_checkout_is_suppressed(): bool {
		// Tested for the opt-out rather than the opt-in, so a store whose setting has not been
		// registered yet behaves like the default instead of the strict case.
		if ( 'no' !== SettingsManager::instance()->get_setting( 'allow_express_without_required_custom_fields' ) ) {
			return false;
		}

		foreach ( $this->get_eligible_fields() as $field ) {
			if ( $field->is_required() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the visibility state of every active field, keyed by field ID.
	 *
	 * Sent to the frontend on load and refreshed on every checkout update, so a field whose
	 * conditions start or stop matching is revealed or hidden without re-rendering its markup —
	 * re-rendering would discard whatever the customer had typed.
	 *
	 * Always keyed by ID rather than a list: the checkout data payload is merged recursively, and a
	 * shrinking list would merge by index and leave stale entries behind.
	 *
	 * @since 11.4.0
	 *
	 * @return array<string, bool>
	 */
	public function get_visibility_map(): array {
		$map = [];

		foreach ( $this->get_active_fields() as $field_id => $field ) {
			$map[ $field_id ] = $this->field_is_eligible( $field );
		}

		return $map;
	}
}
