<?php

declare(strict_types=1);

namespace App\Modules\Central\Security\Actions;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class ResolveTenantEncryptionPolicyAction
{
    /**
     * Resolve encryption policy for a tenant from its plan features.
     *
     * Hierarchy: Plan.features.encryption > defaults
     */
    public function execute(Tenant $tenant): array
    {
        $plan = $tenant->plan;

        if ($plan && $plan instanceof Plan) {
            $features = $plan->features ?? [];
            $encryption = $features['encryption'] ?? [];

            if (! empty($encryption)) {
                return array_merge([
                    'key_rotation_days' => 90,
                    'encrypt_at_rest' => true,
                    'encrypt_in_transit' => true,
                ], $encryption);
            }
        }

        return [
            'key_rotation_days' => 90,
            'encrypt_at_rest' => true,
            'encrypt_in_transit' => true,
        ];
    }
}
