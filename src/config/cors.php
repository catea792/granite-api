<?php

declare(strict_types=1);

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('ADMIN_TRUSTED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')),
)));

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-ID'],

    'max_age' => 0,

    'supports_credentials' => true,
];
