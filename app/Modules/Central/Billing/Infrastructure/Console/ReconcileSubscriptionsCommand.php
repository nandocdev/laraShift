<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Console;

use App\Modules\Central\Billing\Application\Services\BillingScheduler;
use Illuminate\Console\Command;

class ReconcileSubscriptionsCommand extends Command
{
    protected $signature = 'billing:reconcile';

    protected $description = 'Timeout-based PastDue for subscriptions without payment in the current period';

    public function handle(BillingScheduler $scheduler): int
    {
        $scheduler->reconcileTimeouts();

        $this->info('Subscriptions reconciled.');

        return self::SUCCESS;
    }
}
