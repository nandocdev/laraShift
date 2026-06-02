<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Jobs;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SnapshotQuotasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $quotaManager = app(QuotaManager::class);
        $period = now()->format('Y-m');

        Tenant::chunk(100, function ($tenants) use ($quotaManager, $period) {
            foreach ($tenants as $tenant) {
                $metrics = ['staff', 'bookings', 'invitations', 'api_keys'];

                foreach ($metrics as $metric) {
                    $usage = $quotaManager->getCurrentUsage($tenant, $metric);

                    DB::table('quota_snapshots')->updateOrInsert(
                        [
                            'tenant_id' => $tenant->id,
                            'metric' => $metric,
                            'period' => $period,
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'value' => $usage,
                            'captured_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }
}
