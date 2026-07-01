<?php

declare(strict_types=1);

namespace App\Modules\Central\Analytics\Jobs;

use App\Modules\Central\Analytics\Models\PlatformMetric;
use App\Modules\Central\Billing\Services\MrrCalculator;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RefreshPlatformMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $calculator = app(MrrCalculator::class);
        $now = now();
        $period = $now->format('Y-m-d');

        $this->snapshot('mrr', $calculator->calculateMrr(), $period);
        $this->snapshot('churn_rate_30d', $calculator->churnRate(now()->subDays(30)), $period);
        $this->snapshot('churn_rate_90d', $calculator->churnRate(now()->subDays(90)), $period);

        $statuses = $calculator->tenantStatusCounts();
        foreach ($statuses as $status => $count) {
            $this->snapshot("tenants.{$status}", $count, $period);
        }

        $total = Tenant::count();
        $this->snapshot('tenants.total', $total, $period);

        $failedProvisioning = Tenant::where('provisioning_status', 'failed')->count();
        $this->snapshot('provisioning.failed', $failedProvisioning, $period);

        $byPlan = $calculator->mrrByPlan();
        foreach ($byPlan as $planData) {
            $this->snapshot('mrr.plan', $planData['mrr'], $period, $planData['plan']);
            $this->snapshot('tenants.plan', $planData['count'], $period, $planData['plan']);
        }
    }

    private function snapshot(string $metric, float $value, string $period, ?string $group = null): void
    {
        $data = [
            'id' => Str::uuid()->toString(),
            'value' => $value,
            'captured_at' => now(),
        ];

        PlatformMetric::updateOrCreate(
            ['metric' => $metric, 'period' => $period, 'group' => $group],
            $data,
        );
    }
}
