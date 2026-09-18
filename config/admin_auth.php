<?php

declare(strict_types=1);

$trustedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('ADMIN_TRUSTED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')),
)));

return [
    'issuer' => env('JWT_ISSUER', 'granite-api'),
    'audience' => env('JWT_AUDIENCE', 'granite-admin'),
    'ttl_minutes' => 180,
    'trusted_origins' => $trustedOrigins,
    'cookie_profile' => env('ADMIN_COOKIE_PROFILE', 'local'),
    'cookies' => [
        'hosted' => ['name' => '__Host-granite_admin_token', 'secure' => true],
        'local' => ['name' => 'granite_admin_token_local', 'secure' => false],
    ],
    'login' => [
        'max_failures' => 5,
        'decay_seconds' => 15 * 60,
        'dummy_hashes' => [
            'bcrypt' => '$2y$12$2fwh6S9Z6vHDYjFiHBIwH.KAUpVpWXSH2bQFiEUtKDj9LHUJTo40y',
            'argon2id' => '$argon2id$v=19$m=65536,t=4,p=1$WGlkQWt5OXQwQzlQSzBORg$Kt/hiWapHO0zJgtoaH+cnjRYUWzPl+2hDFR38Yu2wBI',
        ],
    ],
];
