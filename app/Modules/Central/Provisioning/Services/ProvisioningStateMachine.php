<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Services;

use App\Modules\Central\Provisioning\Models\ProvisioningLog;
use App\Modules\Central\Provisioning\Models\Tenant;

final readonly class ProvisioningStateMachine
{
    private const array STEPS = [
        'validated',
        'db_created',
        'migrated',
        'dns_configured',
        'ssl_issued',
        'ready',
    ];

    private const array STEP_TRANSITIONS = [
        'pending' => 'validated',
        'validated' => 'db_created',
        'db_created' => 'migrated',
        'migrated' => 'dns_configured',
        'dns_configured' => 'ssl_issued',
        'ssl_issued' => 'ready',
    ];

    public function nextStep(Tenant $tenant): ?string
    {
        $currentStatus = $tenant->provisioning_status ?? 'pending';

        return self::STEP_TRANSITIONS[$currentStatus] ?? null;
    }

    public function isStepCompleted(Tenant $tenant, string $step): bool
    {
        return ProvisioningLog::where('tenant_id', $tenant->id)
            ->where('step', $step)
            ->where('status', 'completed')
            ->exists();
    }

    public function completedSteps(Tenant $tenant): array
    {
        return ProvisioningLog::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->pluck('step')
            ->toArray();
    }

    public function failedSteps(Tenant $tenant): array
    {
        return ProvisioningLog::where('tenant_id', $tenant->id)
            ->where('status', 'failed')
            ->pluck('step')
            ->toArray();
    }

    public function resumeFrom(Tenant $tenant): ?string
    {
        $completed = $this->completedSteps($tenant);

        foreach (self::STEPS as $step) {
            if (! in_array($step, $completed, true)) {
                return $step;
            }
        }

        return null;
    }

    public function isComplete(Tenant $tenant): bool
    {
        return count($this->completedSteps($tenant)) >= count(self::STEPS);
    }

    /**
     * @return string[]
     */
    public static function allSteps(): array
    {
        return self::STEPS;
    }

    public static function indexOf(string $step): int
    {
        return array_search($step, self::STEPS, true);
    }
}
