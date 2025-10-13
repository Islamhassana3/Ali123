<?php
namespace Ali123;

/**
 * Sanitize recursive arrays.
 *
 * @param mixed $data Data to sanitize.
 *
 * @return mixed
 */
function deep_sanitize_text_field( $data ) {
    if ( is_array( $data ) ) {
        return array_map( __NAMESPACE__ . '\\deep_sanitize_text_field', $data );
    }

    return is_scalar( $data ) ? sanitize_text_field( wp_unslash( $data ) ) : $data;
}

/**
 * Helper for safe array access.
 *
 * @param array  $array   Array to inspect.
 * @param string $key     Key to read.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function array_get( array $array, string $key, $default = null ) {
    if ( isset( $array[ $key ] ) ) {
        return $array[ $key ];
    }

    return $default;
}
