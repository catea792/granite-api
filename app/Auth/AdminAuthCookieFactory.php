<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class AdminAuthCookieFactory
{
    public function make(Request $request, string $token): Cookie
    {
        $profile = $this->profile($request);

        return Cookie::create(
            name: $profile['name'],
            value: $token,
            expire: now()->addMinutes((int) config('admin_auth.ttl_minutes')),
            path: '/',
            domain: null,
            secure: $profile['secure'],
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    public function forget(Request $request): Cookie
    {
        $profile = $this->profile($request);

        return Cookie::create(
            name: $profile['name'],
            value: '',
            expire: 1,
            path: '/',
            domain: null,
            secure: $profile['secure'],
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    public function name(Request $request): string
    {
        return $this->profile($request)['name'];
    }

    /** @return array{name: string, secure: bool} */
    private function profile(Request $request): array
    {
        $profileName = (string) config('admin_auth.cookie_profile');
        $profile = config("admin_auth.cookies.{$profileName}");

        if (! is_array($profile) || ! isset($profile['name'], $profile['secure'])) {
            throw new ApiException('AUTH_CONFIGURATION_ERROR', 'Cấu hình cookie xác thực không hợp lệ.', 503);
        }

        if ($profileName === 'local' && ! in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true)) {
            throw new ApiException('AUTH_CONFIGURATION_ERROR', 'Cookie local chỉ được dùng trên loopback.', 503);
        }

        return ['name' => (string) $profile['name'], 'secure' => (bool) $profile['secure']];
    }
}
