<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Jobs;

use App\Modules\Central\Billing\Application\Actions\ChargeSubscriptionAction;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executes one engine-managed recurring charge for a subscription, within the
 * tenant context so RLS/quota lookups behave as in a live request.
 */
class ChargeSubscriptionJob implements ShouldBeUnique, ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [300, 1800];

    public int $uniqueFor = 3600;

    public function __construct(
        public string $tenantId,
        public string $subscriptionId,
    ) {}

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->subscriptionId.':'.now()->format('Y-m');
    }

    public function handle(ChargeSubscriptionAction $action): void
    {
        $action->execute($this->subscriptionId);
    }
}
