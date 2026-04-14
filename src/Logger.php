<?php

function log_message(string $level, string $message, array $context = []): void
{
    $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    $configuredLevel = defined('LOG_LEVEL') ? LOG_LEVEL : 'info';

    if (($levels[$level] ?? 1) < ($levels[$configuredLevel] ?? 1)) {
        return;
    }

    $logPath = defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/app.log';
    $logDir  = dirname($logPath);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp  = date('Y-m-d H:i:s');
    $levelUpper = strtoupper($level);
    $line       = "[{$timestamp}] [{$levelUpper}] {$message}";

    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
