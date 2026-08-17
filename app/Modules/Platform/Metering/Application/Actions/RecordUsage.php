<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Application\Actions;

use App\Modules\Platform\Contracts\TenantContract;
use App\Modules\Platform\Metering\Application\DTO\RecordUsageData;
use App\Modules\Platform\Metering\Application\Services\UsageReader;
use App\Modules\Platform\Metering\Domain\Enums\AggregationType;
use App\Modules\Platform\Metering\Domain\Events\MeterUsageRecorded;
use App\Modules\Platform\Metering\Domain\MeterRegistry;
use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use App\Modules\Platform\Metering\Domain\UsagePeriod;
use App\Modules\Platform\Tenancy\Domain\Exceptions\QuotaExceededException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Records a single metered usage event durably and updates the fast-path
 * Redis counter. This is the authoritative write path of the Metering module.
 */
final readonly class RecordUsage
{
    public function __construct(
        private MeterRegistry $registry,
        private UsageReader $reader,
    ) {}

    public function execute(RecordUsageData $data, TenantContract $tenant): ?UsageEvent
    {
        if (! config('metering.enabled')) {
            return null;
        }

        if ($data->quantity < 1) {
            throw new InvalidArgumentException('Usage quantity must be greater than zero.');
        }

        $meter = $this->registry->get($data->meter);

        $occurredAt = CarbonImmutable::parse($data->occurredAt ?? now());
        $period = UsagePeriod::fromDate($occurredAt);

        if ($data->enforceQuota) {
            $this->assertWithinQuota($tenant, $meter->key, $data->quantity, $period->period);
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getId(),
            'meter' => $meter->key,
            'quantity' => $data->quantity,
            'occurred_at' => $occurredAt,
            'metadata' => json_encode($data->metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'dedupe_key' => $data->dedupeKey,
            'subscription_item_id' => $data->subscriptionItemId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $inserted = $data->dedupeKey !== null
            ? UsageEvent::query()->insertOrIgnore($payload)
            : UsageEvent::query()->insert($payload);

        if ($inserted === 0) {
            return null; // Duplicate idempotency key — already recorded.
        }

        if ($meter->aggregation === AggregationType::Sum) {
            $this->bumpHotCounter($tenant, $meter->key, $data->quantity, $period->period);
        }

        Event::dispatch(new MeterUsageRecorded(
            meter: $meter->key,
            quantity: $data->quantity,
            period: $period->period,
            tenantId: (string) $tenant->getId(),
            occurredAt: $occurredAt,
            metadata: $data->metadata,
        ));

        return UsageEvent::query()->find($payload['id']);
    }

    private function assertWithinQuota(TenantContract $tenant, string $meter, int $quantity, string $period): void
    {
        $limit = $tenant->getQuotaLimit($meter);

        if ($limit === -1) {
            return;
        }

        $current = $this->reader->current((string) $tenant->getId(), $meter, $period);

        if (($current + $quantity) > $limit) {
            throw new QuotaExceededException($meter);
        }
    }

    private function bumpHotCounter(TenantContract $tenant, string $meter, int $quantity, string $period): void
    {
        $key = $this->reader->hotCounterKey((string) $tenant->getId(), $meter, $period);

        if (! Cache::add($key, $quantity, now()->addDays(35))) {
            Cache::increment($key, $quantity);
        }
    }
}
