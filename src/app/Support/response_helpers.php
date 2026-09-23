<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

if (! function_exists('responseOk')) {
    /**
     * Return a successful JSON response.
     *
     * @param  array<string, string>  $headers
     */
    function responseOk(
        mixed $data = null,
        int $status = Response::HTTP_OK,
        array $headers = [],
    ): JsonResponse {
        if ($data instanceof JsonResource) {
            $response = $data->response()->setStatusCode($status);
            $response->headers->add($headers);

            return $response;
        }

        return response()->json(['data' => $data], $status, $headers);
    }
}

if (! function_exists('responsePaginate')) {
    /**
     * Return a paginated JSON resource response.
     *
     * @param  array<string, mixed>  $additional
     * @param  array<string, string>  $headers
     */
    function responsePaginate(
        ResourceCollection $data,
        int $status = Response::HTTP_OK,
        array $additional = [],
        array $headers = [],
    ): JsonResponse {
        if (! $data->resource instanceof LengthAwarePaginator) {
            throw new InvalidArgumentException('The resource collection must contain a length-aware paginator.');
        }

        $reservedKeys = array_intersect(['data', 'meta'], array_keys($additional));

        if ($reservedKeys !== []) {
            throw new InvalidArgumentException('Additional pagination data cannot contain the reserved data or meta keys.');
        }

        $paginator = $data->resource;
        $output = [
            'data' => $data->resolve(request()),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];

        return response()->json(array_merge($output, $additional), $status, $headers);
    }
}

if (! function_exists('responseError')) {
    /**
     * Return an error JSON response.
     *
     * @param  array<string, string>  $headers
     */
    function responseError(
        mixed $message,
        int $status,
        ?string $errorCode = null,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'data' => null,
            'error_messages' => $message,
            'error_code' => $errorCode,
        ], $status, $headers);
    }
}
