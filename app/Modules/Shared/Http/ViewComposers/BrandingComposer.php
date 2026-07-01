<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\ViewComposers;

use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final readonly class BrandingComposer
{
    /**
     * Injects tenant branding data into all tenant-scoped views.
     *
     * Provides a consistent $branding object with:
     * - name: tenant organization name
     * - logo_url: public URL of the uploaded logo
     * - primary_color: brand primary color hex
     */
    public function compose(View $view): void
    {
        if (! function_exists('tenant') || ! tenant()) {
            return;
        }

        $tenantId = tenant('id');

        $branding = Cache::remember("tenant_branding_{$tenantId}", 300, function () use ($tenantId) {
            $settings = TenantSetting::where('tenant_id', $tenantId)->first();

            if (! $settings) {
                return (object) [
                    'name' => tenant('name'),
                    'logo_url' => null,
                    'primary_color' => '#4f46e5',
                ];
            }

            $logoUrl = $settings->logo_path
                ? tenant_asset("storage/{$settings->logo_path}")
                : null;

            return (object) [
                'name' => $settings->name ?: tenant('name'),
                'logo_url' => $logoUrl,
                'primary_color' => $settings->primary_color ?: '#4f46e5',
            ];
        });

        $view->with('branding', $branding);
    }
}
