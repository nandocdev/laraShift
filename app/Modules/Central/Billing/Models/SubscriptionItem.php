<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{
    use HasUuids;

    protected $fillable = [
        'subscription_id',
        'stripe_id',
        'stripe_product',
        'stripe_price',
        'quantity',
    ];
}
