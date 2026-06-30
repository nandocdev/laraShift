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
        return (float) Tenant::query()
            ->where('tenants.status', 'active')
            ->whereNotNull('tenants.plan_id')
            ->where('tenants.plan_id', '!=', 'free')
            ->leftJoin('plans', 'tenants.plan_id', '=', 'plans.slug')
            ->sum(DB::raw('COALESCE(plans.price_monthly, 0) / 100.0'));
    }

    /**
     * Get MRR breakdown by plan.
     *
     * @return array<int, array{plan: string, count: int, mrr: float}>
     */
    public function mrrByPlan(): array
    {
        return Tenant::query()
            ->where('tenants.status', 'active')
            ->whereNotNull('tenants.plan_id')
            ->where('tenants.plan_id', '!=', 'free')
            ->leftJoin('plans', 'tenants.plan_id', '=', 'plans.slug')
            ->groupBy('tenants.plan_id', 'plans.name')
            ->orderBy('plans.name')
            ->get([
                'tenants.plan_id',
                DB::raw('COALESCE(plans.name, tenants.plan_id) as plan'),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(plans.price_monthly), 0) / 100.0 as mrr'),
            ])
            ->map(fn ($row) => [
                'plan' => $row->plan,
                'count' => (int) $row->count,
                'mrr' => (float) $row->mrr,
            ])
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
        $monthsAgo = now()->subMonths($months - 1)->startOfMonth();

        $allActiveTenants = Tenant::where('status', 'active')
            ->whereNotNull('plan_id')
            ->where('plan_id', '!=', 'free')
            ->with('plan')
            ->get();

        $newByMonth = Tenant::where('created_at', '>=', $monthsAgo)
            ->get(['created_at'])
            ->groupBy(fn (Tenant $t) => $t->created_at->format('Y-m'))
            ->map(fn ($group) => $group->count());

        $churnedByMonth = Tenant::where('status', 'archived')
            ->where('archived_at', '>=', $monthsAgo)
            ->get(['archived_at'])
            ->groupBy(fn (Tenant $t) => $t->archived_at->format('Y-m'))
            ->map(fn ($group) => $group->count());

        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $monthEnd = $month->copy()->endOfMonth();

            $mrr = (float) $allActiveTenants
                ->filter(fn (Tenant $t) => $t->created_at <= $monthEnd)
                ->sum(fn (Tenant $t) => $this->planMrr($t->plan));

            $data[] = [
                'month' => $monthKey,
                'mrr' => $mrr,
                'new_tenants' => (int) ($newByMonth[$monthKey] ?? 0),
                'churned' => (int) ($churnedByMonth[$monthKey] ?? 0),
            ];
        }

        return $data;
    }

    private function planMrr(?Plan $plan): float
    {
        if (! $plan) {
            return 0;
        }

        return (float) ($plan->price_monthly?->getAmount() ?? 0) / 100;
    }
}
