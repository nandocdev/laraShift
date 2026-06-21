<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Actions;

use App\Modules\Central\Landings\Models\Landing;
use App\Modules\Shared\Events\TenantMfaRequirementChanged;
use App\Modules\Shared\Events\TenantSettingsUpdated;
use App\Modules\Tenant\Settings\DTOs\BrandingData;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use App\Modules\Tenant\Settings\Support\BrandingPresets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class UpdateTenantBrandingAction
{
    /**
     * Updates tenant branding settings and synchronizes related systems.
     * Strategy: Use DB transaction for atomic updates and clean up old files.
     */
    public function execute(BrandingData $data): TenantSetting
    {
        return DB::transaction(function () use ($data) {
            $settings = TenantSetting::where('tenant_id', tenant('id'))->firstOrFail();
            
            $oldLogoPath = $settings->logo_path;
            $updateData = [
                'name' => $data->name,
                'primary_color' => $data->primaryColor,
                'mfa_required' => $data->mfaRequired,
            ];

            if ($data->logo) {
                $updateData['logo_path'] = $data->logo->store('branding', 'public');
            }

            $settings->update($updateData);

            // 1. Cleanup old logo if new one provided
            if ($data->logo && $oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            // 2. Sync with Central Tenant record
            tenant()->update(['name' => $data->name]);

            // 3. Sync with saas-landing theme if it exists
            $landing = Landing::where('tenant_id', tenant('id'))->where('slug', 'saas-landing')->first();
            if ($landing) {
                $preset = BrandingPresets::get($data->themePreset);
                $theme = $landing->theme ?? [];
                
                $theme['colors']['primary'] = $data->primaryColor;
                $theme['colors']['secondary'] = $preset['secondary'];
                $theme['typography']['font_heading'] = $preset['font_heading'];
                $theme['typography']['font_body'] = $preset['font_body'];
                
                $landing->update(['theme' => $theme]);
            }

            // 4. Fire Events
            event(new TenantSettingsUpdated(tenant('id'), array_keys($updateData)));
            
            if (isset($updateData['mfa_required'])) {
                event(new TenantMfaRequirementChanged(tenant('id'), (bool)$data->mfaRequired));
            }

            return $settings;
        });
    }
}
