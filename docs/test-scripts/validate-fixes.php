#!/usr/bin/env php
<?php
/**
 * Validate Bug Fixes
 * 
 * This script validates that all identified bugs have been fixed.
 */

echo "=== Ali123 Bug Fix Validation ===\n\n";

$plugin_dir = '/home/runner/work/Ali123/Ali123/ali123';
$all_fixed = true;

// Bug 1: Missing Import_Queue_Store use statement
echo "Bug Fix 1: Import_Queue_Store use statement\n";
$plugin_file = file_get_contents($plugin_dir . '/includes/class-plugin.php');
if (strpos($plugin_file, 'use Ali123\Importer\Import_Queue_Store;') !== false) {
    echo "  ✓ FIXED: Import_Queue_Store use statement added\n";
} else {
    echo "  ✗ NOT FIXED: Missing use statement\n";
    $all_fixed = false;
}
echo "\n";

// Bug 2: JavaScript accessing row.scheduled_time instead of row.scheduled_at
echo "Bug Fix 2: JavaScript scheduled_at field\n";
$js_file = file_get_contents($plugin_dir . '/assets/js/admin.js');
if (strpos($js_file, 'row.scheduled_time') !== false) {
    echo "  ✗ NOT FIXED: Still using row.scheduled_time\n";
    $all_fixed = false;
} else if (strpos($js_file, 'row.scheduled_at') !== false) {
    echo "  ✓ FIXED: Now correctly uses row.scheduled_at\n";
} else {
    echo "  ⚠ WARNING: Neither field found in JavaScript\n";
}
echo "\n";

// Bug 3: JavaScript accessing row.ali_id instead of row.payload.ali_id
echo "Bug Fix 3: JavaScript payload.ali_id access\n";
$js_file = file_get_contents($plugin_dir . '/assets/js/admin.js');
if (strpos($js_file, 'row.payload.ali_id') !== false || strpos($js_file, 'row.payload && row.payload.ali_id') !== false) {
    echo "  ✓ FIXED: Now correctly accesses row.payload.ali_id\n";
} else if (preg_match('/row\.ali_id(?!\s*[:}])/', $js_file)) {
    echo "  ✗ NOT FIXED: Still accessing row.ali_id directly\n";
    $all_fixed = false;
} else {
    echo "  ⚠ WARNING: Could not verify fix\n";
}
echo "\n";

// Additional validation: Check for proper datetime handling
echo "Additional Check: Datetime handling in JavaScript\n";
if (strpos($js_file, "replace(' ', 'T')") !== false || strpos($js_file, "replace(' ', 'T')" !== false)) {
    echo "  ✓ GOOD: Datetime conversion implemented for MySQL format\n";
} else if (strpos($js_file, 'new Date(row.scheduled_at') !== false) {
    echo "  ⚠ WARNING: Datetime conversion may not handle MySQL format correctly\n";
} else {
    echo "  ℹ INFO: No datetime conversion found\n";
}
echo "\n";

// Validation: Check for null safety
echo "Additional Check: Null safety in JavaScript\n";
if (strpos($js_file, 'row.payload && row.payload.ali_id') !== false || 
    strpos($js_file, 'row.payload.ali_id ? row.payload.ali_id') !== false) {
    echo "  ✓ GOOD: Null safety checks implemented\n";
} else {
    echo "  ⚠ WARNING: Consider adding null safety checks\n";
}
echo "\n";

// Summary
echo "=== Validation Summary ===\n";
if ($all_fixed) {
    echo "✅ All identified bugs have been fixed!\n";
    echo "\nThe plugin is now ready for testing in a WordPress environment.\n";
    exit(0);
} else {
    echo "❌ Some bugs are not fixed. Please review the output above.\n";
    exit(1);
}
