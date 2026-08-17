<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Jobs;

use App\Modules\Central\Provisioning\Actions\ProvisionTenantPipeline;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Provisions a single tenant in the background, resuming from the last
 * completed step. Re-dispatched by provisioning:reconcile on failure.
 */
class ProvisionTenantJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public string $tenantId,
        public string $adminEmail,
        public ?string $password = null,
        public string $adminName = 'Administrator',
        public string $finalStatus = 'active',
    ) {}

    public function handle(ProvisionTenantPipeline $pipeline): void
    {
        $pipeline->execute(
            tenantId: $this->tenantId,
            adminEmail: $this->adminEmail,
            password: $this->password,
            adminName: $this->adminName,
            finalStatus: $this->finalStatus,
        );
    }

    public function failed(\Throwable $e): void
    {
        $tenant = Tenant::withTrashed()->find($this->tenantId);

        if ($tenant) {
            $tenant->update(['status' => 'failed']);

            activity('provisioning')
                ->performedOn($tenant)
                ->withProperties(['error' => $e->getMessage()])
                ->log('tenant_provisioning_failed');
        }

        Log::error('Provisioning failed after retries for tenant '.$this->tenantId.': '.$e->getMessage());
    }
}
