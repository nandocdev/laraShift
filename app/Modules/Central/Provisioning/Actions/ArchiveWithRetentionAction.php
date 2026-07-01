<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

final readonly class ArchiveWithRetentionAction
{
    private const int DEFAULT_RETENTION_DAYS = 90;

    private const int TRIAL_RETENTION_DAYS = 30;

    private const int ENTERPRISE_RETENTION_DAYS = 365;

    public function execute(Tenant $tenant): array
    {
        $retentionDays = $this->resolveRetentionDays($tenant);
        $purgeAt = Carbon::now()->addDays($retentionDays);

        $tenant->update([
            'status' => 'archived',
            'archived_at' => Carbon::now(),
            'read_only' => true,
            'data' => array_merge((array) $tenant->data, [
                'retention_until' => $purgeAt->toIso8601String(),
                'archived_by' => request()?->user()?->id ?? 'system',
            ]),
        ]);

        activity('provisioning')
            ->performedOn($tenant)
            ->withProperties([
                'retention_days' => $retentionDays,
                'purge_at' => $purgeAt->toIso8601String(),
            ])
            ->log('tenant_archived_with_retention');

        Log::info('Tenant archived with retention', [
            'tenant_id' => $tenant->id,
            'retention_days' => $retentionDays,
            'purge_at' => $purgeAt,
        ]);

        return [
            'status' => 'archived',
            'retention_days' => $retentionDays,
            'purge_at' => $purgeAt->toIso8601String(),
        ];
    }

    public function restore(Tenant $tenant): void
    {
        if ($tenant->status !== 'archived') {
            throw new \RuntimeException('Only archived tenants can be restored.');
        }

        $data = $tenant->data ?? [];
        unset($data['retention_until'], $data['archived_by']);

        $tenant->update([
            'status' => 'active',
            'archived_at' => null,
            'read_only' => false,
            'data' => $data,
        ]);

        activity('provisioning')
            ->performedOn($tenant)
            ->log('tenant_restored_from_archive');
    }

    /**
     * Check if archived tenant is past retention and should be purged.
     */
    public function shouldPurge(Tenant $tenant): bool
    {
        if ($tenant->status !== 'archived' || ! $tenant->archived_at) {
            return false;
        }

        $retentionDays = $this->resolveRetentionDays($tenant);

        return $tenant->archived_at->addDays($retentionDays)->isPast();
    }

    private function resolveRetentionDays(Tenant $tenant): int
    {
        $planId = $tenant->plan_id;

        return match ($planId) {
            'enterprise' => self::ENTERPRISE_RETENTION_DAYS,
            'trial' => self::TRIAL_RETENTION_DAYS,
            default => self::DEFAULT_RETENTION_DAYS,
        };
    }
}
