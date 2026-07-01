<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Actions;

use App\Modules\Central\Settings\Support\CentralBranding;

final readonly class SaveBrandingAction
{
    public function execute(string $platformName, string $primaryColor, ?string $logoUrl = null): void
    {
        CentralBranding::set('platform_name', $platformName);
        CentralBranding::set('primary_color', $primaryColor);
        CentralBranding::set('logo_url', $logoUrl ?? '');

        activity('settings')
            ->withProperties([
                'platform_name' => $platformName,
                'primary_color' => $primaryColor,
                'logo_url' => $logoUrl,
            ])
            ->log('platform_branding_updated');
    }
}
