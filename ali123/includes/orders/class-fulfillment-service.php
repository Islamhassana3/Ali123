<?php
namespace Ali123\Orders;

use WC_Order;
use WC_Order_Query;
use WP_Error;
use function __;

/**
 * Handles fulfillment workflows between WooCommerce and AliExpress.
 */
class Fulfillment_Service {
    /**
     * Detect orders eligible for AliExpress fulfillment.
     */
    public function detect_orders() : array {
        $query = new \WC_Order_Query( [
            'limit'      => 50,
            'status'     => [ 'processing', 'on-hold' ],
            'meta_query' => [
                [
                    'key'     => '_ali123_ali_id',
                    'compare' => 'EXISTS',
                ],
            ],
            'return'     => 'ids',
        ] );

        return $query->get_orders();
    }

    /**
     * Map WooCommerce order to AliExpress payload.
     */
    public function map_order( WC_Order $order ) : array {
        $items = [];
        foreach ( $order->get_items() as $item ) {
            $items[] = [
                'product_id'   => $item->get_product_id(),
                'ali_id'       => get_post_meta( $item->get_product_id(), '_ali123_ali_id', true ),
                'quantity'     => $item->get_quantity(),
                'price'        => $item->get_total(),
                'sku'          => $item->get_meta( '_ali123_sku', true ),
                'variation_id' => $item->get_variation_id(),
            ];
        }

        return [
            'order_id'      => $order->get_id(),
            'number'        => $order->get_order_number(),
            'shipping'      => $order->get_address( 'shipping' ),
            'billing'       => $order->get_address( 'billing' ),
            'items'         => $items,
            'currency'      => $order->get_currency(),
            'total'         => $order->get_total(),
            'customer_note' => $order->get_customer_note(),
            'meta'          => [
                'cpf'  => $order->get_meta( '_billing_cpf' ),
                'rut'  => $order->get_meta( '_billing_rut' ),
                'rfc'  => $order->get_meta( '_billing_rfc' ),
                'curp' => $order->get_meta( '_billing_curp' ),
            ],
        ];
    }

    /**
     * Update order after fulfillment.
     */
    public function mark_fulfilled( int $order_id, array $tracking ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'ali123_order_missing', __( 'Order not found.', 'ali123' ) );
        }

        $order->update_status( 'completed', __( 'Ali123: Order fulfilled via AliExpress.', 'ali123' ) );
        $order->update_meta_data( '_ali123_tracking', $tracking );
        $order->save();

        return true;
    }
}
