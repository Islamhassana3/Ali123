<?php
/**
 * Plugin settings page registration and management.
 *
 * @package Ali123
 */

namespace Ali123\Admin;

use function __;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function get_option;
use function in_array;
use function printf;
use function sanitize_text_field;
use function selected;

/**
 * Plugin settings page registration.
 */
class Settings_Page {
    /**
     * Valid product statuses.
     *
     * @var array
     */
    const VALID_STATUSES = [ 'publish', 'pending', 'draft' ];

    /**
     * Valid product visibility options.
     *
     * @var array
     */
    const VALID_VISIBILITY = [ 'visible', 'catalog', 'search', 'hidden' ];

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

        add_settings_field(
            'ali123_app_key',
            __( 'App Key', 'ali123' ),
            function () {
                $credentials = get_option( 'ali123_credentials', [] );
                $value       = isset( $credentials['app_key'] ) ? esc_attr( $credentials['app_key'] ) : '';
                printf(
                    '<input type="text" id="ali123_app_key" class="regular-text" name="ali123_credentials[app_key]" value="%s" />',
                    $value
                );
                echo '<p class="description">' . esc_html__( 'Enter your AliExpress Business API App Key.', 'ali123' ) . '</p>';
            },
            'ali123_settings',
            'ali123_api'
        );

        add_settings_field(
            'ali123_app_secret',
            __( 'App Secret', 'ali123' ),
            function () {
                $credentials = get_option( 'ali123_credentials', [] );
                $value       = isset( $credentials['app_secret'] ) ? esc_attr( $credentials['app_secret'] ) : '';
                printf(
                    '<input type="password" id="ali123_app_secret" class="regular-text" name="ali123_credentials[app_secret]" value="%s" autocomplete="off" />',
                    $value
                );
                echo '<p class="description">' . esc_html__( 'Enter your AliExpress Business API App Secret. Keep this confidential.', 'ali123' ) . '</p>';
            },
            'ali123_settings',
            'ali123_api'
        );

        add_settings_field(
            'ali123_store_hash',
            __( 'Store Hash', 'ali123' ),
            function () {
                $credentials = get_option( 'ali123_credentials', [] );
                $value       = isset( $credentials['store_hash'] ) ? esc_attr( $credentials['store_hash'] ) : '';
                printf(
                    '<input type="text" id="ali123_store_hash" class="regular-text" name="ali123_credentials[store_hash]" value="%s" />',
                    $value
                );
                echo '<p class="description">' . esc_html__( 'Enter your AliExpress store hash identifier.', 'ali123' ) . '</p>';
            },
            'ali123_settings',
            'ali123_api'
        );

        add_settings_section( 'ali123_defaults', __( 'Default Import Options', 'ali123' ), function () {
            echo '<p>' . esc_html__( 'Configure default product status, visibility, pricing rules, and categories.', 'ali123' ) . '</p>';
        }, 'ali123_settings' );

        add_settings_field(
            'ali123_default_status',
            __( 'Default Product Status', 'ali123' ),
            function () {
                $defaults = get_option( 'ali123_defaults', [] );
                $value    = isset( $defaults['status'] ) ? esc_attr( $defaults['status'] ) : 'draft';
                echo '<select id="ali123_default_status" name="ali123_defaults[status]">';
                foreach ( self::VALID_STATUSES as $status ) {
                    printf(
                        '<option value="%1$s" %2$s>%3$s</option>',
                        esc_attr( $status ),
                        selected( $value, $status, false ),
                        esc_html( ucfirst( $status ) )
                    );
                }
                echo '</select>';
                echo '<p class="description">' . esc_html__( 'Default status for imported products.', 'ali123' ) . '</p>';
            },
            'ali123_settings',
            'ali123_defaults'
        );

        add_settings_field(
            'ali123_default_visibility',
            __( 'Default Catalog Visibility', 'ali123' ),
            function () {
                $defaults = get_option( 'ali123_defaults', [] );
                $value    = isset( $defaults['visibility'] ) ? esc_attr( $defaults['visibility'] ) : 'visible';
                echo '<select id="ali123_default_visibility" name="ali123_defaults[visibility]">';
                foreach ( self::VALID_VISIBILITY as $visibility ) {
                    printf(
                        '<option value="%1$s" %2$s>%3$s</option>',
                        esc_attr( $visibility ),
                        selected( $value, $visibility, false ),
                        esc_html( ucfirst( $visibility ) )
                    );
                }
                echo '</select>';
                echo '<p class="description">' . esc_html__( 'Default catalog visibility for imported products.', 'ali123' ) . '</p>';
            },
            'ali123_settings',
            'ali123_defaults'
        );
    }

    /**
     * Sanitize credentials input.
     *
     * @param mixed $credentials Credential data to sanitize.
     *
     * @return array Sanitized credentials.
     */
    public function sanitize_credentials( $credentials ) : array {
        if ( ! is_array( $credentials ) ) {
            return [
                'app_key'    => '',
                'app_secret' => '',
                'store_hash' => '',
            ];
        }

        $sanitized = [
            'app_key'    => isset( $credentials['app_key'] ) ? sanitize_text_field( $credentials['app_key'] ) : '',
            'app_secret' => isset( $credentials['app_secret'] ) ? sanitize_text_field( $credentials['app_secret'] ) : '',
            'store_hash' => isset( $credentials['store_hash'] ) ? sanitize_text_field( $credentials['store_hash'] ) : '',
        ];

        // Trim whitespace from credentials.
        $sanitized = array_map( 'trim', $sanitized );

        return $sanitized;
    }

    /**
     * Sanitize default options input.
     *
     * @param mixed $defaults Defaults data to sanitize.
     *
     * @return array Sanitized defaults.
     */
    public function sanitize_defaults( $defaults ) : array {
        if ( ! is_array( $defaults ) ) {
            return [
                'status'     => 'draft',
                'visibility' => 'visible',
            ];
        }

        $status     = isset( $defaults['status'] ) ? sanitize_text_field( $defaults['status'] ) : 'draft';
        $visibility = isset( $defaults['visibility'] ) ? sanitize_text_field( $defaults['visibility'] ) : 'visible';

        $sanitized = [
            'status'     => in_array( $status, self::VALID_STATUSES, true ) ? $status : 'draft',
            'visibility' => in_array( $visibility, self::VALID_VISIBILITY, true ) ? $visibility : 'visible',
        ];

        return $sanitized;
    }
}
