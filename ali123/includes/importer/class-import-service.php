<?php
/**
 * Import service for product synchronization.
 *
 * @package Ali123
 */

namespace Ali123\Importer;

use Ali123\Exceptions\Ali123_Exception;
use Ali123\Importer\Import_Queue_Store;
use Ali123\Importer\Pricing_Rules_Engine;
use Ali123\Importer\Product_Mapper;
use Ali123\Scheduler\Job_Runner;
use WP_Error;
use function __;
use function Ali123\deep_sanitize_text_field;
use function get_current_blog_id;
use function get_option;
use function is_wp_error;
use function wp_parse_args;

/**
 * Handles import queue and product synchronization.
 */
class Import_Service {
    /**
     * Maximum batch size for processing.
     *
     * @var int
     */
    const MAX_BATCH_SIZE = 25;

    /**
     * Maximum retry attempts for failed imports.
     *
     * @var int
     */
    const MAX_ATTEMPTS = 3;

    /**
     * Background job runner.
     *
     * @var Job_Runner
     */
    protected $job_runner;

    /**
     * Pricing engine.
     *
     * @var Pricing_Rules_Engine
     */
    protected $pricing_engine;

    /**
     * Product mapper.
     *
     * @var Product_Mapper
     */
    protected $product_mapper;

    /**
     * Queue persistence layer.
     *
     * @var Import_Queue_Store
     */
    protected $queue_store;

    /**
     * Constructor.
     *
     * @param Job_Runner          $job_runner   Job runner instance.
     * @param Import_Queue_Store  $queue_store  Queue store instance.
     */
    public function __construct( Job_Runner $job_runner, Import_Queue_Store $queue_store ) {
        $this->job_runner     = $job_runner;
        $this->queue_store    = $queue_store;
        $this->pricing_engine = new Pricing_Rules_Engine();
        $this->product_mapper = new Product_Mapper();

        add_action( 'ali123/queue/process', [ $this, 'process_queue' ] );
    }

    /**
     * Retrieve queue entries with filtering.
     *
     * @param array $args Optional query arguments.
     *
     * @return array List of queue entries.
     */
    public function get_queue( array $args = [] ) : array {
        try {
            return $this->queue_store->all( $args );
        } catch ( \Exception $e ) {
            // Log error and return empty array.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'Ali123: Failed to retrieve queue: ' . $e->getMessage() );
            }
            return [];
        }
    }

    /**
     * Queue a new import job.
     *
     * @param array $payload Import payload containing product data.
     *
     * @return array|WP_Error Queue entry data or error.
     */
    public function queue_import( array $payload ) {
        // Validate required fields.
        if ( empty( $payload['ali_id'] ) ) {
            return new WP_Error(
                'ali123_missing_ali_id',
                __( 'AliExpress product ID is required.', 'ali123' )
            );
        }

        $defaults = get_option( 'ali123_defaults', [] );
        $payload  = wp_parse_args(
            $payload,
            [
                'ali_id'         => '',
                'status'         => $defaults['status'] ?? 'draft',
            'visibility'     => $defaults['visibility'] ?? 'visible',
            'price_rules'    => [],
            'attributes'     => [],
            'images'         => [],
            'variations'     => [],
            'meta'           => [],
            'scheduled_time' => time(),
        ] );

        $payload = deep_sanitize_text_field( $payload );
        $payload['meta']['created_at'] = current_time( 'mysql' );

        $entry = $this->queue_store->add(
            $payload,
            [
                'scheduled_at' => $payload['scheduled_time'] ?? time(),
                'store_id'     => $payload['meta']['store_id'] ?? get_current_blog_id(),
            ]
        );

        if ( ! $entry ) {
            return new WP_Error( 'ali123_queue_error', __( 'Unable to queue import entry.', 'ali123' ) );
        }

        $this->job_runner->ensure_schedule();

        return $entry;
    }

    /**
     * Update queue entry.
     */
    public function update_import( int $id, array $data ) {
        $entry = $this->queue_store->get( $id );
        if ( ! $entry ) {
            return new WP_Error( 'ali123_import_missing', __( 'Import entry not found.', 'ali123' ) );
        }

        $data = deep_sanitize_text_field( $data );

        if ( isset( $data['scheduled_time'] ) ) {
            $data['scheduled_at'] = $data['scheduled_time'];
            unset( $data['scheduled_time'] );
        }

        if ( isset( $data['payload'] ) && is_array( $data['payload'] ) ) {
            $data['payload'] = array_merge( $entry['payload'], $data['payload'] );
        }

        $updated = $this->queue_store->update( $id, $data );

        if ( ! $updated ) {
            return new WP_Error( 'ali123_import_update_failed', __( 'Unable to update import entry.', 'ali123' ) );
        }

        return $updated;
    }

    /**
     * Delete queue entry.
     */
    public function delete_import( int $id ) {
        $entry = $this->queue_store->get( $id );
        if ( ! $entry ) {
            return new WP_Error( 'ali123_import_missing', __( 'Import entry not found.', 'ali123' ) );
        }

        return $this->queue_store->delete( $id );
    }

    /**
     * Preview pricing rules for payload.
     */
    public function preview_pricing( array $payload ) {
        try {
            return $this->pricing_engine->preview( $payload );
        } catch ( Ali123_Exception $exception ) {
            return new WP_Error( 'ali123_pricing_error', $exception->getMessage() );
        }
    }

    /**
     * Process queue.
     */
    public function process_queue() : void {
        $processed = 0;

        do {
            $batch = $this->queue_store->claim_due( 25 );
            if ( empty( $batch ) ) {
                break;
            }

            foreach ( $batch as $entry ) {
                $result = $this->import_product( $entry['payload'] );
                if ( is_wp_error( $result ) ) {
                    $this->queue_store->mark_failed( $entry['id'], $result->get_error_message() );
                } else {
                    $this->queue_store->mark_completed( $entry['id'] );
                }
                $processed++;
            }
        } while ( $processed < 100 );
    }

    /**
     * Import single product.
     */
    protected function import_product( array $payload ) {
        $mapped = $this->product_mapper->map( $payload );
        $priced = $this->pricing_engine->apply( $mapped );

        return $this->product_mapper->sync_to_store( $priced );
    }

}
