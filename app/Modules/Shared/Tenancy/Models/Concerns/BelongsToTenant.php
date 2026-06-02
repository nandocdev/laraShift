<?php

declare(strict_types=1);

namespace App\Modules\Shared\Tenancy\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (! $model->tenant_id && function_exists('tenancy') && tenancy()->initialized) {
                $model->tenant_id = tenancy()->tenant->getTenantKey();
            }
        });
    }

    /**
     * Define the relationship to the Tenant.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Modules\Central\Provisioning\Models\Tenant::class, 'tenant_id');
    }
}
