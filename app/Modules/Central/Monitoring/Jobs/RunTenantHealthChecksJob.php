<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Jobs;

use App\Modules\Central\Monitoring\Actions\RunTenantHealthCheckAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunTenantHealthChecksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $action = app(RunTenantHealthCheckAction::class);

        Tenant::whereNull('archived_at')->chunk(50, function ($tenants) use ($action) {
            foreach ($tenants as $tenant) {
                $action->execute($tenant);
            }
        });
    }
}
