<?php

declare(strict_types=1);

return [
    'driver' => env('HASH_DRIVER', in_array('argon2id', password_algos(), true) ? 'argon2id' : 'bcrypt'),
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT'),
    ],
    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],
    'rehash_on_login' => true,
];
