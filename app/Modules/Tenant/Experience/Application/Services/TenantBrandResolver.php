<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Services;

use App\Modules\Platform\Contracts\TenantBrandResolverContract;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Support\Facades\Cache;

class TenantBrandResolver implements TenantBrandResolverContract
{
    public function name(): string
    {
        return tenant()->name ?? config('app.name', 'LaraShift');
    }

    public function logoUrl(): ?string
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            return null;
        }

        $logoPath = Cache::remember(
            "tenant:{$tenantId}:brand_logo_path",
            now()->addMinutes(15),
            fn (): ?string => TenantSetting::query()
                ->where('tenant_id', $tenantId)
                ->value('logo_path')
        );

        return $logoPath !== null ? tenant_asset($logoPath) : null;
    }
}
