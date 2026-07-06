<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        Log::info("Starting platform resource reconciliation...");

        // 1. Detect orphaned domains (no tenant or tenant doesn't exist)
        $orphanedDomains = DB::table('domains')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tenants')
                    ->whereRaw('tenants.id = domains.tenant_id');
            })
            ->get();
        
        foreach ($orphanedDomains as $domain) {
            Log::warning("Orphaned domain detected: {$domain->domain}. Deleting...");
            DB::table('domains')->where('id', $domain->id)->delete();
        }

        // 2. Detect failed tenants with residual resources
        $failedTenants = DB::table('tenants')->where('status', 'failed')->get();

        foreach ($failedTenants as $tenant) {
            $hasDomains = DB::table('domains')->where('tenant_id', $tenant->id)->exists();
            if ($hasDomains) {
                Log::info("Cleaning up residual domains for failed tenant: {$tenant->slug}");
                DB::table('domains')->where('tenant_id', $tenant->id)->delete();
            }
        }

        // 3. Storage reconciliation could go here (detecting directories without tenants)

        Log::info("Resource reconciliation completed.");
    }
}
