<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Jobs;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Audit\Actions\PurgeExpiredAuditLogsAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeExpiredAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $action = app(PurgeExpiredAuditLogsAction::class);

        Tenant::whereNull('archived_at')->chunk(100, function ($tenants) use ($action) {
            foreach ($tenants as $tenant) {
                try {
                    tenancy()->initialize($tenant);

                    $deleted = $action->execute($tenant);

                    if ($deleted > 0) {
                        Log::info('Purged expired audit logs', [
                            'tenant_id' => $tenant->id,
                            'deleted' => $deleted,
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to purge audit logs for tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }
        });
    }
}
