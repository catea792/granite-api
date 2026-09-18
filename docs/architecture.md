# Granite API architecture

## Request flow

The standard mutation/read path is:

`FormRequest → Controller → Service → Eloquent → JsonResource`

- FormRequest owns authorization entry points, validation, Vietnamese messages, normalization, and rejection of unsupported fields.
- Controllers remain thin: accept validated input, call one service operation, and return a resource or status-only response.
- Services own reusable application behavior. `BaseCrudService` provides typed generic CRUD primitives over an Eloquent model.
- Eloquent owns persistence, scopes, casts, soft deletion, and route-model binding.
- JsonResource is the sole owner of successful API response fields. Raw models and raw Laravel paginator metadata must not leave a controller.

Authentication is intentionally separate. Tymon signs and verifies HS256 tokens; Granite middleware reads the configured cookie, validates Granite claims, checks the MySQL denylist, attaches the signed Admin ID to the request, and never performs a live Admin lookup on normal authenticated requests.

## Decision matrix

| Component | Add when | Do not add when | Current decision |
| --- | --- | --- | --- |
| Service | Behavior is reused, transactional, coordinates persistence, or forms the application boundary for a controller | The class only renames one Eloquent call without establishing a stable boundary | Use concrete services; Product directly extends `BaseCrudService` |
| Action | One application operation has a distinct lifecycle, many collaborators, or is reused outside a service/controller | It merely splits a short service method | Not present until a concrete operation needs it |
| Query Object | A read query is complex, independently reusable/testable, or has many filters/joins | A named Eloquent scope or short builder expression is sufficient | Not present; Product ordering stays in `ProductService` |
| Repository | Persistence must be swapped, aggregate storage is not Eloquent, or a real domain boundary needs isolation | It wraps Eloquent CRUD one-for-one | Not present |
| Trait | Stateless behavior is genuinely shared by unrelated classes and composition is not clearer | It hides dependencies or exists for anticipated reuse | Not present in application code |
| Transaction | One operation must atomically change multiple records or external state is coordinated with an outbox | A single insert/update/delete is already atomic | Add at the service/application-operation boundary only |
| Storage abstraction | Provider-specific I/O must be replaceable or faked | The code only handles local temporary files | `ImageStorage` is bound to `R2ImageStorage` |

Interfaces are not created automatically for every service. A concrete service is preferred until there are multiple implementations or a meaningful architectural boundary. Models, repositories, Actions, Query Objects, and traits must not be introduced as placeholders.

## Environment boundaries

Product is a learning/reference module, not a Granite domain. Its route registration and migration loading are guarded by `local` or `testing`. The Product PHP classes may be deployed, but production exposes no Product endpoints and runs no Product migration.

The common migrations contain only Admin authentication, JWT revocation, and database cache tables. The target runtime uses MySQL; tests use SQLite in memory. Provider integrations are exercised through fakes.

## Error and security boundary

All API failures use a stable JSON envelope with `error.code`, a Vietnamese `error.message`, `error.details`, and `request_id`. Specific renderers cover validation, authentication, authorization, missing resources, conflicts represented by `ApiException`, throttling, and unexpected failures. Production responses never echo exception text.

Unsafe Admin traffic is Origin-checked before cookie authentication. JWT verification uses a fixed algorithm, required claim set, exact issuer/audience, 180-minute lifetime, zero leeway, and a minimum 32-byte secret. The application does not accept Authorization bearer tokens and does not use Tymon's cache blacklist. Revocation is durable in MySQL.

## Explicitly deferred scope

The following are not implemented in this base:

- Granite business modules and public APIs
- Media Upload API or `media` table
- Media ownership, claim, content association, and replacement workflows
- Image upload example inside Product
- Orphan cleanup or storage/database reconciliation
- S3, MinIO, or local production storage implementations
- Redis, queues, Docker, trusted proxy production values, and provider/host operational ownership

These items require a separate accepted use case and lifecycle design rather than speculative abstractions.
