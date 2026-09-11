<?php

declare(strict_types=1);

namespace App\Modules\Central\Settings\Infrastructure\Services;

use App\Modules\Platform\Contracts\PlatformBrandingContract;

class CentralPlatformBranding implements PlatformBrandingContract
{
    public function name(): string
    {
        return CentralBranding::platformName();
    }

    public function logoUrl(): ?string
    {
        return CentralBranding::logoUrl();
    }
}
