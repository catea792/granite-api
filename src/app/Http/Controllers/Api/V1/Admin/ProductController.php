<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListProductsRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductRequest;
use App\Http\Resources\Api\V1\Admin\ProductCollection;
use App\Http\Resources\Api\V1\Admin\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    /**
     * Display a listing of the resource.
     */
    public function index(ListProductsRequest $request): ProductCollection
    {
        return new ProductCollection($this->products->paginate($request->perPage()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->products->update($product, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): Response
    {
        $this->products->delete($product);

        return response()->noContent();
    }
}
