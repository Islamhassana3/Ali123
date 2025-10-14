<?php
/**
 * Job runner for scheduled background tasks.
 *
 * @package Ali123
 */

namespace Ali123\Scheduler;

use function __;
use function add_action;
use function do_action;
use function time;
use function wp_next_scheduled;
use function wp_schedule_event;

/**
 * Schedules background processing tasks.
 */
class Job_Runner {
    /**
     * Cron hook identifier.
     *
     * @var string
     */
    const CRON_HOOK = 'ali123_process_queue';

    /**
     * Custom interval identifier.
     *
     * @var string
     */
    const INTERVAL_NAME = 'five_minutes';

    /**
     * Constructor - registers cron action.
     */
    public function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'handle' ] );
    }

    /**
     * Register custom cron intervals.
     *
     * @param array $schedules Existing cron schedules.
     *
     * @return array Modified schedules array.
     */
    public static function register_intervals( array $schedules ) : array {
        if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
            define( 'MINUTE_IN_SECONDS', 60 );
        }

        if ( ! isset( $schedules[ self::INTERVAL_NAME ] ) ) {
            $schedules[ self::INTERVAL_NAME ] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every Five Minutes', 'ali123' ),
            ];
        }

        return $schedules;
    }

    /**
     * Ensure cron job is scheduled.
     *
     * @return void
     */
    public function ensure_schedule() : void {
        if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
            define( 'MINUTE_IN_SECONDS', 60 );
        }

        // Only schedule if not already scheduled.
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            $scheduled = wp_schedule_event(
                time() + MINUTE_IN_SECONDS,
                self::INTERVAL_NAME,
                self::CRON_HOOK
            );

            // Log if scheduling failed.
            if ( false === $scheduled && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'Ali123: Failed to schedule cron job ' . self::CRON_HOOK );
            }
        }
    }

    /**
     * Cron handler - triggers queue processing.
     *
     * @return void
     */
    public function handle() : void {
        // Allow other plugins to hook into queue processing.
        do_action( 'ali123/queue/process' );

        // Log processing if debugging is enabled.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'Ali123: Processing import queue via cron' );
        }
    }
}
