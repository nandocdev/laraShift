<?php

declare(strict_types=1);

namespace App\Modules\Platform\Metering\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated usage value for a tenant/meter/period, produced by the
 * AggregateUsageJob. billed_at marks the period as already reported to the
 * active MeterBillingProvider (idempotency guard for billing).
 */
class UsageRollup extends Model
{
    use HasUuids;

    protected $table = 'usage_rollups';

    protected $fillable = [
        'id',
        'tenant_id',
        'meter',
        'period',
        'value',
        'aggregation',
        'billed_at',
    ];

    protected $casts = [
        'value' => 'integer',
        'billed_at' => 'immutable_datetime',
    ];

    public function scopeForTenant(Builder $query, string|int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForMeter(Builder $query, string $meter): Builder
    {
        return $query->where('meter', $meter);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function isBilled(): bool
    {
        return $this->billed_at !== null;
    }
}
