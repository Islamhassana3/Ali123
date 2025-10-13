<?php
/**
 * Plugin Name: Ali123 Dropshipping Automation
 * Plugin URI:  https://example.com/ali123
 * Description: Full-featured dropshipping and fulfillment automation for WooCommerce with AliExpress Business integration.
 * Version:     1.0.0
 * Author:      Ali123 Team
 * Author URI:  https://example.com
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ali123
 * Domain Path: /languages
 */

define( 'ALI123_PLUGIN_FILE', __FILE__ );
define( 'ALI123_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALI123_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

autoload();

function autoload() {
    require_once ALI123_PLUGIN_DIR . 'includes/class-autoloader.php';
    require_once ALI123_PLUGIN_DIR . 'includes/helpers.php';
    Ali123\Autoloader::register();
}

register_activation_hook( __FILE__, [ 'Ali123\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Ali123\\Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'ali123', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    $plugin = new Ali123\Plugin();
    $plugin->boot();
} );
