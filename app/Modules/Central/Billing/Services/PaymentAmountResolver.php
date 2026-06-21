<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Services;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Shared\Contracts\PaymentAmountResolverContract;

class PaymentAmountResolver implements PaymentAmountResolverContract
{
    public function resolveAmount(string $displayId): float
    {
        // displayId could be an invoice ID or a plan ID.
        // We first try to find an invoice, then a plan.
        $invoice = Invoice::find($displayId);
        if ($invoice) {
            return (float) $invoice->amount;
        }

        $plan = Plan::find($displayId);
        if ($plan) {
            return (float) $plan->price;
        }

        throw new \InvalidArgumentException("Invalid payment reference: {$displayId}");
    }
}
