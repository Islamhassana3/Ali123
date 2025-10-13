<?php
namespace Ali123;

use Ali123\Admin\Admin_Menu;
use Ali123\Admin\Settings_Page;
use Ali123\Api\Rest_Controller;
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
     * Activate callback.
     */
    public static function activate() : void {
        ( new Import_Queue_Store() )->install();
        add_filter( 'cron_schedules', [ Job_Runner::class, 'register_intervals' ] );
        if ( ! wp_next_scheduled( Job_Runner::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', Job_Runner::CRON_HOOK );
        }
    }

    /**
     * Deactivation callback.
     */
    public static function deactivate() : void {
        wp_clear_scheduled_hook( Job_Runner::CRON_HOOK );
    }

    /**
     * Boot plugin services.
     */
    public function boot() : void {
        add_filter( 'cron_schedules', [ Job_Runner::class, 'register_intervals' ] );
        $this->container->get( Job_Runner::class );
        $this->container->get( Admin_Menu::class );
        $this->container->get( Settings_Page::class );
        $this->container->get( Rest_Controller::class );
        $this->container->get( Import_Service::class );
        $this->container->get( Fulfillment_Service::class );
        $this->container->get( Tracking_Sync::class );
    }

    /**
     * Register services in the container.
     */
    protected function register_services() : void {
        $this->container->singleton( Job_Runner::class, static function () {
            return new Job_Runner();
        } );

        $this->container->singleton( Admin_Menu::class, function ( Service_Container $c ) {
            return new Admin_Menu( $c->get( Import_Service::class ), $c->get( Fulfillment_Service::class ) );
        } );

        $this->container->singleton( Settings_Page::class, static function () {
            return new Settings_Page();
        } );

        $this->container->singleton( Rest_Controller::class, function ( Service_Container $c ) {
            return new Rest_Controller(
                $c->get( Import_Service::class ),
                $c->get( Tracking_Sync::class )
            );
        } );

        $this->container->singleton( Import_Queue_Store::class, static function () {
            return new Import_Queue_Store();
        } );

        $this->container->singleton( Import_Service::class, function ( Service_Container $c ) {
            return new Import_Service( $c->get( Job_Runner::class ), $c->get( Import_Queue_Store::class ) );
        } );

        $this->container->singleton( Fulfillment_Service::class, static function () {
            return new Fulfillment_Service();
        } );

        $this->container->singleton( Tracking_Sync::class, function ( Service_Container $c ) {
            return new Tracking_Sync( $c->get( Fulfillment_Service::class ) );
        } );
    }
}
