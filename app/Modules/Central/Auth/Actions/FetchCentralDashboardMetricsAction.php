<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Actions;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Models\Activity;

final readonly class FetchCentralDashboardMetricsAction
{
    public function execute(): array
    {
        $tenantCount = Tenant::count();
        $activeSubscriptionsCount = Subscription::where('status', 'active')->count();

        $totalRevenue = Invoice::where('status', 'paid')
            ->where('issued_at', '>=', now()->subDays(30))
            ->sum('amount');

        $recentTenants = Tenant::latest()->take(5)->get();

        $recentActivities = Activity::latest()->take(10)->get();

        return [
            'tenantCount' => $tenantCount,
            'activeSubscriptionsCount' => $activeSubscriptionsCount,
            'totalRevenue' => $totalRevenue / 100,
            'recentTenants' => $recentTenants,
            'recentActivities' => $recentActivities,
        ];
    }
}
