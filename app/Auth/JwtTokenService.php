<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\ApiException;
use App\Models\Admin;
use Carbon\CarbonImmutable;
use Tymon\JWTAuth\JWT;

final class JwtTokenService
{
    public function __construct(private readonly JWT $jwt) {}

    public function issue(Admin $admin): string
    {
        $this->ensureConfigurationIsSafe();

        try {
            return $this->jwt
                ->customClaims([
                    'iss' => (string) config('admin_auth.issuer'),
                    'aud' => (string) config('admin_auth.audience'),
                ])
                ->fromSubject($admin);
        } finally {
            $this->jwt->customClaims([]);
        }
    }

    public function decode(string $token): AuthenticatedJwt
    {
        $this->ensureConfigurationIsSafe();

        try {
            $payload = $this->jwt->setToken($token)->getPayload();
        } finally {
            $this->jwt->unsetToken();
        }

        $issuer = $payload->get('iss');
        $audience = $payload->get('aud');
        $expectedAudience = (string) config('admin_auth.audience');
        $audienceMatches = is_string($audience)
            ? hash_equals($expectedAudience, $audience)
            : is_array($audience)
                && count($audience) === 1
                && is_string($audience[0] ?? null)
                && hash_equals($expectedAudience, $audience[0]);

        if (! is_string($issuer)
            || ! hash_equals((string) config('admin_auth.issuer'), $issuer)
            || ! $audienceMatches) {
            throw new ApiException('AUTH_UNAUTHENTICATED', 'Phiên đăng nhập không hợp lệ.', 401);
        }

        $subject = $payload->get('sub');
        $jti = $payload->get('jti');
        $expiresAt = $payload->get('exp');

        if ((! is_int($subject) && ! (is_string($subject) && ctype_digit($subject)))
            || (int) $subject < 1
            || ! is_string($jti)
            || $jti === ''
            || ! is_int($expiresAt)) {
            throw new ApiException('AUTH_UNAUTHENTICATED', 'Phiên đăng nhập không hợp lệ.', 401);
        }

        return new AuthenticatedJwt(
            (int) $subject,
            $jti,
            CarbonImmutable::createFromTimestampUTC($expiresAt),
        );
    }

    private function ensureConfigurationIsSafe(): void
    {
        $secret = (string) config('jwt.secret');

        if (strlen($secret) < 32) {
            throw new ApiException(
                'AUTH_CONFIGURATION_ERROR',
                'Dịch vụ xác thực chưa được cấu hình an toàn.',
                503,
            );
        }
    }
}
