<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Experience\Application\Actions;

use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Support\Facades\Gate;

final readonly class MarkTenantSmtpVerified
{
    /**
     * Flags the active tenant SMTP configuration as verified.
     */
    public function execute(): void
    {
        $settings = TenantSetting::firstOrCreate(['tenant_id' => tenant('id')]);

        Gate::authorize('update', $settings);

        $settings->update(['smtp_verified' => true]);
    }
}
