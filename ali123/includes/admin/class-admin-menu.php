<?php
/**
 * Admin menu and dashboard rendering.
 *
 * @package Ali123
 */

namespace Ali123\Admin;

use Ali123\Importer\Import_Service;
use Ali123\Orders\Fulfillment_Service;
use function __;
use function esc_html;
use function esc_html__;
use function esc_url_raw;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

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
     *
     * @param Import_Service      $import_service      Import service instance.
     * @param Fulfillment_Service $fulfillment_service Fulfillment service instance.
     */
    public function __construct( Import_Service $import_service, Fulfillment_Service $fulfillment_service ) {
        $this->import_service      = $import_service;
        $this->fulfillment_service = $fulfillment_service;

        add_action( 'admin_menu', [ $this, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register admin menu pages.
     *
     * @return void
     */
    public function register() : void {
        // Main menu page.
        add_menu_page(
            __( 'Ali123', 'ali123' ),
            __( 'Ali123', 'ali123' ),
            'manage_woocommerce',
            'ali123-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-cart',
            56 // Position after WooCommerce.
        );

        // Import list submenu.
        add_submenu_page(
            'ali123-dashboard',
            __( 'Import List', 'ali123' ),
            __( 'Import List', 'ali123' ),
            'manage_woocommerce',
            'ali123-import-list',
            [ $this, 'render_import_list' ]
        );

        // Orders submenu.
        add_submenu_page(
            'ali123-dashboard',
            __( 'Orders & Fulfillment', 'ali123' ),
            __( 'Orders', 'ali123' ),
            'manage_woocommerce',
            'ali123-orders',
            [ $this, 'render_orders' ]
        );

        // Settings submenu.
        add_submenu_page(
            'ali123-dashboard',
            __( 'Ali123 Settings', 'ali123' ),
            __( 'Settings', 'ali123' ),
            'manage_woocommerce',
            'ali123-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * Enqueue admin assets on Ali123 pages.
     *
     * @param string $hook Current admin page hook.
     *
     * @return void
     */
    public function enqueue_assets( string $hook ) : void {
        // Only load on Ali123 admin pages.
        if ( false === strpos( $hook, 'ali123' ) ) {
            return;
        }

        // Verify constants are defined.
        if ( ! defined( 'ALI123_PLUGIN_URL' ) ) {
            return;
        }

        // Enqueue admin stylesheet.
        wp_enqueue_style(
            'ali123-admin',
            ALI123_PLUGIN_URL . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        // Enqueue admin JavaScript with dependencies.
        wp_enqueue_script(
            'ali123-admin',
            ALI123_PLUGIN_URL . 'assets/js/admin.js',
            [ 'wp-i18n', 'wp-api' ],
            '1.0.0',
            true
        );

        // Localize script with configuration.
        wp_localize_script(
            'ali123-admin',
            'Ali123Config',
            [
                'root'    => esc_url_raw( rest_url( 'ali123/v1' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'version' => '1.0.0',
            ]
        );
    }

    /**
     * Render dashboard page.
     *
     * @return void
     */
    public function render_dashboard() : void {
        // Check user permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ali123' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ali123 Automation Dashboard', 'ali123' ) . '</h1>';
        echo '<div id="ali123-dashboard-root"></div>';
        echo '</div>';
    }

    /**
     * Render import list page.
     *
     * @return void
     */
    public function render_import_list() : void {
        // Check user permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ali123' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Import List', 'ali123' ) . '</h1>';
        echo '<div id="ali123-import-root"></div>';
        echo '</div>';
    }

    /**
     * Render orders page.
     *
     * @return void
     */
    public function render_orders() : void {
        // Check user permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ali123' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Orders & Fulfillment', 'ali123' ) . '</h1>';
        echo '<div id="ali123-orders-root"></div>';
        echo '</div>';
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings() : void {
        // Check user permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ali123' ) );
        }

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
