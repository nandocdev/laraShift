<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Jobs;

use App\Modules\Central\Billing\Actions\SyncInvoicesAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncTenantInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of minutes to wait between syncs per tenant.
     */
    private const THROTTLE_MINUTES = 15;

    public function __construct(
        public Tenant $tenant
    ) {}

    public function handle(SyncInvoicesAction $action): void
    {
        $cacheKey = "tenant_invoice_sync_{$this->tenant->id}";

        if (Cache::has($cacheKey)) {
            Log::debug("SyncTenantInvoicesJob throttled for tenant {$this->tenant->id}. Skipping.");
            return;
        }

        try {
            $action->execute($this->tenant);
            
            Cache::put($cacheKey, true, now()->addMinutes(self::THROTTLE_MINUTES));
        } catch (\Exception $e) {
            Log::error("Failed to sync invoices for tenant {$this->tenant->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
