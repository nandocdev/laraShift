<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Jobs;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $tenantSlug
    ) {}

    public function handle(): void
    {
        Log::info("Starting background purge for tenant: {$this->tenantSlug} ({$this->tenantId})");

        // 1. Find the tenant (including trashed if soft deleted)
        $tenant = Tenant::withTrashed()->find($this->tenantId);

        if (! $tenant) {
            Log::warning("Tenant not found for purging: {$this->tenantId}");

            return;
        }

        // 2. Perform hard delete (Stancl/Tenancy handles DB/Storage cleanup if configured)
        $tenant->forceDelete();

        // 3. Additional cleanup if needed (DNS, Third-party APIs)

        Log::info("Purge completed for tenant: {$this->tenantSlug}");

        activity('provisioning')
            ->withProperties(['slug' => $this->tenantSlug, 'id' => $this->tenantId])
            ->log('tenant_purged_from_infrastructure');
    }
}
