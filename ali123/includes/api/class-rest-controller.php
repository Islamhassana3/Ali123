<?php
namespace Ali123\Api;

use Ali123\Importer\Import_Service;
use Ali123\Orders\Tracking_Sync;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for Ali123 endpoints.
 */
class Rest_Controller {
    /**
     * REST namespace.
     */
    const REST_NAMESPACE = 'ali123/v1';

    /**
     * Import service.
     *
     * @var Import_Service
     */
    protected $import_service;

    /**
     * Tracking sync.
     *
     * @var Tracking_Sync
     */
    protected $tracking_sync;

    /**
     * Constructor.
     */
    public function __construct( Import_Service $import_service, Tracking_Sync $tracking_sync ) {
        $this->import_service      = $import_service;
        $this->tracking_sync       = $tracking_sync;

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() : void {
        register_rest_route( self::REST_NAMESPACE, '/imports', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_imports' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'queue_import' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/imports/(?P<id>[0-9]+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'update_import' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_import' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/orders/sync', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'trigger_sync' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/pricing/preview', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'preview_pricing' ],
            'permission_callback' => [ $this, 'permissions_check' ],
        ] );
    }

    /**
     * Verify permissions.
     */
    public function permissions_check() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * List import queue.
     */
    public function list_imports() {
        return new WP_REST_Response( $this->import_service->get_queue(), 200 );
    }

    /**
     * Queue import.
     */
    public function queue_import( WP_REST_Request $request ) {
        $data = $this->import_service->queue_import( $request->get_json_params() );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        return new WP_REST_Response( $data, 201 );
    }

    /**
     * Update import entry.
     */
    public function update_import( WP_REST_Request $request ) {
        $updated = $this->import_service->update_import( (int) $request['id'], $request->get_json_params() );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        return new WP_REST_Response( $updated, 200 );
    }

    /**
     * Delete an import entry.
     */
    public function delete_import( WP_REST_Request $request ) {
        $deleted = $this->import_service->delete_import( (int) $request['id'] );

        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }

        return new WP_REST_Response( null, 204 );
    }

    /**
     * Trigger order sync manually.
     */
    public function trigger_sync() {
        $this->tracking_sync->schedule_immediate_sync();

        return new WP_REST_Response( [ 'status' => 'scheduled' ], 202 );
    }

    /**
     * Preview pricing rules.
     */
    public function preview_pricing( WP_REST_Request $request ) {
        $preview = $this->import_service->preview_pricing( $request->get_json_params() );

        if ( is_wp_error( $preview ) ) {
            return $preview;
        }

        return new WP_REST_Response( $preview, 200 );
    }
}
