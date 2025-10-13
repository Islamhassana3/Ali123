<?php
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
    protected static $base_dir = ALI123_PLUGIN_DIR . 'includes/';

    /**
     * Register the autoloader with SPL.
     */
    public static function register() : void {
        spl_autoload_register( [ static::class, 'autoload' ] );
    }

    /**
     * Autoload callback.
     *
     * @param string $class Class being requested.
     */
    protected static function autoload( string $class ) : void {
        if ( 0 !== strpos( $class, static::$prefix ) ) {
            return;
        }

        $relative = substr( $class, strlen( static::$prefix ) );
        $relative = str_replace( '\\', '/', $relative );
        $parts    = explode( '/', $relative );
        $file     = array_pop( $parts );
        $file     = 'class-' . strtolower( str_replace( '_', '-', $file ) ) . '.php';
        $path     = strtolower( implode( '/', $parts ) );
        if ( ! empty( $path ) ) {
            $path .= '/';
        }

        $file = static::$base_dir . $path . $file;

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
