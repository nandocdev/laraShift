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

    /**
     * Compatibility accessor for Laravel Cashier's type column.
     * Cashier defaults the type of standard subscriptions to 'default'.
     */
    public function getTypeAttribute(): string
    {
        return 'default';
    }

    /**
     * Compatibility accessor for Laravel Cashier's stripe_status column.
     * Maps our local status column to stripe_status for active() verification.
     */
    public function getStripeStatusAttribute(): ?string
    {
        return $this->status;
    }
}
