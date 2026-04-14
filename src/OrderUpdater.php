<?php

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/ShopifyClient.php';

function update_shipping_line(string $orderId, string $title, string $price, string $currency): bool
{
    // Step 1: open an edit session and grab the current shipping line ID
    $beginQuery = '
        mutation beginEdit($id: ID!) {
            orderEditBegin(id: $id) {
                calculatedOrder {
                    id
                    shippingLines {
                        id
                    }
                }
                userErrors { field message }
            }
        }
    ';

    $begin = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $beginQuery, ['id' => $orderId]);

    if (!$begin) {
        log_message('error', 'orderEditBegin failed', ['order_id' => $orderId]);
        return false;
    }

    $userErrors = $begin['orderEditBegin']['userErrors'] ?? [];
    if (!empty($userErrors)) {
        log_message('error', 'orderEditBegin errors', ['errors' => $userErrors]);
        return false;
    }

    $calculatedOrderId = $begin['orderEditBegin']['calculatedOrder']['id'] ?? null;
    $shippingLines     = $begin['orderEditBegin']['calculatedOrder']['shippingLines'] ?? [];

    if (!$calculatedOrderId) {
        log_message('error', 'No calculatedOrder ID returned', ['order_id' => $orderId]);
        return false;
    }

    // Step 2: remove existing shipping lines
    foreach ($shippingLines as $line) {
        $removeQuery = '
            mutation removeShipping($id: ID!, $lineId: ID!) {
                orderEditRemoveShippingLine(id: $id, shippingLineId: $lineId) {
                    userErrors { field message }
                }
            }
        ';

        $remove = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $removeQuery, [
            'id'     => $calculatedOrderId,
            'lineId' => $line['id'],
        ]);

        $removeErrors = $remove['orderEditRemoveShippingLine']['userErrors'] ?? [];
        if (!empty($removeErrors)) {
            log_message('error', 'Failed to remove shipping line', ['errors' => $removeErrors]);
            return false;
        }
    }

    // Step 3: add new shipping line with updated title
    $addQuery = '
        mutation addShipping($id: ID!, $line: OrderEditAddShippingLineInput!) {
            orderEditAddShippingLine(id: $id, shippingLine: $line) {
                userErrors { field message }
            }
        }
    ';

    $add = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $addQuery, [
        'id'   => $calculatedOrderId,
        'line' => [
            'title' => $title,
            'price' => [
                'amount'       => $price,
                'currencyCode' => $currency,
            ],
        ],
    ]);

    $addErrors = $add['orderEditAddShippingLine']['userErrors'] ?? [];
    if (!empty($addErrors)) {
        log_message('error', 'Failed to add shipping line', ['errors' => $addErrors]);
        return false;
    }

    // Step 4: commit the edit
    $commitQuery = '
        mutation commitEdit($id: ID!) {
            orderEditCommit(id: $id, notifyCustomer: false) {
                userErrors { field message }
            }
        }
    ';

    $commit = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $commitQuery, [
        'id' => $calculatedOrderId,
    ]);

    $commitErrors = $commit['orderEditCommit']['userErrors'] ?? [];
    if (!empty($commitErrors)) {
        log_message('error', 'Failed to commit order edit', ['errors' => $commitErrors]);
        return false;
    }

    log_message('info', 'Shipping line updated', [
        'order_id' => $orderId,
        'title'    => $title,
    ]);

    return true;
}

function update_metafields(string $orderId, string $method, string $branchCode): bool
{
    $query = '
        mutation setMetafields($metafields: [MetafieldsSetInput!]!) {
            metafieldsSet(metafields: $metafields) {
                userErrors { field message }
            }
        }
    ';

    $data = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $query, [
        'metafields' => [
            [
                'ownerId'   => $orderId,
                'namespace' => 'delivery',
                'key'       => 'method',
                'value'     => $method,
                'type'      => 'single_line_text_field',
            ],
            [
                'ownerId'   => $orderId,
                'namespace' => 'delivery',
                'key'       => 'branch_code',
                'value'     => $branchCode,
                'type'      => 'single_line_text_field',
            ],
        ],
    ]);

    $errors = $data['metafieldsSet']['userErrors'] ?? [];
    if (!empty($errors)) {
        log_message('error', 'Failed to set metafields', ['errors' => $errors]);
        return false;
    }

    log_message('info', 'Metafields set', [
        'order_id'    => $orderId,
        'method'      => $method,
        'branch_code' => $branchCode,
    ]);

    return true;
}

function update_tags(string $orderId, array $tags): bool
{
    $query = '
        mutation addTags($id: ID!, $tags: [String!]!) {
            tagsAdd(id: $id, tags: $tags) {
                userErrors { field message }
            }
        }
    ';

    $data = graphql_request(SHOPIFY_GRAPHQL_URL_STABLE, $query, [
        'id'   => $orderId,
        'tags' => $tags,
    ]);

    $errors = $data['tagsAdd']['userErrors'] ?? [];
    if (!empty($errors)) {
        log_message('error', 'Failed to add tags', ['errors' => $errors]);
        return false;
    }

    log_message('info', 'Tags added', [
        'order_id' => $orderId,
        'tags'     => $tags,
    ]);

    return true;
}
