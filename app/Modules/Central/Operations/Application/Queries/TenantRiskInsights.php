<?php

declare(strict_types=1);

namespace App\Modules\Central\Operations\Application\Queries;

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Tenancy\Application\Services\QuotaManager;

/**
 * Portfolio insights v1: transparent rule-based heuristics (RF10).
 * No ML models: with current tenant volume a model would be noise.
 * Every score ships its reasons so staff can audit it; the interface
 * stays stable if a statistical model replaces the rules later.
 */
final readonly class TenantRiskInsights
{
    private const RISK_PAST_DUE_SUBSCRIPTION = 40;

    private const RISK_MAXED_FAILURES = 20;

    private const RISK_NO_RECENT_PAYMENT = 20;

    private const RISK_TENANT_PAST_DUE = 10;

    private const UPGRADE_SATURATION_PCT = 80;

    public function __construct(
        private PlanManager $plans,
        private QuotaManager $quotas,
    ) {}

    /**
     * Tenants showing churn signals, highest first.
     *
     * @return array<int, array{tenant_id: string, slug: string, name: string, score: int, band: string, reasons: array<int, string>}>
     */
    public function churnRisks(int $limit = 5): array
    {
        $risks = [];

        $tenants = Tenant::whereIn('status', ['active', 'past_due'])
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'slug', 'name', 'status']);

        foreach ($tenants as $tenant) {
            $score = 0;
            $reasons = [];

            $pastDueSubs = Subscription::where('tenant_id', $tenant->id)
                ->where('status', 'past_due')
                ->count();

            if ($pastDueSubs > 0) {
                $score += self::RISK_PAST_DUE_SUBSCRIPTION;
                $reasons[] = $pastDueSubs === 1 ? '1 suscripción en mora.' : "{$pastDueSubs} suscripciones en mora.";
            }

            $maxFailures = (int) Subscription::where('tenant_id', $tenant->id)->max('failed_attempts');

            if ($maxFailures >= 3) {
                $score += self::RISK_MAXED_FAILURES;
                $reasons[] = "3+ intentos de cobro fallidos ({$maxFailures}).";
            }

            $hasLiveSubscription = Subscription::where('tenant_id', $tenant->id)
                ->whereNotIn('status', ['canceled'])
                ->exists();

            $recentPayment = Payment::where('tenant_id', $tenant->id)
                ->where('status', 'approved')
                ->where('created_at', '>=', now()->subDays(60))
                ->exists();

            if ($hasLiveSubscription && ! $recentPayment) {
                $score += self::RISK_NO_RECENT_PAYMENT;
                $reasons[] = 'Sin pagos aprobados en 60 días.';
            }

            if ($tenant->status === 'past_due') {
                $score += self::RISK_TENANT_PAST_DUE;
                $reasons[] = 'Tenant en estado past_due.';
            }

            if ($score === 0) {
                continue;
            }

            $risks[] = [
                'tenant_id' => (string) $tenant->id,
                'slug' => (string) $tenant->slug,
                'name' => (string) ($tenant->name ?? $tenant->slug),
                'score' => min(100, $score),
                'band' => $score >= 60 ? 'high' : 'watch',
                'reasons' => $reasons,
            ];
        }

        usort($risks, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($risks, 0, $limit);
    }

    /**
     * Tenants saturating plan quotas with a higher plan available.
     *
     * @return array<int, array{tenant_id: string, slug: string, name: string, metric: string, usage: int, limit: int, percentage: int, suggested_plan: string}>
     */
    public function upgradeCandidates(int $limit = 5): array
    {
        $candidates = [];

        try {
            $plans = $this->plans->active();
        } catch (\Throwable) {
            return [];
        }

        if ($plans->isEmpty()) {
            return [];
        }

        $tenants = Tenant::where('status', 'active')->orderBy('name')->limit(200)->get();

        foreach ($tenants as $tenant) {
            try {
                $current = $this->plans->find($tenant->getPlanSlug());
            } catch (\Throwable) {
                continue;
            }

            $higher = $plans
                ->where('price_monthly', '>', $current->price_monthly)
                ->sortBy('price_monthly')
                ->first();

            if (! $higher) {
                continue;
            }

            foreach (array_keys($this->plans->quotas($current)) as $metric) {
                if (! is_string($metric)) {
                    continue;
                }

                try {
                    $usage = $this->quotas->getCurrentUsage($tenant, $metric);
                    $quotaLimit = $this->quotas->getLimit($tenant, $metric);
                } catch (\Throwable) {
                    continue;
                }

                if ($quotaLimit <= 0) {
                    continue;
                }

                $percentage = (int) round(($usage / $quotaLimit) * 100);

                if ($percentage >= self::UPGRADE_SATURATION_PCT) {
                    $candidates[] = [
                        'tenant_id' => (string) $tenant->id,
                        'slug' => (string) $tenant->slug,
                        'name' => (string) ($tenant->name ?? $tenant->slug),
                        'metric' => $metric,
                        'usage' => $usage,
                        'limit' => $quotaLimit,
                        'percentage' => $percentage,
                        'suggested_plan' => (string) $higher->slug,
                    ];
                    break;
                }
            }
        }

        usort($candidates, fn ($a, $b) => $b['percentage'] <=> $a['percentage']);

        return array_slice($candidates, 0, $limit);
    }

    /**
     * Platform-wide quota saturation: tenants near limit per metric.
     * Usage-pattern view for capacity planning (RF10.2).
     *
     * @return array<int, array{metric: string, tenants_near_limit: int, tenants_total: int}>
     */
    public function quotaSaturation(): array
    {
        $metrics = [];
        $tenants = Tenant::where('status', 'active')->limit(200)->get();

        foreach ($tenants as $tenant) {
            try {
                $current = $this->plans->find($tenant->getPlanSlug());
            } catch (\Throwable) {
                continue;
            }

            foreach (array_keys($this->plans->quotas($current)) as $metric) {
                if (! is_string($metric)) {
                    continue;
                }

                try {
                    $usage = $this->quotas->getCurrentUsage($tenant, $metric);
                    $quotaLimit = $this->quotas->getLimit($tenant, $metric);
                } catch (\Throwable) {
                    continue;
                }

                if ($quotaLimit <= 0) {
                    continue;
                }

                $metrics[$metric]['total'] = ($metrics[$metric]['total'] ?? 0) + 1;

                if (($usage / $quotaLimit) * 100 >= self::UPGRADE_SATURATION_PCT) {
                    $metrics[$metric]['near'] = ($metrics[$metric]['near'] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        foreach ($metrics as $metric => $counts) {
            $rows[] = [
                'metric' => $metric,
                'tenants_near_limit' => $counts['near'] ?? 0,
                'tenants_total' => $counts['total'],
            ];
        }

        usort($rows, fn ($a, $b) => $b['tenants_near_limit'] <=> $a['tenants_near_limit']);

        return $rows;
    }
}
