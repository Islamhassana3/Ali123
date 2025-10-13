<?php
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
     * @param string  $id      Service identifier.
     * @param Closure $factory Factory callback.
     */
    public function singleton( string $id, Closure $factory ) : void {
        $this->bindings[ $id ] = $factory;
    }

    /**
     * Resolve a service instance.
     *
     * @param string $id Service identifier.
     *
     * @return mixed
     */
    public function get( string $id ) {
        if ( isset( $this->resolved[ $id ] ) ) {
            return $this->resolved[ $id ];
        }

        if ( ! isset( $this->bindings[ $id ] ) ) {
            throw new RuntimeException( sprintf( 'Service "%s" not registered.', $id ) );
        }

        $this->resolved[ $id ] = call_user_func( $this->bindings[ $id ], $this );

        return $this->resolved[ $id ];
    }
}
