<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Console;

use App\Modules\Central\Billing\Application\Services\BillingScheduler;
use Illuminate\Console\Command;

class ProcessRecurringChargesCommand extends Command
{
    protected $signature = 'billing:process-recurring';

    protected $description = 'Charge due MIT subscriptions and generate Clave renewal checkouts';

    public function handle(BillingScheduler $scheduler): int
    {
        $scheduler->processRecurring();

        $this->info('Recurring billing processed.');

        return self::SUCCESS;
    }
}
