<?php

namespace Objectiv\Plugins\Checkout\Features\CustomFields;

use Objectiv\Plugins\Checkout\Managers\CustomFieldManager;
use Objectiv\Plugins\Checkout\Model\CustomField;
use WC_Order;

/**
 * Persists custom field values to the order and shows them where merchants look.
 *
 * Two meta shapes, each with a distinct job:
 *
 *  - `_cfw_cf_{id}` holds the raw submitted value under a stable, predictable key. This is what
 *    exports, invoicing plugins and integrations read.
 *  - `_cfw_custom_fields` holds a snapshot of `{ id, label, type, value }` per field. Display reads
 *    this, so an order still renders correctly after the merchant renames or deletes the field —
 *    the label at the time of purchase is what belongs on the record.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Features\CustomFields
 */
class OrderData {

	/**
	 * Order meta key holding the display snapshot.
	 *
	 * @var string
	 */
	const SNAPSHOT_META_KEY = '_cfw_custom_fields';

	/**
	 * Order meta key listing the required fields the order arrived without, as `[ id => label ]`.
	 *
	 * Deliberately separate from the snapshot rather than an entry within it. The snapshot feeds the
	 * customer's order details, the thank-you page and the order emails, and a customer has no use
	 * for being told a field was not collected. Kept apart, only the admin order screen reads it.
	 *
	 * Recorded when the order is created rather than worked out when the order is viewed, for the
	 * same reason the snapshot is: a field renamed or deleted afterwards must not change what the
	 * order says happened.
	 *
	 * @var string
	 */
	const MISSING_META_KEY = '_cfw_custom_fields_missing';

	/**
	 * Whether the customer-facing styles have already been printed this request.
	 *
	 * Static because the two customer renderings are separate methods and, on an order view page,
	 * both can run.
	 *
	 * @var bool
	 */
	private static $printed_customer_styles = false;

	/**
	 * Registers hooks.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	public function init(): void {
		// create_order rather than update_order_meta: meta set here is written by the order's own
		// save, instead of triggering a second one after the order already exists.
		add_action( 'woocommerce_checkout_create_order', [ $this, 'persist_values' ] );

		// An order placed through the Store API never reaches the hook above, so an express checkout
		// built on wc/store/v1/checkout — the Stripe and WooPayments Express Checkout Element —
		// dropped every value the customer entered while still charging the fees those values
		// carried. This is the Store API's own seam for exactly this.
		add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $this, 'persist_values' ] );

		// after_shipping_address rather than after_order_details: it is the last hook inside the column
		// container, so output_admin_order_fields() only has to hop one level to reach full width.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ $this, 'output_admin_order_fields' ] );
		add_action( 'woocommerce_order_details_after_order_table', [ $this, 'output_customer_order_fields' ] );

		// 90 puts the section after Information, the last of the built in ones at 80.
		add_action( 'cfw_thank_you_content', [ $this, 'output_cfw_fields_section' ], 90 );
		add_filter( 'woocommerce_email_order_meta_fields', [ $this, 'add_email_order_fields' ], 10, 3 );
	}

	/**
	 * Writes the submitted custom field values onto the order.
	 *
	 * Written to be safe to run more than once against the same order. The classic checkout calls
	 * this once, but the Store API can revisit a draft order several times in a session, so an
	 * emptied value clears the meta it previously wrote rather than leaving the earlier answer
	 * stranded on the order.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order $order The order being created or updated.
	 *
	 * @return void
	 */
	public function persist_values( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$values   = ValueStore::instance()->all();
		$snapshot = [];
		$missing  = [];

		// Only eligible fields: a field hidden by its display conditions, or in a slot that never
		// rendered for this cart, did not apply to this order and must not land on it.
		foreach ( CustomFieldManager::instance()->get_eligible_fields() as $field_id => $field ) {
			$value = $values[ $field_id ] ?? null;

			if ( null === $value || '' === $value ) {
				$order->delete_meta_data( $field->get_meta_key() );

				// A required field with no value can only have got here down an express checkout
				// path: the classic checkout validates server side, whatever the browser did. Worth
				// recording, because otherwise the field is simply absent from the order screen and
				// indistinguishable from one the merchant never added. An optional field left blank
				// says nothing and is passed over.
				if ( $field->is_required() ) {
					$missing[ $field_id ] = $field->get_label();
				}

				continue;
			}

			$order->update_meta_data( $field->get_meta_key(), $value );

			$snapshot[ $field_id ] = [
				'id'    => $field_id,
				'label' => $field->get_label(),
				'type'  => $field->get_type(),
				'value' => $value,
			];
		}

		// Both keys are written or cleared on every run, so a draft order the Store API revisits ends
		// up describing the submission that finally placed it rather than an earlier attempt.
		if ( empty( $missing ) ) {
			$order->delete_meta_data( self::MISSING_META_KEY );
		} else {
			$order->update_meta_data( self::MISSING_META_KEY, $missing );
		}

		if ( empty( $snapshot ) ) {
			$order->delete_meta_data( self::SNAPSHOT_META_KEY );

			return;
		}

		$order->update_meta_data( self::SNAPSHOT_META_KEY, $snapshot );
	}

	/**
	 * Returns the required fields this order arrived without, as `[ id => label ]`.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order $order
	 *
	 * @return array<string, string>
	 */
	private function get_missing_required_fields( WC_Order $order ): array {
		$missing = $order->get_meta( self::MISSING_META_KEY );

		if ( ! is_array( $missing ) ) {
			return [];
		}

		$clean = [];

		foreach ( $missing as $field_id => $label ) {
			if ( '' !== (string) $label ) {
				$clean[ (string) $field_id ] = (string) $label;
			}
		}

		return $clean;
	}

	/**
	 * Returns the stored fields for an order, formatted for display.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order $order
	 *
	 * @return array<int, array{label: string, value: string}>
	 */
	private function get_display_rows( WC_Order $order ): array {
		$snapshot = $order->get_meta( self::SNAPSHOT_META_KEY );

		if ( ! is_array( $snapshot ) ) {
			return [];
		}

		$rows = [];

		foreach ( $snapshot as $entry ) {
			if ( ! is_array( $entry ) || '' === (string) ( $entry['label'] ?? '' ) ) {
				continue;
			}

			$rows[] = [
				'label' => (string) $entry['label'],
				'value' => $this->format_value( (string) ( $entry['id'] ?? '' ), (string) ( $entry['type'] ?? 'text' ), (string) ( $entry['value'] ?? '' ) ),
			];
		}

		return $rows;
	}

	/**
	 * Turns a stored value into something a person can read.
	 *
	 * A checkbox stores WooCommerce's raw checked value, and select and radio fields store the
	 * option value rather than its label, so both need translating back for display.
	 *
	 * @since 11.4.0
	 *
	 * @param string $field_id
	 * @param string $type
	 * @param string $value
	 *
	 * @return string
	 */
	private function format_value( string $field_id, string $type, string $value ): string {
		if ( 'checkbox' === $type ) {
			return '' !== $value ? __( 'Yes', 'checkout-wc' ) : __( 'No', 'checkout-wc' );
		}

		if ( in_array( $type, [ 'select', 'radio' ], true ) ) {
			$field = CustomFieldManager::instance()->get_field( $field_id );

			if ( $field instanceof CustomField ) {
				foreach ( $field->get_options() as $option ) {
					if ( $option['value'] === $value ) {
						return $option['label'];
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Outputs the fields on the admin order screen.
	 *
	 * WooCommerce offers no hook after the General / Billing / Shipping column container, so this renders
	 * inside the Shipping column and output_reposition_script() moves it out to full width.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function output_admin_order_fields( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$rows    = $this->get_display_rows( $order );
		$missing = $this->get_missing_required_fields( $order );

		// Missing fields alone are enough to render the block. An express order can arrive with every
		// required field uncollected, and that is precisely the order worth saying something about.
		if ( empty( $rows ) && empty( $missing ) ) {
			return;
		}

		$this->output_styles();

		echo '<div class="cfw-order-custom-fields"><div class="cfw-order-custom-fields__inner">';
		echo '<div class="cfw-order-custom-fields__head">';
		echo '<h3>' . esc_html__( 'Checkout Fields', 'checkout-wc' ) . '</h3>';
		echo '<span class="cfw-order-custom-fields__note">' . esc_html__( 'Data collected by CheckoutWC custom fields', 'checkout-wc' ) . '</span>';
		echo '</div><div class="cfw-order-custom-fields__grid">';

		foreach ( $rows as $row ) {
			// Give long or multi-line answers two columns to wrap in. Keying this off the field type instead
			// would widen a textarea holding one short word, leaving a hole in the grid.
			$is_wide = mb_strlen( $row['value'] ) > 60 || false !== strpos( $row['value'], "\n" );
			$classes = $is_wide ? 'cfw-order-custom-fields__field cfw-order-custom-fields__field--wide' : 'cfw-order-custom-fields__field';

			echo '<div class="' . esc_attr( $classes ) . '">';
			echo '<span class="cfw-order-custom-fields__label">' . esc_html( $row['label'] ) . '</span>';
			echo '<span class="cfw-order-custom-fields__value">' . nl2br( esc_html( $row['value'] ) ) . '</span>';
			echo '</div>';
		}

		// Listed after everything that was collected, so the order reads as its answers first and the
		// gaps afterwards.
		foreach ( $missing as $label ) {
			echo '<div class="cfw-order-custom-fields__field">';
			echo '<span class="cfw-order-custom-fields__label">' . esc_html( $label ) . '</span>';
			echo '<span class="cfw-order-custom-fields__value cfw-order-custom-fields__value--missing">';
			echo esc_html__( 'Not collected - placed with express checkout', 'checkout-wc' );
			echo '</span>';
			echo '</div>';
		}

		echo '</div></div></div>';

		$this->output_reposition_script();
	}

	/**
	 * Outputs the styles for the admin order screen fields.
	 *
	 * The gap above the rule is padding rather than margin because a cleared element's top margin is
	 * absorbed as clearance, which left the rule sitting closer to the columns than to the heading.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	private function output_styles(): void {
		?>
		<style>
			.cfw-order-custom-fields {
				clear: both;
				padding-top: 22px;
			}

			.cfw-order-custom-fields__inner {
				border-top: 1px solid #dcdcde;
				padding-top: 22px;
			}

			.cfw-order-custom-fields__head {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 4px 10px;
				margin: 0 0 14px;
			}

			#order_data .cfw-order-custom-fields h3 {
				margin: 0;
				padding: 0;
			}

			.cfw-order-custom-fields__note {
				font-size: 10px;
				font-weight: 400;
				color: #787c82;
			}

			.cfw-order-custom-fields__grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
				gap: 14px 24px;
			}

			.cfw-order-custom-fields__field {
				min-width: 0;
			}

			.cfw-order-custom-fields__field--wide {
				grid-column: span 2;
			}

			.cfw-order-custom-fields__label {
				display: block;
				margin-bottom: 2px;
				font-size: 11px;
				font-weight: 600;
				letter-spacing: .03em;
				text-transform: uppercase;
				color: #646970;
			}

			.cfw-order-custom-fields__value {
				display: block;
				color: #1d2327;
				word-break: break-word;
			}

			.cfw-order-custom-fields__value--missing {
				color: #646970;
				font-style: italic;
			}
		</style>
		<?php
	}

	/**
	 * Moves the rendered fields out of the Shipping column to sit full width below all three columns.
	 *
	 * This runs during parsing, while the column container is still the parser's open element, so the
	 * fields are already in place on first paint. If anything is missing the markup simply stays put.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	private function output_reposition_script(): void {
		?>
		<script>
			( function () {
				var fields    = document.querySelector( '.cfw-order-custom-fields' );
				var container = document.querySelector( '#order_data .order_data_column_container' );

				if ( fields && container && container.parentNode ) {
					container.parentNode.insertBefore( fields, container.nextSibling );
				}
			} )();
		</script>
		<?php
	}

	/**
	 * Outputs the styles shared by both customer-facing renderings of the fields.
	 *
	 * A value can be a single unbroken token — an order reference, a URL, an engraving written
	 * without spaces — and nothing above it constrains one. Neither the section grid nor
	 * WooCommerce's order table breaks a long word, so the value runs out of its column, across the
	 * text of the one beside it and past the edge of the section. `anywhere` rather than
	 * `break-word` because these values sit in grid columns: it shrinks the min-content width too,
	 * so a long value cannot force its column wider instead of wrapping.
	 *
	 * Inline rather than in the frontend stylesheet because the table rendering also serves
	 * WooCommerce's own order pages, where CheckoutWC's stylesheet is not necessarily loaded.
	 *
	 * @since 11.4.0
	 *
	 * @return void
	 */
	private function output_customer_styles(): void {
		if ( self::$printed_customer_styles ) {
			return;
		}

		self::$printed_customer_styles = true;
		?>
		<style>
			.cfw-order-additional-details p,
			.cfw-order-additional-details td {
				/* Safari below 15.4, which does not support `anywhere`. */
				word-break: break-word;
				overflow-wrap: anywhere;
			}
		</style>
		<?php
	}

	/**
	 * Outputs the fields beneath WooCommerce's order details table.
	 *
	 * Covers WooCommerce's own thank you page and order view. CFW's templates fire this hook too,
	 * from cfw_customer_information(), so they are skipped here and served by output_cfw_fields_section()
	 * in the section shape that page is built from — otherwise the fields would render twice.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function output_customer_order_fields( $order ): void {
		if ( ! $order instanceof WC_Order || ( is_order_received_page() && cfw_is_thank_you_page_active() ) ) {
			return;
		}

		$rows = $this->get_display_rows( $order );

		if ( empty( $rows ) ) {
			return;
		}

		$this->output_customer_styles();

		// The WooCommerce table classes make this read as a continuation of the order details table
		// it sits directly beneath, under whatever the theme applies.
		echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Additional details', 'checkout-wc' ) . '</h2>';
		echo '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details cfw-order-additional-details"><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td>' . nl2br( esc_html( $row['value'] ) ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Outputs the fields as their own section on CFW's thank you page and order view.
	 *
	 * No page test: the hook only fires from CFW's own templates, which are also the only pages
	 * built out of bordered sections. The fields get one of their own rather than trailing the end
	 * of Information unlabelled.
	 *
	 * @since 11.4.0
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function output_cfw_fields_section( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$rows = $this->get_display_rows( $order );

		if ( empty( $rows ) ) {
			return;
		}

		// A closure rather than a callable pair: the helper is a global function, so it cannot reach
		// a private method of this class.
		cfw_thank_you_section_auto_wrap(
			function () use ( $rows ) {
				$this->output_cfw_fields( $rows );
			},
			'cfw-additional-details'
		);
	}

	/**
	 * Outputs the fields as label-above-value columns matching CFW's own sections.
	 *
	 * Every detail on those pages is an h6 label above its value in a two column grid, both already
	 * styled by the section rules. The one thing those rules do not cover is a value that never
	 * wraps, which output_customer_styles() handles.
	 *
	 * @since 11.4.0
	 *
	 * @param array<int, array{label: string, value: string}> $rows
	 *
	 * @return void
	 */
	private function output_cfw_fields( array $rows ): void {
		$this->output_customer_styles();

		echo '<h3>' . esc_html__( 'Additional details', 'checkout-wc' ) . '</h3>';
		echo '<div class="row cfw-order-additional-details">';

		foreach ( $rows as $row ) {
			echo '<div class="col-lg-6">';
			echo '<h6>' . esc_html( $row['label'] ) . '</h6>';
			echo '<p>' . nl2br( esc_html( $row['value'] ) ) . '</p>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Adds the fields to order emails.
	 *
	 * WooCommerce renders whatever this filter returns, so no template work is needed.
	 *
	 * @since 11.4.0
	 *
	 * @param array    $fields        Existing email meta fields.
	 * @param bool     $sent_to_admin Whether the email is going to the merchant.
	 * @param WC_Order $order         The order.
	 *
	 * @return array
	 */
	public function add_email_order_fields( $fields, $sent_to_admin = false, $order = null ) {
		if ( ! is_array( $fields ) || ! $order instanceof WC_Order ) {
			return $fields;
		}

		foreach ( $this->get_display_rows( $order ) as $index => $row ) {
			$fields[ 'cfw_custom_field_' . $index ] = [
				'label' => $row['label'],
				'value' => $row['value'],
			];
		}

		return $fields;
	}
}
