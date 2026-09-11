<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

/**
 * Public cross-module entry point: Billing suspends a tenant after dunning
 * exhaustion WITHOUT importing the Tenant model into Billing.
 */
final readonly class SuspendTenantForNonPayment
{
    public function execute(string $tenantId, string $reason): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        if ($tenant->status === 'suspended') {
            return;
        }

        $tenant->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        activity('billing')
            ->performedOn($tenant)
            ->withProperties(['reason' => $reason])
            ->log('tenant_suspended_for_non_payment');
    }
}
