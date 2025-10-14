<?php
/**
 * Ali123 Dropshipping Automation Plugin
 *
 * @package           Ali123
 * @author            Ali123 Team
 * @copyright         2024 Ali123 Team
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Ali123 Dropshipping Automation
 * Plugin URI:        https://example.com/ali123
 * Description:       Full-featured dropshipping and fulfillment automation for WooCommerce with AliExpress Business integration.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ali123 Team
 * Author URI:        https://example.com
 * Text Domain:       ali123
 * Domain Path:       /languages
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Update URI:        https://example.com/ali123
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'ALI123_VERSION', '1.0.0' );
define( 'ALI123_PLUGIN_FILE', __FILE__ );
define( 'ALI123_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALI123_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALI123_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check minimum PHP version.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action(
        'admin_notices',
        function () {
            printf(
                '<div class="error"><p>%s</p></div>',
                esc_html__(
                    'Ali123 requires PHP 7.4 or higher. Please upgrade PHP to use this plugin.',
                    'ali123'
                )
            );
        }
    );
    return;
}

// Check if WooCommerce is active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
    add_action(
        'admin_notices',
        function () {
            printf(
                '<div class="error"><p>%s</p></div>',
                esc_html__(
                    'Ali123 requires WooCommerce to be installed and active.',
                    'ali123'
                )
            );
        }
    );
    return;
}

/**
 * Load plugin files and register autoloader.
 *
 * @return void
 */
function ali123_autoload() {
    require_once ALI123_PLUGIN_DIR . 'includes/class-autoloader.php';
    require_once ALI123_PLUGIN_DIR . 'includes/helpers.php';
    Ali123\Autoloader::register();
}

ali123_autoload();

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, [ 'Ali123\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Ali123\\Plugin', 'deactivate' ] );

// Initialize plugin on plugins_loaded.
add_action(
    'plugins_loaded',
    static function () {
        // Load text domain for translations.
        load_plugin_textdomain(
            'ali123',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );

        // Initialize plugin.
        try {
            $plugin = new Ali123\Plugin();
            $plugin->boot();
        } catch ( Exception $e ) {
            // Log initialization error.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log( 'Ali123 initialization failed: ' . $e->getMessage() );
            }

            // Show admin notice.
            add_action(
                'admin_notices',
                function () use ( $e ) {
                    printf(
                        '<div class="error"><p>%s</p></div>',
                        esc_html(
                            sprintf(
                                /* translators: %s: error message */
                                __( 'Ali123 failed to initialize: %s', 'ali123' ),
                                $e->getMessage()
                            )
                        )
                    );
                }
            );
        }
    },
    20 // Load after WooCommerce.
);
