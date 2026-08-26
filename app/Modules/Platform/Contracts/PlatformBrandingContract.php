<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

interface PlatformBrandingContract
{
    /**
     * Display name of the platform (central) brand.
     */
    public function name(): string;

    /**
     * Logo URL of the platform, or null when not configured.
     */
    public function logoUrl(): ?string;
}
