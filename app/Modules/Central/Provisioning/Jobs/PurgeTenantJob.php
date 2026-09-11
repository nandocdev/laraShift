<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Jobs;

use App\Modules\Central\Provisioning\Actions\PurgeTenantDataAction;
use App\Modules\Central\Provisioning\Infrastructure\Jobs\PurgeTenantContext;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Security\RateLimiting\TenantRateLimiter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class PurgeTenantJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $tenantSlug
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new PurgeTenantContext];
    }

    public function handle(TenantRateLimiter $rateLimiter): void
    {
        Log::info("Starting background purge for tenant: {$this->tenantSlug} ({$this->tenantId})");

        $tenant = Tenant::withTrashed()->find($this->tenantId);

        if (! $tenant) {
            Log::warning("Tenant not found for purging: {$this->tenantId}");

            return;
        }

        // 1. Clear Rate Limit Metrics
        RateLimiter::clear($rateLimiter->key($this->tenantId));

        // 2. Physical Data Cascade
        app(PurgeTenantDataAction::class)->execute($this->tenantId);

        // 3. Storage Object Deletion (Local/S3 tenant-prefixed directories)
        Storage::disk('local')->deleteDirectory("tenant{$this->tenantId}");
        Storage::disk('public')->deleteDirectory("tenant{$this->tenantId}");

        // 4. Force Delete Tenant Record
        $tenant->forceDelete();

        Log::info("Purge completed for tenant: {$this->tenantSlug}");

        activity('provisioning')
            ->withProperties(['slug' => $this->tenantSlug, 'id' => $this->tenantId])
            ->log('tenant_purged_from_infrastructure');
    }
}
