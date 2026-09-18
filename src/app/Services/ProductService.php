<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** @extends BaseCrudService<Product> */
final class ProductService extends BaseCrudService
{
    public function __construct(Product $product)
    {
        parent::__construct($product);
    }

    /** @return LengthAwarePaginator<int, Product> */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()->orderByDesc('id')->paginate($perPage);
    }
}
