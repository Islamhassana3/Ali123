<?php
/**
 * Pricing rules engine for product pricing calculations.
 *
 * @package Ali123
 */

namespace Ali123\Importer;

use Ali123\Exceptions\Ali123_Exception;
use function __;

/**
 * Applies pricing rules to imported products.
 */
class Pricing_Rules_Engine {
    /**
     * Supported pricing rule types.
     *
     * @var array
     */
    const RULE_TYPES = [ 'fixed', 'percentage', 'multiplier' ];

    /**
     * Minimum price value (prevents negative prices).
     *
     * @var float
     */
    const MIN_PRICE = 0.01;

    /**
     * Preview pricing changes without applying them.
     *
     * @param array $payload Product payload with price rules.
     *
     * @return array Preview with original and transformed prices.
     * @throws Ali123_Exception If pricing calculation fails.
     */
    public function preview( array $payload ) : array {
        $original    = $payload['price'] ?? [];
        $rules       = $payload['price_rules'] ?? [];
        $transformed = $this->apply_rules( $rules, $original );

        return [
            'original' => $original,
            'preview'  => $transformed,
            'rules'    => $rules,
        ];
    }

    /**
     * Apply pricing rules to product payload.
     *
     * @param array $payload Product payload.
     *
     * @return array Modified payload with updated prices.
     * @throws Ali123_Exception If pricing calculation fails.
     */
    public function apply( array $payload ) : array {
        $price = $payload['price'] ?? [];
        $rules = $payload['price_rules'] ?? [];

        $payload['price'] = $this->apply_rules( $rules, $price );

        return $payload;
    }

    /**
     * Apply layered price rules to base price.
     *
     * @param array $rules Price rules to apply.
     * @param array $base  Base price structure.
     *
     * @return array Calculated prices.
     * @throws Ali123_Exception If rule type is unsupported or calculation fails.
     */
    protected function apply_rules( array $rules, array $base ) : array {
        // Start with base price or empty array.
        $price = is_array( $base ) ? $base : [];

        // Ensure regular price exists.
        if ( ! isset( $price['regular'] ) ) {
            $price['regular'] = 0.0;
        }

        // Apply each rule in sequence.
        foreach ( $rules as $index => $rule ) {
            if ( ! is_array( $rule ) ) {
                continue;
            }

            $type  = $rule['type'] ?? 'fixed';
            $value = isset( $rule['value'] ) ? (float) $rule['value'] : 0;

            // Validate rule type.
            if ( ! in_array( $type, self::RULE_TYPES, true ) ) {
                throw new Ali123_Exception(
                    sprintf(
                        /* translators: 1: rule type, 2: supported types */
                        __( 'Unsupported pricing rule type: %1$s. Supported types: %2$s', 'ali123' ),
                        esc_html( $type ),
                        implode( ', ', self::RULE_TYPES )
                    )
                );
            }

            // Apply rule based on type.
            switch ( $type ) {
                case 'percentage':
                    if ( $value < -100 ) {
                        throw new Ali123_Exception(
                            __( 'Percentage discount cannot be less than -100%.', 'ali123' )
                        );
                    }
                    $price['regular'] = $price['regular'] * ( 1 + $value / 100 );
                    break;

                case 'multiplier':
                    if ( $value < 0 ) {
                        throw new Ali123_Exception(
                            __( 'Multiplier cannot be negative.', 'ali123' )
                        );
                    }
                    $price['regular'] = $price['regular'] * $value;
                    break;

                case 'fixed':
                    $price['regular'] = $value;
                    break;
            }

            // Apply sale price adjustment if specified.
            if ( ! empty( $rule['sale_adjustment'] ) ) {
                $adjustment    = (float) $rule['sale_adjustment'];
                $price['sale'] = max( self::MIN_PRICE, $price['regular'] - $adjustment );
            }

            // Apply pretty pricing if specified.
            if ( ! empty( $rule['pretty'] ) ) {
                $price['regular'] = $this->apply_pretty_pricing(
                    $price['regular'],
                    (string) $rule['pretty']
                );
            }
        }

        // Ensure prices are not negative.
        $price['regular'] = max( self::MIN_PRICE, $price['regular'] );

        if ( isset( $price['sale'] ) ) {
            $price['sale'] = max( self::MIN_PRICE, $price['sale'] );

            // Ensure sale price is less than regular price.
            if ( $price['sale'] >= $price['regular'] ) {
                unset( $price['sale'] );
            }
        }

        // Round all prices to 2 decimal places.
        return array_map(
            static function ( $amount ) {
                return round( (float) $amount, 2 );
            },
            $price
        );
    }

    /**
     * Apply pretty pricing endings (e.g., .99, .95).
     *
     * @param float  $price  Original price.
     * @param string $format Ending format (e.g., '0.99').
     *
     * @return float Price with pretty ending.
     */
    protected function apply_pretty_pricing( float $price, string $format ) : float {
        // Parse the ending value.
        $ending = (float) $format;

        // Validate ending is between 0 and 1.
        if ( $ending < 0 || $ending >= 1 ) {
            return $price;
        }

        // Get integer part and add ending.
        $integer = floor( $price );

        return (float) ( $integer + $ending );
    }
}
