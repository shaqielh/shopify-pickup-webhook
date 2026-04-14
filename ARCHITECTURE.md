# Architecture

## Overview
Shopify → POST /index.php → HMAC check → GraphQL query → Parse → 3x Mutations

## File responsibilities

- `index.php` — Entry point. Validates the webhook, extracts the order ID, coordinates the pipeline.
- `src/Config.php` — Loads `.env` and defines all constants including both GraphQL URLs.
- `src/Logger.php` — Writes timestamped log entries to `logs/app.log`.
- `src/ShopifyClient.php` — Low-level GraphQL client plus two query functions: `get_pickup_external_id()` and `get_shipping_price()`.
- `src/PickupPointParser.php` — Parses `externalId` into parts, formats the shipping title, builds the tag array.
- `src/OrderUpdater.php` — Three mutation functions: `update_shipping_line()`, `update_metafields()`, `update_tags()`.

## Key decisions

**Respond before processing**
The handler returns `200 OK` to Shopify immediately after HMAC validation. All GraphQL work runs after the response is flushed. Shopify enforces a 5 second timeout on webhook delivery — any slower response triggers a retry.

**Two API versions**
`deliveryOptionGeneratorPickupPoint.externalId` only exists in the unstable API. All order mutations use the stable API so downstream systems reading from stable get consistent data. Both URLs are constants built at boot time in `Config.php`.

**Order Editing API for shipping lines**
Shopify does not allow direct mutation of existing shipping lines via the API. The only supported approach is the Order Editing API: open a session with `orderEditBegin`, stage a remove and an add, then commit with `orderEditCommit`. This preserves the original price and is the pattern Shopify recommends.

**`metafieldsSet` over `metafieldCreate`**
`metafieldsSet` is an upsert — calling it multiple times on the same order is safe and won't create duplicates. `metafieldCreate` would error on a second run.

**`tagsAdd` is additive**
`tagsAdd` appends without replacing, so existing tags on the order are preserved. Safe to call on any order regardless of current tag state.

**Flat functions over classes**
For a focused single-purpose integration, plain functions grouped by file are easier to read and explain than classes. The four source files map directly to the four concerns: config, logging, API calls, and business logic.

## Data flow

Shopify POSTs order payload to /index.php
HMAC header validated against webhook secret
Order ID extracted from payload
200 OK returned to Shopify
Unstable API queried for pickup externalId
externalId parsed → courier, method, branch_code
Stable API: shipping line updated via Order Editing API
Stable API: metafields set (delivery.method, delivery.branch_code)
Stable API: tags added (3 tags)
All steps logged to logs/app.log


## Error handling

Every GraphQL response is checked for `errors` and `userErrors`. On failure the error is logged with full context and the function returns `false`. The caller stops the pipeline. Since Shopify already received `200 OK`, no retry is triggered — failures are visible in the log only.
