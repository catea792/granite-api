<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Auth\JwtTokenService;
use App\Exceptions\ApiException;
use App\Models\Admin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

final class ApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_id_is_propagated_and_invalid_value_is_replaced(): void
    {
        $requestId = '01J8Z4Y6BCDEFGHJKMNPQRSTVW';

        $response = $this->withHeader('X-Request-ID', 'unsafe value')
            ->getJson('/api/v1/admin/products');
        $response->assertUnauthorized();
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $response->headers->get('X-Request-ID'));
        $this->assertNotSame('unsafe value', $response->headers->get('X-Request-ID'));

        $this->withCredentials()->withHeader('X-Request-ID', $requestId)
            ->withUnencryptedCookie('granite_admin_token_local', $this->token())
            ->getJson('/api/v1/admin/products')
            ->assertHeader('X-Request-ID', $requestId);
    }

    public function test_unexpected_errors_are_sanitized(): void
    {
        Route::get('/api/foundation-test/boom', static function (): never {
            throw new RuntimeException('SQLSTATE secret /var/www/private.php');
        });

        $response = $this->getJson('/api/foundation-test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('data', null)
            ->assertJsonPath('error_code', 'SERVER_ERROR')
            ->assertJsonPath('error_messages', 'Đã xảy ra lỗi hệ thống.')
            ->assertHeader('X-Request-ID');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/var/www', $response->getContent());
    }

    public function test_not_found_uses_vietnamese_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/missing');

        $response->assertNotFound()
            ->assertExactJson([
                'data' => null,
                'error_messages' => 'Không tìm thấy tài nguyên yêu cầu.',
                'error_code' => 'RESOURCE_NOT_FOUND',
            ])
            ->assertHeader('X-Request-ID');
    }

    public function test_authorization_errors_use_common_403_contract(): void
    {
        Route::get('/api/foundation-test/forbidden', static function (): never {
            throw new AuthorizationException;
        });

        $response = $this->getJson('/api/foundation-test/forbidden');

        $response->assertForbidden()->assertExactJson([
            'data' => null,
            'error_messages' => 'Bạn không có quyền thực hiện thao tác này.',
            'error_code' => 'AUTH_FORBIDDEN',
        ]);
    }

    public function test_method_not_allowed_uses_common_405_contract_and_preserves_allow_header(): void
    {
        Route::get('/api/foundation-test/get-only', static fn () => responseOk());

        $response = $this->postJson('/api/foundation-test/get-only');

        $response->assertStatus(405)
            ->assertJsonPath('error_code', 'METHOD_NOT_ALLOWED')
            ->assertJsonPath('error_messages', 'Phương thức HTTP không được hỗ trợ.')
            ->assertHeader('Allow', 'GET, HEAD');
    }

    public function test_framework_rate_limit_uses_common_429_contract_and_preserves_retry_after(): void
    {
        Route::get('/api/foundation-test/rate-limited', static function (): never {
            throw new TooManyRequestsHttpException(17);
        });

        $response = $this->getJson('/api/foundation-test/rate-limited');

        $response->assertStatus(429)
            ->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('Retry-After', '17');
    }

    public function test_standard_validation_errors_use_wrapped_issues_contract(): void
    {
        Route::post('/api/foundation-test/validation', static function (Request $request): void {
            $request->validate(['name' => ['required']]);
        });

        $response = $this->postJson('/api/foundation-test/validation');

        $response->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_FAILED')
            ->assertJsonPath('error_messages.issues.0.field', 'name')
            ->assertJsonPath('error_messages.issues.0.code', 'INVALID');
    }

    public function test_api_exception_reporting_policy_ignores_4xx_and_reports_5xx(): void
    {
        /** @var Handler $handler */
        $handler = app(ExceptionHandlerContract::class);

        $this->assertFalse($handler->shouldReport(new ApiException('EXPECTED', 'Expected.', 400)));
        $this->assertTrue($handler->shouldReport(new ApiException('FAILED', 'Failed.', 503)));
    }

    private function token(): string
    {
        return app(JwtTokenService::class)->issue(Admin::factory()->create());
    }
}
