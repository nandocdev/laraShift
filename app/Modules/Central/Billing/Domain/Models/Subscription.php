<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    use HasUuids;

    protected $fillable = [
        'plan_id',
        'provider_subscription_id',
        'status',
        'gateway',
        'current_period_end',
        'next_payment_at',
        'pm_card_id',
        'failed_attempts',
        'cancelled_at',
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_period_end' => 'datetime',
        'next_payment_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_attempts' => 'integer',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
