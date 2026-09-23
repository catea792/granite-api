<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Auth\AdminAuthCookieFactory;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class LogoutController extends Controller
{
    public function __construct(private readonly AdminAuthCookieFactory $cookies) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        try {
            DB::table('revoked_jwt_tokens')->insert([
                'jti' => (string) $request->attributes->get('jwt_jti'),
                'admin_id' => (int) $request->attributes->get('admin_id'),
                'expires_at' => $request->attributes->get('jwt_expires_at'),
                'revoked_at' => CarbonImmutable::now('UTC'),
            ]);
        } catch (QueryException $exception) {
            throw new ApiException(
                'AUTH_SERVICE_UNAVAILABLE',
                'Không thể thu hồi phiên đăng nhập lúc này.',
                503,
                previous: $exception,
            );
        }

        $response = response()->noContent();
        $response->headers->setCookie($this->cookies->forget($request));

        return $response;
    }
}
