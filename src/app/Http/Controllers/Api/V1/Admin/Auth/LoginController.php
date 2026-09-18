<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Auth\AdminAuthCookieFactory;
use App\Auth\JwtTokenService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class LoginController extends Controller
{
    public function __construct(
        private readonly JwtTokenService $tokens,
        private readonly AdminAuthCookieFactory $cookies,
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $email = (string) $credentials['email'];
        $key = 'admin-login:'.hash('sha256', $email.'|'.(string) $request->ip());
        $maxAttempts = (int) config('admin_auth.login.max_failures');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw new ApiException(
                'RATE_LIMIT_EXCEEDED',
                'Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau.',
                429,
                ['retry_after_seconds' => $retryAfter],
                ['Retry-After' => (string) $retryAfter],
            );
        }

        $admin = Admin::query()->where('email', $email)->first();
        $hashDriver = (string) config('hashing.driver');
        $dummyHash = (string) config("admin_auth.login.dummy_hashes.{$hashDriver}");
        $passwordHash = $admin?->password_hash ?? $dummyHash;
        $matches = $passwordHash !== '' && Hash::check((string) $credentials['password'], $passwordHash);

        if (! $admin instanceof Admin || ! $matches) {
            RateLimiter::hit($key, (int) config('admin_auth.login.decay_seconds'));
            throw new ApiException('AUTH_INVALID_CREDENTIALS', 'Email hoặc mật khẩu không chính xác.', 401);
        }

        RateLimiter::clear($key);
        $token = $this->tokens->issue($admin);
        $response = response()->json([
            'data' => [
                'admin' => ['id' => $admin->getKey(), 'email' => $admin->email],
                'expires_in' => (int) config('admin_auth.ttl_minutes') * 60,
            ],
        ]);
        $response->headers->setCookie($this->cookies->make($request, $token));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
