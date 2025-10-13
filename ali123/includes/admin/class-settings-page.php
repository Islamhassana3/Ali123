<?php
namespace Ali123\Admin;

use function __;
use function esc_attr;
use function esc_html__;
use function printf;
use function selected;
/**
 * Plugin settings page registration.
 */
class Settings_Page {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() : void {
        register_setting( 'ali123_settings', 'ali123_credentials', [ $this, 'sanitize_credentials' ] );
        register_setting( 'ali123_settings', 'ali123_defaults', [ $this, 'sanitize_defaults' ] );

        add_settings_section( 'ali123_api', __( 'AliExpress Business API', 'ali123' ), function () {
            echo '<p>' . esc_html__( 'Configure the AliExpress Business API keys, secrets, and endpoints.', 'ali123' ) . '</p>';
        }, 'ali123_settings' );

        add_settings_field( 'ali123_app_key', __( 'App Key', 'ali123' ), function () {
            $credentials = get_option( 'ali123_credentials', [] );
            $value       = isset( $credentials['app_key'] ) ? esc_attr( $credentials['app_key'] ) : '';
            echo '<input type="text" class="regular-text" name="ali123_credentials[app_key]" value="' . $value . '" />';
        }, 'ali123_settings', 'ali123_api' );

        add_settings_field( 'ali123_app_secret', __( 'App Secret', 'ali123' ), function () {
            $credentials = get_option( 'ali123_credentials', [] );
            $value       = isset( $credentials['app_secret'] ) ? esc_attr( $credentials['app_secret'] ) : '';
            echo '<input type="password" class="regular-text" name="ali123_credentials[app_secret]" value="' . $value . '" autocomplete="off" />';
        }, 'ali123_settings', 'ali123_api' );

        add_settings_field( 'ali123_store_hash', __( 'Store Hash', 'ali123' ), function () {
            $credentials = get_option( 'ali123_credentials', [] );
            $value       = isset( $credentials['store_hash'] ) ? esc_attr( $credentials['store_hash'] ) : '';
            echo '<input type="text" class="regular-text" name="ali123_credentials[store_hash]" value="' . $value . '" />';
        }, 'ali123_settings', 'ali123_api' );

        add_settings_section( 'ali123_defaults', __( 'Default Import Options', 'ali123' ), function () {
            echo '<p>' . esc_html__( 'Configure default product status, visibility, pricing rules, and categories.', 'ali123' ) . '</p>';
        }, 'ali123_settings' );

        add_settings_field( 'ali123_default_status', __( 'Default Product Status', 'ali123' ), function () {
            $defaults = get_option( 'ali123_defaults', [] );
            $value    = isset( $defaults['status'] ) ? esc_attr( $defaults['status'] ) : 'draft';
            echo '<select name="ali123_defaults[status]">';
            foreach ( [ 'publish', 'pending', 'draft' ] as $status ) {
                printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $status ), selected( $value, $status, false ) );
            }
            echo '</select>';
        }, 'ali123_settings', 'ali123_defaults' );

        add_settings_field( 'ali123_default_visibility', __( 'Default Catalog Visibility', 'ali123' ), function () {
            $defaults = get_option( 'ali123_defaults', [] );
            $value    = isset( $defaults['visibility'] ) ? esc_attr( $defaults['visibility'] ) : 'visible';
            echo '<select name="ali123_defaults[visibility]">';
            foreach ( [ 'visible', 'catalog', 'search', 'hidden' ] as $visibility ) {
                printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $visibility ), selected( $value, $visibility, false ) );
            }
            echo '</select>';
        }, 'ali123_settings', 'ali123_defaults' );
    }

    /**
     * Sanitize credentials.
     *
     * @param array $credentials Credential data.
     */
    public function sanitize_credentials( array $credentials ) : array {
        return [
            'app_key'    => sanitize_text_field( $credentials['app_key'] ?? '' ),
            'app_secret' => sanitize_text_field( $credentials['app_secret'] ?? '' ),
            'store_hash' => sanitize_text_field( $credentials['store_hash'] ?? '' ),
        ];
    }

    /**
     * Sanitize default options.
     *
     * @param array $defaults Defaults.
     */
    public function sanitize_defaults( array $defaults ) : array {
        $sanitized = [];
        $sanitized['status']     = in_array( $defaults['status'] ?? 'draft', [ 'publish', 'pending', 'draft' ], true ) ? $defaults['status'] : 'draft';
        $sanitized['visibility'] = in_array( $defaults['visibility'] ?? 'visible', [ 'visible', 'catalog', 'search', 'hidden' ], true ) ? $defaults['visibility'] : 'visible';

        return $sanitized;
    }
}
