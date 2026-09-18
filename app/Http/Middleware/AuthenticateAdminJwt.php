<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\AdminAuthCookieFactory;
use App\Auth\JwtTokenService;
use App\Exceptions\ApiException;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthenticateAdminJwt
{
    public function __construct(
        private readonly JwtTokenService $tokens,
        private readonly AdminAuthCookieFactory $cookies,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookies->get($this->cookies->name($request));

        if (! is_string($token) || $token === '') {
            throw new ApiException('AUTH_UNAUTHENTICATED', 'Bạn chưa đăng nhập.', 401);
        }

        try {
            $authenticated = $this->tokens->decode($token);
        } catch (ApiException $exception) {
            throw $exception;
        } catch (JWTException) {
            throw new ApiException('AUTH_UNAUTHENTICATED', 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.', 401);
        }

        try {
            $isRevoked = DB::table('revoked_jwt_tokens')->where('jti', $authenticated->jti)->exists();
        } catch (QueryException $exception) {
            throw new ApiException('AUTH_SERVICE_UNAVAILABLE', 'Dịch vụ xác thực tạm thời không khả dụng.', 503);
        }

        if ($isRevoked) {
            throw new ApiException('AUTH_UNAUTHENTICATED', 'Phiên đăng nhập đã bị thu hồi.', 401);
        }

        $request->attributes->set('admin_id', $authenticated->adminId);
        $request->attributes->set('jwt_jti', $authenticated->jti);
        $request->attributes->set('jwt_expires_at', $authenticated->expiresAt);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
