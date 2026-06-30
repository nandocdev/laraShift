<?php

declare(strict_types=1);

namespace App\Modules\Central\Features\Actions;

use App\Modules\Central\Features\Models\Feature;
use App\Modules\Central\Features\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final readonly class ApplyTenantFeatureOverrideAction
{
    /**
     * Applies a feature override to a specific tenant.
     *
     * [SIDE-EFFECTS]
     * - Creates or updates an override record.
     * - Invalidates the tenant's feature cache.
     * - Logs the activity for audit.
     */
    public function execute(
        Tenant $tenant,
        string $featureKey,
        string $type,
        ?string $reason = null,
        ?string $expiresAt = null
    ): TenantFeatureOverride {
        Gate::authorize('features:manage');

        return DB::transaction(function () use ($tenant, $featureKey, $type, $reason, $expiresAt) {
            $feature = Feature::where('key', $featureKey)->firstOrFail();

            $override = TenantFeatureOverride::updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
                [
                    'id' => Str::uuid()->toString(),
                    'type' => $type,
                    'reason' => $reason,
                    'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
                    'created_by' => auth('central')->id(),
                ]
            );

            // Invalidate cache to reflect changes immediately
            app(ResolveTenantFeaturesAction::class)->execute($tenant, true);

            activity('features')
                ->performedOn($tenant)
                ->withProperties([
                    'feature' => $featureKey,
                    'type' => $type,
                    'expires_at' => $expiresAt,
                ])
                ->log('tenant_feature_override_applied');

            return $override;
        });
    }
}
