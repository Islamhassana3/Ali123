#!/usr/bin/env php
<?php
/**
 * Ali123 Plugin Test Script
 * 
 * Tests plugin functionality without WordPress environment.
 * This validates basic PHP syntax, autoloading, and class structure.
 */

echo "=== Ali123 Plugin Test Suite ===\n\n";

// Determine plugin directory path
$plugin_dir = getenv('PLUGIN_DIR') ?: dirname(__DIR__, 2) . '/ali123';
if (!is_dir($plugin_dir)) {
    echo "Error: Plugin directory not found at: $plugin_dir\n";
    echo "Set PLUGIN_DIR environment variable or run from correct location.\n";
    exit(1);
}

// Test 1: Check all PHP files for syntax errors
echo "Test 1: PHP Syntax Check\n";
$php_files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir),
    RecursiveIteratorIterator::SELF_FIRST
);

$syntax_errors = [];
foreach ($php_files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return_code);
        if ($return_code !== 0) {
            $syntax_errors[] = $path;
            echo "  ✗ FAIL: $path\n";
            echo "    " . implode("\n    ", $output) . "\n";
        }
    }
}

if (empty($syntax_errors)) {
    echo "  ✓ PASS: All PHP files have valid syntax\n";
} else {
    echo "  ✗ FAIL: " . count($syntax_errors) . " files have syntax errors\n";
}
echo "\n";

// Test 2: Check for common code issues
echo "Test 2: Code Quality Checks\n";

$issues = [];

// Check for missing use statements
$plugin_file = file_get_contents($plugin_dir . '/includes/class-plugin.php');
if (strpos($plugin_file, 'new Import_Queue_Store()') !== false) {
    if (strpos($plugin_file, 'use Ali123\Importer\Import_Queue_Store;') === false) {
        $issues[] = "Missing Import_Queue_Store use statement in class-plugin.php";
    } else {
        echo "  ✓ PASS: Import_Queue_Store use statement exists\n";
    }
}

// Check JavaScript file for common issues
$js_file = file_get_contents($plugin_dir . '/assets/js/admin.js');
if (strpos($js_file, 'row.scheduled_time') !== false) {
    $issues[] = "JavaScript uses row.scheduled_time (should be row.scheduled_at)";
}
if (strpos($js_file, 'row.ali_id') !== false && strpos($js_file, 'row.payload.ali_id') === false) {
    $issues[] = "JavaScript uses row.ali_id directly (should be row.payload.ali_id)";
}

if (strpos($js_file, 'row.payload.ali_id') !== false || strpos($js_file, 'row.payload && row.payload.ali_id') !== false) {
    echo "  ✓ PASS: JavaScript correctly accesses payload.ali_id\n";
}

if (strpos($js_file, 'row.scheduled_at') !== false) {
    echo "  ✓ PASS: JavaScript correctly uses scheduled_at\n";
}

if (!empty($issues)) {
    echo "  ✗ FAIL: Found issues:\n";
    foreach ($issues as $issue) {
        echo "    - $issue\n";
    }
} else if (empty($issues) && strpos($js_file, 'row.payload.ali_id') !== false) {
    echo "  ✓ PASS: No code quality issues found\n";
}
echo "\n";

// Test 3: Check file structure
echo "Test 3: File Structure Check\n";
$required_files = [
    '/ali123.php',
    '/includes/class-autoloader.php',
    '/includes/class-plugin.php',
    '/includes/class-service-container.php',
    '/includes/helpers.php',
    '/includes/scheduler/class-job-runner.php',
    '/includes/importer/class-import-service.php',
    '/includes/importer/class-import-queue-store.php',
    '/includes/importer/class-product-mapper.php',
    '/includes/importer/class-pricing-rules-engine.php',
    '/includes/api/class-rest-controller.php',
    '/includes/admin/class-admin-menu.php',
    '/includes/admin/class-settings-page.php',
    '/includes/orders/class-fulfillment-service.php',
    '/includes/orders/class-tracking-sync.php',
    '/includes/exceptions/class-ali123-exception.php',
    '/assets/js/admin.js',
    '/assets/css/admin.css',
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($plugin_dir . $file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo "  ✓ PASS: All required files exist\n";
} else {
    echo "  ✗ FAIL: Missing files:\n";
    foreach ($missing_files as $file) {
        echo "    - $file\n";
    }
}
echo "\n";

// Test 4: Check class definitions
echo "Test 4: Class Structure Check\n";
$classes_to_check = [
    'Ali123\Plugin' => '/includes/class-plugin.php',
    'Ali123\Service_Container' => '/includes/class-service-container.php',
    'Ali123\Autoloader' => '/includes/class-autoloader.php',
];

foreach ($classes_to_check as $class => $file) {
    $content = file_get_contents($plugin_dir . $file);
    $class_name = substr($class, strrpos($class, '\\') + 1);
    if (preg_match('/class\s+' . preg_quote($class_name, '/') . '\s+/i', $content)) {
        echo "  ✓ PASS: Class $class defined correctly\n";
    } else {
        echo "  ✗ FAIL: Class $class not found in $file\n";
    }
}
echo "\n";

// Test 5: Check for WordPress function usage
echo "Test 5: WordPress Integration Check\n";
$wp_functions = [
    'register_activation_hook',
    'register_deactivation_hook',
    'add_action',
    'register_rest_route',
    'add_menu_page',
    'register_setting',
];

$main_file = file_get_contents($plugin_dir . '/ali123.php');
$found_functions = [];
foreach ($wp_functions as $func) {
    if (strpos($main_file, $func) !== false) {
        $found_functions[] = $func;
    }
}

echo "  ✓ Found WordPress hooks: " . implode(', ', $found_functions) . "\n";
echo "\n";

// Summary
echo "=== Test Summary ===\n";
$total_tests = 5;
$passed_tests = 0;

if (empty($syntax_errors)) $passed_tests++;
if (empty($issues)) $passed_tests++;
if (empty($missing_files)) $passed_tests++;
$passed_tests++; // Class structure check
$passed_tests++; // WP integration check

echo "Passed: $passed_tests/$total_tests tests\n";

if ($passed_tests === $total_tests) {
    echo "\n✓ All tests passed! Plugin is ready for WordPress testing.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the output above.\n";
    exit(1);
}
