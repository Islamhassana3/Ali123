<?php
/**
 * Helper functions for Ali123 plugin.
 *
 * @package Ali123
 */

namespace Ali123;

/**
 * Sanitize recursive arrays and nested data structures.
 *
 * @param mixed $data Data to sanitize (array, string, or other scalar).
 *
 * @return mixed Sanitized data maintaining the same structure.
 */
function deep_sanitize_text_field( $data ) {
    if ( is_array( $data ) ) {
        return array_map( __NAMESPACE__ . '\\deep_sanitize_text_field', $data );
    }

    if ( is_scalar( $data ) && ! is_bool( $data ) ) {
        return sanitize_text_field( wp_unslash( $data ) );
    }

    return $data;
}

/**
 * Helper for safe array access with default value support.
 *
 * @param array  $array   Array to inspect.
 * @param string $key     Key to read.
 * @param mixed  $default Default value if key doesn't exist. Default null.
 *
 * @return mixed Value at key or default value.
 */
function array_get( array $array, string $key, $default = null ) {
    return $array[ $key ] ?? $default;
}
