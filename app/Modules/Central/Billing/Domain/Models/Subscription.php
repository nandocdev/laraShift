<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Domain\Models;

use App\Modules\Central\Billing\Database\Factories\SubscriptionFactory;
use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Platform\Tenancy\Domain\Concerns\ScopedToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, HasUuids, ScopedToTenant;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'provider_subscription_id',
        'status',
        'gateway',
        'current_period_start',
        'current_period_end',
        'next_payment_at',
        'failed_attempts',
        'pm_card_id',
        'renewal_link_sent_at',
        'renewal_reminder_sent_at',
        'renewal_link_expires_at',
        'cancel_at_period_end',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'next_payment_at' => 'datetime',
            'failed_attempts' => 'integer',
            'renewal_link_sent_at' => 'datetime',
            'renewal_reminder_sent_at' => 'datetime',
            'renewal_link_expires_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }
}
