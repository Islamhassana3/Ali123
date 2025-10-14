# Ali123 Plugin Testing Guide

## Overview
This guide provides comprehensive testing instructions for the Ali123 Dropshipping Automation WordPress plugin.

## Pre-Installation Testing

### 1. PHP Syntax Validation
All PHP files pass syntax validation:
```bash
find ali123/ -name "*.php" -exec php -l {} \;
```

### 2. Code Quality Checks
- ✅ All required use statements are present
- ✅ No undefined class references
- ✅ Proper namespace declarations

### 3. JavaScript Validation
- ✅ Correct data access patterns (row.payload.ali_id)
- ✅ Proper datetime handling (row.scheduled_at)
- ✅ WordPress i18n integration

## Installation Testing

### Step 1: Install Plugin
1. Copy the `ali123` directory to `wp-content/plugins/`
2. Navigate to WordPress admin → Plugins
3. Activate "Ali123 Dropshipping Automation"

### Step 2: Verify Activation
Check for these indicators:
- ✅ No PHP errors or warnings
- ✅ New menu item "Ali123" appears in admin
- ✅ Database table `wp_ali123_queue` created

Verify database table:
```sql
SHOW TABLES LIKE 'wp_ali123_queue';
DESCRIBE wp_ali123_queue;
```

Expected columns:
- id (BIGINT)
- store_id (BIGINT)
- status (VARCHAR)
- attempts (SMALLINT)
- scheduled_at (DATETIME)
- payload (LONGTEXT)
- last_error (TEXT)
- last_error_at (DATETIME)
- created_at (DATETIME)
- updated_at (DATETIME)

### Step 3: Verify Cron Job
Check if cron event is scheduled:
```php
wp_next_scheduled('ali123_process_queue');
// Should return a timestamp
```

Check custom interval:
```php
wp_get_schedules();
// Should include 'five_minutes' with 300 second interval
```

## Functional Testing

### Test 1: Admin Menu Pages

#### Dashboard Page
1. Navigate to **Ali123 → Ali123**
2. Verify: Page loads without errors
3. Verify: Message displayed: "Ali123 automation is ready..."

#### Import List Page
1. Navigate to **Ali123 → Import List**
2. Verify: Page loads without errors
3. Verify: Empty table or list of imports displayed
4. Check browser console for JavaScript errors

#### Orders Page
1. Navigate to **Ali123 → Orders**
2. Verify: "Sync Tracking Now" button appears
3. Click button
4. Verify: Button changes to "Scheduling..." then "Scheduled"

#### Settings Page
1. Navigate to **Ali123 → Settings**
2. Verify: Form with following fields:
   - App Key (text input)
   - App Secret (password input)
   - Store Hash (text input)
   - Default Product Status (dropdown)
   - Default Catalog Visibility (dropdown)
3. Enter test values and save
4. Verify: Settings saved successfully

### Test 2: REST API Endpoints

#### Test GET /wp-json/ali123/v1/imports
```bash
curl -X GET "http://your-site.com/wp-json/ali123/v1/imports" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --user admin:password
```
Expected: 200 response with array of imports (empty if none)

#### Test POST /wp-json/ali123/v1/imports
```bash
curl -X POST "http://your-site.com/wp-json/ali123/v1/imports" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --user admin:password \
  -d '{
    "ali_id": "123456789",
    "title": "Test Product",
    "price": {"regular": 29.99},
    "status": "draft"
  }'
```
Expected: 201 response with created import entry

#### Test GET /wp-json/ali123/v1/imports/{id}
Via PATCH to update:
```bash
curl -X PATCH "http://your-site.com/wp-json/ali123/v1/imports/1" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --user admin:password \
  -d '{"status": "processing"}'
```
Expected: 200 response with updated entry

#### Test DELETE /wp-json/ali123/v1/imports/{id}
```bash
curl -X DELETE "http://your-site.com/wp-json/ali123/v1/imports/1" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --user admin:password
```
Expected: 204 No Content

#### Test POST /wp-json/ali123/v1/orders/sync
```bash
curl -X POST "http://your-site.com/wp-json/ali123/v1/orders/sync" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --user admin:password
```
Expected: 202 response with {"status": "scheduled"}

#### Test POST /wp-json/ali123/v1/pricing/preview
```bash
curl -X POST "http://your-site.com/wp-json/ali123/v1/pricing/preview" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  --user admin:password \
  -d '{
    "price": {"regular": 10.00},
    "price_rules": [
      {"type": "multiplier", "value": 2.0}
    ]
  }'
```
Expected: 200 response with original and preview prices

### Test 3: Import Queue Functionality

#### Create Import Entry
Use WordPress admin or REST API to create import entry.

#### Trigger Processing
Manually trigger cron:
```bash
wp cron event run ali123_process_queue
```

Or programmatically:
```php
do_action('ali123/queue/process');
```

#### Verify Processing
1. Check queue status changed from 'pending' to 'processing' or 'completed'
2. Check attempts counter incremented
3. Verify WooCommerce product created (if successful)

### Test 4: Product Mapping

#### Test Product Creation
1. Create import with complete payload:
```json
{
  "ali_id": "987654321",
  "title": "Test Product Name",
  "description": "Product description",
  "price": {"regular": 49.99, "sale": 39.99},
  "status": "publish",
  "visibility": "visible",
  "images": [],
  "variations": [],
  "attributes": []
}
```

2. Process the queue
3. Verify WooCommerce product created with:
   - Correct title
   - Correct description
   - Regular price: 49.99
   - Sale price: 39.99
   - Post meta `_ali123_ali_id`: "987654321"

#### Test Product Update
1. Modify existing import with same ali_id
2. Process queue
3. Verify product updated (not duplicated)

### Test 5: Pricing Rules Engine

#### Test Fixed Price Rule
```json
{
  "price": {"regular": 10.00},
  "price_rules": [
    {"type": "fixed", "value": 25.00}
  ]
}
```
Expected result: regular price = 25.00

#### Test Multiplier Rule
```json
{
  "price": {"regular": 10.00},
  "price_rules": [
    {"type": "multiplier", "value": 3.0}
  ]
}
```
Expected result: regular price = 30.00

#### Test Percentage Rule
```json
{
  "price": {"regular": 10.00},
  "price_rules": [
    {"type": "percentage", "value": 50}
  ]
}
```
Expected result: regular price = 15.00 (10 + 50%)

#### Test Pretty Pricing
```json
{
  "price": {"regular": 27.34},
  "price_rules": [
    {"type": "fixed", "value": 27.34, "pretty": 0.99}
  ]
}
```
Expected result: regular price = 27.99

### Test 6: Error Handling

#### Test Invalid ali_id
Create import without ali_id:
```json
{"title": "Test", "price": {"regular": 10}}
```
Expected: Error "AliExpress identifier missing"

#### Test Failed Processing
1. Create import with invalid data
2. Process queue
3. Verify:
   - Status changed to 'failed'
   - last_error populated
   - last_error_at timestamp set

#### Test Permission Checks
1. Log out or use non-admin user
2. Try to access REST endpoints
3. Expected: 403 Forbidden or permission error

### Test 7: Deactivation

#### Test Clean Deactivation
1. Deactivate plugin
2. Verify cron event cleared:
```php
wp_next_scheduled('ali123_process_queue');
// Should return false
```
3. Database table should persist (not deleted on deactivation)

#### Test Reactivation
1. Reactivate plugin
2. Verify:
   - Cron event rescheduled
   - Database table still intact
   - No duplicate table creation errors

## Performance Testing

### Load Testing
1. Create 100+ import entries
2. Trigger queue processing
3. Monitor:
   - Processing time
   - Memory usage
   - Database queries

### Concurrent Processing
1. Ensure only pending items are claimed
2. Verify no race conditions with multiple workers

## Security Testing

### Permission Checks
- ✅ REST API requires 'manage_woocommerce' capability
- ✅ Admin pages require 'manage_woocommerce' capability
- ✅ Nonce verification for AJAX requests

### Data Sanitization
- ✅ All input sanitized with sanitize_text_field or wp_kses_post
- ✅ deep_sanitize_text_field for arrays
- ✅ SQL queries use prepared statements

### SQL Injection Prevention
- ✅ All $wpdb queries use prepare() method
- ✅ No direct SQL concatenation

## Debugging Tips

### Enable WordPress Debug Mode
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Error Log
```bash
tail -f wp-content/debug.log
```

### Database Queries
Enable query logging:
```php
define('SAVEQUERIES', true);
```

View queries:
```php
global $wpdb;
print_r($wpdb->queries);
```

### Cron Debugging
List all scheduled events:
```bash
wp cron event list
```

Test specific event:
```bash
wp cron event run ali123_process_queue --due-now
```

## Common Issues and Solutions

### Issue: Database table not created
**Solution**: Check activation errors, verify dbDelta requirements, manually run activate() method

### Issue: Cron not running
**Solution**: Check wp-cron.php execution, verify hosting allows cron, use server cron instead

### Issue: REST API 404
**Solution**: Flush rewrite rules: Settings → Permalinks → Save Changes

### Issue: JavaScript errors
**Solution**: Check browser console, verify wp-i18n and wp-api scripts enqueued

### Issue: Imports not processing
**Solution**: Check cron execution, verify 'ali123/queue/process' action hook, check for PHP errors

## Test Checklist

- [ ] Plugin activates without errors
- [ ] Database table created correctly
- [ ] Cron job scheduled
- [ ] Admin menu appears
- [ ] Dashboard page loads
- [ ] Import list page loads
- [ ] Orders page loads with button
- [ ] Settings page displays form
- [ ] Settings can be saved
- [ ] REST API endpoints respond correctly
- [ ] Import queue accepts new entries
- [ ] Queue processing works
- [ ] Products created in WooCommerce
- [ ] Pricing rules apply correctly
- [ ] Error handling works
- [ ] Permission checks enforced
- [ ] Plugin deactivates cleanly
- [ ] No PHP errors in logs
- [ ] No JavaScript console errors

## Success Criteria

✅ All PHP files have valid syntax
✅ All tests pass without errors
✅ Plugin integrates correctly with WordPress
✅ REST API endpoints function as expected
✅ Queue processing completes successfully
✅ Products sync to WooCommerce store
✅ No security vulnerabilities identified
✅ Performance is acceptable under load
