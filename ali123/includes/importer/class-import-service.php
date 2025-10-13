<?php
namespace Ali123\Importer;

use Ali123\Importer\Pricing_Rules_Engine;
use Ali123\Importer\Product_Mapper;
use Ali123\Scheduler\Job_Runner;
use Ali123\Exceptions\Ali123_Exception;
use function __;
use function Ali123\deep_sanitize_text_field;
use WP_Error;

/**
 * Handles import queue and product synchronization.
 */
class Import_Service {
    const OPTION_QUEUE = 'ali123_import_queue';

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
     * Constructor.
     */
    public function __construct( Job_Runner $job_runner ) {
        $this->job_runner     = $job_runner;
        $this->pricing_engine = new Pricing_Rules_Engine();
        $this->product_mapper = new Product_Mapper();

        add_action( 'ali123/queue/process', [ $this, 'process_queue' ] );
    }

    /**
     * Retrieve queue entries.
     */
    public function get_queue() : array {
        return get_option( self::OPTION_QUEUE, [] );
    }

    /**
     * Queue a new import.
     *
     * @param array $payload Import payload.
     */
    public function queue_import( array $payload ) : array {
        $queue     = $this->get_queue();
        $id        = $this->generate_id( $queue );
        $defaults  = get_option( 'ali123_defaults', [] );
        $payload   = wp_parse_args( $payload, [
            'id'             => $id,
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

        $queue[ $id ] = $payload;
        update_option( self::OPTION_QUEUE, $queue, false );

        $this->job_runner->ensure_schedule();

        return $payload;
    }

    /**
     * Update queue entry.
     */
    public function update_import( int $id, array $data ) {
        $queue = $this->get_queue();
        if ( ! isset( $queue[ $id ] ) ) {
            return new WP_Error( 'ali123_import_missing', __( 'Import entry not found.', 'ali123' ) );
        }

        $queue[ $id ] = array_merge( $queue[ $id ], deep_sanitize_text_field( $data ) );
        update_option( self::OPTION_QUEUE, $queue, false );

        return $queue[ $id ];
    }

    /**
     * Delete queue entry.
     */
    public function delete_import( int $id ) {
        $queue = $this->get_queue();
        if ( ! isset( $queue[ $id ] ) ) {
            return new WP_Error( 'ali123_import_missing', __( 'Import entry not found.', 'ali123' ) );
        }

        unset( $queue[ $id ] );
        update_option( self::OPTION_QUEUE, $queue, false );

        return true;
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
        $queue = $this->get_queue();
        if ( empty( $queue ) ) {
            return;
        }

        foreach ( $queue as $id => $payload ) {
            if ( $payload['scheduled_time'] > time() ) {
                continue;
            }

            $result = $this->import_product( $payload );
            if ( is_wp_error( $result ) ) {
                $queue[ $id ]['error']        = $result->get_error_message();
                $queue[ $id ]['last_attempt'] = current_time( 'mysql' );
            } else {
                unset( $queue[ $id ] );
            }
        }

        update_option( self::OPTION_QUEUE, $queue, false );
    }

    /**
     * Import single product.
     */
    protected function import_product( array $payload ) {
        $mapped = $this->product_mapper->map( $payload );
        $priced = $this->pricing_engine->apply( $mapped );

        return $this->product_mapper->sync_to_store( $priced );
    }

    /**
     * Generate unique ID.
     */
    protected function generate_id( array $queue ) : int {
        $id = time();
        while ( isset( $queue[ $id ] ) ) {
            $id++;
        }

        return $id;
    }
}
