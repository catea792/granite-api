# Granite API

Backend-only REST API foundation built with Laravel 13 and PHP 8.4. The project intentionally contains no Vite configuration, Node package, frontend assets, Blade UI, public web route, Docker setup, or bundled MySQL server.

## Requirements

- PHP 8.4 with `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `sodium`, `tokenizer`, and `xml`
- Composer 2
- MySQL 8.0+ for the target runtime
- SQLite PHP extension for the automated test suite

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan admin:create
php artisan serve
```

Configure MySQL in `.env` before migrating. `JWT_SECRET` must remain private and contain at least 32 bytes of unpredictable data. The application refuses to issue or accept Admin tokens when this minimum is not met.

## Authentication and browser security

Admin login and logout are available at:

- `POST /api/v1/admin/auth/login`
- `POST /api/v1/admin/auth/logout`

JWTs are signed and verified with HS256, expire exactly 180 minutes after issue, use zero clock leeway, and require `iss`, `aud`, `sub`, `jti`, `iat`, `nbf`, and `exp`. Tokens are accepted only from the configured cookie; the API deliberately ignores bearer authorization headers. Logout stores the `jti` in the `revoked_jwt_tokens` MySQL table before expiring the cookie.

Set `ADMIN_COOKIE_PROFILE=hosted` outside local development. Hosted mode emits `__Host-granite_admin_token` with `Secure`, `HttpOnly`, `SameSite=Strict`, and `Path=/`. Local mode emits the non-secure `granite_admin_token_local` cookie and is rejected unless the request host is loopback.

Every unsafe Admin request requires an exact `Origin` match from the comma-separated `ADMIN_TRUSTED_ORIGINS` allowlist. Do not use wildcards. Login permits five failed attempts per normalized email and IP in 15 minutes; the sixth failure returns HTTP 429. Authenticated read traffic is limited to 120 requests per minute and mutation traffic to 60 requests per minute.

Create administrators interactively; never place bootstrap credentials in source code:

```bash
php artisan admin:create
# or
php artisan admin:create admin@example.com
```

Passwords are entered through hidden prompts, are never printed, and use Argon2id when the PHP runtime supports it.

## Response conventions

Every API error has a stable code, Vietnamese message, details object, and request ID:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Dữ liệu gửi lên không hợp lệ.",
    "details": {
      "issues": [
        {"field": "name", "code": "REQUIRED", "message": "Tên sản phẩm là bắt buộc."}
      ]
    }
  },
  "request_id": "01J8Z4Y6BCDEFGHJKMNPQRSTVW"
}
```

`X-Request-ID` is accepted only when it is a valid ULID; otherwise the server generates one. Authenticated responses include `Cache-Control: no-store`. Unexpected failures are logged through Laravel while clients receive a sanitized error without SQL, stack traces, paths, or secrets.

## Reference Product CRUD

Product demonstrates `FormRequest → Controller → Service → Eloquent → JsonResource`:

- `GET /api/v1/admin/products`
- `POST /api/v1/admin/products`
- `GET /api/v1/admin/products/{product}`
- `PATCH /api/v1/admin/products/{product}`
- `DELETE /api/v1/admin/products/{product}`

This reference route and its migration are loaded only in `local` and `testing`. Production Granite has neither a Product route nor a Product table. Listing defaults to 20 rows, caps `per_page` at 100, orders by descending ID, and returns only `data` plus `meta.pagination`. Unknown and read-only fields are validation errors. PATCH supports partial payloads, including an empty no-op object. Deleted rows use soft deletion and are absent from normal route-model binding.

## Cloudflare R2

Configure:

```dotenv
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
R2_URL=https://media.example.com
R2_REGION=auto
```

Application code depends on `App\Contracts\ImageStorage`, currently bound to `App\Storage\R2ImageStorage`. Uploads use random ULID object keys, derive the extension from the detected MIME type, and never reuse the original filename. Domain records should persist only the returned object path; resolve a public URL only when presenting data.

This task intentionally implements only the storage abstraction. It does **not** implement a Media Upload API, media ownership or claim workflow, an image upload CRUD example, replacement lifecycle, cleanup, or reconciliation.

## Database and tests

The target database is MySQL. Automated tests override it with isolated, in-memory SQLite and fake R2 storage; they never call Cloudflare.

```bash
php artisan migrate:fresh --env=testing
php artisan test
./vendor/bin/pint --test
composer audit
```

Expired JWT denylist rows are removed by the daily scheduler. Production must run Laravel's scheduler:

```bash
php artisan schedule:run
```

## Adding a CRUD module

1. Generate the model, migration, factory, FormRequests, API controller, and JsonResource with Artisan.
2. Add a concrete service extending `BaseCrudService`; override only behavior that differs, such as Product ordering.
3. Keep validation and field rejection in FormRequests, orchestration in the controller, domain/application behavior in the service, persistence in Eloquent, and response shape in JsonResource.
4. Add versioned routes under `/api/v1`, then write feature tests for authorization, validation, not-found behavior, response shape, and persistence.
5. Run the full verification commands before review.

See [docs/architecture.md](docs/architecture.md) for dependency and abstraction decisions.

## Package policy

Runtime integrations are intentionally limited to:

- `tymon/jwt-auth`: HS256 token creation and cryptographic verification only; application middleware owns cookies, claims, denylist, and Admin identity handling.
- `league/flysystem-aws-s3-v3`: S3-compatible adapter required by the R2 implementation.

Laravel Boost is development-only project tooling. Pint, PHPUnit, Collision, Mockery, Faker, Tinker, Pail, and Pao are retained as Laravel development/test tooling. Larastan, Sanctum, MediaLibrary, role/permission packages, Redis, queue workers, and Docker are not added. Add a package only when a concrete use case is accepted and the maintenance/security cost is justified.
