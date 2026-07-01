<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class FetchTenantSubscriptionDetailAction
{
    public function execute(Tenant $tenant): array
    {
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->latest()
            ->first();

        $invoices = Invoice::where('tenant_id', $tenant->id)
            ->latest('issued_at')
            ->paginate(10);

        return [
            'subscription' => $subscription,
            'invoices' => $invoices,
        ];
    }
}
