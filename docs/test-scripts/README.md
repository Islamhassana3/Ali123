# Ali123 Plugin Test Scripts

This directory contains automated test scripts for validating the Ali123 plugin.

## Available Scripts

### 1. test-ali123-plugin.php
**Purpose:** Basic plugin validation  
**What it tests:**
- PHP syntax validation for all files
- Code quality checks (use statements, class definitions)
- File structure verification
- WordPress integration basics

**Run it:**
```bash
php test-ali123-plugin.php
```

**Expected output:** All tests should pass (5/5)

---

### 2. wordpress-integration-test.php
**Purpose:** WordPress integration validation  
**What it tests:**
- Activation/deactivation hooks
- REST API endpoint registration
- Database schema structure
- Cron job configuration
- Admin menu integration
- Settings page structure
- Service container setup
- JavaScript integration
- Security checks (permissions, sanitization)

**Run it:**
```bash
php wordpress-integration-test.php
```

**Expected output:** All integration tests pass

---

### 3. validate-fixes.php
**Purpose:** Verify all bugs were fixed  
**What it tests:**
- Import_Queue_Store use statement present
- JavaScript uses correct field names
- Data structure access is correct
- Null safety implemented

**Run it:**
```bash
php validate-fixes.php
```

**Expected output:** All bugs fixed ✅

---

## Running All Tests

Run all tests in sequence:
```bash
cd docs/test-scripts
php test-ali123-plugin.php && \
php wordpress-integration-test.php && \
php validate-fixes.php
```

Or as a one-liner:
```bash
for script in *.php; do [ -f "$script" ] && php "$script" && echo ""; done
```

## Test Requirements

These scripts can run **without WordPress installed**. They perform static analysis of the plugin code.

**Requirements:**
- PHP 7.4 or higher
- Access to the plugin files

## Understanding Test Output

### Success Indicators
- `✓ PASS:` - Test passed successfully
- `✓ FIXED:` - Bug has been fixed
- `✓ GOOD:` - Code quality check passed

### Warning Indicators
- `⚠ WARNING:` - Potential issue (not critical)
- `ℹ INFO:` - Informational message

### Failure Indicators
- `✗ FAIL:` - Test failed
- `✗ NOT FIXED:` - Bug not fixed

## When to Run These Tests

### During Development
Run after making any code changes to ensure nothing broke.

### Before Committing
Verify all tests pass before committing changes.

### Before Deployment
Final validation before deploying to staging or production.

### Continuous Integration
Can be integrated into CI/CD pipeline for automated testing.

## Adding New Tests

To add a new test script:
1. Create a new PHP file in this directory
2. Follow the pattern from existing scripts
3. Make it executable: `chmod +x your-script.php`
4. Update this README with script description

## Test Coverage

Current test coverage:
- ✅ PHP syntax validation
- ✅ Code structure validation
- ✅ WordPress integration
- ✅ Security best practices
- ✅ Bug fix verification
- ⚠️ Functional testing (requires WordPress environment)
- ⚠️ Performance testing (requires WordPress environment)

## Next Steps

For functional testing in WordPress environment, see:
- [`../TESTING_GUIDE.md`](../TESTING_GUIDE.md) - Complete testing instructions
- [`../QUICK_START.md`](../QUICK_START.md) - Quick installation and testing

---

**Note:** These scripts perform static analysis only. For complete testing, install the plugin in a WordPress environment and follow the TESTING_GUIDE.md.
