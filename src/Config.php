<?php

$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    die('[Config] .env file not found. Copy .env.example to .env and fill in your credentials.' . PHP_EOL);
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (!str_contains($line, '=')) continue;

    [$key, $value] = explode('=', $line, 2);
    $key   = trim($key);
    $value = trim($value);

    if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}

function requireEnv(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        die("[Config] Missing required environment variable: {$key}" . PHP_EOL);
    }
    return $value;
}

define('SHOPIFY_STORE_DOMAIN',         requireEnv('SHOPIFY_STORE_DOMAIN'));
define('SHOPIFY_ADMIN_API_TOKEN',      requireEnv('SHOPIFY_ADMIN_API_TOKEN'));
define('SHOPIFY_WEBHOOK_SECRET',       requireEnv('SHOPIFY_WEBHOOK_SECRET'));
define('SHOPIFY_API_VERSION_STABLE',   getenv('SHOPIFY_API_VERSION_STABLE')   ?: '2025-01');
define('SHOPIFY_API_VERSION_UNSTABLE', getenv('SHOPIFY_API_VERSION_UNSTABLE') ?: 'unstable');
define('LOG_LEVEL',                    getenv('LOG_LEVEL') ?: 'info');
define('LOG_PATH',                     getenv('LOG_PATH')  ?: __DIR__ . '/../logs/app.log');

define('SHOPIFY_GRAPHQL_URL_STABLE',
    'https://' . SHOPIFY_STORE_DOMAIN . '/admin/api/' . SHOPIFY_API_VERSION_STABLE . '/graphql.json'
);
define('SHOPIFY_GRAPHQL_URL_UNSTABLE',
    'https://' . SHOPIFY_STORE_DOMAIN . '/admin/api/' . SHOPIFY_API_VERSION_UNSTABLE . '/graphql.json'
);
