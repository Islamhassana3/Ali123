<?php
/**
 * Product mapper for AliExpress to WooCommerce conversion.
 *
 * @package Ali123
 */

namespace Ali123\Importer;

use Ali123\Exceptions\Ali123_Exception;
use WC_Product;
use WC_Product_Factory;
use WP_Error;
use function __;
use function is_wp_error;
use function sanitize_text_field;
use function update_post_meta;
use function wp_kses_post;

/**
 * Maps AliExpress payloads to WooCommerce products.
 */
class Product_Mapper {
    /**
     * Required fields for product mapping.
     *
     * @var array
     */
    const REQUIRED_FIELDS = [ 'ali_id', 'title' ];
    /**
     * WooCommerce factory.
     *
     * @var WC_Product_Factory
     */
    protected $factory;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->factory = new WC_Product_Factory();
    }

    /**
     * Normalize and validate payload for WooCommerce operations.
     *
     * @param array $payload Raw AliExpress product data.
     *
     * @return array Normalized product data.
     * @throws Ali123_Exception If required fields are missing.
     */
    public function map( array $payload ) : array {
        // Validate required fields.
        foreach ( self::REQUIRED_FIELDS as $field ) {
            if ( empty( $payload[ $field ] ) ) {
                throw new Ali123_Exception(
                    sprintf(
                        /* translators: %s: field name */
                        __( 'Required field missing: %s', 'ali123' ),
                        $field
                    )
                );
            }
        }

        // Sanitize and normalize data.
        return [
            'id'         => isset( $payload['id'] ) ? absint( $payload['id'] ) : 0,
            'ali_id'     => sanitize_text_field( $payload['ali_id'] ),
            'post_data'  => [
                'post_title'   => sanitize_text_field( $payload['title'] ?? '' ),
                'post_status'  => in_array( $payload['status'] ?? 'draft', [ 'publish', 'draft', 'pending' ], true )
                    ? $payload['status']
                    : 'draft',
                'post_content' => wp_kses_post( $payload['description'] ?? '' ),
            ],
            'meta'       => is_array( $payload['meta'] ?? null ) ? $payload['meta'] : [],
            'images'     => is_array( $payload['images'] ?? null ) ? array_map( 'esc_url_raw', $payload['images'] ) : [],
            'variations' => is_array( $payload['variations'] ?? null ) ? $payload['variations'] : [],
            'attributes' => is_array( $payload['attributes'] ?? null ) ? $payload['attributes'] : [],
            'visibility' => in_array( $payload['visibility'] ?? 'visible', [ 'visible', 'catalog', 'search', 'hidden' ], true )
                ? $payload['visibility']
                : 'visible',
            'price'      => is_array( $payload['price'] ?? null ) ? $payload['price'] : [],
        ];
    }

    /**
     * Sync mapped payload to WooCommerce store.
     *
     * @param array $payload Mapped product data.
     *
     * @return array|WP_Error Product data with ID or error.
     */
    public function sync_to_store( array $payload ) {
        try {
            // Validate payload.
            if ( empty( $payload['ali_id'] ) ) {
                return new WP_Error(
                    'ali123_missing_ali_id',
                    __( 'AliExpress ID is required for sync.', 'ali123' )
                );
            }

            // Find existing product or create new one.
            $existing_id = $this->find_existing_product( $payload['ali_id'] );
            $product     = $existing_id
                ? $this->factory->get_product( $existing_id )
                : $this->factory->create( 'simple' );

            if ( ! $product instanceof WC_Product ) {
                return new WP_Error(
                    'ali123_sync_error',
                    __( 'Unable to instantiate WooCommerce product.', 'ali123' )
                );
            }

            // Set product properties.
            $product->set_name( $payload['post_data']['post_title'] );
            $product->set_status( $payload['post_data']['post_status'] );
            $product->set_description( $payload['post_data']['post_content'] );
            $product->set_catalog_visibility( $payload['visibility'] );

            // Set pricing if provided.
            if ( ! empty( $payload['price']['regular'] ) && is_numeric( $payload['price']['regular'] ) ) {
                $product->set_regular_price( (float) $payload['price']['regular'] );
            }

            if ( ! empty( $payload['price']['sale'] ) && is_numeric( $payload['price']['sale'] ) ) {
                $product->set_sale_price( (float) $payload['price']['sale'] );
            }

            // Save the product.
            $product_id = $product->save();

            if ( ! $product_id ) {
                return new WP_Error(
                    'ali123_product_save_error',
                    __( 'Product could not be saved.', 'ali123' )
                );
            }

            // Update custom meta data.
            update_post_meta( $product_id, '_ali123_ali_id', sanitize_text_field( $payload['ali_id'] ) );
            update_post_meta( $product_id, '_ali123_meta', $payload['meta'] );
            update_post_meta( $product_id, '_ali123_synced_at', current_time( 'mysql' ) );

            // TODO: Implement variation and attribute syncing.
            // This would involve creating WooCommerce product variations
            // based on the AliExpress product variations data.

            return [
                'product_id' => $product_id,
                'ali_id'     => $payload['ali_id'],
                'status'     => $existing_id ? 'updated' : 'created',
            ];
        } catch ( \Exception $e ) {
            return new WP_Error(
                'ali123_sync_exception',
                sprintf(
                    /* translators: %s: error message */
                    __( 'Product sync failed: %s', 'ali123' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Locate existing WooCommerce product by AliExpress ID.
     *
     * @param string $ali_id AliExpress product ID.
     *
     * @return int|null Product ID if found, null otherwise.
     */
    protected function find_existing_product( string $ali_id ) {
        global $wpdb;

        if ( empty( $ali_id ) ) {
            return null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $product_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                '_ali123_ali_id',
                sanitize_text_field( $ali_id )
            )
        );

        return $product_id ? (int) $product_id : null;
    }
}
