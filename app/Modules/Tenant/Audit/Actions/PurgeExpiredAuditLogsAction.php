<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Actions;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Audit\Models\AuditLog;
use App\Modules\Tenant\Settings\Services\MetadataManager;

final readonly class PurgeExpiredAuditLogsAction
{
    public function __construct(
        private MetadataManager $metadata
    ) {}

    public function execute(Tenant $tenant): int
    {
        $retentionDays = $this->resolveRetentionDays($tenant);

        $cutoff = now()->subDays($retentionDays);

        $deleted = AuditLog::whereDate('created_at', '<', $cutoff)->delete();

        return $deleted;
    }

    public function resolveRetentionDays(Tenant $tenant): int
    {
        $override = $this->metadata->get($tenant, 'audit_retention_days');

        if ($override !== null && is_numeric($override)) {
            return max(30, (int) $override);
        }

        $plan = $tenant->plan;

        if ($plan && $plan instanceof Plan) {
            $features = $plan->features ?? [];

            $planDays = $features['audit_retention_days'] ?? null;

            if ($planDays !== null && is_numeric($planDays)) {
                return max(30, (int) $planDays);
            }
        }

        return 365;
    }
}
