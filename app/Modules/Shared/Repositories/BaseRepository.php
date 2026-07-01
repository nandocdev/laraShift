<?php

declare(strict_types=1);

namespace App\Modules\Shared\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @template TModel of Model
 */
abstract readonly class BaseRepository
{
    /** @param class-string<TModel> $modelClass */
    public function __construct(
        protected string $modelClass,
    ) {}

    /** @return TModel */
    protected function newQuery(): Model
    {
        return new $this->modelClass;
    }

    /** @return TModel|null */
    public function find(string $id): ?Model
    {
        return $this->newQuery()->newQuery()->find($id);
    }

    /** @return TModel */
    public function findOrFail(string $id): Model
    {
        return $this->newQuery()->newQuery()->findOrFail($id);
    }

    /** @return Collection<int, TModel> */
    public function all(): Collection
    {
        return $this->newQuery()->newQuery()->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Model
    {
        return $this->newQuery()->newQuery()->create($data);
    }

    /** @return TModel|null */
    public function findBy(string $field, mixed $value): ?Model
    {
        return $this->newQuery()->newQuery()->where($field, $value)->first();
    }

    /** @return TModel */
    public function findByOrFail(string $field, mixed $value): Model
    {
        $model = $this->findBy($field, $value);

        if (! $model) {
            throw (new ModelNotFoundException)->setModel($this->modelClass);
        }

        return $model;
    }

    public function delete(string $id): bool
    {
        $model = $this->find($id);

        return $model?->delete() ?? false;
    }

    public function count(): int
    {
        return $this->newQuery()->newQuery()->count();
    }
}
