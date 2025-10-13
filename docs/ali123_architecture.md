# Ali123 – AliExpress Business Integration Architecture Proposal

## Confirmation
We have reviewed the Ali123 specification and understand the full scope of dropshipping, fulfillment automation, and AliExpress Business feature integrations required for the WooCommerce plugin.

## High-Level Architecture Overview
- **Core Components**
  - **WordPress/WooCommerce Plugin** written in PHP following WordPress coding standards, leveraging custom post types, taxonomies, and WooCommerce data stores for products, orders, and variations.
  - **Background Worker Layer** utilizing WP-Cron and optional external queue workers (via Action Scheduler or custom queue runner) for high-volume imports, synchronization, and scheduled jobs.
  - **AliExpress Integration Service** implemented as a PHP service layer with REST client abstraction, handling OAuth2/Access Token management, request signing, rate limiting, and retries.
  - **Data Persistence** through WooCommerce HPOS-compatible tables and custom tables for import lists, sync jobs, logs, and AliExpress mappings (products, variants, vouchers, RFQs).
  - **Chrome Extension Bridge** communicating via a secured REST endpoint with nonce/secret validation for one-click imports and authenticated user actions.
  - **AI & Automation Hooks** (future-proof) enabling integration with analytics and recommendation engines using webhook events and scheduled data exports.
- **Scalability Considerations**
  - Bulk imports split into batched tasks (e.g., 200 item chunks) processed in parallel background workers.
  - Optimistic locking and checksum-based differential updates to minimize redundant operations.
  - CDN-aware media ingestion pipeline with deduplication via hashed URLs.

## AliExpress Business Feature Integrations
| Feature | API Touchpoints & Data Flow | Workflow Integration | Storage/Tracking |
| --- | --- | --- | --- |
| Monthly Vouchers | Utilize AliExpress Business voucher API to fetch available vouchers per store token, cache in custom table with expiry; apply vouchers during price calculations and order submission. | Scheduled job retrieves vouchers daily; voucher selection UI in pricing rules; fulfillment flow attaches voucher IDs to checkout payload. | `wp_ali123_vouchers` storing voucher metadata and usage logs.
| Logo-Free Packaging | Expose option per supplier/product using AliExpress logistics API flags; request flag during order creation. | Product import includes supplier capability check; order fulfillment UI toggles logo-free flag by default. | Mapping table storing supplier packaging capabilities and audit log per order.
| No Price Information in Packages | During order submission, include appropriate packaging instruction flag. | Global setting with per-order override; validation ensures field is present in payload. | Stored in order meta synced with AliExpress response confirmation.
| Market Trend Insights | Connect to AliExpress Market Trend/Insight API for trending categories and SKUs; ingest data into discovery service feeding import list. | Nightly job refreshes trends, surfaces in admin dashboard with filters; AI assistant can cross-reference store analytics. | `wp_ali123_trends` table storing scores, categories, timestamp.
| RFQ & Sourcing Center | Embed authenticated iframe or API-based chat module for RFQ; maintain conversations via AliExpress seller contact APIs. | RFQ wizard accessible from import list; ability to convert RFQ result directly into import pipeline. | Tables for RFQ threads, attachments metadata, and linked products.

## API Feasibility Assessment
- **Authentication**: AliExpress Business APIs support OAuth2 client credentials and refresh tokens. We will implement a token manager with secure storage in the WordPress options table using encryption (e.g., `openssl_encrypt`).
- **Rate Limits**: Documented limits (e.g., 500 requests/day per endpoint) require batching and caching. The background worker will queue calls and respect limits using token bucket algorithm.
- **Data Coverage**: APIs expose product details, logistics options, vouchers, and RFQ endpoints, which align with required features. Some advanced analytics (market trends) may be available via beta endpoints; we will engage with AliExpress Business support to confirm access tiers.
- **Feasibility Risks**: Potential restrictions around RFQ messaging and voucher auto-claiming; we plan to confirm whether programmatic claiming is permitted or if manual confirmation is needed.

## Workflow Automation Strategy
1. **Import Pipeline**
   - Triggered via Chrome extension, category bulk selection, or scheduled sync.
   - Products staged in import list table with normalized attributes.
   - Attribute mapping templates applied; AI-driven suggestions optional.
   - Approved items pushed to WooCommerce using batched background jobs.
2. **Pricing & Inventory Sync**
   - Pricing engine applies multi-layer rules, currency conversion using scheduled exchange rate fetches.
   - Scheduled cron tasks reconcile price and inventory every X hours; differential updates reduce API calls.
3. **Order Fulfillment**
   - Orders tagged as AliExpress-sourced via product metadata.
   - Fulfillment service assembles payload including voucher IDs, packaging flags, and custom meta.
   - Tracking numbers pulled via webhook or scheduled poll, updating WooCommerce order notes and status.
4. **Notifications & Alerts**
   - Event dispatcher records changes (stock, price) and notifies store owners via email/webhooks.
5. **Configuration Portability**
   - Import/export module serializes settings, mappings, and pricing rules into JSON packages for multi-store rollout.

## Milestones & Timeline (Estimate)
1. **Foundation & Infrastructure (3 weeks)**: Plugin skeleton, HPOS compatibility, REST client abstraction, database schema.
2. **Core Import & Product Management (4 weeks)**: Import list UI, attribute mapping, media handling, pricing engine.
3. **Order Fulfillment Module (3 weeks)**: Order detection, fulfillment payloads, tracking synchronization.
4. **AliExpress Business Integrations (4 weeks)**: Vouchers, packaging flags, market insights, RFQ module.
5. **Automation & Scalability Enhancements (2 weeks)**: Scheduled jobs, bulk operations optimization, notification system.
6. **QA, Security Hardening & Documentation (2 weeks)**: Nonce checks, sanitization audits, load testing, user guides.
*Total Estimate: ~18 weeks, adjustable based on API access confirmation and team size.*

## Outstanding Questions
1. Do we have confirmed developer access (API keys, sandbox) for AliExpress Business endpoints, especially Market Insights and RFQ features?
2. Are there preferred third-party services for background job execution (e.g., external queues) or should we rely solely on WordPress cron/action scheduler?
3. Should AI-driven recommendations be part of v1 or scoped for later, and are there existing analytics sources to integrate?
4. What are the localization priorities (languages/regions) to validate translation coverage and currency setups?
5. Are there constraints on hosting environment (PHP version, memory limits) we should account for in scalability planning?

We are ready to refine the plan once these points are clarified and will proceed with detailed technical designs for each module upon confirmation.
