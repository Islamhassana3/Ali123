<?php
/**
 * PSR-4 autoloader for Ali123 plugin.
 *
 * @package Ali123
 */

namespace Ali123;

/**
 * Simple PSR-4-like autoloader for the plugin.
 */
class Autoloader {
    /**
     * Namespace prefix for the autoloader.
     *
     * @var string
     */
    protected static $prefix = 'Ali123\\';

    /**
     * Base directory for the namespace prefix.
     *
     * @var string
     */
    protected static $base_dir;

    /**
     * Register the autoloader with SPL.
     *
     * @return void
     */
    public static function register() : void {
        if ( ! defined( 'ALI123_PLUGIN_DIR' ) ) {
            return;
        }

        static::$base_dir = ALI123_PLUGIN_DIR . 'includes/';
        spl_autoload_register( [ static::class, 'autoload' ] );
    }

    /**
     * Autoload callback.
     *
     * @param string $class Class being requested.
     *
     * @return void
     */
    protected static function autoload( string $class ) : void {
        // Bail if class doesn't belong to this namespace.
        if ( 0 !== strpos( $class, static::$prefix ) ) {
            return;
        }

        // Convert class name to file path.
        $relative = substr( $class, strlen( static::$prefix ) );
        $relative = str_replace( '\\', '/', $relative );
        $parts    = explode( '/', $relative );
        $file     = array_pop( $parts );
        
        // Convert class name to file name following WordPress conventions.
        $file = 'class-' . strtolower( str_replace( '_', '-', $file ) ) . '.php';
        
        // Build directory path.
        $path = strtolower( implode( '/', $parts ) );
        if ( ! empty( $path ) ) {
            $path .= '/';
        }

        // Build full file path.
        $file_path = static::$base_dir . $path . $file;

        // Load the file if it exists.
        if ( is_readable( $file_path ) ) {
            require_once $file_path;
        }
    }
}
