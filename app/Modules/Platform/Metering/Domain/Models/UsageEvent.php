<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Durable, append-only record of a single metered usage event.
 *
 * This is the source of truth for the Metering module. Redis counters and
 * usage_rollups are derived from these records and may be rebuilt at any time.
 */
class UsageEvent extends Model
{
    use HasUuids;

    protected $table = 'usage_events';

    protected $fillable = [
        'id',
        'tenant_id',
        'meter',
        'quantity',
        'occurred_at',
        'metadata',
        'dedupe_key',
        'subscription_item_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'occurred_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function scopeForTenant(Builder $query, string|int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMeter(Builder $query, string $meter): Builder
    {
        return $query->where('meter', $meter);
    }

    /**
     * @param  string  $period  'Y-m'
     */
    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->whereBetween('occurred_at', [
            CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth(),
            CarbonImmutable::createFromFormat('Y-m', $period)->startOfMonth()->addMonth(),
        ]);
    }
}
