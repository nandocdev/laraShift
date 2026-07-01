<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Jobs;

use App\Modules\Central\Provisioning\Models\Domain;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileResourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Executes the reconciliation of platform resources.
     * Detects orphaned domains and failed provisioning leftovers.
     */
    public function handle(): void
    {
        Log::info('Starting platform resource reconciliation...');

        // 1. Detect orphaned domains (no tenant or tenant doesn't exist)
        $orphanedDomains = Domain::whereDoesntHave('tenant')->get();

        foreach ($orphanedDomains as $domain) {
            Log::warning("Orphaned domain detected: {$domain->domain}. Deleting...");
            $domain->delete();
        }

        // 2. Detect failed tenants with residual resources
        $failedTenants = Tenant::where('status', 'failed')->get();

        foreach ($failedTenants as $tenant) {
            if ($tenant->domains()->exists()) {
                Log::info("Cleaning up residual domains for failed tenant: {$tenant->slug}");
                $tenant->domains()->delete();
            }
        }

        // 3. Storage reconciliation could go here (detecting directories without tenants)

        Log::info('Resource reconciliation completed.');
    }
}
