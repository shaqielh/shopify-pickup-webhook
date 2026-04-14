<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Logger.php';

function graphql_request(string $url, string $query, array $variables = []): ?array
{
    $payload = json_encode(['query' => $query, 'variables' => $variables]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Shopify-Access-Token: ' . SHOPIFY_ADMIN_API_TOKEN,
        ],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        log_message('error', 'cURL error', ['error' => $curlError]);
        return null;
    }

    if ($httpCode !== 200) {
        log_message('error', 'GraphQL request failed', ['status' => $httpCode, 'body' => $response]);
        return null;
    }

    $data = json_decode($response, true);

    if (isset($data['errors'])) {
        log_message('error', 'GraphQL errors returned', ['errors' => $data['errors']]);
        return null;
    }

    return $data['data'] ?? null;
}

function get_pickup_external_id(string $orderId): ?string
{
    $query = '
        query getPickupPoint($id: ID!) {
            order(id: $id) {
                fulfillmentOrders(first: 5) {
                    nodes {
                        deliveryMethod {
                            deliveryOptionGeneratorPickupPoint {
                                externalId
                            }
                        }
                    }
                }
            }
        }
    ';

    $data = graphql_request(SHOPIFY_GRAPHQL_URL_UNSTABLE, $query, ['id' => $orderId]);

    if (!$data) {
        log_message('warning', 'No data from fulfillment query', ['order_id' => $orderId]);
        return null;
    }

    $fulfillmentOrders = $data['order']['fulfillmentOrders']['nodes'] ?? [];

    foreach ($fulfillmentOrders as $fo) {
        $externalId = $fo['deliveryMethod']['deliveryOptionGeneratorPickupPoint']['externalId'] ?? null;
        if ($externalId) {
            log_message('info', 'Pickup externalId found', [
                'order_id'    => $orderId,
                'external_id' => $externalId,
            ]);
            return $externalId;
        }
    }

    log_message('info', 'No pickup point on order', ['order_id' => $orderId]);
    return null;
}

function get_shipping_price(string $orderId): array
{
    $query = '
        query getShipping($id: ID!) {
            order(id: $id) {
                shippingLines(first: 1) {
                    nodes {
                        originalPriceSet {
                            shopMoney {
                                amount
                                currencyCode
                            }
                        }
                    }
                }
            }
        }
    ';

    $data = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $query, ['id' => $orderId]);

    $money = $data['order']['shippingLines']['nodes'][0]['originalPriceSet']['shopMoney'] ?? null;

    if (!$money) {
        log_message('warning', 'Could not fetch shipping price, defaulting to 0', ['order_id' => $orderId]);
        return ['amount' => '0.00', 'currencyCode' => 'ZAR'];
    }

    return $money;
}
