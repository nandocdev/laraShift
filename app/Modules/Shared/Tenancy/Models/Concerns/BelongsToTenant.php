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
            if (! $model->tenant_id && $tenantId = config('tenancy.current_tenant_id')) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * Define the relationship to the Tenant.
     */
    public function tenant()
    {
        // return $this->belongsTo(config('tenancy.tenant_model'), 'tenant_id');
    }
}
