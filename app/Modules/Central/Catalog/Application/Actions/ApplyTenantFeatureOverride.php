<?php

declare(strict_types=1);

namespace App\Modules\Central\Catalog\Application\Actions;

use App\Modules\Central\Catalog\Domain\Models\Feature;
use App\Modules\Central\Catalog\Domain\Models\TenantFeatureOverride;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class ApplyTenantFeatureOverride
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

            $expiresAtCarbon = null;
            if ($expiresAt !== null) {
                try {
                    $expiresAtCarbon = CarbonImmutable::parse($expiresAt, 'UTC');
                } catch (\Throwable $e) {
                    throw ValidationException::withMessages(['expiresAt' => __('Invalid expiration date format.')]);
                }
            }

            // Use withTrashed to support re-apply after soft-delete (C004) and keep PK stable (C002)
            $override = TenantFeatureOverride::withTrashed()->firstOrNew([
                'tenant_id' => $tenant->id,
                'feature_id' => $feature->id,
            ]);

            if ($override->exists && $override->trashed()) {
                $override->restore();
            }

            $override->fill([
                'type' => $type,
                'reason' => $reason,
                'expires_at' => $expiresAtCarbon,
                'created_by' => auth('central')->id(),
            ]);
            $override->save();

            // Invalidate cache to reflect changes immediately
            app(ResolveTenantFeatures::class)->execute($tenant, true);

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
