<?php
namespace Ali123\Scheduler;

use function __;

/**
 * Schedules background processing tasks.
 */
class Job_Runner {
    const CRON_HOOK = 'ali123_process_queue';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'handle' ] );
    }

    /**
     * Register custom intervals.
     */
    public static function register_intervals( array $schedules ) : array {
        if ( ! isset( $schedules['five_minutes'] ) ) {
            $schedules['five_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __( 'Every Five Minutes', 'ali123' ),
            ];
        }

        return $schedules;
    }

    /**
     * Ensure cron is scheduled.
     */
    public function ensure_schedule() : void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', self::CRON_HOOK );
        }
    }

    /**
     * Cron handler.
     */
    public function handle() : void {
        do_action( 'ali123/queue/process' );
    }
}
