<?php

namespace Objectiv\Plugins\Checkout\Compatibility\Traits;

/**
 * Resolves Store Policy pages to their translation in the active language.
 *
 * Store Policies are localized to the front end as fixed page IDs, and the modal fetches that exact
 * ID over the REST API, so a translated checkout shows default language content unless the ID is
 * remapped here. Used by the WPML and Polylang compatibility classes.
 */
trait TranslatesStorePoliciesTrait {
	/**
	 * Swap each configured Store Policy page for its translation
	 *
	 * @param array $event_object The localized event object.
	 * @return array
	 */
	public function translate_store_policies( array $event_object ): array {
		$policies = $event_object['settings']['store_policies'] ?? [];

		if ( empty( $policies ) || ! is_array( $policies ) ) {
			return $event_object;
		}

		foreach ( $policies as $index => $policy ) {
			$event_object['settings']['store_policies'][ $index ] = $this->translate_store_policy( (array) $policy );
		}

		return $event_object;
	}

	/**
	 * Swap a single Store Policy page for its translation
	 *
	 * @param array $policy The policy settings.
	 * @return array
	 */
	protected function translate_store_policy( array $policy ): array {
		$page = isset( $policy['page'] ) ? (array) $policy['page'] : [];

		if ( empty( $page['id'] ) ) {
			return $policy;
		}

		$page_id       = (int) $page['id'];
		$translated_id = $this->get_translated_page_id( $page_id );

		if ( $translated_id === $page_id ) {
			return $policy;
		}

		$page['id']     = $translated_id;
		$policy['page'] = $page;

		// Only swap the link text when it is still the source page title - a custom title is the
		// merchant's own copy, translated via WPML / Polylang string translation instead.
		if ( isset( $policy['title'] ) && $policy['title'] === get_the_title( $page_id ) ) {
			$translated_title = get_the_title( $translated_id );

			if ( ! empty( $translated_title ) ) {
				$policy['title'] = $translated_title;
			}
		}

		return $policy;
	}

	/**
	 * Get the ID of the page in the active language
	 *
	 * @param int $page_id The configured page ID.
	 * @return int The translated page ID, or the original when there is no translation.
	 */
	abstract protected function get_translated_page_id( int $page_id ): int;
}
