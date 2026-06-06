<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
        'tenant_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_period_end' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];
}
