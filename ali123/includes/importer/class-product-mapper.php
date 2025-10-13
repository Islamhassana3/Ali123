<?php
namespace Ali123\Importer;

use Ali123\Exceptions\Ali123_Exception;
use WC_Product;
use WC_Product_Factory;
use WP_Error;
use function __;
use function sanitize_text_field;
use function wp_kses_post;

/**
 * Maps AliExpress payloads to WooCommerce products.
 */
class Product_Mapper {
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
     * Normalize payload for WooCommerce operations.
     */
    public function map( array $payload ) : array {
        if ( empty( $payload['ali_id'] ) ) {
            throw new Ali123_Exception( __( 'AliExpress identifier missing.', 'ali123' ) );
        }

        return [
            'id'         => $payload['id'],
            'ali_id'     => $payload['ali_id'],
            'post_data'  => [
                'post_title'   => sanitize_text_field( $payload['title'] ?? '' ),
                'post_status'  => $payload['status'] ?? 'draft',
                'post_content' => wp_kses_post( $payload['description'] ?? '' ),
            ],
            'meta'       => $payload['meta'] ?? [],
            'images'     => $payload['images'] ?? [],
            'variations' => $payload['variations'] ?? [],
            'attributes' => $payload['attributes'] ?? [],
            'visibility' => $payload['visibility'] ?? 'visible',
            'price'      => $payload['price'] ?? [],
        ];
    }

    /**
     * Apply map to WooCommerce store.
     */
    public function sync_to_store( array $payload ) {
        $existing_id = $this->find_existing_product( $payload['ali_id'] );
        $product     = $existing_id ? $this->factory->get_product( $existing_id ) : $this->factory->create( 'simple' );

        if ( ! $product instanceof WC_Product ) {
            return new WP_Error( 'ali123_sync_error', __( 'Unable to instantiate WooCommerce product.', 'ali123' ) );
        }

        $product->set_name( $payload['post_data']['post_title'] );
        $product->set_status( $payload['post_data']['post_status'] );
        $product->set_description( $payload['post_data']['post_content'] );
        $product->set_catalog_visibility( $payload['visibility'] );

        if ( isset( $payload['price']['regular'] ) ) {
            $product->set_regular_price( $payload['price']['regular'] );
        }

        if ( isset( $payload['price']['sale'] ) ) {
            $product->set_sale_price( $payload['price']['sale'] );
        }

        $product_id = $product->save();

        if ( ! $product_id ) {
            return new WP_Error( 'ali123_product_save_error', __( 'Product could not be saved.', 'ali123' ) );
        }

        update_post_meta( $product_id, '_ali123_ali_id', sanitize_text_field( $payload['ali_id'] ) );
        update_post_meta( $product_id, '_ali123_meta', $payload['meta'] );

        // Variation and attribute syncing would be implemented here.

        return [ 'product_id' => $product_id ];
    }

    /**
     * Locate WooCommerce product by AliExpress reference.
     */
    protected function find_existing_product( string $ali_id ) {
        global $wpdb;

        $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_ali123_ali_id', $ali_id ) );

        return $product_id ? (int) $product_id : null;
    }
}
