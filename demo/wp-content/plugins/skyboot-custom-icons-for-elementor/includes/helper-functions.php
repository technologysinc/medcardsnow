<?php
if( !defined('ABSPATH') ) exit;

/**
 * Get the value of a settings field with caching.
 *
 * This function retrieves an option from a section and caches the section's
 * options array in a static variable to prevent multiple database calls per request.
 *
 * @param string $option  Settings field name.
 * @param string $section The section name this field belongs to.
 * @param string $default Default text if it's not found.
 *
 * @return mixed The value of the option.
 */
function skb_cife_get_option( $option, $section, $default = '' ) {
    // Static variable to hold our cached options.
    static $options_cache = [];

    // If we haven't fetched this section yet, get it from the database and cache it.
    if ( ! isset( $options_cache[ $section ] ) ) {
        $options_cache[ $section ] = get_option( $section );
    }

    // Now, get the specific option from our cached array.
    if ( isset( $options_cache[ $section ][ $option ] ) ) {
        return $options_cache[ $section ][ $option ];
    }

    return $default;
}    