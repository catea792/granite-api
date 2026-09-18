<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
abstract class BaseCrudService
{
    /** @param TModel $model */
    public function __construct(protected readonly Model $model) {}

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }

    /** @return TModel|null */
    public function find(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    /** @return TModel */
    public function findOrFail(int|string $id): Model
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        if ($attributes !== []) {
            $model->update($attributes);
        }

        return $model->refresh();
    }

    /** @param TModel $model */
    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function exists(int|string $id): bool
    {
        return $this->model->newQuery()->whereKey($id)->exists();
    }
}
