<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Auth\JwtTokenService;
use App\Models\Admin;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
    }

    public function test_list_is_paginated_without_laravel_urls_and_sorted_by_descending_id(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->authenticated()->getJson('/api/v1/admin/products?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 3)
            ->assertJsonPath('data.1.id', 2)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('meta.path');
    }

    public function test_create_show_patch_noop_and_delete_follow_exact_resource_contract(): void
    {
        $create = $this->authenticated()->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/products', [
            'name' => 'Granite reference',
            'description' => null,
            'is_active' => true,
        ]);

        $create->assertCreated()->assertJsonStructure([
            'data' => ['id', 'name', 'description', 'is_active', 'created_at', 'updated_at'],
        ])->assertJsonPath('data.name', 'Granite reference');
        $id = (int) $create->json('data.id');

        $this->authenticated()->getJson("/api/v1/admin/products/{$id}")
            ->assertOk()->assertJsonPath('data.id', $id);

        $this->authenticated()->withHeader('Origin', 'http://localhost')
            ->patchJson("/api/v1/admin/products/{$id}", [])
            ->assertOk()->assertJsonPath('data.name', 'Granite reference');

        $this->authenticated()->withHeader('Origin', 'http://localhost')
            ->patchJson("/api/v1/admin/products/{$id}", ['name' => 'Updated'])
            ->assertOk()->assertJsonPath('data.name', 'Updated');

        $this->authenticated()->withHeader('Origin', 'http://localhost')
            ->deleteJson("/api/v1/admin/products/{$id}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->authenticated()->getJson("/api/v1/admin/products/{$id}")
            ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_validation_rejects_missing_invalid_unknown_and_read_only_fields(): void
    {
        $response = $this->authenticated()->withHeader('Origin', 'http://localhost')->postJson('/api/v1/admin/products', [
            'name' => '',
            'is_active' => 'yes',
            'id' => 99,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonFragment(['field' => 'name', 'code' => 'REQUIRED'])
            ->assertJsonFragment(['field' => 'is_active', 'code' => 'INVALID'])
            ->assertJsonFragment(['field' => 'id', 'code' => 'UNSUPPORTED_FIELD']);
    }

    public function test_pagination_limits_and_unknown_query_fields_are_rejected(): void
    {
        $this->authenticated()->getJson('/api/v1/admin/products?per_page=101')
            ->assertUnprocessable()->assertJsonFragment(['field' => 'per_page', 'code' => 'MAX_VALUE']);
        $this->authenticated()->getJson('/api/v1/admin/products?sort=name')
            ->assertUnprocessable()->assertJsonFragment(['field' => 'sort', 'code' => 'UNSUPPORTED_FIELD']);
    }

    public function test_admin_read_and_mutation_rate_limits_are_enforced(): void
    {
        $client = $this->authenticated();

        for ($attempt = 1; $attempt <= 120; $attempt++) {
            $client->getJson('/api/v1/admin/products')->assertOk();
        }
        $client->getJson('/api/v1/admin/products')
            ->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');

        $client = $this->authenticated()->withHeader('Origin', 'http://localhost');
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $client->postJson('/api/v1/admin/products', [
                'name' => "Product {$attempt}",
                'is_active' => true,
            ])->assertCreated();
        }
        $client->postJson('/api/v1/admin/products', ['name' => 'Limited', 'is_active' => true])
            ->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
    }

    private function authenticated(): self
    {
        $token = app(JwtTokenService::class)->issue(Admin::factory()->create());

        return $this->withCredentials()->withUnencryptedCookie('granite_admin_token_local', $token);
    }
}
