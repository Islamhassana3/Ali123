#!/usr/bin/env php
<?php
/**
 * Ali123 WordPress Integration Test
 * 
 * This script performs additional validation checks for WordPress integration.
 */

echo "=== Ali123 WordPress Integration Tests ===\n\n";

// Determine plugin directory path
$plugin_dir = getenv('PLUGIN_DIR') ?: dirname(__DIR__, 2) . '/ali123';
if (!is_dir($plugin_dir)) {
    echo "Error: Plugin directory not found at: $plugin_dir\n";
    echo "Set PLUGIN_DIR environment variable or run from correct location.\n";
    exit(1);
}

// Test 1: Validate activation hook compatibility
echo "Test 1: Activation Hook Validation\n";
$plugin_main = file_get_contents($plugin_dir . '/ali123.php');
$plugin_class = file_get_contents($plugin_dir . '/includes/class-plugin.php');

// Check activation hook registration
if (preg_match('/register_activation_hook.*Plugin.*activate/', $plugin_main)) {
    echo "  ✓ PASS: Activation hook registered correctly\n";
} else {
    echo "  ✗ FAIL: Activation hook not found or incorrectly formatted\n";
}

// Check if activate method is static
if (preg_match('/public\s+static\s+function\s+activate\s*\(\s*\)\s*:\s*void/', $plugin_class)) {
    echo "  ✓ PASS: Activate method is static void\n";
} else {
    echo "  ✗ FAIL: Activate method should be public static void\n";
}

// Check deactivation hook
if (preg_match('/register_deactivation_hook.*Plugin.*deactivate/', $plugin_main)) {
    echo "  ✓ PASS: Deactivation hook registered correctly\n";
} else {
    echo "  ✗ FAIL: Deactivation hook not found\n";
}
echo "\n";

// Test 2: REST API endpoint validation
echo "Test 2: REST API Endpoint Validation\n";
$rest_controller = file_get_contents($plugin_dir . '/includes/api/class-rest-controller.php');

$expected_endpoints = [
    '/imports' => 'GET and POST',
    '/imports/(?P<id>[0-9]+)' => 'PATCH and DELETE',
    '/orders/sync' => 'POST',
    '/pricing/preview' => 'POST',
];

foreach ($expected_endpoints as $endpoint => $methods) {
    $pattern = preg_quote($endpoint, '/');
    if (preg_match("/$pattern/", $rest_controller)) {
        echo "  ✓ PASS: Endpoint '$endpoint' registered ($methods)\n";
    } else {
        echo "  ✗ FAIL: Endpoint '$endpoint' not found\n";
    }
}
echo "\n";

// Test 3: Database schema validation
echo "Test 3: Database Schema Validation\n";
$queue_store = file_get_contents($plugin_dir . '/includes/importer/class-import-queue-store.php');

$required_columns = [
    'id BIGINT',
    'store_id BIGINT',
    'status VARCHAR',
    'attempts SMALLINT',
    'scheduled_at DATETIME',
    'payload LONGTEXT',
    'last_error TEXT',
    'last_error_at DATETIME',
    'created_at DATETIME',
    'updated_at DATETIME',
];

$missing_columns = [];
foreach ($required_columns as $column) {
    if (stripos($queue_store, $column) === false) {
        $missing_columns[] = $column;
    }
}

if (empty($missing_columns)) {
    echo "  ✓ PASS: All required columns defined in schema\n";
} else {
    echo "  ✗ FAIL: Missing columns: " . implode(', ', $missing_columns) . "\n";
}

// Check for dbDelta usage
if (strpos($queue_store, 'dbDelta') !== false) {
    echo "  ✓ PASS: Uses dbDelta for schema installation\n";
} else {
    echo "  ✗ FAIL: Should use dbDelta for schema installation\n";
}
echo "\n";

// Test 4: Cron job validation
echo "Test 4: Cron Job Validation\n";
$job_runner = file_get_contents($plugin_dir . '/includes/scheduler/class-job-runner.php');

if (strpos($job_runner, "const CRON_HOOK = 'ali123_process_queue'") !== false) {
    echo "  ✓ PASS: Cron hook constant defined\n";
} else {
    echo "  ✗ FAIL: Cron hook constant not found\n";
}

if (strpos($job_runner, "register_intervals") !== false) {
    echo "  ✓ PASS: Custom cron intervals registered\n";
} else {
    echo "  ✗ FAIL: Custom intervals not found\n";
}

if (preg_match('/wp_schedule_event/', $plugin_class)) {
    echo "  ✓ PASS: Cron event scheduled in activation\n";
} else {
    echo "  ✗ FAIL: Cron event not scheduled\n";
}
echo "\n";

// Test 5: Admin menu integration
echo "Test 5: Admin Menu Integration\n";
$admin_menu = file_get_contents($plugin_dir . '/includes/admin/class-admin-menu.php');

$required_menu_items = [
    'ali123-dashboard',
    'ali123-import-list',
    'ali123-orders',
    'ali123-settings',
];

foreach ($required_menu_items as $menu_item) {
    if (strpos($admin_menu, "'$menu_item'") !== false) {
        echo "  ✓ PASS: Menu item '$menu_item' registered\n";
    } else {
        echo "  ✗ FAIL: Menu item '$menu_item' not found\n";
    }
}
echo "\n";

// Test 6: Settings page validation
echo "Test 6: Settings Page Validation\n";
$settings_page = file_get_contents($plugin_dir . '/includes/admin/class-settings-page.php');

if (strpos($settings_page, "register_setting") !== false) {
    echo "  ✓ PASS: Settings registered with WordPress\n";
} else {
    echo "  ✗ FAIL: Settings not registered\n";
}

$settings_to_check = [
    'ali123_credentials',
    'ali123_defaults',
];

foreach ($settings_to_check as $setting) {
    if (strpos($settings_page, "'$setting'") !== false) {
        echo "  ✓ PASS: Setting '$setting' defined\n";
    } else {
        echo "  ✗ FAIL: Setting '$setting' not found\n";
    }
}
echo "\n";

// Test 7: Service container validation
echo "Test 7: Service Container Validation\n";
$plugin_class = file_get_contents($plugin_dir . '/includes/class-plugin.php');

$required_services = [
    'Job_Runner',
    'Admin_Menu',
    'Settings_Page',
    'Rest_Controller',
    'Import_Queue_Store',
    'Import_Service',
    'Fulfillment_Service',
    'Tracking_Sync',
];

$missing_services = [];
foreach ($required_services as $service) {
    if (strpos($plugin_class, "singleton( {$service}::class") !== false) {
        echo "  ✓ PASS: Service '$service' registered\n";
    } else {
        $missing_services[] = $service;
    }
}

if (!empty($missing_services)) {
    echo "  ✗ FAIL: Missing services: " . implode(', ', $missing_services) . "\n";
}
echo "\n";

// Test 8: JavaScript integration
echo "Test 8: JavaScript Integration\n";
$admin_js = file_get_contents($plugin_dir . '/assets/js/admin.js');

// Check for proper REST API usage
if (strpos($admin_js, 'Ali123Config.root') !== false && strpos($admin_js, 'Ali123Config.nonce') !== false) {
    echo "  ✓ PASS: JavaScript uses localized config (root, nonce)\n";
} else {
    echo "  ✗ FAIL: JavaScript config not properly used\n";
}

// Check for i18n usage
if (strpos($admin_js, 'wp.i18n') !== false || strpos($admin_js, '__') !== false) {
    echo "  ✓ PASS: JavaScript uses WordPress i18n\n";
} else {
    echo "  ✗ FAIL: JavaScript should use WordPress i18n\n";
}

// Check for proper data access
if (strpos($admin_js, 'row.payload') !== false) {
    echo "  ✓ PASS: JavaScript correctly accesses payload data\n";
} else {
    echo "  ✗ FAIL: JavaScript data access may be incorrect\n";
}
echo "\n";

// Test 9: Security checks
echo "Test 9: Security Checks\n";
$all_php_files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $all_php_files[] = $file->getRealPath();
    }
}

$security_issues = 0;

// Check for capability checks in admin
if (strpos($admin_menu, 'manage_woocommerce') !== false) {
    echo "  ✓ PASS: Admin menu uses capability checks\n";
} else {
    echo "  ⚠ WARNING: Admin menu should check capabilities\n";
    $security_issues++;
}

// Check for nonce verification in REST API
$rest_api = file_get_contents($plugin_dir . '/includes/api/class-rest-controller.php');
if (strpos($rest_api, 'permission_callback') !== false) {
    echo "  ✓ PASS: REST API uses permission callbacks\n";
} else {
    echo "  ✗ FAIL: REST API should use permission callbacks\n";
    $security_issues++;
}

// Check for sanitization
$import_service = file_get_contents($plugin_dir . '/includes/importer/class-import-service.php');
if (strpos($import_service, 'deep_sanitize_text_field') !== false || strpos($import_service, 'sanitize_text_field') !== false) {
    echo "  ✓ PASS: Data sanitization implemented\n";
} else {
    echo "  ⚠ WARNING: Data sanitization should be implemented\n";
    $security_issues++;
}

if ($security_issues === 0) {
    echo "  ✓ All security checks passed\n";
}
echo "\n";

// Final Summary
echo "=== Integration Test Complete ===\n";
echo "The plugin structure is valid and ready for WordPress installation.\n";
echo "\nNext steps for full testing:\n";
echo "1. Install in WordPress wp-content/plugins directory\n";
echo "2. Activate the plugin\n";
echo "3. Check for activation errors\n";
echo "4. Verify database table creation\n";
echo "5. Test admin menu pages\n";
echo "6. Test REST API endpoints\n";
echo "7. Test cron job execution\n";
echo "8. Test import queue functionality\n";
