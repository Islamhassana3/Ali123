<?php
/**
 * Fulfillment service for order processing.
 *
 * @package Ali123
 */

namespace Ali123\Orders;

use WC_Order;
use WC_Order_Query;
use WP_Error;
use function __;
use function current_time;
use function defined;
use function get_post_meta;
use function is_wp_error;
use function update_post_meta;
use function wc_get_order;

/**
 * Handles fulfillment workflows between WooCommerce and AliExpress.
 */
class Fulfillment_Service {
    /**
     * Maximum orders to process per batch.
     *
     * @var int
     */
    const MAX_BATCH_SIZE = 50;

    /**
     * Detect orders eligible for AliExpress fulfillment.
     *
     * @param int $limit Maximum number of orders to return (default 50).
     *
     * @return array Order IDs eligible for fulfillment.
     */
    public function detect_orders( int $limit = self::MAX_BATCH_SIZE ) : array {
        // Validate limit parameter.
        $limit = max( 1, min( $limit, self::MAX_BATCH_SIZE ) );

        try {
            $query = new WC_Order_Query(
                [
                    'limit'      => $limit,
                    'status'     => [ 'processing', 'on-hold' ],
                    'meta_query' => [
                        [
                            'key'     => '_ali123_ali_id',
                            'compare' => 'EXISTS',
                        ],
                        [
                            'key'     => '_ali123_fulfilled',
                            'compare' => 'NOT EXISTS',
                        ],
                    ],
                    'return'     => 'ids',
                    'orderby'    => 'date',
                    'order'      => 'ASC',
                ]
            );

            $orders = $query->get_orders();

            return is_array( $orders ) ? $orders : [];
        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'Ali123: Failed to detect orders: ' . $e->getMessage() );
            }
            return [];
        }
    }

    /**
     * Map WooCommerce order to AliExpress fulfillment payload.
     *
     * @param WC_Order $order WooCommerce order object.
     *
     * @return array|WP_Error Mapped order data or error.
     */
    public function map_order( WC_Order $order ) {
        if ( ! $order || ! $order->get_id() ) {
            return new WP_Error(
                'ali123_invalid_order',
                __( 'Invalid order provided.', 'ali123' )
            );
        }

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();

            if ( ! $product_id ) {
                continue;
            }

            $ali_id = get_post_meta( $product_id, '_ali123_ali_id', true );

            // Skip items without AliExpress ID.
            if ( empty( $ali_id ) ) {
                continue;
            }

            $items[] = [
                'product_id'   => $product_id,
                'ali_id'       => sanitize_text_field( $ali_id ),
                'quantity'     => max( 1, (int) $item->get_quantity() ),
                'price'        => (float) $item->get_total(),
                'sku'          => sanitize_text_field( $item->get_meta( '_ali123_sku', true ) ),
                'variation_id' => absint( $item->get_variation_id() ),
                'name'         => sanitize_text_field( $item->get_name() ),
            ];
        }

        // Validate that order has items.
        if ( empty( $items ) ) {
            return new WP_Error(
                'ali123_no_items',
                __( 'Order contains no items eligible for AliExpress fulfillment.', 'ali123' )
            );
        }

        return [
            'order_id'      => $order->get_id(),
            'number'        => $order->get_order_number(),
            'status'        => $order->get_status(),
            'date_created'  => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
            'shipping'      => $order->get_address( 'shipping' ),
            'billing'       => $order->get_address( 'billing' ),
            'items'         => $items,
            'currency'      => $order->get_currency(),
            'total'         => (float) $order->get_total(),
            'customer_note' => sanitize_textarea_field( $order->get_customer_note() ),
            'customer_id'   => absint( $order->get_customer_id() ),
            'meta'          => [
                'cpf'  => sanitize_text_field( $order->get_meta( '_billing_cpf' ) ),
                'rut'  => sanitize_text_field( $order->get_meta( '_billing_rut' ) ),
                'rfc'  => sanitize_text_field( $order->get_meta( '_billing_rfc' ) ),
                'curp' => sanitize_text_field( $order->get_meta( '_billing_curp' ) ),
            ],
        ];
    }

    /**
     * Mark order as fulfilled with tracking information.
     *
     * @param int   $order_id Order ID.
     * @param array $tracking Tracking information.
     *
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function mark_fulfilled( int $order_id, array $tracking ) {
        if ( $order_id < 1 ) {
            return new WP_Error(
                'ali123_invalid_order_id',
                __( 'Invalid order ID provided.', 'ali123' )
            );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error(
                'ali123_order_missing',
                sprintf(
                    /* translators: %d: order ID */
                    __( 'Order #%d not found.', 'ali123' ),
                    $order_id
                )
            );
        }

        // Validate tracking data.
        if ( empty( $tracking['tracking_number'] ) ) {
            return new WP_Error(
                'ali123_missing_tracking',
                __( 'Tracking number is required.', 'ali123' )
            );
        }

        try {
            // Update order status.
            $order->update_status(
                'completed',
                __( 'Ali123: Order fulfilled via AliExpress.', 'ali123' )
            );

            // Store tracking information.
            $order->update_meta_data( '_ali123_tracking', $tracking );
            $order->update_meta_data( '_ali123_fulfilled', 'yes' );
            $order->update_meta_data( '_ali123_fulfilled_at', current_time( 'mysql' ) );
            
            // Add order note.
            $order->add_order_note(
                sprintf(
                    /* translators: 1: tracking number, 2: carrier */
                    __( 'Tracking information updated: %1$s via %2$s', 'ali123' ),
                    $tracking['tracking_number'],
                    $tracking['carrier'] ?? __( 'Unknown Carrier', 'ali123' )
                )
            );

            $order->save();

            return true;
        } catch ( \Exception $e ) {
            return new WP_Error(
                'ali123_fulfillment_error',
                sprintf(
                    /* translators: %s: error message */
                    __( 'Failed to mark order as fulfilled: %s', 'ali123' ),
                    $e->getMessage()
                )
            );
        }
    }
}
