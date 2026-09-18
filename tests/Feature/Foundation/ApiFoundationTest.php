<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Auth\JwtTokenService;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
            throw new \RuntimeException('SQLSTATE secret /var/www/private.php');
        });

        $response = $this->getJson('/api/foundation-test/boom');

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'SERVER_ERROR')
            ->assertJsonPath('error.message', 'Đã xảy ra lỗi hệ thống.');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/var/www', $response->getContent());
    }

    public function test_not_found_uses_vietnamese_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/missing');

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonPath('error.message', 'Không tìm thấy tài nguyên yêu cầu.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details'], 'request_id']);
    }

    private function token(): string
    {
        return app(JwtTokenService::class)->issue(Admin::factory()->create());
    }
}
