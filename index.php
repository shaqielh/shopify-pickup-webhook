<?php

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Logger.php';

// Only handle POST
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

// Verify this came from Shopify
$hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

if (!$hmac) {
    log_message('warning', 'No HMAC header');
    http_response_code(401);
    exit;
}

$expected = base64_encode(hash_hmac('sha256', $body, SHOPIFY_WEBHOOK_SECRET, true));

if (!hash_equals($expected, $hmac)) {
    log_message('warning', 'HMAC mismatch', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(401);
    exit;
}

// We only care about order creation
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

// Respond fast — Shopify has a 5s timeout
http_response_code(200);
echo 'OK';

// Next: query fulfillment data for pickup point
