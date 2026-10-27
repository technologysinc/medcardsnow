<?php

namespace Objectiv\Plugins\Checkout\Managers;

/**
 * The curated icons a trust badge collection can be built from.
 *
 * A badge collection is a single trust badge that renders several of these icons at once, so the
 * merchant picks from a set we ship rather than sourcing and uploading artwork themselves.
 *
 * Icons are declared here rather than read from the directory: an icon needs a label and a category to
 * be presentable in the picker, and a declared map doubles as the allow-list an ID is validated against
 * before it is ever turned into a path.
 *
 * The files live under assets/images/badge-collections/, one subdirectory per set, each carrying its
 * own licence and provenance notes - see the README there before adding one.
 *
 * @link checkoutwc.com
 * @since 11.4.0
 * @package Objectiv\Plugins\Checkout\Managers
 */
class BadgeIconRegistry {

	/**
	 * The plan a merchant needs before they can build a collection.
	 *
	 * @var string
	 */
	const MIN_PLAN = 'plus';

	/**
	 * Directory every icon set lives under, relative to the plugin root.
	 *
	 * @var string
	 */
	const ICON_DIR = 'assets/images/badge-collections/';

	/**
	 * The icon sets that ship with the plugin.
	 *
	 * One block per set: its own subdirectory, a label for the category, and its icons as ID => label.
	 * Adding a set is a new block plus the files - the paths are built from `dir`, so nothing else needs
	 * changing. The picker is one flat grid today, so `label` and `category` are carried but not yet
	 * shown; they are what a grouped or filtered gallery would read once there is more than one set.
	 *
	 * `recolorable` says whether the merchant's icon colour may be applied to the set. It is true only
	 * for artwork we draw ourselves in `currentColor`; the payment marks are third-party logos and are
	 * reproduced in their published brand colours, which is a condition of using them at all. The flag
	 * travels with every icon so the front end and the editor make the same call without either of them
	 * knowing which set an icon came from.
	 *
	 * Brand names are proper nouns and stay untranslated. Everything else here - the category labels and
	 * the labels on our own icons - is translated, because it is read out as alt text. Our artwork has
	 * its wording drawn into the SVG in English, so a translated label describes the image rather than
	 * transcribing it, which is the more useful of the two.
	 *
	 * IDs are global rather than per set, because an ID is what gets stored on a merchant's badge. Two
	 * sets must never declare the same ID, and an ID must never be reused for different artwork - a
	 * stored badge would silently start showing something else.
	 *
	 * @return array<string, array{label: string, dir: string, recolorable: bool, icons: array<string, string>}>
	 */
	private static function get_sets(): array {
		return [
			'payment'   => [
				'label'       => __( 'Payment methods', 'checkout-wc' ),
				'dir'         => 'payment-icons/',
				'recolorable' => false,
				'icons'       => [
					'visa'        => 'Visa',
					'mastercard'  => 'Mastercard',
					'amex'        => 'American Express',
					'discover'    => 'Discover',
					'diners'      => 'Diners Club',
					'jcb'         => 'JCB',
					'unionpay'    => 'UnionPay',
					'paypal'      => 'PayPal',
					'apple-pay'   => 'Apple Pay',
					'google-pay'  => 'Google Pay',
					'amazon-pay'  => 'Amazon Pay',
					'samsung-pay' => 'Samsung Pay',
					'klarna'      => 'Klarna',
					'skrill'      => 'Skrill',
				],
			],
			'security'  => [
				'label'       => __( 'Security', 'checkout-wc' ),
				'dir'         => 'security-icons/',
				'recolorable' => true,
				'icons'       => [
					'secure-checkout'   => __( 'Secure checkout', 'checkout-wc' ),
					'encryption-256'    => __( '256-bit encryption', 'checkout-wc' ),
					'privacy-protected' => __( 'Privacy protected', 'checkout-wc' ),
				],
			],
			'guarantee' => [
				'label'       => __( 'Guarantees', 'checkout-wc' ),
				'dir'         => 'guarantee-icons/',
				'recolorable' => true,
				'icons'       => [
					'money-back-30'           => __( '30-day money back guarantee', 'checkout-wc' ),
					'money-back-60'           => __( '60-day money back guarantee', 'checkout-wc' ),
					'money-back-90'           => __( '90-day money back guarantee', 'checkout-wc' ),
					'free-shipping'           => __( 'Free shipping', 'checkout-wc' ),
					'free-returns'            => __( 'Free returns', 'checkout-wc' ),
					'satisfaction-guaranteed' => __( 'Satisfaction guaranteed', 'checkout-wc' ),
				],
			],
		];
	}

	/**
	 * Every icon that can go in a collection, keyed by ID.
	 *
	 * @return array<string, array{label: string, file: string, category: string, recolorable: bool}>
	 */
	public static function get_icons(): array {
		$icons = [];

		foreach ( self::get_sets() as $category => $set ) {
			foreach ( $set['icons'] as $icon_id => $label ) {
				$icons[ $icon_id ] = [
					'label'       => $label,
					'file'        => $set['dir'] . $icon_id . '.svg',
					'category'    => $category,
					'recolorable' => $set['recolorable'],
				];
			}
		}

		return $icons;
	}

	/**
	 * Every icon, shaped for the editor's picker.
	 *
	 * A list rather than a map so the picker renders in the order declared above, which puts the marks
	 * most stores accept first.
	 *
	 * Icons whose file is not on disk are left out rather than offered as a broken image. That is the
	 * normal state in the Lite build, where bin/build-lite.sh removes the artwork outright - without this
	 * check Lite would advertise icons it has just deleted.
	 *
	 * @return array<int, array{id: string, label: string, url: string, category: string, recolorable: bool}>
	 */
	public static function get_picker_icons(): array {
		$icons = [];

		foreach ( self::get_icons() as $icon_id => $icon ) {
			if ( ! file_exists( trailingslashit( CFW_PATH ) . self::ICON_DIR . $icon['file'] ) ) {
				continue;
			}

			$icons[] = [
				'id'          => $icon_id,
				'label'       => $icon['label'],
				'url'         => self::get_icon_url( $icon_id ),
				'category'    => $icon['category'],
				'recolorable' => $icon['recolorable'],
			];
		}

		return $icons;
	}

	/**
	 * Whether an ID names an icon we actually ship.
	 *
	 * Stored badge data is never sanitised on the way in ( see SettingsManagerAbstract::save_settings ),
	 * so every ID is checked against the registry before it is used to build a path or a URL.
	 *
	 * @param string $icon_id The icon ID to check.
	 * @return bool
	 */
	public static function is_valid_id( string $icon_id ): bool {
		return array_key_exists( $icon_id, self::get_icons() );
	}

	/**
	 * The public URL for an icon, or an empty string when the ID is not one of ours.
	 *
	 * @param string $icon_id The icon ID.
	 * @return string
	 */
	public static function get_icon_url( string $icon_id ): string {
		if ( ! self::is_valid_id( $icon_id ) ) {
			return '';
		}

		$icons = self::get_icons();

		return trailingslashit( CFW_PATH_URL_BASE ) . self::ICON_DIR . $icons[ $icon_id ]['file'];
	}

	/**
	 * Resolves a stored list of icon IDs to what the front end needs to render them.
	 *
	 * Unknown IDs are dropped rather than rendered as a broken image, which is what would happen if an
	 * icon were retired from the registry while a merchant still had it in a collection.
	 *
	 * @param array $icon_ids Ordered list of icon IDs from a badge.
	 * @return array<int, array{id: string, label: string, url: string, recolorable: bool}>
	 */
	public static function resolve( array $icon_ids ): array {
		$icons    = self::get_icons();
		$resolved = [];

		foreach ( $icon_ids as $icon_id ) {
			$icon_id = (string) $icon_id;

			if ( ! isset( $icons[ $icon_id ] ) ) {
				continue;
			}

			$resolved[] = [
				'id'          => $icon_id,
				'label'       => $icons[ $icon_id ]['label'],
				'url'         => self::get_icon_url( $icon_id ),
				'recolorable' => $icons[ $icon_id ]['recolorable'],
			];
		}

		return $resolved;
	}

	/**
	 * Whether the merchant's plan lets them use badge collections.
	 *
	 * The single place the tier is decided. Trust badges themselves sit at Basic while collections sit
	 * at Plus, and that boundary is expected to move, so callers ask this rather than comparing plan
	 * levels for themselves.
	 *
	 * @return bool
	 */
	public static function collections_are_available(): bool {
		return PlanManager::has_premium_plan_or_higher( self::MIN_PLAN );
	}
}
