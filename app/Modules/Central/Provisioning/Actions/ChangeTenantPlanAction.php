<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual plan assignment by platform staff (RF2.2).
 *
 * Single point of truth for changing a tenant's plan: syncs
 * tenants.plan_id (slug) with the non-canceled subscriptions'
 * plan_id (plans.id) so both stay consistent, and logs the change.
 *
 * No feature-cache invalidation needed: ResolveTenantFeatures keys
 * include slug + content hash, so the new plan misses to a fresh entry.
 */
final readonly class ChangeTenantPlanAction
{
    /**
     * @return array{from: string, to: string, changed: bool}
     */
    public function execute(Tenant $tenant, string $planSlug): array
    {
        $plan = DB::table('plans')->where('slug', $planSlug)->first();

        if (! $plan) {
            throw ValidationException::withMessages(['plan_id' => __('Selected plan does not exist.')]);
        }

        if (! (bool) ($plan->is_active ?? false)) {
            throw ValidationException::withMessages(['plan_id' => __('Selected plan is not active.')]);
        }

        $from = (string) ($tenant->plan_id ?? 'free');

        if ($from === $planSlug) {
            return ['from' => $from, 'to' => $planSlug, 'changed' => false];
        }

        DB::transaction(function () use ($tenant, $plan, $planSlug): void {
            $tenant->update(['plan_id' => $planSlug]);

            DB::table('subscriptions')
                ->where('tenant_id', $tenant->id)
                ->whereNotIn('status', ['canceled'])
                ->update([
                    'plan_id' => $plan->id,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                ]);
        });

        $log = activity('provisioning')
            ->performedOn($tenant)
            ->withProperties(['from' => $from, 'to' => $planSlug]);

        if (auth('central')->check()) {
            $log->causedBy(auth('central')->user());
        }

        $log->log('tenant_plan_changed');

        return ['from' => $from, 'to' => $planSlug, 'changed' => true];
    }

    /**
     * ID-based entry point for Billing, which must not import the Tenant model.
     *
     * @return array{from: string, to: string, changed: bool}
     */
    public function executeById(string $tenantId, string $planSlug): array
    {
        return $this->execute(Tenant::findOrFail($tenantId), $planSlug);
    }
}
