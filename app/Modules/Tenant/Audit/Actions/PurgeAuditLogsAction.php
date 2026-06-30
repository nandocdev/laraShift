<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Audit\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Log;

final readonly class PurgeAuditLogsAction
{
    private const array RETENTION_DAYS = [
        'free' => 30,
        'pro' => 90,
        'enterprise' => 365,
    ];

    private const int DEFAULT_RETENTION_DAYS = 90;

    /**
     * Purge audit logs that are older than the retention period for the tenant's plan.
     */
    public function execute(?Tenant $tenant = null): int
    {
        if ($tenant) {
            return $this->purgeForTenant($tenant);
        }

        return $this->purgeAll();
    }

    private function purgeForTenant(Tenant $tenant): int
    {
        $retentionDays = $this->resolveRetentionDays($tenant);
        $cutoff = now()->subDays($retentionDays);

        $deleted = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($deleted > 0) {
            Log::info("Purged {$deleted} audit logs for tenant", [
                'tenant_id' => $tenant->id,
                'retention_days' => $retentionDays,
            ]);
        }

        return $deleted;
    }

    private function purgeAll(): int
    {
        $total = 0;

        foreach (self::RETENTION_DAYS as $planId => $days) {
            $cutoff = now()->subDays($days);

            $deleted = AuditLog::whereHas('tenant', function ($q) use ($planId) {
                $q->where('plan_id', $planId);
            })->where('created_at', '<', $cutoff)->delete();

            $total += $deleted;
        }

        // Default for unknown plans
        $defaultCutoff = now()->subDays(self::DEFAULT_RETENTION_DAYS);
        $deleted = AuditLog::whereDoesntHave('tenant')
            ->where('created_at', '<', $defaultCutoff)
            ->delete();

        $total += $deleted;

        if ($total > 0) {
            Log::info("Purged {$total} audit logs across all tenants");
        }

        return $total;
    }

    private function resolveRetentionDays(Tenant $tenant): int
    {
        return self::RETENTION_DAYS[$tenant->plan_id] ?? self::DEFAULT_RETENTION_DAYS;
    }
}
