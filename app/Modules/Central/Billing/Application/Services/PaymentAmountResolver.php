<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Services;

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Platform\Contracts\PaymentAmountResolverContract;

class PaymentAmountResolver implements PaymentAmountResolverContract
{
    public function resolveAmount(string $displayId): float
    {
        // displayId could be an invoice ID or a plan ID.
        // We first try to find an invoice, then a plan.
        // B011: Re-derive amount server-side; check by slug as well (displayId may be plan slug)
        $invoice = Invoice::find($displayId);
        if ($invoice) {
            return (float) $invoice->amount->getAmount() / 100;
        }

        // Plan lookup by id or slug
        $plan = Plan::find($displayId) ?? Plan::where('slug', $displayId)->first();
        if ($plan) {
            // Prefer Money object if available (price_monthly MoneyCast), fallback to legacy integer
            if (isset($plan->price_monthly)) {
                return (float) $plan->price_monthly->getAmount() / 100;
            }

            return (float) ($plan->price ?? $plan->amount ?? 0) / 100;
        }

        throw new \InvalidArgumentException("Invalid payment reference: {$displayId}");
    }
}
