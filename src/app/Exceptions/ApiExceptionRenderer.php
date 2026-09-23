<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ApiException => responseError(
                $exception->responseMessage(),
                $exception->status,
                $exception->errorCode,
                $exception->headers,
            ),
            $exception instanceof ValidationException => $this->validationResponse($exception),
            $exception instanceof AuthenticationException => responseError(
                'Bạn chưa đăng nhập.',
                Response::HTTP_UNAUTHORIZED,
                'AUTH_UNAUTHENTICATED',
            ),
            $exception instanceof HttpExceptionInterface => $this->httpExceptionResponse($exception),
            default => responseError(
                'Đã xảy ra lỗi hệ thống.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'SERVER_ERROR',
            ),
        };
    }

    private function validationResponse(ValidationException $exception): JsonResponse
    {
        $issues = [];

        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $issues[] = [
                    'field' => $field,
                    'code' => 'INVALID',
                    'message' => $message,
                ];
            }
        }

        return responseError(
            ['issues' => $issues],
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'VALIDATION_FAILED',
        );
    }

    private function httpExceptionResponse(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        [$errorCode, $message] = match ($status) {
            Response::HTTP_UNAUTHORIZED => ['AUTH_UNAUTHENTICATED', 'Bạn chưa đăng nhập.'],
            Response::HTTP_FORBIDDEN => ['AUTH_FORBIDDEN', 'Bạn không có quyền thực hiện thao tác này.'],
            Response::HTTP_NOT_FOUND => ['RESOURCE_NOT_FOUND', 'Không tìm thấy tài nguyên yêu cầu.'],
            Response::HTTP_METHOD_NOT_ALLOWED => ['METHOD_NOT_ALLOWED', 'Phương thức HTTP không được hỗ trợ.'],
            Response::HTTP_REQUEST_ENTITY_TOO_LARGE => ['PAYLOAD_TOO_LARGE', 'Dữ liệu gửi lên vượt quá giới hạn cho phép.'],
            Response::HTTP_TOO_MANY_REQUESTS => ['RATE_LIMIT_EXCEEDED', 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.'],
            default => ['HTTP_ERROR', 'Yêu cầu không thể được xử lý.'],
        };

        $headers = $exception->getHeaders();

        if ($status === Response::HTTP_TOO_MANY_REQUESTS && ! isset($headers['Retry-After'])) {
            $headers['Retry-After'] = '60';
        }

        return responseError($message, $status, $errorCode, $headers);
    }
}
