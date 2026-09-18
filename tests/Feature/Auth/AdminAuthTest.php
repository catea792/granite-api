<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\JwtTokenService;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;
use Tymon\JWTAuth\JWT;

final class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_strict_http_only_local_cookie_with_exact_ttl(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/auth/login', [
            'email' => ' ADMIN@EXAMPLE.COM ',
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.admin.id', $admin->id)
            ->assertJsonPath('data.admin.email', 'admin@example.com')
            ->assertJsonPath('data.expires_in', 10800);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $cookie = collect($response->headers->getCookies())
            ->first(static fn (Cookie $cookie): bool => $cookie->getName() === 'granite_admin_token_local');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertFalse($cookie->isSecure());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());

        $payload = app(JWT::class)->setToken((string) $cookie->getValue())->getPayload();
        $this->assertSame(10800, $payload->get('exp') - $payload->get('iat'));
        $this->assertSame('granite-api', $payload->get('iss'));
        $this->assertSame(['granite-admin'], $payload->get('aud'));
    }

    public function test_login_validation_and_unknown_fields_use_error_contract(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/auth/login', [
            'email' => 'not-an-email',
            'unexpected' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.issues.0.field', 'email')
            ->assertJsonFragment(['field' => 'unexpected', 'code' => 'UNSUPPORTED_FIELD']);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) $response->json('request_id'));
    }

    public function test_invalid_credentials_do_not_disclose_whether_email_exists(): void
    {
        Admin::factory()->create([
            'email' => 'known@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $known = $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrong-password',
        ]);
        $unknown = $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/auth/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);

        $known->assertUnauthorized()->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
        $unknown->assertUnauthorized()->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
        $this->assertSame($known->json('error.message'), $unknown->json('error.message'));
    }

    public function test_login_throttles_sixth_failure_and_success_resets_counter(): void
    {
        Admin::factory()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login('wrong-password')->assertUnauthorized();
        }

        $this->login('wrong-password')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');

        RateLimiter::clear($this->loginRateKey('admin@example.com'));
        $this->login('wrong-password')->assertUnauthorized();
        $this->login('correct-password')->assertOk();
        $this->login('wrong-password')->assertUnauthorized();
    }

    public function test_unsafe_admin_requests_require_exact_trusted_origin(): void
    {
        $this->postJson('/api/v1/admin/auth/login', [])->assertForbidden()
            ->assertJsonPath('error.code', 'ORIGIN_NOT_ALLOWED');
        $this->withHeader('Origin', 'https://evil.example')->postJson('/api/v1/admin/auth/login', [])->assertForbidden();
    }

    public function test_bearer_header_is_ignored(): void
    {
        $token = app(JwtTokenService::class)->issue(Admin::factory()->create());

        $this->withToken($token)->getJson('/api/v1/admin/products')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_hosted_cookie_uses_host_prefix_and_secure_flag(): void
    {
        config()->set('admin_auth.cookie_profile', 'hosted');
        Admin::factory()->create([
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('correct-password'),
        ]);

        $response = $this->login('correct-password');
        $cookie = collect($response->headers->getCookies())
            ->first(static fn (Cookie $cookie): bool => $cookie->getName() === '__Host-granite_admin_token');

        $response->assertOk();
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('/', $cookie->getPath());
    }

    public function test_malformed_wrong_claim_and_revoked_tokens_are_rejected(): void
    {
        $admin = Admin::factory()->create();

        $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', 'malformed')
            ->getJson('/api/v1/admin/products')
            ->assertUnauthorized();

        $token = app(JwtTokenService::class)->issue($admin);
        config()->set('admin_auth.issuer', 'another-issuer');
        $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', $token)
            ->getJson('/api/v1/admin/products')
            ->assertUnauthorized();

        config()->set('admin_auth.issuer', 'granite-api');
        $decoded = app(JwtTokenService::class)->decode($token);
        DB::table('revoked_jwt_tokens')->insert([
            'jti' => $decoded->jti,
            'admin_id' => $admin->id,
            'expires_at' => $decoded->expiresAt,
            'revoked_at' => now(),
        ]);
        $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', $token)
            ->getJson('/api/v1/admin/products')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_expired_token_is_rejected_and_stale_logout_cookie_is_expired(): void
    {
        $admin = Admin::factory()->create();
        Carbon::setTestNow('2026-09-18 00:00:00');
        $token = app(JwtTokenService::class)->issue($admin);
        Carbon::setTestNow('2026-09-18 03:01:00');

        $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', $token)
            ->getJson('/api/v1/admin/products')
            ->assertUnauthorized();

        $this->withCredentials()->withHeader('Origin', 'http://localhost')
            ->withUnencryptedCookie('granite_admin_token_local', $token)
            ->postJson('/api/v1/admin/auth/logout')
            ->assertUnauthorized()
            ->assertCookieExpired('granite_admin_token_local');

        Carbon::setTestNow();
    }

    public function test_logout_revokes_token_before_expiring_cookie(): void
    {
        $admin = Admin::factory()->create();
        $token = app(JwtTokenService::class)->issue($admin);
        $jti = app(JwtTokenService::class)->decode($token)->jti;

        $response = $this->withCredentials()->withHeader('Origin', 'http://localhost')
            ->withUnencryptedCookie('granite_admin_token_local', $token)
            ->postJson('/api/v1/admin/auth/logout');

        $response->assertNoContent()->assertCookieExpired('granite_admin_token_local');
        $this->assertDatabaseHas('revoked_jwt_tokens', ['jti' => $jti, 'admin_id' => $admin->id]);
        $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', $token)
            ->getJson('/api/v1/admin/products')
            ->assertUnauthorized();
    }

    public function test_logout_write_failure_returns_503_and_keeps_cookie(): void
    {
        $admin = Admin::factory()->create();
        $token = app(JwtTokenService::class)->issue($admin);
        DB::statement("CREATE TRIGGER fail_revocation BEFORE INSERT ON revoked_jwt_tokens BEGIN SELECT RAISE(ABORT, 'failure'); END");

        $response = $this->withCredentials()->withHeader('Origin', 'http://localhost')
            ->withUnencryptedCookie('granite_admin_token_local', $token)
            ->postJson('/api/v1/admin/auth/logout');

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'AUTH_SERVICE_UNAVAILABLE')
            ->assertCookieMissing('granite_admin_token_local');
    }

    public function test_authenticated_request_does_not_query_admin_again(): void
    {
        $admin = Admin::factory()->create();
        $token = app(JwtTokenService::class)->issue($admin);
        $admin->delete();

        $response = $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', $token)
            ->getJson('/api/v1/admin/products')
            ->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    private function login(string $password): TestResponse
    {
        return $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@example.com',
            'password' => $password,
        ]);
    }

    private function loginRateKey(string $email): string
    {
        return 'admin-login:'.hash('sha256', $email.'|127.0.0.1');
    }
}
