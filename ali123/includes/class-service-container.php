<?php
/**
 * Service container for dependency injection.
 *
 * @package Ali123
 */

namespace Ali123;

use Closure;
use RuntimeException;

/**
 * Lightweight dependency injection container.
 */
class Service_Container {
    /**
     * Resolved services cache.
     *
     * @var array<string,mixed>
     */
    protected $resolved = [];

    /**
     * Registered factory callbacks.
     *
     * @var array<string,Closure>
     */
    protected $bindings = [];

    /**
     * Register a singleton service.
     *
     * @param string  $id      Service identifier (typically class name).
     * @param Closure $factory Factory callback that returns the service instance.
     *
     * @return void
     */
    public function singleton( string $id, Closure $factory ) : void {
        if ( empty( $id ) ) {
            return;
        }

        $this->bindings[ $id ] = $factory;
    }

    /**
     * Resolve a service instance.
     *
     * @param string $id Service identifier.
     *
     * @return mixed Resolved service instance.
     * @throws RuntimeException If service is not registered.
     */
    public function get( string $id ) {
        if ( empty( $id ) ) {
            throw new RuntimeException( 'Service identifier cannot be empty.' );
        }

        if ( isset( $this->resolved[ $id ] ) ) {
            return $this->resolved[ $id ];
        }

        if ( ! isset( $this->bindings[ $id ] ) ) {
            throw new RuntimeException(
                sprintf(
                    /* translators: %s: service identifier */
                    __( 'Service "%s" not registered in container.', 'ali123' ),
                    esc_html( $id )
                )
            );
        }

        $this->resolved[ $id ] = call_user_func( $this->bindings[ $id ], $this );

        return $this->resolved[ $id ];
    }

    /**
     * Check if a service is registered.
     *
     * @param string $id Service identifier.
     *
     * @return bool True if service is registered, false otherwise.
     */
    public function has( string $id ) : bool {
        return isset( $this->bindings[ $id ] );
    }

    /**
     * Clear a resolved service from cache.
     *
     * @param string $id Service identifier.
     *
     * @return void
     */
    public function forget( string $id ) : void {
        unset( $this->resolved[ $id ] );
    }
}
