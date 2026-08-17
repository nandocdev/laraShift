<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Console;

use App\Modules\Central\Billing\Application\Jobs\ChargeSubscriptionJob;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use Illuminate\Console\Command;

/**
 * Finds subscriptions due for renewal and dispatches one ChargeSubscriptionJob
 * per subscription. Gateways that manage recurrence on their own (Clave) are
 * reconciled by billing:reconcile instead.
 */
class ProcessRecurringChargesCommand extends Command
{
    protected $signature = 'billing:process-recurring {--tenant= : Only process subscriptions for a specific tenant ID}';

    protected $description = 'Charge due subscriptions and roll their billing period forward';

    public function handle(): int
    {
        $query = Subscription::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('next_payment_at', '<=', now())
                    ->orWhere(function ($due) {
                        $due->whereNull('next_payment_at')
                            ->where('current_period_end', '<=', now());
                    });
            });

        if ($tenantId = $this->option('tenant')) {
            $query->where('tenant_id', (string) $tenantId);
        }

        $count = 0;

        $query->chunkById(100, function ($subscriptions) use (&$count) {
            foreach ($subscriptions as $subscription) {
                ChargeSubscriptionJob::dispatch($subscription->tenant_id, $subscription->id);
                $count++;
            }
        });

        $this->info("Dispatched recurring charges for {$count} subscriptions.");

        return self::SUCCESS;
    }
}
