<?php

declare(strict_types=1);

namespace App\Modules\Platform\Tenancy\Application\Jobs;

use App\Modules\Platform\Data\PlatformTenant;
use App\Modules\Platform\Tenancy\Application\Services\QuotaManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @deprecated Superseded by the Metering module (metering:aggregate /
 * AggregateUsageJob writing to usage_rollups). Kept for back-compat; the
 * column mismatch with quota_snapshots was fixed here.
 */
class SnapshotQuotasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $quotaManager = app(QuotaManager::class);
        $period = now()->format('Y-m');

        DB::table('tenants')->chunkById(100, function ($tenants) use ($quotaManager, $period) {
            foreach ($tenants as $tenantData) {
                $tenant = new PlatformTenant((string) $tenantData->id, $tenantData->name ?? 'Unknown');
                $metrics = ['staff', 'bookings', 'invitations', 'api_keys'];

                foreach ($metrics as $metric) {
                    $usage = $quotaManager->getCurrentUsage($tenant, $metric);

                    DB::table('quota_snapshots')->updateOrInsert(
                        [
                            'tenant_id' => $tenant->getId(),
                            'metric' => $metric,
                            'period' => $period,
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'usage' => $usage,
                            'limit' => $quotaManager->getLimit($tenant, $metric),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }
}
