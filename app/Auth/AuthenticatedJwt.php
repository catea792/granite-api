<?php

declare(strict_types=1);

namespace App\Auth;

use Carbon\CarbonImmutable;

final readonly class AuthenticatedJwt
{
    public function __construct(
        public int $adminId,
        public string $jti,
        public CarbonImmutable $expiresAt,
    ) {}
}
