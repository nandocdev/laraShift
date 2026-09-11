<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts\Billing;

enum BillingCapability: string
{
    case Checkout = 'checkout';
    case DirectPayment = 'direct_payment';
    case Subscriptions = 'subscriptions';
    case Refunds = 'refunds';
    case Trials = 'trials';
}
