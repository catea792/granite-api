<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $origin = $request->headers->get('Origin');
        $trustedOrigins = config('admin_auth.trusted_origins', []);

        if (! is_string($origin) || ! is_array($trustedOrigins) || ! in_array(rtrim($origin, '/'), $trustedOrigins, true)) {
            throw new ApiException('ORIGIN_NOT_ALLOWED', 'Nguồn gửi yêu cầu không được phép.', 403);
        }

        return $next($request);
    }
}
