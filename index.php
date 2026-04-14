<?php

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Logger.php';
require_once __DIR__ . '/src/ShopifyClient.php';
require_once __DIR__ . '/src/PickupPointParser.php';
require_once __DIR__ . '/src/OrderUpdater.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$body = file_get_contents('php://input');

if (!$body) {
    log_message('warning', 'Empty body');
    http_response_code(400);
    exit;
}

$hmac     = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$expected = base64_encode(hash_hmac('sha256', $body, SHOPIFY_WEBHOOK_SECRET, true));

if (!$hmac || !hash_equals($expected, $hmac)) {
    log_message('warning', 'HMAC mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(401);
    exit;
}

$topic = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';

if ($topic !== 'orders/create') {
    http_response_code(200);
    exit;
}

$order = json_decode($body, true);

if (!$order || json_last_error() !== JSON_ERROR_NONE) {
    log_message('error', 'Bad JSON payload');
    http_response_code(422);
    exit;
}

$orderId = $order['admin_graphql_api_id'] ?? null;

if (!$orderId) {
    log_message('error', 'No order ID in payload');
    http_response_code(422);
    exit;
}

log_message('info', 'Order received', [
    'id'   => $orderId,
    'name' => $order['name'] ?? '',
]);

// Respond to Shopify immediately
http_response_code(200);
echo 'OK';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// For testing: hardcode externalId since Pickup Point Generator beta isn't enabled
// In production this comes from: get_pickup_external_id($orderId)
$externalId = 'Ackermans-EXPRESS-1569';

// Uncomment for production:
// $externalId = get_pickup_external_id($orderId);
// if (!$externalId) {
//     log_message('info', 'No pickup point — skipping', ['order_id' => $orderId]);
//     exit;
// }

$parsed = parse_external_id($externalId);

if (!$parsed) {
    log_message('error', 'Could not parse externalId', ['external_id' => $externalId]);
    exit;
}

$shippingTitle = format_shipping_title($parsed);
$tags          = build_tags($parsed);

// Fetch original shipping price to preserve it
$shippingPrice = get_shipping_price($orderId);

// Step 5: update shipping line title
update_shipping_line(
    $orderId,
    $shippingTitle,
    $shippingPrice['amount'],
    $shippingPrice['currencyCode']
);

// Step 6: set metafields
update_metafields($orderId, $parsed['method'], $parsed['branch_code']);

// Step 7: add tags
update_tags($orderId, $tags);

log_message('info', 'Order processing complete', [
    'order_id' => $orderId,
    'title'    => $shippingTitle,
    'tags'     => $tags,
]);
