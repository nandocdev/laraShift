<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class ValidateProvisioningAction
{
    /**
     * Pre-provisioning validation (dry run).
     * Returns array of error messages. Empty array = valid.
     *
     * @return array<int, string>
     */
    public function execute(Tenant $tenant): array
    {
        $errors = [];

        if (empty($tenant->name)) {
            $errors[] = 'Tenant name is required.';
        }

        if (empty($tenant->slug)) {
            $errors[] = 'Tenant slug is required.';
        }

        if (! $tenant->plan_id) {
            $errors[] = 'A billing plan must be assigned.';
        } else {
            $plan = $tenant->plan;

            if (! $plan) {
                $errors[] = "Plan '{$tenant->plan_id}' not found.";
            } elseif (! $plan->is_active) {
                $errors[] = "Plan '{$plan->name}' is not active.";
            }

            $slugExists = Tenant::where('slug', $tenant->slug)
                ->where('id', '!=', $tenant->id)
                ->exists();

            if ($slugExists) {
                $errors[] = "Slug '{$tenant->slug}' is already taken.";
            }
        }

        if (! in_array($tenant->status, ['provisioning', 'pending'], true)) {
            $errors[] = "Tenant status must be 'provisioning' or 'pending', got '{$tenant->status}'.";
        }

        if (empty($tenant->email)) {
            $errors[] = 'Tenant owner email is required.';
        }

        return $errors;
    }
}
