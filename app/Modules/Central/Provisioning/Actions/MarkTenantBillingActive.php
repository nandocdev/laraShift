<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

/**
 * Public cross-module entry point: Billing activates a tenant after an
 * approved payment WITHOUT importing the Tenant model into Billing.
 */
final readonly class MarkTenantBillingActive
{
    public function execute(string $tenantId, string $planSlug): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        $tenant->update([
            'plan_id' => $planSlug,
            'status' => 'active',
            'provisioned_at' => $tenant->provisioned_at ?? now(),
        ]);

        activity('billing')
            ->performedOn($tenant)
            ->withProperties(['plan' => $planSlug])
            ->log('tenant_billing_activated');
    }
}
