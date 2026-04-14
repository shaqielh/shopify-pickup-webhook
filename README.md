# Shopify Pickup Webhook Handler

A PHP webhook handler that processes Shopify `orders/create` events and enriches orders with pickup point data from the Pickup Point Generator.

## What it does

When an order is created with a pickup point selected, this handler:

1. Validates the webhook signature (HMAC)
2. Queries the Shopify GraphQL API (unstable) to extract the pickup point `externalId`
3. Parses the `externalId` format: `<COURIER>-<METHOD>-<BRANCHCODE>`
4. Updates the order in three places:
   - Shipping line title: `"Ackermans - EXPRESS - 1569"`
   - Metafields: `delivery.method` and `delivery.branch_code`
   - Tags: `delivery:express`, `branch:1569`, `click-and-collect-express`

## Project structure
shopify-pickup-handler/
├── index.php                 — Webhook entry point
├── src/
│   ├── Config.php            — Loads .env and defines constants
│   ├── Logger.php            — File-based logger
│   ├── ShopifyClient.php     — GraphQL client and query functions
│   ├── PickupPointParser.php — Parses and formats externalId
│   └── OrderUpdater.php      — Handles all three order mutations
├── logs/
│   └── app.log               — Runtime logs (gitignored)
├── .env                      — Local credentials (gitignored)
└── .env.example              — Credential template

## Requirements

- PHP 8.1+
- cURL extension
- Shopify custom app with scopes:
  - `read_orders`, `write_orders`
  - `read_fulfillments`, `write_fulfillments`
  - `write_order_edits`

## Setup

**1. Clone the repo**
```bash
git clone https://github.com/shaqielh/shopify-pickup-webhook.git
cd shopify-pickup-webhook
```

**2. Configure credentials**
```bash
cp .env.example .env
```

Fill in `.env`:
SHOPIFY_STORE_DOMAIN=your-store.myshopify.com
SHOPIFY_ADMIN_API_TOKEN=shpat_xxxxxxxxxxxxxxxxxxxxx
SHOPIFY_WEBHOOK_SECRET=xxxxxxxxxxxxxxxxxxxxx
SHOPIFY_API_VERSION_STABLE=2026-04
SHOPIFY_API_VERSION_UNSTABLE=unstable
LOG_LEVEL=debug
LOG_PATH=logs/app.log

**3. Start the PHP server**
```bash
php -S localhost:8000
```

**4. Expose with ngrok**
```bash
ngrok http 8000
```

**5. Register webhook in Shopify**

Admin → Settings → Notifications → Webhooks → Create webhook

| Field | Value |
|---|---|
| Event | Order creation |
| Format | JSON |
| URL | `https://your-ngrok-url/index.php` |
| API version | 2026-04 |

## API version strategy

Two versions are used intentionally. The unstable API is required to query `deliveryOptionGeneratorPickupPoint.externalId` on the fulfillment order — this field does not exist in any stable version. All order mutations use the stable API so downstream systems reading order data get consistent results.

## Logging

Logs write to `logs/app.log`. Set `LOG_LEVEL=debug` in `.env` during development to see full request details.
