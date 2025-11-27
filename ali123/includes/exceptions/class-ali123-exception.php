<?php
/**
 * Custom exception class for Ali123 plugin.
 *
 * @package Ali123
 */

namespace Ali123\Exceptions;

use Exception;

/**
 * Base plugin exception for Ali123-specific errors.
 */
class Ali123_Exception extends Exception {
    /**
     * Constructor.
     *
     * @param string     $message  Exception message.
     * @param int        $code     Exception code (default 0).
     * @param \Throwable $previous Previous exception for chaining.
     */
    public function __construct( string $message = '', int $code = 0, \Throwable $previous = null ) {
        parent::__construct( $message, $code, $previous );

        // Log exception if debugging is enabled.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf( 'Ali123 Exception: %s in %s:%d', $message, $this->getFile(), $this->getLine() ) );
        }
    }

    /**
     * Get a user-friendly error message.
     *
     * @return string Formatted error message.
     */
    public function getUserMessage() : string {
        return sprintf(
            /* translators: %s: error message */
            __( 'Ali123 Error: %s', 'ali123' ),
            $this->getMessage()
        );
    }
}
