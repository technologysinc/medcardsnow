<?php

namespace Objectiv\Plugins\Checkout\Renderers;

use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Managers\SlotManager;
use Objectiv\Plugins\Checkout\Managers\SettingsManager;
use Objectiv\Plugins\Checkout\SingletonAbstract;

/**
 * Registers WordPress action hooks for each populated slot and renders the
 * assigned items at the correct position in the checkout template.
 *
 * The renderer deliberately keeps its output in sync with the container-div
 * convention used by the existing JS checkout bundle:
 *
 *   - Order bumps → <div id="cfw_bumps_{location}"></div>
 *   - Trust/review badges → reuses TrustBadges HTML structure
 *   - Custom HTML → <div class="cfw-slot-html-block"> … </div>
 *
 * @link checkoutwc.com
 * @since 11.2.0
 * @package Objectiv\Plugins\Checkout\Renderers
 */
class SlotRenderer extends SingletonAbstract {

	/**
	 * The slot whose position follows the billing address across cart types.
	 */
	const BILLING_SLOT_ID = 'after_billing_address';

	/**
	 * Hook fired after the billing address on the information step, i.e. for
	 * no-shipping (e.g. digital) carts where billing is collected up front.
	 */
	const INFO_STEP_BILLING_HOOK = 'cfw_checkout_after_billing_address';

	/**
	 * Badge ID => template, built on first use. Null until then.
	 *
	 * @var array<string, string>|null
	 */
	private $badge_templates = null;

	/**
	 * Registers the WordPress action hooks for all non-empty slots.
	 *
	 * In editor preview mode every slot is registered (even empty ones) so that
	 * the admin panel can highlight their position in the iframe on hover.
	 *
	 * Call this on the `init` action (after SlotManager migration has run).
	 */
	public function init(): void {
		$slot_manager = SlotManager::instance();
		$assignments  = $slot_manager->get_slots();
		$hook_map     = SlotManager::get_slot_hook_map();
		$is_preview   = $this->is_editor_preview();

		foreach ( $hook_map as $slot_id => $hook_config ) {
			$has_items = ! empty( $assignments[ $slot_id ] );

			// In normal (non-preview) mode skip slots with no assigned items.
			if ( ! $has_items && ! $is_preview ) {
				continue;
			}

			// The "After Billing Address" slot follows the billing address, which
			// lives on the payment step for shipping carts but on the information
			// step for no-shipping (e.g. digital) carts. Register both positions,
			// each guarded so the slot renders exactly once per cart type.
			if ( self::BILLING_SLOT_ID === $slot_id ) {
				$this->register_slot_output(
					$slot_id,
					$hook_config['hook'],
					$hook_config['priority'],
					$has_items,
					$is_preview,
					static function (): bool {
						return WC()->cart && WC()->cart->needs_shipping_address();
					}
				);
				$this->register_slot_output(
					$slot_id,
					self::INFO_STEP_BILLING_HOOK,
					10,
					$has_items,
					$is_preview,
					static function (): bool {
						return WC()->cart && ! WC()->cart->needs_shipping();
					}
				);
				continue;
			}

			$this->register_slot_output( $slot_id, $hook_config['hook'], $hook_config['priority'], $has_items, $is_preview );
		}
	}

	/**
	 * Registers a single slot-output action on a hook.
	 *
	 * @param string        $slot_id    The slot identifier.
	 * @param string        $hook       The action hook to render on.
	 * @param int           $priority   The hook priority.
	 * @param bool          $has_items  Whether the slot has assigned items.
	 * @param bool          $is_preview Whether this is an editor preview load.
	 * @param callable|null $condition  Optional predicate; when it returns false the slot does not render.
	 */
	private function register_slot_output( string $slot_id, string $hook, int $priority, bool $has_items, bool $is_preview, ?callable $condition = null ): void {
		add_action(
			$hook,
			function () use ( $slot_id, $has_items, $is_preview, $condition ) {
				if ( null !== $condition && ! $condition() ) {
					return;
				}
				$classes = 'cfw-slot-wrap';
				if ( $is_preview ) {
					$classes .= ' cfw-slot-marker';
				}
				echo '<div class="' . esc_attr( $classes ) . '" data-cfw-slot="' . esc_attr( $slot_id ) . '"';
				if ( $is_preview ) {
					echo ' data-cfw-slot-marker="' . esc_attr( $slot_id ) . '"';
				}
				echo '>';
				if ( $has_items ) {
					$this->render_slot( $slot_id );
				}
				echo '</div>';
			},
			$priority
		);
	}

	/**
	 * Returns true when the current request is an editor preview iframe load.
	 *
	 * @return bool
	 */
	private function is_editor_preview(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['cfw-editor-preview'] ) && '1' === $_GET['cfw-editor-preview'];
	}

	// ------------------------------------------------------------------
	// Slot rendering
	// ------------------------------------------------------------------

	/**
	 * Renders all items assigned to a slot in sort_order sequence.
	 *
	 * Consecutive trust-badge items are grouped into a single container so that
	 * they share the same CSS grid and multi-column layout.  Non-consecutive runs
	 * (e.g. trust badge → review badge → trust badge) each get their own
	 * container with a unique mount-point ID so they render at the correct
	 * position in the output, respecting sort order.
	 *
	 * @param string $slot_id The slot identifier.
	 */
	public function render_slot( string $slot_id ): void {
		$assignments                  = SlotManager::instance()->get_slots();
		$html_blocks                  = SlotManager::instance()->get_custom_html_blocks();
		$slot_items                   = $assignments[ $slot_id ] ?? [];
		$output_bump_ids              = []; // Prevent processing the same bump/test twice per slot.
		$slot_bump_container_rendered = false; // All order bumps in a slot share one container div.

		usort( $slot_items, fn( $a, $b ) => ( $a['sort_order'] ?? 0 ) - ( $b['sort_order'] ?? 0 ) );

		$slot_items = array_values( $slot_items );
		$count      = count( $slot_items );
		$group_idx  = 0; // Disambiguates multiple trust-badge group containers.
		$i          = 0;

		while ( $i < $count ) {
			$item   = $slot_items[ $i ];
			$type   = $item['type'] ?? '';
			$margin = sanitize_text_field( $item['margin'] ?? '' );

			// Group consecutive individual trust-badge items that share the same margin into one container.
			// A margin change breaks the group so each badge can have its own spacing.
			//
			// A collection never joins a run and never absorbs one: it lays its icons out by wrapping
			// rather than on the column grid the group container applies, so it has to end up alone in
			// its own container. It still renders through the same group markup with a single ID -
			// the React side decides the layout from the badge's template.
			if ( 'trust_badges' === $type && ! empty( $item['id'] ) ) {
				$run_ids      = [];
				$group_margin = $margin;
				$run_started  = false;
				while ( $i < $count && ( $slot_items[ $i ]['type'] ?? '' ) === 'trust_badges' && ! empty( $slot_items[ $i ]['id'] ) && sanitize_text_field( $slot_items[ $i ]['margin'] ?? '' ) === $group_margin ) {
					$is_collection = $this->is_collection_badge( (string) $slot_items[ $i ]['id'] );

					if ( $run_started && $is_collection ) {
						break; // A collection cannot be appended to a run already under way.
					}

					$run_ids[]   = (string) $slot_items[ $i ]['id'];
					$run_started = true;
					$i++;

					if ( $is_collection ) {
						break; // Nor can anything be appended after one.
					}
				}
				if ( ! empty( $run_ids ) ) {
					if ( $group_margin ) {
						echo '<div style="margin: ' . esc_attr( $group_margin ) . '">';
					}
					$this->render_trust_badges_group( $slot_id, $run_ids, $group_idx );
					if ( $group_margin ) {
						echo '</div>';
					}
					$group_idx++;
				}
				continue;
			}

			// Group consecutive custom fields into one grid row. WooCommerce stamps each field with
			// a col-lg-* class, and those need a flex .row parent to pick up the grid gutters —
			// without it a field renders mis-guttered and two half-width fields never sit side by side.
			//
			// Margins are carried along per field rather than wrapping the run, so each field keeps
			// its own spacing. Keyed by field ID: the same field twice in one slot is one field.
			if ( 'custom_field' === $type && ! empty( $item['id'] ) ) {
				$run_margins = [];
				while ( $i < $count && ( $slot_items[ $i ]['type'] ?? '' ) === 'custom_field' && ! empty( $slot_items[ $i ]['id'] ) ) {
					$run_margins[ (string) $slot_items[ $i ]['id'] ] = sanitize_text_field( $slot_items[ $i ]['margin'] ?? '' );
					$i++;
				}
				if ( ! empty( $run_margins ) ) {
					$this->render_custom_field_row( $run_margins, $slot_id );
				}
				continue;
			}

			switch ( $type ) {
				case 'order_bump':
					$bump_id = (int) ( $item['id'] ?? 0 );
					if ( $bump_id && ! in_array( $bump_id, $output_bump_ids, true ) ) {
						// All order bumps in the same slot share a single container div.
						// Per-bump margins are applied by the React OrderBumpsList component
						// using the margin value carried in the JS bump data.
						if ( ! $slot_bump_container_rendered ) {
							$this->render_order_bump_container( $slot_id );
							$slot_bump_container_rendered = true;
						}
						$output_bump_ids[] = $bump_id;
					}
					break;

				case 'review_badges':
					if ( $margin ) {
						echo '<div style="margin: ' . esc_attr( $margin ) . '">';
					}
					$this->render_review_badges( $slot_id );
					if ( $margin ) {
						echo '</div>';
					}
					break;

				case 'heading':
					if ( $margin ) {
						echo '<div style="margin: ' . esc_attr( $margin ) . '">';
					}
					$this->render_heading( $item );
					if ( $margin ) {
						echo '</div>';
					}
					break;

				case 'custom_html':
					$block_id = (string) ( $item['id'] ?? '' );
					if ( $block_id ) {
						if ( $margin ) {
							echo '<div style="margin: ' . esc_attr( $margin ) . '">';
						}
						$this->render_custom_html( $block_id, $html_blocks );
						if ( $margin ) {
							echo '</div>';
						}
					}
					break;
			}

			$i++;
		}
	}

	// ------------------------------------------------------------------
	// Type-specific renderers
	// ------------------------------------------------------------------

	/**
	 * Outputs the single bump container div for an entire slot.
	 *
	 * The div ID uses the slot_id (e.g. "above_terms_and_conditions") so that
	 * each slot gets its own unique mount point regardless of the bumps' legacy
	 * cfw_ob_display_location meta values. checkout.tsx mounts an OrderBumpsList
	 * component here and cfw_get_order_bumps_data() sets each bump's location to
	 * its assigned slot so the list filters correctly.
	 *
	 * All order_bump items inside the same slot share this single
	 * container – it is rendered only once per slot by render_slot().
	 *
	 * @param string $slot_id The slot identifier (e.g. "above_terms_and_conditions").
	 */
	private function render_order_bump_container( string $slot_id ): void {
		echo '<div id="' . esc_attr( 'cfw_bumps_' . $slot_id ) . '"></div>';
	}

	/**
	 * Whether a badge ID belongs to a collection.
	 *
	 * Slot items carry only type, id, sort_order and margin, so the badge's template has to be looked
	 * up. Memoised because render_slot() runs once per slot and the badge list is the same every time.
	 *
	 * @since 11.4.0
	 *
	 * @param string $badge_id The badge ID from a slot item.
	 * @return bool
	 */
	private function is_collection_badge( string $badge_id ): bool {
		if ( null === $this->badge_templates ) {
			$this->badge_templates = [];

			foreach ( cfw_get_trust_badges( false ) as $badge ) {
				if ( ! empty( $badge['id'] ) ) {
					$this->badge_templates[ (string) $badge['id'] ] = $badge['template'] ?? '';
				}
			}
		}

		return ( $this->badge_templates[ $badge_id ] ?? '' ) === 'collection';
	}

	/**
	 * Outputs a single grouped container for a consecutive run of individually-
	 * assigned trust badges in a slot.  checkout.tsx mounts one TrustBadges
	 * component here and passes the badge-ID array so all badges share the same
	 * CSS grid, producing the correct multi-column layout.
	 *
	 * Multiple non-consecutive runs within the same slot each get a unique mount
	 * ID via $group_idx (0 = first run, 1 = second, …). A badge collection always
	 * arrives here alone, and lays its own icons out rather than using the grid.
	 *
	 * @param string   $slot_id   The slot this block is being rendered in.
	 * @param string[] $badge_ids Ordered list of badge IDs in this run.
	 * @param int      $group_idx Zero-based index of this trust-badge group within the slot.
	 */
	private function render_trust_badges_group( string $slot_id, array $badge_ids, int $group_idx = 0 ): void {
		if ( empty( $badge_ids ) ) {
			return;
		}
		if ( SettingsManager::instance()->get_setting( 'enable_trust_badges' ) !== 'yes' ) {
			return;
		}

		$suffix         = $group_idx > 0 ? '-' . $group_idx : '';
		$mount_id       = 'cfw-trust-badges-' . $slot_id . $suffix;
		$badge_ids_json = wp_json_encode( array_values( $badge_ids ) );
		?>
		<div class="cfw-module cfw-trust-badges-position-<?php echo esc_attr( $slot_id ); ?>">
			<div class="cfw-tw">
				<div
					id="<?php echo esc_attr( $mount_id ); ?>"
					data-cfw-badge-ids="<?php echo esc_attr( $badge_ids_json ); ?>"
					data-cfw-slot-id="<?php echo esc_attr( $slot_id ); ?>"
				></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Outputs the review-badges container.
	 *
	 * Renders a dedicated container that the React ReviewBadges component mounts
	 * into.  This is separate from the trust-badges container so that review badges
	 * and guarantee/trust badges can live in different slots and use independent
	 * column settings.
	 *
	 * @param string $slot_id The slot this block is being rendered in.
	 */
	private function render_review_badges( string $slot_id ): void {
		if ( SettingsManager::instance()->get_setting( 'enable_wc_review_badges' ) !== 'yes' ) {
			return;
		}

		$mount_id = 'cfw-review-badges-' . $slot_id;
		?>
		<div class="cfw-module cfw-review-badges-position-<?php echo esc_attr( $slot_id ); ?>">
			<div class="cfw-tw">
				<div
					id="<?php echo esc_attr( $mount_id ); ?>"
					data-cfw-review-badges="true"
					data-cfw-slot-id="<?php echo esc_attr( $slot_id ); ?>"
				></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Outputs a heading block.
	 *
	 * Uses the same CSS class as the legacy trust-badges heading so existing
	 * theme styles are inherited automatically.  The heading level (h1–h6) is
	 * taken from the block data; defaults to h4.
	 *
	 * @param array{text: string, level?: int} $block The heading block data.
	 */
	private function render_heading( array $block ): void {
		$text  = $block['text'] ?? '';
		$level = max( 1, min( 6, (int) ( $block['level'] ?? 4 ) ) );
		$tag   = 'h' . $level;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag is validated above
		echo '<' . $tag . ' class="cfw-trust-badges-list-title">' . wp_kses_post( do_shortcode( $text ) ) . '</' . $tag . '>';
	}

	/**
	 * Renders a run of merchant-authored custom fields inside a single grid row.
	 *
	 * Fields go through core's woocommerce_form_field() rather than the cfw_output_fieldset path,
	 * so they pick up CheckoutWC's field augmentation (grid width, floating labels, validation
	 * attributes) from the woocommerce_form_field_args filter without belonging to any WooCommerce
	 * fieldset.
	 *
	 * @since 11.4.0
	 *
	 * @param array<string, string> $field_margins Field ID => the margin set for it in this slot, if any.
	 * @param string                $slot_id       The slot being rendered, which decides mobile repositioning.
	 *
	 * @return void
	 */
	private function render_custom_field_row( array $field_margins, string $slot_id ): void {
		// The footer slots render outside the checkout form, so a field there would be visible but
		// never submitted — the customer's answer would be discarded silently. get_active_fields()
		// already refuses to treat those as active, so skip the markup as well rather than show an
		// input that does nothing.
		if ( in_array( $slot_id, CustomFieldManager::UNSUPPORTED_SLOTS, true ) ) {
			return;
		}

		$manager  = CustomFieldManager::instance();
		$fields   = $manager->get_fields();
		$slot_map = $manager->get_field_slot_map();
		$render   = [];

		foreach ( $field_margins as $field_id => $margin ) {
			if ( ! isset( $fields[ $field_id ] ) ) {
				continue;
			}

			// Rendering reads get_fields() rather than get_active_fields(), so the plan check that
			// keeps a locked type out of validation and fees has to be repeated here — otherwise a
			// downgraded store shows an input nothing behind it will accept.
			if ( ! CustomFieldManager::type_is_available( $fields[ $field_id ]->get_type() ) ) {
				continue;
			}

			// A field belongs to one slot. The editor enforces that, but a configuration saved before
			// it did could hold the same field twice, and rendering it twice would put two inputs
			// sharing a name and an element ID in the form. get_field_slot_map() already resolves a
			// duplicate to the first slot that claims it, so defer to that and skip the rest.
			if ( isset( $slot_map[ $field_id ] ) && $slot_map[ $field_id ] !== $slot_id ) {
				continue;
			}

			$render[] = [ $fields[ $field_id ], $margin ];
		}

		if ( empty( $render ) ) {
			return;
		}

		// Cart-summary slots sit inside the order summary, which collapses on small screens. Mark
		// these fields so the frontend can move them into the payment step on mobile, the same
		// position order bumps in these slots move to.
		$in_cart_summary = in_array( $slot_id, CustomFieldManager::CART_SUMMARY_SLOTS, true );

		// Those slots render outside the step loop, so the field would inherit no validation group and
		// a required value would never be checked in the browser. Assign both submitting steps as a
		// JSON group list: on mobile the field has been moved into the payment step, on desktop the
		// summary is always visible, and either way it is validated when the order is submitted.
		$parsley_group = $in_cart_summary ? wp_json_encode( [ 'cfw-payment-method', 'cfw-order-review' ] ) : '';

		echo '<div class="row cfw-input-wrap-row">';

		foreach ( $render as list( $field, $margin ) ) {
			$key = $field->get_input_name();

			// Fields whose conditions do not currently match are still rendered, but hidden and
			// disabled. The frontend flips that state when the cart changes, which it could not do if
			// the markup were absent — and re-rendering the markup instead would wipe the customer's
			// input, since checkout refreshes never replace field markup.
			//
			// The editor preview is treated exactly like a real checkout here, so the merchant sees
			// what a customer with that preview cart sees. A conditional field is instead flagged on
			// its slot row in the editor, which also covers the conditions a preview can never
			// reproduce — user role, order history, an address not yet entered.
			$args = $field->get_form_field_args( $manager->field_is_eligible( $field ), $parsley_group );

			if ( $in_cart_summary ) {
				$args['class'][] = 'cfw-custom-field--repositions-on-mobile';
			}

			if ( '' === $margin ) {
				woocommerce_form_field( $key, $args, WC()->checkout()->get_value( $key ) );
				continue;
			}

			// The margin belongs on the field's own row, and WooCommerce offers no argument for it —
			// the container markup is built from a hardcoded format string. Take the markup back and
			// add the style to that element rather than wrapping the field in a div: the row is a grid
			// column, and a wrapper between it and .row would cost it the grid's gutters.
			$args['return'] = true;
			$html           = (string) woocommerce_form_field( $key, $args, WC()->checkout()->get_value( $key ) );

			echo str_replace( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- woocommerce_form_field() escapes its own markup; the margin is escaped here.
				'<p class="form-row',
				'<p style="margin: ' . esc_attr( $margin ) . '" class="form-row',
				$html
			);
		}

		echo '</div>';
	}

	/**
	 * Outputs an inline custom HTML block.
	 *
	 * Content is passed through wp_kses_post to allow standard HTML but strip
	 * potentially dangerous markup (scripts, event handlers, etc.).
	 *
	 * @param string $block_id   The UUID of the custom HTML block.
	 * @param array  $html_blocks The full blocks array from SlotManager.
	 */
	private function render_custom_html( string $block_id, array $html_blocks ): void {
		$block = $html_blocks[ $block_id ] ?? null;

		if ( ! $block || empty( $block['content'] ) ) {
			return;
		}

		echo '<div class="cfw-slot-html-block cfw-slot-html-block--' . esc_attr( $block_id ) . '">';
		echo wp_kses_post( do_shortcode( $block['content'] ) );
		echo '</div>';
	}
}
