<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Jobs;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeTenantJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $tenantSlug
    ) {}

    public function handle(): void
    {
        Log::info("Starting background purge for tenant: {$this->tenantSlug} ({$this->tenantId})");

        $tenant = Tenant::withTrashed()->find($this->tenantId);

        if (! $tenant) {
            Log::warning("Tenant not found for purging: {$this->tenantId}");

            return;
        }

        $tenant->forceDelete();

        Log::info("Purge completed for tenant: {$this->tenantSlug}");

        activity('provisioning')
            ->withProperties(['slug' => $this->tenantSlug, 'id' => $this->tenantId])
            ->log('tenant_purged_from_infrastructure');
    }
}
