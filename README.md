# Shopify Pickup Webhook Handler

A PHP webhook handler for processing Shopify `orders/create` events. When an order comes in with a pickup point attached, it extracts the pickup details and writes them back to the order in three places so downstream fulfilment systems have what they need.

## What it does

1. Validates the webhook signature to confirm the request came from Shopify
2. Queries the GraphQL API (unstable branch) to pull the pickup point `externalId`
3. Parses the `externalId` — format is `<COURIER>-<METHOD>-<BRANCHCODE>`
4. Updates the order with the structured data:
   - Shipping line title updated to e.g. `Ackermans - EXPRESS - 1569`
   - Two metafields written: `delivery.method` and `delivery.branch_code`
   - Three tags added for easy filtering in Shopify Admin

## Project structure
shopify-pickup-handler/
├── index.php                 — Entry point, handles the incoming webhook
├── src/
│   ├── Config.php            — Reads .env and sets up constants
│   ├── Logger.php            — Writes to logs/app.log
│   ├── ShopifyClient.php     — GraphQL client and query functions
│   ├── PickupPointParser.php — Parses externalId and builds title/tags
│   └── OrderUpdater.php      — Runs the three order mutations
├── logs/
│   └── app.log               — Runtime logs (gitignored)
├── .env                      — Your credentials (gitignored)
└── .env.example              — Credential template

## Requirements

- PHP 8.1+
- cURL extension enabled
- Shopify custom app with these scopes:
  - `read_orders`, `write_orders`
  - `read_fulfillments`, `write_fulfillments`
  - `write_order_edits`

## Setup

**1. Clone the repo**
```bash
git clone https://github.com/shaqielh/shopify-pickup-webhook.git
cd shopify-pickup-webhook
```

**2. Set up credentials**
```bash
cp .env.example .env
```

Open `.env` and fill in your values:
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

**4. Expose it publicly with ngrok**
```bash
ngrok http 8000
```

**5. Register the webhook in Shopify**

Admin > Settings > Notifications > Webhooks > Create webhook

| Field | Value |
|---|---|
| Event | Order creation |
| Format | JSON |
| URL | `https://your-ngrok-url/index.php` |
| API version | 2026-04 |

## A note on API versions

Two versions are used on purpose. The pickup point field (`deliveryOptionGeneratorPickupPoint.externalId`) only exists in the unstable API — it is not available in any stable release yet. All mutations that write back to the order use the stable API (`2026-04`) so that other systems reading order data get consistent results.

## Logging

Everything gets logged to `logs/app.log`. During development, `LOG_LEVEL=debug` in `.env` will log the full parsed pickup data. Switch to `info` in production to keep things tidy.
