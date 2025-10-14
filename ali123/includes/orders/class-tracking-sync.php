<?php
/**
 * Tracking synchronization service.
 *
 * @package Ali123
 */

namespace Ali123\Orders;

use function add_action;
use function current_time;
use function defined;
use function is_wp_error;
use function time;
use function update_post_meta;
use function wp_next_scheduled;
use function wp_schedule_single_event;

/**
 * Schedules and runs tracking synchronizations.
 */
class Tracking_Sync {
    /**
     * Tracking sync hook identifier.
     *
     * @var string
     */
    const TRACKING_HOOK = 'ali123_sync_tracking';

    /**
     * Fulfillment service.
     *
     * @var Fulfillment_Service
     */
    protected $fulfillment_service;

    /**
     * Constructor.
     *
     * @param Fulfillment_Service $fulfillment_service Fulfillment service instance.
     */
    public function __construct( Fulfillment_Service $fulfillment_service ) {
        $this->fulfillment_service = $fulfillment_service;

        add_action( self::TRACKING_HOOK, [ $this, 'sync' ] );
    }

    /**
     * Schedule immediate tracking sync.
     *
     * @return bool True if scheduled successfully, false otherwise.
     */
    public function schedule_immediate_sync() : bool {
        if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
            define( 'MINUTE_IN_SECONDS', 60 );
        }

        // Only schedule if not already scheduled.
        if ( wp_next_scheduled( self::TRACKING_HOOK ) ) {
            return false;
        }

        $result = wp_schedule_single_event(
            time() + MINUTE_IN_SECONDS,
            self::TRACKING_HOOK
        );

        return false !== $result;
    }

    /**
     * Synchronize tracking numbers from AliExpress.
     *
     * @return array{synced: int, errors: int} Sync statistics.
     */
    public function sync() : array {
        $stats = [
            'synced' => 0,
            'errors' => 0,
        ];

        $orders = $this->fulfillment_service->detect_orders();

        if ( empty( $orders ) ) {
            return $stats;
        }

        foreach ( $orders as $order_id ) {
            if ( ! is_numeric( $order_id ) || $order_id < 1 ) {
                $stats['errors']++;
                continue;
            }

            // Retrieve tracking data from AliExpress API.
            $tracking = $this->retrieve_tracking_from_ali( (int) $order_id );

            if ( is_wp_error( $tracking ) ) {
                update_post_meta( $order_id, '_ali123_tracking_error', $tracking->get_error_message() );
                update_post_meta( $order_id, '_ali123_tracking_error_time', current_time( 'mysql' ) );
                $stats['errors']++;
                continue;
            }

            $result = $this->fulfillment_service->mark_fulfilled( (int) $order_id, $tracking );

            if ( is_wp_error( $result ) ) {
                $stats['errors']++;
            } else {
                $stats['synced']++;
            }
        }

        // Log sync results if debugging is enabled.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(
                sprintf(
                    'Ali123 Tracking Sync: %d synced, %d errors',
                    $stats['synced'],
                    $stats['errors']
                )
            );
        }

        return $stats;
    }

    /**
     * Retrieve tracking data from AliExpress API.
     *
     * This is a placeholder implementation. In production, this would
     * make an actual API call to AliExpress to retrieve tracking information.
     *
     * @param int $order_id WooCommerce order ID.
     *
     * @return array|WP_Error Tracking data or error.
     */
    protected function retrieve_tracking_from_ali( int $order_id ) {
        // TODO: Implement actual AliExpress API integration.
        // This is a placeholder that returns mock data for testing.

        $data = [
            'tracking_number' => 'ALI-' . $order_id,
            'carrier'         => 'AliExpress Standard Shipping',
            'carrier_code'    => 'aliexpress-standard',
            'synced_at'       => current_time( 'mysql' ),
            'status'          => 'in_transit',
        ];

        return $data;
    }
}
