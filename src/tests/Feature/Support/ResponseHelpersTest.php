<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ResponseHelpersTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function successPayloadProvider(): array
    {
        return [
            'null' => [null],
            'empty array' => [[]],
            'false' => [false],
            'zero' => [0],
            'empty string' => [''],
            'array' => [['name' => 'Granite']],
        ];
    }

    #[DataProvider('successPayloadProvider')]
    public function test_success_response_preserves_payload(mixed $payload): void
    {
        $response = responseOk($payload);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['data' => $payload], $response->getData(true));
    }

    public function test_success_response_uses_json_resource_serialization_status_and_headers(): void
    {
        $resource = new class(['id' => 7, 'name' => 'Granite']) extends JsonResource
        {
            /** @return array<string, mixed> */
            public function toArray(Request $request): array
            {
                return [
                    'id' => $this->resource['id'],
                    'name' => $this->resource['name'],
                ];
            }
        };

        $response = responseOk($resource, Response::HTTP_CREATED, ['X-Response-Test' => 'resource']);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('resource', $response->headers->get('X-Response-Test'));
        $this->assertSame([
            'data' => ['id' => 7, 'name' => 'Granite'],
        ], $response->getData(true));
    }

    public function test_paginated_response_uses_flat_meta_without_laravel_links(): void
    {
        $paginator = new LengthAwarePaginator(
            [['id' => 1], ['id' => 2]],
            total: 3,
            perPage: 2,
            currentPage: 1,
        );
        $collection = JsonResource::collection($paginator);

        $response = responsePaginate(
            $collection,
            additional: ['filters' => ['active' => true]],
            headers: ['X-Response-Test' => 'pagination'],
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('pagination', $response->headers->get('X-Response-Test'));
        $this->assertSame([
            'data' => [['id' => 1], ['id' => 2]],
            'meta' => [
                'total' => 3,
                'per_page' => 2,
                'current_page' => 1,
                'last_page' => 2,
            ],
            'filters' => ['active' => true],
        ], $response->getData(true));
        $this->assertArrayNotHasKey('links', $response->getData(true));
        $this->assertArrayNotHasKey('path', $response->getData(true)['meta']);
    }

    public function test_paginated_response_rejects_non_paginated_collection(): void
    {
        $collection = JsonResource::collection(collect([['id' => 1]]));

        $this->expectException(InvalidArgumentException::class);

        responsePaginate($collection);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedPaginationKeyProvider(): array
    {
        return [
            'data' => ['data'],
            'meta' => ['meta'],
        ];
    }

    #[DataProvider('reservedPaginationKeyProvider')]
    public function test_paginated_response_rejects_reserved_additional_key(string $reservedKey): void
    {
        $paginator = new LengthAwarePaginator([['id' => 1]], total: 1, perPage: 10);
        $collection = JsonResource::collection($paginator);

        $this->expectException(InvalidArgumentException::class);

        responsePaginate($collection, additional: [$reservedKey => []]);
    }

    public function test_error_response_returns_message_code_status_and_headers(): void
    {
        $response = responseError(
            'Không tìm thấy dữ liệu.',
            Response::HTTP_NOT_FOUND,
            'RESOURCE_NOT_FOUND',
            ['X-Response-Test' => 'error'],
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame('error', $response->headers->get('X-Response-Test'));
        $this->assertSame([
            'data' => null,
            'error_messages' => 'Không tìm thấy dữ liệu.',
            'error_code' => 'RESOURCE_NOT_FOUND',
        ], $response->getData(true));
    }

    public function test_error_response_accepts_validation_messages_without_error_code(): void
    {
        $messages = ['name' => ['Tên là bắt buộc.']];

        $response = responseError($messages, Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame([
            'data' => null,
            'error_messages' => $messages,
            'error_code' => null,
        ], $response->getData(true));
    }
}
