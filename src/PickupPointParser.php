<?php

require_once __DIR__ . '/Logger.php';

function parse_external_id(string $externalId): ?array
{
    // Format: <COURIER>-<METHOD>-<BRANCHCODE>
    // Limit to 3 parts so courier names with hyphens don't break
    $parts = explode('-', $externalId, 3);

    if (count($parts) !== 3) {
        log_message('warning', 'Invalid externalId format', ['external_id' => $externalId]);
        return null;
    }

    [$courier, $method, $branchCode] = $parts;

    if (!$courier || !$method || !$branchCode) {
        log_message('warning', 'Empty segment in externalId', ['external_id' => $externalId]);
        return null;
    }

    $parsed = [
        'courier'     => $courier,
        'method'      => strtoupper($method),
        'branch_code' => $branchCode,
    ];

    log_message('debug', 'externalId parsed', $parsed);

    return $parsed;
}

function format_shipping_title(array $parsed): string
{
    return $parsed['courier'] . ' - ' . $parsed['method'] . ' - ' . $parsed['branch_code'];
}

function build_tags(array $parsed): array
{
    $method = strtolower($parsed['method']);

    return [
        'delivery:' . $method,
        'branch:' . $parsed['branch_code'],
        'click-and-collect-' . $method,
    ];
}
