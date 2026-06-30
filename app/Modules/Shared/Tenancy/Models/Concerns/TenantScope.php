<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (tenancy()->initialized) {
            $builder->where($model->getTable().'.tenant_id', tenancy()->tenant->getTenantKey());
        }
    }
}
