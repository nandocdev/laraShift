<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Jobs;

use App\Modules\Central\Billing\Application\Actions\SyncInvoices;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\RehydrateTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncTenantInvoicesJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const THROTTLE_MINUTES = 15;

    public function __construct(
        public string $tenantId
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function middleware(): array
    {
        return [new RehydrateTenantContext];
    }

    public function handle(SyncInvoices $action): void
    {
        $cacheKey = "tenant_invoice_sync_{$this->tenantId}";

        if (Cache::has($cacheKey)) {
            Log::debug("SyncTenantInvoicesJob throttled for tenant {$this->tenantId}. Skipping.");

            return;
        }

        try {
            $tenant = Tenant::find($this->tenantId);

            if (! $tenant) {
                Log::warning("Tenant not found for invoice sync: {$this->tenantId}");

                return;
            }

            $action->execute($tenant);

            Cache::put($cacheKey, true, now()->addMinutes(self::THROTTLE_MINUTES));
        } catch (\Exception $e) {
            Log::error("Failed to sync invoices for tenant {$this->tenantId}: ".$e->getMessage());
            throw $e;
        }
    }
}
