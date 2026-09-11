<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Actions;

use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Support\Facades\Gate;

final readonly class EnsureUserCanManageTenantSettings
{
    /**
     * Authorization primitive: throws unless the current user manages settings.
     */
    public function execute(): void
    {
        $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrNew();

        Gate::authorize('update', $settings);
    }
}
