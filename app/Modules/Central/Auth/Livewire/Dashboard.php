<?php

declare(strict_types=1);

namespace App\Modules\Central\Auth\Livewire;

use App\Modules\Central\Billing\Models\Invoice;
use App\Modules\Central\Billing\Models\Subscription;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Models\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.central')]
class Dashboard extends Component
{
    public function render(): View
    {
        $tenantCount = Tenant::count();
        $activeSubscriptionsCount = Subscription::where('status', 'active')->count();
        
        // Revenue in last 30 days
        $totalRevenue = Invoice::where('status', 'paid')
            ->where('issued_at', '>=', now()->subDays(30))
            ->sum('amount');

        $recentTenants = Tenant::latest()->take(5)->get();
        
        $recentActivities = Activity::latest()->take(10)->get();

        return view('central-auth::pages.dashboard', [
            'tenantCount' => $tenantCount,
            'activeSubscriptionsCount' => $activeSubscriptionsCount,
            'totalRevenue' => $totalRevenue / 100, // Assuming cents
            'recentTenants' => $recentTenants,
            'recentActivities' => $recentActivities,
        ]);
    }
}
