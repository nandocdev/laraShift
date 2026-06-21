<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Console\Commands;

use App\Modules\Central\Billing\Actions\ReconcileSubscriptionAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Console\Command;

class ReconcileSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:reconcile {--tenant= : Specific tenant ID to reconcile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and reconcile local subscriptions with payment gateways (Anti-Drift)';

    /**
     * Execute the console command.
     */
    public function handle(ReconcileSubscriptionAction $action): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
        } else {
            // Reconcile all tenants with a plan that isn't 'free' 
            // or those who have existing subscriptions
            $tenants = Tenant::where('plan_id', '!=', 'free')
                ->orWhereHas('subscriptions')
                ->get();
        }

        $this->info("Starting reconciliation for " . $tenants->count() . " tenants...");
        $bar = $this->output->createProgressBar($tenants->count());

        foreach ($tenants as $tenant) {
            $action->execute($tenant);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Reconciliation completed.');

        return self::SUCCESS;
    }
}
