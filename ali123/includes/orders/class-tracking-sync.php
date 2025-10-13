<?php
namespace Ali123\Orders;

/**
 * Schedules and runs tracking synchronizations.
 */
class Tracking_Sync {
    const TRACKING_HOOK = 'ali123_sync_tracking';

    /**
     * Fulfillment service.
     *
     * @var Fulfillment_Service
     */
    protected $fulfillment_service;

    /**
     * Constructor.
     */
    public function __construct( Fulfillment_Service $fulfillment_service ) {
        $this->fulfillment_service = $fulfillment_service;

        add_action( self::TRACKING_HOOK, [ $this, 'sync' ] );
    }

    /**
     * Schedule immediate sync.
     */
    public function schedule_immediate_sync() : void {
        if ( ! wp_next_scheduled( self::TRACKING_HOOK ) ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::TRACKING_HOOK );
        }
    }

    /**
     * Synchronize tracking numbers.
     */
    public function sync() : void {
        $orders = $this->fulfillment_service->detect_orders();

        foreach ( $orders as $order_id ) {
            // Placeholder for AliExpress tracking API integration.
            $tracking = $this->retrieve_tracking_from_ali( $order_id );

            if ( is_wp_error( $tracking ) ) {
                update_post_meta( $order_id, '_ali123_tracking_error', $tracking->get_error_message() );
                continue;
            }

            $this->fulfillment_service->mark_fulfilled( $order_id, $tracking );
        }
    }

    /**
     * Retrieve tracking data from AliExpress.
     */
    protected function retrieve_tracking_from_ali( int $order_id ) {
        $data = [
            'tracking_number' => 'ALI-' . $order_id,
            'carrier'         => 'AliExpress Standard Shipping',
            'synced_at'       => current_time( 'mysql' ),
        ];

        return $data;
    }
}
