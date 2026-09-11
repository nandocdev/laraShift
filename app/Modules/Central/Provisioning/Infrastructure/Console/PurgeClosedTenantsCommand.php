<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Infrastructure\Console;

use App\Modules\Central\Provisioning\Jobs\PurgeTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Dispatches the physical purge for self-closed workspaces (UC-T-10)
 * whose legal grace window has elapsed. Runs from console without tenancy,
 * so the queued job carries no stancl payload and processes soft-deleted
 * tenants through the RLS-scoped purge path.
 */
class PurgeClosedTenantsCommand extends Command
{
    protected $signature = 'tenants:purge-closed';

    protected $description = 'Purge self-closed tenants past the grace window';

    public function handle(): int
    {
        $graceDays = max(1, (int) config('workspace.closure_grace_days', 30));
        $cutoff = now()->subDays($graceDays);

        $due = Tenant::onlyTrashed()
            ->where('status', 'archived')
            ->where('deleted_at', '<=', $cutoff)
            ->get(['id', 'slug']);

        foreach ($due as $tenant) {
            PurgeTenantJob::dispatch($tenant->id, $tenant->slug);
        }

        $this->info("Queued purge for {$due->count()} closed tenant(s).");

        return self::SUCCESS;
    }
}
