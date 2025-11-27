<?php
/**
 * REST API controller for Ali123 endpoints.
 *
 * @package Ali123
 */

namespace Ali123\Api;

use Ali123\Importer\Import_Service;
use Ali123\Orders\Tracking_Sync;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for Ali123 endpoints.
 */
class Rest_Controller {
    /**
     * REST namespace version.
     *
     * @var string
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
     *
     * @param Import_Service $import_service Import service instance.
     * @param Tracking_Sync  $tracking_sync  Tracking sync service instance.
     */
    public function __construct( Import_Service $import_service, Tracking_Sync $tracking_sync ) {
        $this->import_service = $import_service;
        $this->tracking_sync  = $tracking_sync;

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes() : void {
        // Import list and create endpoints.
        register_rest_route(
            self::REST_NAMESPACE,
            '/imports',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'list_imports' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => $this->get_list_params(),
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'queue_import' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => $this->get_import_params(),
                ],
            ]
        );

        // Import update and delete endpoints.
        register_rest_route(
            self::REST_NAMESPACE,
            '/imports/(?P<id>[\d]+)',
            [
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the import.', 'ali123' ),
                        'type'        => 'integer',
                        'required'    => true,
                        'minimum'     => 1,
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_import' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                    'args'                => $this->get_update_params(),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_import' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );

        // Order tracking sync endpoint.
        register_rest_route(
            self::REST_NAMESPACE,
            '/orders/sync',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'trigger_sync' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ]
        );

        // Pricing preview endpoint.
        register_rest_route(
            self::REST_NAMESPACE,
            '/pricing/preview',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'preview_pricing' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->get_pricing_params(),
            ]
        );
    }

    /**
     * Verify permissions for API access.
     *
     * @return bool|WP_Error True if user has permission, WP_Error otherwise.
     */
    public function permissions_check() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this endpoint.', 'ali123' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * List import queue entries.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response Response object.
     */
    public function list_imports( WP_REST_Request $request ) {
        $args = [];

        if ( isset( $request['status'] ) ) {
            $args['status'] = sanitize_text_field( $request['status'] );
        }

        if ( isset( $request['limit'] ) ) {
            $args['limit'] = absint( $request['limit'] );
        }

        $data = $this->import_service->get_queue( $args );

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * Queue a new import.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function queue_import( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params ) || ! is_array( $params ) ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Invalid import data provided.', 'ali123' ),
                [ 'status' => 400 ]
            );
        }

        $data = $this->import_service->queue_import( $params );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        return new WP_REST_Response( $data, 201 );
    }

    /**
     * Update an existing import entry.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function update_import( WP_REST_Request $request ) {
        $id     = absint( $request['id'] );
        $params = $request->get_json_params();

        if ( $id < 1 ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Invalid import ID.', 'ali123' ),
                [ 'status' => 400 ]
            );
        }

        if ( empty( $params ) || ! is_array( $params ) ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Invalid update data provided.', 'ali123' ),
                [ 'status' => 400 ]
            );
        }

        $updated = $this->import_service->update_import( $id, $params );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        return new WP_REST_Response( $updated, 200 );
    }

    /**
     * Delete an import entry.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function delete_import( WP_REST_Request $request ) {
        $id = absint( $request['id'] );

        if ( $id < 1 ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Invalid import ID.', 'ali123' ),
                [ 'status' => 400 ]
            );
        }

        $deleted = $this->import_service->delete_import( $id );

        if ( is_wp_error( $deleted ) ) {
            return $deleted;
        }

        return new WP_REST_Response( null, 204 );
    }

    /**
     * Trigger order tracking sync manually.
     *
     * @return WP_REST_Response Response object.
     */
    public function trigger_sync() {
        $this->tracking_sync->schedule_immediate_sync();

        return new WP_REST_Response(
            [
                'status'  => 'scheduled',
                'message' => __( 'Tracking sync has been scheduled.', 'ali123' ),
            ],
            202
        );
    }

    /**
     * Preview pricing rules application.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function preview_pricing( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params ) || ! is_array( $params ) ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Invalid pricing data provided.', 'ali123' ),
                [ 'status' => 400 ]
            );
        }

        $preview = $this->import_service->preview_pricing( $params );

        if ( is_wp_error( $preview ) ) {
            return $preview;
        }

        return new WP_REST_Response( $preview, 200 );
    }

    /**
     * Get list parameters schema.
     *
     * @return array Parameters schema.
     */
    protected function get_list_params() : array {
        return [
            'status' => [
                'description' => __( 'Filter by import status.', 'ali123' ),
                'type'        => 'string',
                'enum'        => [ 'pending', 'processing', 'completed', 'failed' ],
            ],
            'limit'  => [
                'description' => __( 'Maximum number of results to return.', 'ali123' ),
                'type'        => 'integer',
                'minimum'     => 1,
                'maximum'     => 100,
                'default'     => 50,
            ],
        ];
    }

    /**
     * Get import parameters schema.
     *
     * @return array Parameters schema.
     */
    protected function get_import_params() : array {
        return [
            'ali_id' => [
                'description' => __( 'AliExpress product ID.', 'ali123' ),
                'type'        => 'string',
                'required'    => true,
            ],
        ];
    }

    /**
     * Get update parameters schema.
     *
     * @return array Parameters schema.
     */
    protected function get_update_params() : array {
        return [
            'status'       => [
                'description' => __( 'Update import status.', 'ali123' ),
                'type'        => 'string',
                'enum'        => [ 'pending', 'processing', 'completed', 'failed' ],
            ],
            'scheduled_at' => [
                'description' => __( 'Update scheduled time.', 'ali123' ),
                'type'        => 'string',
                'format'      => 'date-time',
            ],
        ];
    }

    /**
     * Get pricing parameters schema.
     *
     * @return array Parameters schema.
     */
    protected function get_pricing_params() : array {
        return [
            'price'       => [
                'description' => __( 'Base price data.', 'ali123' ),
                'type'        => 'object',
            ],
            'price_rules' => [
                'description' => __( 'Pricing rules to apply.', 'ali123' ),
                'type'        => 'array',
            ],
        ];
    }
}
