<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApiExceptionTest extends TestCase
{
    public function test_previous_exception_is_preserved_for_reporting(): void
    {
        $previous = new RuntimeException('Database connection failed.');

        $exception = new ApiException(
            'SERVICE_UNAVAILABLE',
            'Dịch vụ tạm thời không khả dụng.',
            503,
            previous: $previous,
        );

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_log_context_contains_error_code_and_http_status(): void
    {
        $exception = new ApiException(
            'AUTH_SERVICE_UNAVAILABLE',
            'Dịch vụ xác thực tạm thời không khả dụng.',
            503,
        );

        $this->assertSame([
            'error_code' => 'AUTH_SERVICE_UNAVAILABLE',
            'http_status' => 503,
        ], $exception->context());
    }
}
