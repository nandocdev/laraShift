<?php

declare(strict_types=1);

namespace App\Modules\Central\Monitoring\Actions;

use App\Modules\Central\Monitoring\Models\TenantHealthCheck;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Services\QuotaManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final readonly class CheckCriticalAlertsAction
{
    public function execute(): array
    {
        $alerts = [];

        $recentFailures = TenantHealthCheck::where('status', 'fail')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentFailures > 0) {
            $alerts[] = [
                'type' => 'health_check_failures',
                'severity' => 'critical',
                'message' => "{$recentFailures} tenant(s) failed health check in the last hour.",
                'count' => $recentFailures,
            ];

            Log::critical('Health check failures detected', ['count' => $recentFailures]);
        }

        $recentProvisioningFailures = Tenant::where('provisioning_status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        if ($recentProvisioningFailures > 0) {
            $alerts[] = [
                'type' => 'provisioning_failures',
                'severity' => 'critical',
                'message' => "{$recentProvisioningFailures} tenant(s) with provisioning failures in the last 24h.",
                'count' => $recentProvisioningFailures,
            ];

            Log::critical('Provisioning failures detected', ['count' => $recentProvisioningFailures]);
        }

        $suspendedByBilling = Tenant::where('status', 'suspended')
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '>=', now()->subDay())
            ->count();

        if ($suspendedByBilling > 0) {
            $alerts[] = [
                'type' => 'billing_suspensions',
                'severity' => 'warning',
                'message' => "{$suspendedByBilling} tenant(s) suspended for billing in the last 24h.",
                'count' => $suspendedByBilling,
            ];

            Log::warning('Billing suspensions detected', ['count' => $suspendedByBilling]);
        }

        $resourceExhausted = $this->checkResourceExhaustion();

        if ($resourceExhausted > 0) {
            $alerts[] = [
                'type' => 'resource_exhaustion',
                'severity' => 'warning',
                'message' => "{$resourceExhausted} tenant(s) at quota limit.",
                'count' => $resourceExhausted,
            ];

            Log::warning('Resource exhaustion detected', ['count' => $resourceExhausted]);
        }

        return $alerts;
    }

    private function checkResourceExhaustion(): int
    {
        try {
            $count = 0;

            Tenant::where('status', 'active')
                ->whereHas('plan', fn ($q) => $q->whereNotNull('features'))
                ->with('plan')
                ->chunkById(100, function ($tenants) use (&$count) {
                    foreach ($tenants as $tenant) {
                        $plan = $tenant->plan;
                        $quotas = $plan->features['quotas'] ?? [];

                        foreach ($quotas as $metric => $limit) {
                            if ($limit <= 0) {
                                continue;
                            }

                            $usage = app(QuotaManager::class)->getCurrentUsage($tenant, $metric);

                            if ($usage >= $limit) {
                                $count++;
                                break;
                            }
                        }
                    }
                });

            return $count;
        } catch (\Throwable $e) {
            Log::error('Resource exhaustion check failed', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
