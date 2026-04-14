# Test Plan

## Prerequisites

- PHP 8.1+ with cURL
- Shopify dev store with custom app installed
- ngrok running and webhook registered
- `.env` configured with valid credentials

## Setup checklist

- [ ] Copy `.env.example` to `.env` and fill in credentials
- [ ] Start PHP server: `php -S localhost:8000`
- [ ] Start ngrok: `ngrok http 8000`
- [ ] Register webhook in Shopify pointing to ngrok URL + `/index.php`

## Test 1 — HMAC validation

Send a request with an invalid signature:

```bash
curl -X POST http://localhost:8000/index.php \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: orders/create" \
  -H "X-Shopify-Hmac-Sha256: invalidsignature" \
  -d '{"test": true}'
```

Expected: `401` response. Log shows `HMAC mismatch`.

## Test 2 — Webhook connectivity

Send a test notification from Shopify:

Admin → Settings → Notifications → Webhooks → your webhook → Send test notification

Expected: `200` response. Log shows `Order received`.

## Test 3 — Full order processing

```bash
php -r "
require 'src/Config.php';
require 'src/Logger.php';
require 'src/ShopifyClient.php';
require 'src/PickupPointParser.php';
require 'src/OrderUpdater.php';

\$orderId = 'gid://shopify/Order/YOUR_ORDER_ID';
\$parsed  = parse_external_id('Ackermans-EXPRESS-1569');
\$price   = get_shipping_price(\$orderId);

update_shipping_line(\$orderId, format_shipping_title(\$parsed), \$price['amount'], \$price['currencyCode']);
update_metafields(\$orderId, \$parsed['method'], \$parsed['branch_code']);
update_tags(\$orderId, build_tags(\$parsed));

echo 'Done' . PHP_EOL;
"
```

## Test 4 — Verify results in GraphiQL

```graphql
query {
  order(id: "gid://shopify/Order/YOUR_ORDER_ID") {
    shippingLines(first: 1) {
      nodes {
        code
        title
        originalPriceSet {
          shopMoney { amount currencyCode }
        }
      }
    }
    metafields(first: 10, namespace: "delivery") {
      edges {
        node { namespace key value }
      }
    }
    tags
  }
}
```

Expected:
shippingLines.title:             "Ackermans - EXPRESS - 1569"
shippingLines.amount:            original price preserved
metafields delivery.method:      "EXPRESS"
metafields delivery.branch_code: "1569"
tags: ["branch:1569", "click-and-collect-express", "delivery:express"]

## Test 5 — Order without pickup point

Send a Shopify test notification (dummy order, no pickup point).

Expected: Log shows `No pickup point — skipping update`. No mutations run.

## Test 6 — Invalid externalId

```bash
php -r "
require 'src/Config.php';
require 'src/Logger.php';
require 'src/PickupPointParser.php';
var_dump(parse_external_id('INVALID'));
"
```

Expected: Returns `NULL`. Log shows `Invalid externalId format`.

## Sample externalId formats

| externalId | Courier | Method | Branch |
|---|---|---|---|
| `Ackermans-EXPRESS-1569` | Ackermans | EXPRESS | 1569 |
| `Ackermans-COLLECT-2014` | Ackermans | COLLECT | 2014 |
| `CourierName-DELIVERY-0519` | CourierName | DELIVERY | 0519 |

## Note on Pickup Point Generator beta

The `deliveryOptionGeneratorPickupPoint.externalId` field requires Shopify's Pickup Point Generator beta to be enabled on the store. During testing, the externalId was hardcoded in `index.php`. In production, uncomment the `get_pickup_external_id()` call and remove the hardcoded value.
