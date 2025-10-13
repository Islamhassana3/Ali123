<?php
namespace Ali123\Admin;

use Ali123\Importer\Import_Service;
use Ali123\Orders\Fulfillment_Service;
use function __;
use function esc_html__;
use function esc_url_raw;

/**
 * Registers admin menus and renders dashboards.
 */
class Admin_Menu {
    /**
     * Import service.
     *
     * @var Import_Service
     */
    protected $import_service;

    /**
     * Fulfillment service.
     *
     * @var Fulfillment_Service
     */
    protected $fulfillment_service;

    /**
     * Constructor.
     */
    public function __construct( Import_Service $import_service, Fulfillment_Service $fulfillment_service ) {
        $this->import_service      = $import_service;
        $this->fulfillment_service = $fulfillment_service;

        add_action( 'admin_menu', [ $this, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function register() : void {
        add_menu_page(
            __( 'Ali123', 'ali123' ),
            __( 'Ali123', 'ali123' ),
            'manage_woocommerce',
            'ali123-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-cart'
        );

        add_submenu_page(
            'ali123-dashboard',
            __( 'Import List', 'ali123' ),
            __( 'Import List', 'ali123' ),
            'manage_woocommerce',
            'ali123-import-list',
            [ $this, 'render_import_list' ]
        );

        add_submenu_page(
            'ali123-dashboard',
            __( 'Orders', 'ali123' ),
            __( 'Orders', 'ali123' ),
            'manage_woocommerce',
            'ali123-orders',
            [ $this, 'render_orders' ]
        );

        add_submenu_page(
            'ali123-dashboard',
            __( 'Settings', 'ali123' ),
            __( 'Settings', 'ali123' ),
            'manage_woocommerce',
            'ali123-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( string $hook ) : void {
        if ( false === strpos( $hook, 'ali123' ) ) {
            return;
        }

        wp_enqueue_style( 'ali123-admin', ALI123_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0' );
        wp_enqueue_script( 'ali123-admin', ALI123_PLUGIN_URL . 'assets/js/admin.js', [ 'wp-i18n', 'wp-api' ], '1.0.0', true );
        wp_localize_script( 'ali123-admin', 'Ali123Config', [
            'root'  => esc_url_raw( rest_url( 'ali123/v1' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard() : void {
        echo '<div class="wrap"><h1>' . esc_html__( 'Ali123 Automation Dashboard', 'ali123' ) . '</h1>';
        echo '<div id="ali123-dashboard-root"></div></div>';
    }

    /**
     * Render import list page.
     */
    public function render_import_list() : void {
        echo '<div class="wrap"><h1>' . esc_html__( 'Import List', 'ali123' ) . '</h1>';
        echo '<div id="ali123-import-root"></div></div>';
    }

    /**
     * Render orders page.
     */
    public function render_orders() : void {
        echo '<div class="wrap"><h1>' . esc_html__( 'Orders & Fulfillment', 'ali123' ) . '</h1>';
        echo '<div id="ali123-orders-root"></div></div>';
    }

    /**
     * Render settings page.
     */
    public function render_settings() : void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ali123 Settings', 'ali123' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'ali123_settings' );
        do_settings_sections( 'ali123_settings' );
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
