<?php

declare(strict_types=1);

use App\Auth\AdminAuthCookieFactory;
use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateAdminJwt;
use App\Http\Middleware\EnsureTrustedOrigin;
use App\Http\Middleware\ThrottleAdminRequests;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->alias([
            'trusted.origin' => EnsureTrustedOrigin::class,
            'admin.jwt' => AuthenticateAdminJwt::class,
            'admin.throttle' => ThrottleAdminRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ApiException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $request,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->status,
                $exception->details,
                $exception->headers,
            );
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $issues = [];
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $issues[] = ['field' => $field, 'code' => 'INVALID', 'message' => $message];
                }
            }

            return ApiErrorResponse::make(
                $request,
                'VALIDATION_FAILED',
                'Dữ liệu gửi lên không hợp lệ.',
                422,
                ['issues' => $issues],
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return $request->is('api/*')
                ? ApiErrorResponse::make($request, 'AUTH_UNAUTHENTICATED', 'Bạn chưa đăng nhập.', 401)
                : null;
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return $request->is('api/*')
                ? ApiErrorResponse::make($request, 'AUTH_FORBIDDEN', 'Bạn không có quyền thực hiện thao tác này.', 403)
                : null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            return $request->is('api/*')
                ? ApiErrorResponse::make($request, 'RESOURCE_NOT_FOUND', 'Không tìm thấy tài nguyên yêu cầu.', 404)
                : null;
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $request,
                'RATE_LIMIT_EXCEEDED',
                'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
                429,
                [],
                ['Retry-After' => (string) ($exception->getHeaders()['Retry-After'] ?? 60)],
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            [$code, $message] = match ($status) {
                405 => ['METHOD_NOT_ALLOWED', 'Phương thức HTTP không được hỗ trợ.'],
                413 => ['PAYLOAD_TOO_LARGE', 'Dữ liệu gửi lên vượt quá giới hạn cho phép.'],
                default => ['HTTP_ERROR', 'Yêu cầu không thể được xử lý.'],
            };

            return ApiErrorResponse::make($request, $code, $message, $status);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            return $request->is('api/*')
                ? ApiErrorResponse::make($request, 'SERVER_ERROR', 'Đã xảy ra lỗi hệ thống.', 500)
                : null;
        });

        $exceptions->respond(function (Response $response): Response {
            $request = request();
            $requestId = (string) $request->attributes->get('request_id', Str::ulid());
            $response->headers->set('X-Request-ID', $requestId);

            if ($request->attributes->has('admin_id')) {
                $response->headers->set('Cache-Control', 'no-store');
            }

            if ($response->getStatusCode() === LaravelResponse::HTTP_UNAUTHORIZED
                && $request->routeIs('admin.auth.logout')) {
                $response->headers->setCookie(app(AdminAuthCookieFactory::class)->forget($request));
            }

            return $response;
        });
    })->create();
