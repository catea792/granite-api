<?php

declare(strict_types=1);

use App\Auth\AdminAuthCookieFactory;
use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateAdminJwt;
use App\Http\Middleware\EnsureTrustedOrigin;
use App\Http\Middleware\ThrottleAdminRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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

        $exceptions->dontReportWhen(
            fn (Throwable $exception): bool => $exception instanceof ApiException
                && $exception->status < LaravelResponse::HTTP_INTERNAL_SERVER_ERROR,
        );

        $exceptions->render(new ApiExceptionRenderer);

        $exceptions->respond(function (Response $response, Throwable $_exception, Request $request): Response {
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
