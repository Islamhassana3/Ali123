<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Ali123
 */

namespace Ali123;

use Ali123\Admin\Admin_Menu;
use Ali123\Admin\Settings_Page;
use Ali123\Api\Rest_Controller;
use Ali123\Importer\Import_Queue_Store;
use Ali123\Importer\Import_Service;
use Ali123\Orders\Fulfillment_Service;
use Ali123\Orders\Tracking_Sync;
use Ali123\Scheduler\Job_Runner;

/**
 * Plugin bootstrap container.
 */
class Plugin {
    /**
     * Dependency container.
     *
     * @var Service_Container
     */
    protected $container;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->container = new Service_Container();
        $this->register_services();
    }

    /**
     * Activate callback - runs on plugin activation.
     *
     * @return void
     */
    public static function activate() : void {
        if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
            define( 'MINUTE_IN_SECONDS', 60 );
        }

        // Install database schema.
        $queue_store = new Import_Queue_Store();
        $queue_store->install();

        // Register cron intervals.
        add_filter( 'cron_schedules', [ Job_Runner::class, 'register_intervals' ] );

        // Schedule cron job if not already scheduled.
        if ( ! wp_next_scheduled( Job_Runner::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', Job_Runner::CRON_HOOK );
        }

        // Flush rewrite rules to register any custom permalinks.
        flush_rewrite_rules();
    }

    /**
     * Deactivation callback - runs on plugin deactivation.
     *
     * @return void
     */
    public static function deactivate() : void {
        // Clear all scheduled cron jobs.
        wp_clear_scheduled_hook( Job_Runner::CRON_HOOK );
        wp_clear_scheduled_hook( Tracking_Sync::TRACKING_HOOK );

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Boot plugin services and hooks.
     *
     * @return void
     */
    public function boot() : void {
        // Register custom cron intervals.
        add_filter( 'cron_schedules', [ Job_Runner::class, 'register_intervals' ] );

        // Initialize all services via dependency injection container.
        try {
            $this->container->get( Job_Runner::class );
            $this->container->get( Admin_Menu::class );
            $this->container->get( Settings_Page::class );
            $this->container->get( Rest_Controller::class );
            $this->container->get( Import_Service::class );
            $this->container->get( Fulfillment_Service::class );
            $this->container->get( Tracking_Sync::class );
        } catch ( \Exception $e ) {
            // Log error if service initialization fails.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'Ali123 service initialization error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Register services in the dependency injection container.
     *
     * @return void
     */
    protected function register_services() : void {
        // Register Job Runner service.
        $this->container->singleton(
            Job_Runner::class,
            static function () {
                return new Job_Runner();
            }
        );

        // Register Admin Menu service with dependencies.
        $this->container->singleton(
            Admin_Menu::class,
            function ( Service_Container $c ) {
                return new Admin_Menu(
                    $c->get( Import_Service::class ),
                    $c->get( Fulfillment_Service::class )
                );
            }
        );

        // Register Settings Page service.
        $this->container->singleton(
            Settings_Page::class,
            static function () {
                return new Settings_Page();
            }
        );

        // Register REST API Controller with dependencies.
        $this->container->singleton(
            Rest_Controller::class,
            function ( Service_Container $c ) {
                return new Rest_Controller(
                    $c->get( Import_Service::class ),
                    $c->get( Tracking_Sync::class )
                );
            }
        );

        // Register Import Queue Store service.
        $this->container->singleton(
            Import_Queue_Store::class,
            static function () {
                return new Import_Queue_Store();
            }
        );

        // Register Import Service with dependencies.
        $this->container->singleton(
            Import_Service::class,
            function ( Service_Container $c ) {
                return new Import_Service(
                    $c->get( Job_Runner::class ),
                    $c->get( Import_Queue_Store::class )
                );
            }
        );

        // Register Fulfillment Service.
        $this->container->singleton(
            Fulfillment_Service::class,
            static function () {
                return new Fulfillment_Service();
            }
        );

        // Register Tracking Sync service with dependencies.
        $this->container->singleton(
            Tracking_Sync::class,
            function ( Service_Container $c ) {
                return new Tracking_Sync( $c->get( Fulfillment_Service::class ) );
            }
        );
    }
}
