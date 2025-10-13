<?php
namespace Ali123\Importer;

use Ali123\Exceptions\Ali123_Exception;
use function __;

/**
 * Applies pricing rules to imported products.
 */
class Pricing_Rules_Engine {
    /**
     * Preview pricing changes.
     */
    public function preview( array $payload ) : array {
        $transformed = $this->apply_rules( $payload['price_rules'] ?? [], $payload['price'] ?? [] );

        return [
            'original' => $payload['price'] ?? [],
            'preview'  => $transformed,
        ];
    }

    /**
     * Apply pricing rules to payload.
     */
    public function apply( array $payload ) : array {
        $payload['price'] = $this->apply_rules( $payload['price_rules'] ?? [], $payload['price'] ?? [] );

        return $payload;
    }

    /**
     * Apply layered price rules.
     *
     * @param array $rules Price rules.
     * @param array $base  Base price structure.
     */
    protected function apply_rules( array $rules, array $base ) : array {
        $price = $base;

        foreach ( $rules as $rule ) {
            $type  = $rule['type'] ?? 'fixed';
            $value = (float) ( $rule['value'] ?? 0 );

            switch ( $type ) {
                case 'percentage':
                    $price['regular'] = isset( $price['regular'] ) ? $price['regular'] * ( 1 + $value / 100 ) : $value;
                    break;
                case 'multiplier':
                    $price['regular'] = isset( $price['regular'] ) ? $price['regular'] * $value : $value;
                    break;
                case 'fixed':
                    $price['regular'] = $value;
                    break;
                default:
                    throw new Ali123_Exception( sprintf( __( 'Unsupported pricing rule: %s', 'ali123' ), $type ) );
            }

            if ( ! empty( $rule['sale_adjustment'] ) && isset( $price['regular'] ) ) {
                $price['sale'] = max( 0, $price['regular'] - (float) $rule['sale_adjustment'] );
            }

            if ( ! empty( $rule['pretty'] ) && isset( $price['regular'] ) ) {
                $price['regular'] = $this->apply_pretty_pricing( $price['regular'], $rule['pretty'] );
            }
        }

        return array_map( static function ( $amount ) {
            return round( (float) $amount, 2 );
        }, $price );
    }

    /**
     * Apply pretty pricing endings.
     */
    protected function apply_pretty_pricing( float $price, string $format ) : float {
        $integer = floor( $price );
        $ending  = (float) $format;

        return (float) ( $integer + $ending );
    }
}
