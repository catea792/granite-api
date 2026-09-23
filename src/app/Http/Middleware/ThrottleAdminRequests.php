<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAdminRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isRead = $request->isMethodSafe();
        $maxAttempts = $isRead ? 120 : 60;
        $category = $isRead ? 'read' : 'mutation';
        $adminId = (int) $request->attributes->get('admin_id');
        $key = sprintf('admin-api:%s:%d:%s', $category, $adminId, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new ApiException(
                'RATE_LIMIT_EXCEEDED',
                'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
                429,
                headers: ['Retry-After' => (string) $retryAfter],
            );
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
