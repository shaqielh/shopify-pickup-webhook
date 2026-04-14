<?php

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Logger.php';

// Webhook entry point — built out in Step 2
http_response_code(200);
echo 'OK';
