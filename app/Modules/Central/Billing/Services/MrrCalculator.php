<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Services;

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class MrrCalculator
{
    /**
     * Calculate current Monthly Recurring Revenue.
     */
    public function calculateMrr(): float
    {
        return (float) Tenant::where('status', 'active')
            ->whereNotNull('plan_id')
            ->where('plan_id', '!=', 'free')
            ->get()
            ->sum(function (Tenant $tenant) {
                return $this->planMrr($tenant->plan);
            });
    }

    /**
     * Get MRR breakdown by plan.
     *
     * @return array<int, array{plan: string, count: int, mrr: float}>
     */
    public function mrrByPlan(): array
    {
        return Tenant::where('status', 'active')
            ->whereNotNull('plan_id')
            ->where('plan_id', '!=', 'free')
            ->get()
            ->groupBy('plan_id')
            ->map(function ($tenants, $planId) {
                $plan = Plan::find($planId);

                return [
                    'plan' => $plan?->name ?? $planId,
                    'count' => $tenants->count(),
                    'mrr' => $tenants->sum(fn ($t) => $this->planMrr($t->plan)),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Calculate churn rate for a given period.
     */
    public function churnRate(\DateTimeInterface $since): float
    {
        $totalAtStart = Tenant::where('created_at', '<=', $since)
            ->where('status', '!=', 'archived')
            ->count();

        if ($totalAtStart === 0) {
            return 0;
        }

        $churned = Tenant::where('status', 'archived')
            ->where('archived_at', '>=', $since)
            ->count();

        return round(($churned / $totalAtStart) * 100, 2);
    }

    /**
     * Get counts of tenants by status.
     *
     * @return array<string, int>
     */
    public function tenantStatusCounts(): array
    {
        return Tenant::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get monthly revenue data for the last N months.
     *
     * @return array<int, array{month: string, mrr: float, new_tenants: int, churned: int}>
     */
    public function monthlyBreakdown(int $months = 12): array
    {
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $data[] = [
                'month' => $month->format('Y-m'),
                'mrr' => $this->mrrAtDate($monthEnd),
                'new_tenants' => Tenant::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'churned' => Tenant::where('status', 'archived')
                    ->whereBetween('archived_at', [$monthStart, $monthEnd])
                    ->count(),
            ];
        }

        return $data;
    }

    private function mrrAtDate(\DateTimeInterface $date): float
    {
        return (float) Tenant::where('created_at', '<=', $date)
            ->where('status', 'active')
            ->whereNotNull('plan_id')
            ->where('plan_id', '!=', 'free')
            ->get()
            ->sum(function (Tenant $tenant) {
                return $this->planMrr($tenant->plan);
            });
    }

    private function planMrr(?Plan $plan): float
    {
        if (! $plan) {
            return 0;
        }

        return (float) ($plan->price_monthly?->getAmount() ?? 0) / 100;
    }
}
